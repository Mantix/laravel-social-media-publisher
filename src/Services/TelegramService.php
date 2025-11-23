<?php

namespace Mantix\LaravelSocialMediaPublisher\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mantix\LaravelSocialMediaPublisher\Contracts\ShareDocumentPostInterface;
use Mantix\LaravelSocialMediaPublisher\Contracts\ShareImagePostInterface;
use Mantix\LaravelSocialMediaPublisher\Contracts\ShareInterface;
use Mantix\LaravelSocialMediaPublisher\Contracts\ShareVideoPostInterface;
use Mantix\LaravelSocialMediaPublisher\Exceptions\SocialMediaException;
use Mantix\LaravelSocialMediaPublisher\Models\SocialMediaConnection;

/**
 * Class TelegramService
 *
 * Service for sharing posts to Telegram Channels or Groups using the Telegram Bot API.
 *
 * @package Mantix\LaravelSocialMediaPublisher\Services
 */
class TelegramService extends SocialMediaService implements ShareInterface, ShareImagePostInterface, ShareVideoPostInterface, ShareDocumentPostInterface {
    /** @var string Telegram Bot Token */
    private string $botToken;

    /** @var string Target Chat ID (Channel or Group ID) */
    private string $chatId;

    /** @var string Telegram API Base URL */
    private const API_BASE_URL = 'https://api.telegram.org/bot';

    /**
     * TelegramService Constructor.
     */
    public function __construct() {
        // Empty constructor
    }

    /**
     * Set the credentials for the TelegramService.
     *
     * @param string $botToken
     * @param string $chatId
     * @return self
     */
    public function setCredentials(string $botToken, string $chatId): self {
        $this->botToken = $botToken;
        $this->chatId = $chatId;
        return $this;
    }

    /**
     * Create a new instance from a SocialMediaConnection.
     *
     * @param SocialMediaConnection $connection
     * @return self
     * @throws SocialMediaException
     */
    public static function forConnection(SocialMediaConnection $connection): self {
        if ($connection->platform !== 'telegram') {
            throw new SocialMediaException('Connection is not for the Telegram platform.');
        }

        // We store the Bot Token in 'access_token' column
        $botToken = $connection->getDecryptedAccessToken();

        // We store the Channel/Chat ID in 'platform_user_id' column
        $chatId = $connection->platform_user_id;

        if (!$botToken || !$chatId) {
            throw new SocialMediaException('Telegram connection is missing Bot Token or Chat ID.');
        }

        return new self($botToken, $chatId);
    }

    /* --------------------------------------------------------------------------
     * SETUP & DISCOVERY METHODS
     * -------------------------------------------------------------------------- */

    /**
     * Verify a Bot Token is valid and get Bot info.
     * Useful during the connection setup phase.
     *
     * @param string $token
     * @return array
     */
    public static function verifyBotToken(string $token): array {
        $service = new self($token, 'dummy_chat_id');
        return $service->sendRequest('get', 'getMe');
    }

    /**
     * Get recent updates to find Chat IDs.
     * Useful to help the user find the ID of the channel they added the bot to.
     *
     * @param string|null $token Optional token override
     * @return array
     */
    public function getUpdates(?string $token = null): array {
        // Allow static usage logic or instance usage
        $token = $token ?? $this->botToken;
        $url = self::API_BASE_URL . $token . '/getUpdates';

        $response = Http::get($url);

        if (!$response->successful()) {
            throw new SocialMediaException("Failed to get Telegram updates: " . $response->body());
        }

        return $response->json()['result'] ?? [];
    }

    /* --------------------------------------------------------------------------
     * PUBLISHING METHODS
     * -------------------------------------------------------------------------- */

    /**
     * Share a text post with a URL.
     *
     * @param string $caption
     * @param string $url
     * @return array
     */
    public function shareUrl(string $caption, string $url): array {
        // Telegram doesn't have a specific "Link Post". We append the URL.
        // We disable web page preview if you want just the link, 
        // but usually, for sharing, you WANT the preview.
        $text = $caption . "\n\n" . $url;

        return $this->sendMessage($text);
    }

    /**
     * Share text-only message.
     *
     * @param string $caption
     * @return array
     */
    public function shareText(string $caption): array {
        return $this->sendMessage($caption);
    }

    /**
     * Share an image.
     * Supports both Remote URLs and Local File Paths.
     *
     * @param string $caption
     * @param string $imageUrl
     * @return array
     */
    public function shareImage(string $caption, string $imageUrl): array {
        $this->validateText($caption, 1024); // Caption limit for media is 1024

        // If URL is remote, send as string. If local, attach file.
        if (filter_var($imageUrl, FILTER_VALIDATE_URL)) {
            return $this->sendRequest('post', 'sendPhoto', [
                'chat_id' => $this->chatId,
                'photo' => $imageUrl,
                'caption' => $caption,
                'parse_mode' => 'HTML',
            ]);
        }

        return $this->sendWithFileUpload('sendPhoto', 'photo', $imageUrl, [
            'caption' => $caption,
            'parse_mode' => 'HTML'
        ]);
    }

    /**
     * Share a video.
     *
     * @param string $caption
     * @param string $videoUrl
     * @return array
     */
    public function shareVideo(string $caption, string $videoUrl): array {
        $this->validateText($caption, 1024);

        if (filter_var($videoUrl, FILTER_VALIDATE_URL)) {
            return $this->sendRequest('post', 'sendVideo', [
                'chat_id' => $this->chatId,
                'video' => $videoUrl,
                'caption' => $caption,
                'parse_mode' => 'HTML',
            ]);
        }

        return $this->sendWithFileUpload('sendVideo', 'video', $videoUrl, [
            'caption' => $caption,
            'parse_mode' => 'HTML'
        ]);
    }

    /**
     * Share a document (PDF, Zip, etc).
     *
     * @param string $caption
     * @param string $documentUrl
     * @return array
     */
    public function shareDocument(string $caption, string $documentUrl): array {
        $this->validateText($caption, 1024);

        if (filter_var($documentUrl, FILTER_VALIDATE_URL)) {
            return $this->sendRequest('post', 'sendDocument', [
                'chat_id' => $this->chatId,
                'document' => $documentUrl,
                'caption' => $caption,
                'parse_mode' => 'HTML',
            ]);
        }

        return $this->sendWithFileUpload('sendDocument', 'document', $documentUrl, [
            'caption' => $caption,
            'parse_mode' => 'HTML'
        ]);
    }

    /* --------------------------------------------------------------------------
     * INTERNAL HELPERS
     * -------------------------------------------------------------------------- */

    /**
     * Send a basic text message.
     */
    private function sendMessage(string $text): array {
        $this->validateText($text, 4096); // Limit for text messages

        return $this->sendRequest('post', 'sendMessage', [
            'chat_id' => $this->chatId,
            'text' => $text,
            'parse_mode' => 'HTML', // Using HTML allows basic formatting like <b>bold</b>
            'disable_web_page_preview' => false
        ]);
    }

    /**
     * Handle File Uploads (Multipart/Form-Data).
     *
     * @param string $endpoint
     * @param string $fileField (photo, video, document)
     * @param string $filePath
     * @param array $extraParams
     * @return array
     */
    private function sendWithFileUpload(string $endpoint, string $fileField, string $filePath, array $extraParams): array {
        if (!file_exists($filePath)) {
            throw new SocialMediaException("File not found for Telegram upload: $filePath");
        }

        $url = self::API_BASE_URL . $this->botToken . '/' . $endpoint;
        $extraParams['chat_id'] = $this->chatId;

        $response = Http::timeout(60)
            ->attach($fileField, file_get_contents($filePath), basename($filePath))
            ->post($url, $extraParams);

        if (!$response->successful()) {
            throw new SocialMediaException("Telegram File Upload Error: " . ($response->json()['description'] ?? $response->body()));
        }

        return $response->json()['result'];
    }

    /**
     * Send generic request via Base Class logic.
     */
    protected function sendRequest(string $method, string $endpoint, array $params = [], array $headers = []): array {
        // Build full URL
        $url = self::API_BASE_URL . $this->botToken . '/' . $endpoint;

        $response = parent::sendRequest($method, $url, $params, $headers);

        // Telegram wraps success in 'result' key
        return $response['result'] ?? $response;
    }
}

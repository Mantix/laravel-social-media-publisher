<?php

namespace Mantix\LaravelSocialMediaPublisher\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mantix\LaravelSocialMediaPublisher\Contracts\ShareImagePostInterface;
use Mantix\LaravelSocialMediaPublisher\Contracts\ShareInterface;
use Mantix\LaravelSocialMediaPublisher\Contracts\ShareVideoPostInterface;
use Mantix\LaravelSocialMediaPublisher\Exceptions\SocialMediaException;
use Mantix\LaravelSocialMediaPublisher\Models\SocialMediaConnection;

/**
 * Class PinterestService
 *
 * Service for managing and publishing content to Pinterest using the Pinterest API v5.
 *
 * @package Mantix\LaravelSocialMediaPublisher\Services
 */
class PinterestService extends SocialMediaService implements ShareInterface, ShareImagePostInterface, ShareVideoPostInterface {
    /** @var string Pinterest Access Token */
    private string $accessToken;

    /** @var string|null Default Board ID for this connection */
    private ?string $defaultBoardId;

    /** @var string Pinterest API base URL */
    private const API_BASE_URL = 'https://api.pinterest.com/v5';

    /**
     * PinterestService constructor.
     */
    public function __construct() {
        // Empty constructor
    }

    /**
     * Set the credentials for the PinterestService.
     *
     * @param string $accessToken
     * @param string|null $defaultBoardId
     * @return self
     */
    public function setCredentials(string $accessToken, ?string $defaultBoardId = null): self {
        $this->accessToken = $accessToken;
        $this->defaultBoardId = $defaultBoardId;
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
        if ($connection->platform !== 'pinterest') {
            throw new SocialMediaException('Connection is not for the Pinterest platform.');
        }

        $accessToken = $connection->getDecryptedAccessToken();

        // Check metadata for a selected default board
        $metadata = $connection->metadata ?? [];
        $boardId = $metadata['default_board_id'] ?? null;

        if (!$accessToken) {
            throw new SocialMediaException('Pinterest connection is missing an access token.');
        }

        return new self($accessToken, $boardId);
    }

    /* --------------------------------------------------------------------------
     * AUTHENTICATION & BOARDS
     * -------------------------------------------------------------------------- */

    /**
     * Get the Authorization URL.
     *
     * @param string $redirectUri
     * @param array $scopes
     * @param string|null $state
     * @return string
     */
    public static function getAuthorizationUrl(
        string $redirectUri,
        array $scopes = ['boards:read', 'pins:read', 'pins:write', 'user_accounts:read'],
        ?string $state = null
    ): string {
        $clientId = config('social_media_publisher.pinterest_client_id');

        if (!$clientId) {
            throw new SocialMediaException('Pinterest Client ID is not configured.');
        }

        $state = $state ?? bin2hex(random_bytes(16));
        $scopeString = implode(',', $scopes);

        return sprintf(
            'https://www.pinterest.com/oauth/?client_id=%s&redirect_uri=%s&response_type=code&scope=%s&state=%s',
            urlencode($clientId),
            urlencode($redirectUri),
            urlencode($scopeString),
            urlencode($state)
        );
    }

    /**
     * Handle OAuth Callback.
     *
     * @param string $code
     * @param string $redirectUri
     * @return array
     */
    public static function handleCallback(string $code, string $redirectUri): array {
        $clientId = config('social_media_publisher.pinterest_client_id');
        $clientSecret = config('social_media_publisher.pinterest_client_secret');

        $response = Http::asForm()->withBasicAuth($clientId, $clientSecret)->post(self::API_BASE_URL . '/oauth/token', [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
        ]);

        if (!$response->successful()) {
            throw new SocialMediaException('Failed to retrieve Pinterest access token: ' . $response->body());
        }

        // Fetch User Profile immediately to get the username/ID
        $tokenData = $response->json();

        // Create temp service to fetch profile
        $tempService = new self($tokenData['access_token']);
        $profile = $tempService->getUserInfo();

        return array_merge($tokenData, ['profile' => $profile]);
    }

    /**
     * Get all boards for the authenticated user.
     * Use this to let the user select a default board.
     *
     * @return array
     */
    public function getBoards(): array {
        $response = $this->sendRequest('get', 'boards', ['page_size' => 100]);
        return $response['items'] ?? [];
    }

    /**
     * Create a new Board.
     *
     * @param string $name
     * @param string $description
     * @param string $privacy 'PUBLIC' or 'SECRET'
     * @return array
     */
    public function createBoard(string $name, string $description = '', string $privacy = 'PUBLIC'): array {
        return $this->sendRequest('post', 'boards', [
            'name' => $name,
            'description' => $description,
            'privacy' => $privacy
        ]);
    }

    /* --------------------------------------------------------------------------
     * PUBLISHING METHODS
     * -------------------------------------------------------------------------- */

    /**
     * Share a link as a Pin.
     * * @param string $caption Pin Description
     * @param string $url The destination Link
     * @return array
     */
    public function shareUrl(string $caption, string $url): array {
        $this->ensureBoardIsSet();

        // Pinterest requires an image for a Link Pin. 
        // We cannot scrape the URL here reliable. 
        // If your app has a default "link icon" image, you could use it, 
        // but ideally, you should use shareImage() instead.
        throw new SocialMediaException("Pinterest requires an image to create a Pin. Please use shareImage() instead of shareUrl().");
    }

    /**
     * Share an Image Pin.
     *
     * @param string $caption Pin Description
     * @param string $imageUrl Public URL of the image
     * @return array
     */
    public function shareImage(string $caption, string $imageUrl): array {
        return $this->createPin([
            'title' => $this->generateTitle($caption),
            'description' => $caption,
            'media_source' => [
                'source_type' => 'image_url',
                'url' => $imageUrl
            ]
        ]);
    }

    /**
     * Share a Video Pin.
     * * @param string $caption
     * @param string $videoUrl
     * @return array
     */
    public function shareVideo(string $caption, string $videoUrl): array {
        // Pinterest Video Pins via URL REQUIRE a cover_image_url.
        // Since the standard interface doesn't support a 3rd argument for cover image,
        // we must throw an exception or require the user to use createPin() directly.

        throw new SocialMediaException("Pinterest requires a 'cover_image_url' for video pins. Please use the createPin() method directly and provide a cover image.");
    }

    /**
     * Manually Create a Pin (Flexible Method).
     * Use this if you need to set specific titles, links, or video cover images.
     *
     * @param array $data
     * @param string|null $boardId Override default board
     * @return array
     */
    public function createPin(array $data, ?string $boardId = null): array {
        $boardId = $boardId ?? $this->defaultBoardId;

        if (!$boardId) {
            throw new SocialMediaException('No Board ID provided for Pinterest Pin.');
        }

        $payload = array_merge([
            'board_id' => $boardId,
        ], $data);

        return $this->sendRequest('post', 'pins', $payload);
    }

    /**
     * Get User Info.
     *
     * @return array
     */
    public function getUserInfo(): array {
        return $this->sendRequest('get', 'user_account');
    }

    /* --------------------------------------------------------------------------
     * INTERNAL HELPERS
     * -------------------------------------------------------------------------- */

    private function ensureBoardIsSet(): void {
        if (!$this->defaultBoardId) {
            throw new SocialMediaException('Default Board ID is not set for this connection. Please update the connection settings.');
        }
    }

    private function generateTitle(string $text): string {
        // Pinterest Titles are max 100 chars
        $title = strtok($text, "\n"); // First line
        return mb_substr($title ?: 'New Pin', 0, 100);
    }

    protected function sendRequest(string $method, string $endpoint, array $params = [], array $headers = []): array {
        $url = self::API_BASE_URL . '/' . $endpoint;

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->accessToken,
            'Content-Type' => 'application/json',
        ])->$method($url, $params);

        if (!$response->successful()) {
            $error = $response->json()['message'] ?? $response->body();
            Log::error("Pinterest API Error [{$endpoint}]", ['error' => $error]);
            throw new SocialMediaException("Pinterest API Error: $error");
        }

        return $response->json();
    }
}

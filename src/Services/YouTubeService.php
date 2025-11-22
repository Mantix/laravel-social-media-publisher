<?php

namespace Mantix\LaravelSocialMediaPublisher\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mantix\LaravelSocialMediaPublisher\Contracts\ShareInterface;
use Mantix\LaravelSocialMediaPublisher\Contracts\ShareVideoPostInterface;
use Mantix\LaravelSocialMediaPublisher\Exceptions\SocialMediaException;
use Mantix\LaravelSocialMediaPublisher\Models\SocialMediaConnection;

/**
 * Class YouTubeService
 *
 * Service for managing and publishing content to YouTube using the Data API v3.
 *
 * @package Mantix\LaravelSocialMediaPublisher\Services
 */
class YouTubeService extends SocialMediaService implements ShareInterface, ShareVideoPostInterface {
    /** @var string OAuth 2.0 Access Token */
    private string $accessToken;

    /** @var string YouTube API Base URL */
    private const API_BASE_URL = 'https://www.googleapis.com/youtube/v3';

    /** @var string YouTube Upload URL */
    private const UPLOAD_URL = 'https://www.googleapis.com/upload/youtube/v3';

    /**
     * YouTubeService Constructor.
     *
     * @param string $accessToken
     */
    public function __construct(string $accessToken) {
        $this->accessToken = $accessToken;
    }

    /**
     * Create a new instance from a SocialMediaConnection.
     *
     * @param SocialMediaConnection $connection
     * @return self
     * @throws SocialMediaException
     */
    public static function forConnection(SocialMediaConnection $connection): self {
        if ($connection->platform !== 'youtube') {
            throw new SocialMediaException('Connection is not for the YouTube platform.');
        }

        $token = $connection->getDecryptedAccessToken();

        if (!$token) {
            throw new SocialMediaException('YouTube connection is missing Access Token.');
        }

        return new self($token);
    }

    /* --------------------------------------------------------------------------
     * AUTHENTICATION (OAuth 2.0)
     * -------------------------------------------------------------------------- */

    /**
     * Get Authorization URL.
     *
     * @param string $redirectUri
     * @param array $scopes
     * @param string|null $state
     * @return string
     */
    public static function getAuthorizationUrl(
        string $redirectUri,
        array $scopes = ['https://www.googleapis.com/auth/youtube.upload', 'https://www.googleapis.com/auth/youtube.readonly'],
        ?string $state = null
    ): string {
        $clientId = config('social_media_publisher.youtube_client_id');

        if (!$clientId) {
            throw new SocialMediaException('YouTube Client ID is not configured.');
        }

        $state = $state ?? bin2hex(random_bytes(16));
        $scopeString = implode(' ', $scopes);

        return sprintf(
            'https://accounts.google.com/o/oauth2/v2/auth?response_type=code&client_id=%s&redirect_uri=%s&scope=%s&state=%s&access_type=offline&prompt=consent',
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
        $clientId = config('social_media_publisher.youtube_client_id');
        $clientSecret = config('social_media_publisher.youtube_client_secret');

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
        ]);

        if (!$response->successful()) {
            throw new SocialMediaException('Failed to retrieve YouTube tokens: ' . $response->body());
        }

        $data = $response->json();

        // Fetch Channel Info for Identity
        $tempService = new self($data['access_token']);
        $channel = $tempService->getChannelInfo();

        return array_merge($data, ['channel' => $channel['items'][0] ?? []]);
    }

    /**
     * Refresh Access Token.
     *
     * @param string $refreshToken
     * @return array
     */
    public static function refreshAccessToken(string $refreshToken): array {
        $clientId = config('social_media_publisher.youtube_client_id');
        $clientSecret = config('social_media_publisher.youtube_client_secret');

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'refresh_token' => $refreshToken,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'grant_type' => 'refresh_token',
        ]);

        if (!$response->successful()) {
            throw new SocialMediaException('Failed to refresh YouTube token: ' . $response->body());
        }

        return $response->json();
    }

    /* --------------------------------------------------------------------------
     * PUBLISHING METHODS
     * -------------------------------------------------------------------------- */

    /**
     * Share Video (Resumable Upload).
     *
     * @param string $caption Video Title (Description appended if long)
     * @param string $videoUrl
     * @return array
     */
    public function shareVideo(string $caption, string $videoUrl): array {
        $this->validateText($caption, 100); // Title limit

        // 1. Prepare local file
        $localPath = $this->getTemporaryFilePath($videoUrl);

        try {
            // 2. Start Resumable Upload Session
            $sessionUrl = $this->initResumableUpload($caption, 'Video uploaded via API');

            // 3. Upload File Chunks
            return $this->performResumableUpload($sessionUrl, $localPath);
        } finally {
            if (file_exists($localPath)) {
                @unlink($localPath);
            }
        }
    }

    /**
     * Share Text (Not Supported).
     */
    public function shareText(string $caption): array {
        throw new SocialMediaException('YouTube API does not support creating text posts (Community Tab access is restricted).');
    }

    /**
     * Share Image (Not Supported).
     */
    public function shareImage(string $caption, string $imageUrl): array {
        throw new SocialMediaException('YouTube API does not support image uploads.');
    }

    /**
     * Share URL (Not Supported).
     */
    public function shareUrl(string $caption, string $url): array {
        throw new SocialMediaException('YouTube API does not support URL sharing directly. Upload a video instead.');
    }

    /* --------------------------------------------------------------------------
     * READ METHODS
     * -------------------------------------------------------------------------- */

    /**
     * Get Channel Info (Mine).
     *
     * @return array
     */
    public function getChannelInfo(): array {
        return $this->sendRequest('get', 'channels', [
            'part' => 'snippet,statistics,contentDetails',
            'mine' => 'true'
        ]);
    }

    /* --------------------------------------------------------------------------
     * RESUMABLE UPLOAD LOGIC
     * -------------------------------------------------------------------------- */

    /**
     * Step 1: Initialize Upload Session.
     */
    private function initResumableUpload(string $title, string $description): string {
        $metadata = [
            'snippet' => [
                'title' => $title,
                'description' => $description,
                'categoryId' => '22' // People & Blogs
            ],
            'status' => [
                'privacyStatus' => 'public' // or 'private', 'unlisted'
            ]
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->accessToken,
            'X-Upload-Content-Type' => 'video/mp4',
            'Content-Type' => 'application/json'
        ])->post(self::UPLOAD_URL . '/videos?uploadType=resumable&part=snippet,status', $metadata);

        if (!$response->successful()) {
            throw new SocialMediaException("YouTube Upload Init Failed: " . $response->body());
        }

        $sessionUrl = $response->header('Location');

        if (!$sessionUrl) {
            throw new SocialMediaException("YouTube Upload Init Failed: No Location Header received.");
        }

        return $sessionUrl;
    }

    /**
     * Step 2: Upload Binary Data in Chunks.
     */
    private function performResumableUpload(string $sessionUrl, string $filePath): array {
        $fileSize = filesize($filePath);
        $handle = fopen($filePath, 'rb');
        $chunkSize = 5 * 1024 * 1024; // 5MB Chunks (Must be multiple of 256KB)
        $offset = 0;

        while (!feof($handle)) {
            $chunk = fread($handle, $chunkSize);
            $bytesRead = strlen($chunk);
            $end = $offset + $bytesRead - 1;

            $response = Http::withHeaders([
                'Content-Length' => $bytesRead,
                'Content-Range' => "bytes {$offset}-{$end}/{$fileSize}"
            ])->put($sessionUrl, $chunk);

            // 308 Resume Incomplete means "Chunk received, keep going"
            if ($response->status() !== 308 && !$response->successful()) {
                fclose($handle);
                throw new SocialMediaException("YouTube Upload Chunk Failed: " . $response->body());
            }

            $offset += $bytesRead;
        }

        fclose($handle);

        // Final response contains the video data
        return $response->json();
    }

    /* --------------------------------------------------------------------------
     * INTERNAL HELPERS
     * -------------------------------------------------------------------------- */

    /**
     * Send Request.
     */
    protected function sendRequest(string $method, string $endpoint, array $params = [], array $headers = []): array {
        $url = self::API_BASE_URL . '/' . $endpoint;
        $headers['Authorization'] = 'Bearer ' . $this->accessToken;

        return parent::sendRequest($method, $url, $params, $headers);
    }
}

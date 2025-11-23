<?php

namespace Mantix\LaravelSocialMediaPublisher\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mantix\LaravelSocialMediaPublisher\Contracts\ShareInterface;
use Mantix\LaravelSocialMediaPublisher\Contracts\ShareVideoPostInterface;
use Mantix\LaravelSocialMediaPublisher\Exceptions\SocialMediaException;
use Mantix\LaravelSocialMediaPublisher\Models\SocialMediaConnection;

/**
 * Class TikTokService
 *
 * Service for managing and publishing content to TikTok using the TikTok Display API v2.
 *
 * @package Mantix\LaravelSocialMediaPublisher\Services
 */
class TikTokService extends SocialMediaService implements ShareInterface, ShareVideoPostInterface {
    /** @var string TikTok Access Token */
    private string $accessToken;

    /** @var string TikTok API base URL (v2) */
    private const API_BASE_URL = 'https://open.tiktokapis.com/v2';

    /**
     * TikTokService constructor.
     */
    public function __construct() {
        // Empty constructor
    }

    /**
     * Set the credentials for the TikTokService.
     * 
     * @param string $accessToken
     * @return self
     */
    public function setCredentials(string $accessToken): self {
        $this->accessToken = $accessToken;
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
        if ($connection->platform !== 'tiktok') {
            throw new SocialMediaException('Connection is not for the TikTok platform.');
        }

        $accessToken = $connection->getDecryptedAccessToken();

        if (!$accessToken) {
            throw new SocialMediaException('TikTok connection is missing required credentials.');
        }

        return new self($accessToken);
    }

    /* --------------------------------------------------------------------------
     * AUTHENTICATION
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
        array $scopes = ['user.info.basic', 'video.publish', 'video.upload'],
        ?string $state = null
    ): string {
        $clientKey = config('social_media_publisher.tiktok_client_id');

        if (!$clientKey) {
            throw new SocialMediaException('TikTok Client Key is not configured.');
        }

        $state = $state ?? bin2hex(random_bytes(16));
        $scopeString = implode(',', $scopes);

        return sprintf(
            'https://www.tiktok.com/v2/auth/authorize/?client_key=%s&response_type=code&scope=%s&redirect_uri=%s&state=%s',
            urlencode($clientKey),
            urlencode($scopeString),
            urlencode($redirectUri),
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
        $clientKey = config('social_media_publisher.tiktok_client_id');
        $clientSecret = config('social_media_publisher.tiktok_client_secret');

        $response = Http::asForm()->post(self::API_BASE_URL . '/oauth/token/', [
            'client_key' => $clientKey,
            'client_secret' => $clientSecret,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $redirectUri,
        ]);

        if (!$response->successful()) {
            throw new SocialMediaException('Failed to retrieve TikTok access token: ' . $response->body());
        }

        $data = $response->json();

        // Fetch basic profile info to identify the user
        $tempService = new self($data['access_token']);
        $profile = $tempService->getUserInfo();

        return array_merge($data, ['profile' => $profile['data']['user'] ?? []]);
    }

    /**
     * Refresh Access Token.
     *
     * @param string $refreshToken
     * @return array
     */
    public static function refreshAccessToken(string $refreshToken): array {
        $clientKey = config('social_media_publisher.tiktok_client_id');
        $clientSecret = config('social_media_publisher.tiktok_client_secret');

        $response = Http::asForm()->post(self::API_BASE_URL . '/oauth/token/', [
            'client_key' => $clientKey,
            'client_secret' => $clientSecret,
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]);

        if (!$response->successful()) {
            throw new SocialMediaException('Failed to refresh TikTok token: ' . $response->body());
        }

        return $response->json();
    }

    /* --------------------------------------------------------------------------
     * PUBLISHING METHODS
     * -------------------------------------------------------------------------- */

    /**
     * Share a Video to TikTok.
     * 
     *
     * @param string $caption
     * @param string $videoUrl
     * @return array
     */
    public function shareVideo(string $caption, string $videoUrl): array {
        $this->validateText($caption, 2200);

        // 1. Download video to a local temp file to ensure we can stream it securely
        $localPath = $this->getTemporaryFilePath($videoUrl);
        $fileSize = filesize($localPath);

        try {
            // 2. Initialize Upload
            // TikTok requires 'post_info' and 'source_info' structure
            $initData = $this->sendRequest('post', 'post/publish/video/init/', [
                'post_info' => [
                    'title' => $caption,
                    'privacy_level' => 'PUBLIC_TO_EVERYONE', // Options: PUBLIC_TO_EVERYONE, MUTUAL_FOLLOW_FRIENDS, SELF_ONLY
                    'disable_duet' => false,
                    'disable_comment' => false,
                    'disable_stitch' => false,
                    'video_cover_timestamp_ms' => 1000
                ],
                'source_info' => [
                    'source' => 'FILE_UPLOAD',
                    'video_size' => $fileSize,
                    'chunk_size' => $fileSize, // We upload in one chunk for simplicity (limit: 4GB usually)
                    'total_chunk_count' => 1
                ]
            ]);

            $uploadUrl = $initData['data']['upload_url'] ?? null;
            $publishId = $initData['data']['publish_id'] ?? null;

            if (!$uploadUrl || !$publishId) {
                throw new SocialMediaException('TikTok Init failed: Missing upload URL or Publish ID.');
            }

            // 3. Perform the Upload (PUT Binary)
            // Note: We use raw Http client here because we need to send raw binary body, not JSON
            $uploadResponse = Http::withHeaders([
                'Content-Type' => 'video/mp4',
                'Content-Length' => $fileSize,
                'Content-Range' => "bytes 0-" . ($fileSize - 1) . "/$fileSize"
            ])->put($uploadUrl, fopen($localPath, 'r'));

            if (!$uploadResponse->successful()) {
                throw new SocialMediaException("TikTok Binary Upload Failed: " . $uploadResponse->body());
            }

            // 4. TikTok auto-publishes after the upload is complete for 'FILE_UPLOAD' source.
            // We just return the publish ID reference.
            $this->log('info', 'TikTok video uploaded successfully', ['publish_id' => $publishId]);

            return ['publish_id' => $publishId];
        } finally {
            // Clean up temp file
            if (file_exists($localPath)) {
                @unlink($localPath);
            }
        }
    }

    /**
     * Share URL (Not Supported).
     */
    public function shareUrl(string $caption, string $url): array {
        throw new SocialMediaException('TikTok does not support text/link posts. Please use shareVideo().');
    }

    /**
     * Share Image (Not Supported via API currently).
     */
    public function shareImage(string $caption, string $imageUrl): array {
        throw new SocialMediaException('TikTok API currently only supports Video uploads. Image/Carousel support is limited.');
    }

    /**
     * Share Text (Not Supported).
     */
    public function shareText(string $caption): array {
        throw new SocialMediaException('TikTok does not support text-only posts.');
    }

    /* --------------------------------------------------------------------------
     * READ METHODS
     * -------------------------------------------------------------------------- */

    /**
     * Get User Info.
     *
     * @return array
     */
    public function getUserInfo(): array {
        return $this->sendRequest('get', 'user/info/', [
            'fields' => ['open_id', 'union_id', 'avatar_url', 'display_name', 'follower_count']
        ]);
    }

    /**
     * Get User Videos.
     *
     * @param int $maxCount
     * @return array
     */
    public function getUserVideos(int $maxCount = 20): array {
        return $this->sendRequest('post', 'video/list/', [
            'max_count' => min($maxCount, 20),
            'fields' => ['id', 'title', 'cover_image_url', 'share_url', 'view_count']
        ]);
    }

    /* --------------------------------------------------------------------------
     * INTERNAL HELPERS
     * -------------------------------------------------------------------------- */

    /**
     * Send Request to TikTok API v2.
     */
    protected function sendRequest(string $method, string $endpoint, array $params = [], array $headers = []): array {
        $url = self::API_BASE_URL . '/' . $endpoint;

        $headers['Authorization'] = 'Bearer ' . $this->accessToken;
        $headers['Content-Type'] = 'application/json';

        $response = parent::sendRequest($method, $url, $params, $headers);

        // TikTok wraps success in 'data' key, checks 'error' key inside body
        if (isset($response['error']['code']) && $response['error']['code'] !== 'ok') {
            throw new SocialMediaException("TikTok API Error: " . ($response['error']['message'] ?? 'Unknown'));
        }

        return $response;
    }
}

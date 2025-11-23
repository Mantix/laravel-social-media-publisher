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
 * Class FacebookService
 *
 * Service for managing and publishing content to Facebook Pages using the Graph API.
 *
 * Note: Posting to Personal Profiles is not supported by the Facebook API.
 *
 * @package Mantix\LaravelSocialMediaPublisher\Services
 */
class FacebookService extends SocialMediaService implements ShareInterface, ShareImagePostInterface, ShareVideoPostInterface {
    /** @var string The Access Token (Page Token for publishing, User Token for fetching accounts) */
    private string $accessToken;

    /** @var string|null The Page ID (Required for publishing) */
    private ?string $pageId;

    /** @var string Facebook API version */
    private const API_VERSION = 'v20.0';

    /** @var string Base Graph URL */
    private const GRAPH_URL = 'https://graph.facebook.com';

    /**
     * FacebookService constructor.
     */
    public function __construct() {
        // Empty constructor
    }

    /**
     * Set the credentials for the FacebookService.
     * @param string $accessToken
     * @param string|null $pageId
     * @return self
     */
    public function setCredentials(string $accessToken, ?string $pageId = null): self {
        $this->accessToken = $accessToken;
        $this->pageId = $pageId;
        
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
        if ($connection->platform !== 'facebook') {
            throw new SocialMediaException('Connection is not for the Facebook platform.');
        }

        $token = $connection->getDecryptedAccessToken();

        if (!$token) {
            throw new SocialMediaException('Facebook connection is missing a valid access token.');
        }

        // If it's a Page connection, we expect the platform_user_id to be the Page ID
        $pageId = ($connection->connection_type === 'page') ? $connection->platform_user_id : null;

        return new self($token, $pageId);
    }

    /* --------------------------------------------------------------------------
     * AUTHENTICATION FLOW
     * -------------------------------------------------------------------------- */

    /**
     * Get the authorization URL for Facebook OAuth.
     *
     * @param string $redirectUri
     * @param array $scopes
     * @param string|null $state
     * @return string
     * @throws SocialMediaException
     */
    public static function getAuthorizationUrl(
        string $redirectUri,
        array $scopes = ['pages_manage_posts', 'pages_read_engagement', 'pages_show_list', 'read_insights'],
        ?string $state = null
    ): string {
        $clientId = config('social_media_publisher.facebook_client_id');

        if (!$clientId) {
            throw new SocialMediaException('Facebook Client ID is not configured.');
        }

        $state = $state ?? bin2hex(random_bytes(16));
        $scopeString = implode(',', $scopes);

        return sprintf(
            'https://www.facebook.com/%s/dialog/oauth?client_id=%s&redirect_uri=%s&scope=%s&state=%s&response_type=code',
            self::API_VERSION,
            urlencode($clientId),
            urlencode($redirectUri),
            urlencode($scopeString),
            urlencode($state)
        );
    }

    /**
     * Handle the OAuth callback and exchange code for a Long-Lived User Access Token.
     *
     * @param string $code
     * @param string $redirectUri
     * @return array Contains 'access_token', 'expires_in', and 'profile' info.
     * @throws SocialMediaException
     */
    public static function handleCallback(string $code, string $redirectUri): array {
        $clientId = config('social_media_publisher.facebook_client_id');
        $clientSecret = config('social_media_publisher.facebook_client_secret');

        // 1. Exchange Code for Short-Lived Token
        $response = Http::get(self::GRAPH_URL . '/' . self::API_VERSION . '/oauth/access_token', [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri,
            'code' => $code,
        ]);

        if (!$response->successful()) {
            throw new SocialMediaException('Failed to exchange Facebook code: ' . $response->body());
        }

        $shortToken = $response->json()['access_token'];

        // 2. Exchange Short-Lived Token for Long-Lived Token (lasts 60 days)
        $longResponse = Http::get(self::GRAPH_URL . '/' . self::API_VERSION . '/oauth/access_token', [
            'grant_type' => 'fb_exchange_token',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'fb_exchange_token' => $shortToken,
        ]);

        if (!$longResponse->successful()) {
            // Fallback to short token if exchange fails, though unlikely
            $finalToken = $shortToken;
            $expiresIn = $response->json()['expires_in'] ?? 0;
        } else {
            $data = $longResponse->json();
            $finalToken = $data['access_token'];
            $expiresIn = $data['expires_in'] ?? (60 * 86400);
        }

        // 3. Get User Profile Info
        $profileResponse = Http::get(self::GRAPH_URL . '/' . self::API_VERSION . '/me', [
            'access_token' => $finalToken,
            'fields' => 'id,name,email,picture'
        ]);

        return [
            'access_token' => $finalToken,
            'expires_in' => $expiresIn,
            'token_type' => 'bearer',
            'profile' => $profileResponse->json()
        ];
    }

    /**
     * Get list of Pages managed by the user, including the specific PAGE ACCESS TOKEN.
     *
     * @return array
     * @throws SocialMediaException
     */
    public function getAccounts(): array {
        // We request the 'access_token' field specifically. 
        // This token allows us to post AS the page, not as the user ON the page.
        $response = $this->sendRequest('get', 'me/accounts', [
            'fields' => 'id,name,access_token,category,picture{url},tasks'
        ]);

        return $response['data'] ?? [];
    }

    /* --------------------------------------------------------------------------
     * PUBLISHING METHODS
     * -------------------------------------------------------------------------- */

    /**
     * Share text-only post to the Page Feed.
     *
     * @param string $caption
     * @return array
     * @throws SocialMediaException
     */
    public function shareText(string $caption): array {
        $this->ensurePageContext();

        return $this->sendRequest('post', "{$this->pageId}/feed", [
            'message' => $caption
        ]);
    }

    /**
     * Share a link to the Page Feed.
     *
     * @param string $caption
     * @param string $url
     * @return array
     * @throws SocialMediaException
     */
    public function shareUrl(string $caption, string $url): array {
        $this->ensurePageContext();

        return $this->sendRequest('post', "{$this->pageId}/feed", [
            'message' => $caption,
            'link' => $url
        ]);
    }

    /**
     * Share an image to the Page.
     *
     * @param string $caption
     * @param string $imageUrl
     * @return array
     * @throws SocialMediaException
     */
    public function shareImage(string $caption, string $imageUrl): array {
        $this->ensurePageContext();

        return $this->sendRequest('post', "{$this->pageId}/photos", [
            'url' => $imageUrl,
            'caption' => $caption,
        ]);
    }

    /**
     * Share a video to the Page (Supports Chunked Uploads).
     *
     * @param string $caption
     * @param string $videoUrl
     * @return array
     * @throws SocialMediaException
     */
    public function shareVideo(string $caption, string $videoUrl): array {
        $this->ensurePageContext();

        // 1. Download/Verify File
        $tempPath = $this->getFilePath($videoUrl);
        $fileSize = filesize($tempPath);

        // 2. Initialize Upload Session
        $initResponse = $this->sendRequest('post', "{$this->pageId}/videos", [
            'upload_phase' => 'start',
            'file_size' => $fileSize
        ]);

        $sessionId = $initResponse['upload_session_id'];
        $startOffset = (int) $initResponse['start_offset'];
        $endOffset = (int) $initResponse['end_offset'];

        $handle = fopen($tempPath, 'rb');

        // 3. Upload Chunks
        while ($startOffset < $fileSize) {
            fseek($handle, $startOffset);
            $chunkSize = $endOffset - $startOffset;
            $chunkData = fread($handle, $chunkSize);

            // Send raw binary data using Laravel Http client
            $transferResponse = Http::timeout(300)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->accessToken
                ])
                ->attach('video_file_chunk', $chunkData, 'chunk.mp4')
                ->post(self::GRAPH_URL . '/' . self::API_VERSION . "/{$this->pageId}/videos", [
                    'upload_phase' => 'transfer',
                    'upload_session_id' => $sessionId,
                    'start_offset' => $startOffset
                ]);

            if (!$transferResponse->successful()) {
                fclose($handle);
                throw new SocialMediaException('Video upload transfer failed: ' . $transferResponse->body());
            }

            $json = $transferResponse->json();
            $startOffset = (int) $json['start_offset'];
            $endOffset = (int) $json['end_offset'];
        }

        fclose($handle);

        // 4. Finish Upload
        $finishResponse = $this->sendRequest('post', "{$this->pageId}/videos", [
            'upload_phase' => 'finish',
            'upload_session_id' => $sessionId,
            'description' => $caption,
        ]);

        // Cleanup temp file if we downloaded it
        if ($tempPath !== $videoUrl && file_exists($tempPath)) {
            @unlink($tempPath);
        }

        return $finishResponse;
    }

    /**
     * Get Page Insights.
     *
     * @param array $metrics
     * @param string $period day, week, days_28, lifetime
     * @return array
     */
    public function getPageInsights(array $metrics, string $period = 'day'): array {
        $this->ensurePageContext();

        return $this->sendRequest('get', "{$this->pageId}/insights", [
            'metric' => implode(',', $metrics),
            'period' => $period
        ]);
    }

    /* --------------------------------------------------------------------------
     * INTERNAL HELPERS
     * -------------------------------------------------------------------------- */

    /**
     * Ensure we are set up to post to a Page.
     * @throws SocialMediaException
     */
    private function ensurePageContext(): void {
        if (!$this->pageId) {
            throw new SocialMediaException('This operation requires a Page ID (Page Connection).');
        }
    }

    /**
     * Handle local or remote files for video upload.
     */
    private function getFilePath(string $url): string {
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            $temp = tempnam(sys_get_temp_dir(), 'fb_vid_');
            file_put_contents($temp, fopen($url, 'r'));
            return $temp;
        }

        if (!file_exists($url)) {
            throw new SocialMediaException("File not found: $url");
        }

        return $url;
    }

    /**
     * Send request to Graph API.
     */
    protected function sendRequest(string $method, string $endpoint, array $params = [], array $headers = []): array {
        $url = self::GRAPH_URL . '/' . self::API_VERSION . '/' . $endpoint;

        // Add Access Token to all requests
        $params['access_token'] = $this->accessToken;

        $response = Http::timeout(60)->$method($url, $params);

        if (!$response->successful()) {
            $error = $response->json()['error']['message'] ?? $response->body();
            Log::error("Facebook API Error [{$endpoint}]", ['error' => $error]);
            throw new SocialMediaException("Facebook API Error: $error");
        }

        return $response->json();
    }
}

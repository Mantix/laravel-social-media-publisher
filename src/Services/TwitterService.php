<?php

namespace Mantix\LaravelSocialMediaPublisher\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use mantix\LaravelSocialMediaPublisher\Contracts\ShareImagePostInterface;
use mantix\LaravelSocialMediaPublisher\Contracts\ShareInterface;
use mantix\LaravelSocialMediaPublisher\Contracts\ShareVideoPostInterface;
use mantix\LaravelSocialMediaPublisher\Exceptions\SocialMediaException;
use mantix\LaravelSocialMediaPublisher\Models\SocialMediaConnection;

/**
 * Class TwitterService
 *
 * Service for managing and publishing content to Twitter/X using the Twitter API v2.
 *
 * Implements sharing of general posts, images, and videos to Twitter.
 */
class TwitterService extends SocialMediaService implements ShareInterface, ShareImagePostInterface, ShareVideoPostInterface
{
    /**
     * @var string Twitter Bearer Token
     */
    private $bearer_token;

    /**
     * @var string Twitter API Key
     */
    private $api_key;

    /**
     * @var string Twitter API Secret
     */
    private $api_secret;

    /**
     * @var string Twitter Access Token
     */
    private $access_token;

    /**
     * @var string Twitter Access Token Secret
     */
    private $access_token_secret;

    /**
     * @var TwitterService|null Singleton instance
     */
    private static ?TwitterService $instance = null;

    /**
     * Twitter API base URL
     */
    private const API_BASE_URL = 'https://api.twitter.com/2';

    /**
     * Private constructor to prevent direct instantiation.
     */
    private function __construct(
        string $bearerToken,
        string $apiKey,
        string $apiSecret,
        string $accessToken,
        string $accessTokenSecret
    ) {
        $this->bearer_token = $bearerToken;
        $this->api_key = $apiKey;
        $this->api_secret = $apiSecret;
        $this->access_token = $accessToken;
        $this->access_token_secret = $accessTokenSecret;
    }

    /**
     * Get instance - OAuth connection required.
     * 
     * @return TwitterService
     * @throws SocialMediaException
     * @deprecated Use forConnection() with a SocialMediaConnection instead
     */
    public static function getInstance(): TwitterService
    {
        throw new SocialMediaException('OAuth connection required. Please use forConnection() with a SocialMediaConnection or authenticate via OAuth first.');
    }

    /**
     * Create a new instance with specific credentials.
     *
     * @param string $bearerToken
     * @param string $apiKey
     * @param string $apiSecret
     * @param string $accessToken
     * @param string $accessTokenSecret
     * @return TwitterService
     */
    public static function withCredentials(string $bearerToken, string $apiKey, string $apiSecret, string $accessToken, string $accessTokenSecret): TwitterService
    {
        return new self($bearerToken, $apiKey, $apiSecret, $accessToken, $accessTokenSecret);
    }

    /**
     * Create a new instance from a SocialMediaConnection.
     *
     * @param SocialMediaConnection $connection
     * @return TwitterService
     * @throws SocialMediaException
     */
    public static function forConnection(SocialMediaConnection $connection): TwitterService
    {
        if ($connection->platform !== 'twitter') {
            throw new SocialMediaException('Connection is not for Twitter platform.');
        }

        $accessToken = $connection->getDecryptedAccessToken();
        $tokenSecret = $connection->getDecryptedTokenSecret();
        $metadata = $connection->metadata ?? [];
        $bearerToken = $metadata['bearer_token'] ?? null;
        $apiKey = $metadata['api_key'] ?? null;
        $apiSecret = $metadata['api_secret'] ?? null;

        if (!$accessToken || !$tokenSecret) {
            throw new SocialMediaException('Twitter connection is missing required credentials.');
        }

        // If bearer token and API keys are not in metadata, try to get from config
        if (!$bearerToken || !$apiKey || !$apiSecret) {
            $apiKey = config('social_media_publisher.x_api_key');
            $apiSecret = config('social_media_publisher.x_api_secret_key');
        }

        if (!$bearerToken || !$apiKey || !$apiSecret) {
            throw new SocialMediaException('Twitter API credentials are missing.');
        }

        return new self($bearerToken, $apiKey, $apiSecret, $accessToken, $tokenSecret);
    }

    /**
     * Get the authorization URL for Twitter/X OAuth 2.0.
     *
     * @param string $redirectUri
     * @param string|null $state
     * @param array $scopes
     * @param bool $usePkce Enable PKCE flow (Twitter/X requires PKCE for OAuth 2.0, but we make it optional for backward compatibility)
     * @param string|null $codeVerifier Optional code verifier (will generate if null and PKCE is enabled)
     * @return string|array Returns string URL if PKCE is disabled, or array with 'url' and 'code_verifier' if PKCE is enabled
     * @throws SocialMediaException
     */
    public static function getAuthorizationUrl(
        string $redirectUri, 
        ?string $state = null, 
        array $scopes = ['tweet.read', 'tweet.write', 'users.read', 'offline.access'],
        bool $usePkce = true,
        ?string $codeVerifier = null
    ) {
        $clientId = config('social_media_publisher.x_client_id');

        if (!$clientId) {
            throw new SocialMediaException('X/Twitter Client ID must be configured for OAuth.');
        }

        $state = $state ?? bin2hex(random_bytes(16));
        $scopeString = implode(' ', $scopes);

        $authUrl = sprintf(
            'https://twitter.com/i/oauth2/authorize?response_type=code&client_id=%s&redirect_uri=%s&scope=%s&state=%s',
            urlencode($clientId),
            urlencode($redirectUri),
            urlencode($scopeString),
            urlencode($state)
        );

        // Add PKCE support if enabled (recommended for Twitter/X OAuth 2.0)
        if ($usePkce) {
            // Generate code verifier if not provided
            if ($codeVerifier === null) {
                $codeVerifier = bin2hex(random_bytes(32));
            }

            // Generate code challenge (S256)
            $codeChallenge = base64_encode(hash('sha256', $codeVerifier, true));
            $codeChallenge = rtrim(strtr($codeChallenge, '+/', '-_'), '='); // Base64 URL encoding

            // Add PKCE parameters to authorization URL
            $authUrl .= '&code_challenge=' . urlencode($codeChallenge) . '&code_challenge_method=S256';

            if (config('social_media_publisher.enable_logging', true)) {
                Log::info('Twitter/X OAuth authorization URL generated with PKCE', [
                    'platform' => 'twitter',
                    'redirect_uri' => $redirectUri,
                    'scopes' => $scopes,
                    'state' => $state,
                    'has_client_id' => !empty($clientId),
                    'has_code_verifier' => !empty($codeVerifier),
                ]);
            }

            // Return array with URL and code verifier
            return [
                'url' => $authUrl,
                'code_verifier' => $codeVerifier,
            ];
        }

        if (config('social_media_publisher.enable_logging', true)) {
            Log::info('Twitter/X OAuth authorization URL generated', [
                'platform' => 'twitter',
                'redirect_uri' => $redirectUri,
                'scopes' => $scopes,
                'state' => $state,
                'has_client_id' => !empty($clientId),
            ]);
        }

        return $authUrl;
    }

    /**
     * Handle the OAuth callback and exchange code for access token.
     *
     * @param string $code
     * @param string $redirectUri
     * @param string|null $codeVerifier PKCE code verifier (should be retrieved from session/cache)
     * @return array
     * @throws SocialMediaException
     */
    public static function handleCallback(string $code, string $redirectUri, ?string $codeVerifier = null): array
    {
        $enableLogging = config('social_media_publisher.enable_logging', true);
        
        if ($enableLogging) {
            Log::info('Twitter/X OAuth callback initiated', [
                'platform' => 'twitter',
                'redirect_uri' => $redirectUri,
                'has_code' => !empty($code),
            ]);
        }

        $clientId = config('social_media_publisher.x_client_id');
        $clientSecret = config('social_media_publisher.x_client_secret');

        if (!$clientId || !$clientSecret) {
            if ($enableLogging) {
                Log::error('Twitter/X OAuth callback failed: missing credentials', [
                    'platform' => 'twitter',
                    'has_client_id' => !empty($clientId),
                    'has_client_secret' => !empty($clientSecret),
                ]);
            }
            throw new SocialMediaException('X/Twitter Client ID and Client Secret must be configured for OAuth.');
        }

        // Exchange code for access token
        $tokenUrl = 'https://api.twitter.com/2/oauth2/token';
        
        if ($enableLogging) {
            Log::debug('Exchanging Twitter/X OAuth code for access token', [
                'platform' => 'twitter',
                'token_url' => $tokenUrl,
                'redirect_uri' => $redirectUri,
            ]);
        }
        
        $timeout = config('social_media_publisher.timeout', 30);
        $params = [
            'code' => $code,
            'grant_type' => 'authorization_code',
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
        ];

        // Add PKCE code verifier if provided
        if ($codeVerifier) {
            $params['code_verifier'] = $codeVerifier;
        }

        $response = Http::timeout($timeout)
            ->withBasicAuth($clientId, $clientSecret)
            ->asForm()
            ->post($tokenUrl, $params);

        if (!$response->successful()) {
            if ($enableLogging) {
                Log::error('Twitter/X OAuth token exchange failed', [
                    'platform' => 'twitter',
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);
            }
            throw new SocialMediaException('Failed to exchange code for access token: ' . $response->body());
        }

        $tokenData = $response->json();
        $accessToken = $tokenData['access_token'] ?? null;
        $refreshToken = $tokenData['refresh_token'] ?? null;
        $expiresIn = $tokenData['expires_in'] ?? null;

        if (!$accessToken) {
            if ($enableLogging) {
                Log::error('Twitter/X OAuth callback failed: missing access token', [
                    'platform' => 'twitter',
                    'response' => $tokenData,
                ]);
            }
            throw new SocialMediaException('Failed to obtain access token from Twitter/X OAuth response.');
        }

        // Get user profile
        $userProfile = self::getUserProfile($accessToken);

        if ($enableLogging) {
            Log::info('Twitter/X OAuth callback completed successfully', [
                'platform' => 'twitter',
                'user_id' => $userProfile['id'] ?? null,
                'username' => $userProfile['username'] ?? null,
                'has_refresh_token' => !empty($refreshToken),
                'expires_in' => $expiresIn,
            ]);
        }

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in' => $expiresIn,
            'token_type' => $tokenData['token_type'] ?? 'bearer',
            'scope' => $tokenData['scope'] ?? null,
            'user_profile' => $userProfile,
        ];
    }

    /**
     * Get user profile using access token.
     *
     * @param string $accessToken
     * @return array
     * @throws SocialMediaException
     */
    private static function getUserProfile(string $accessToken): array
    {
        $timeout = config('social_media_publisher.timeout', 30);
        $response = Http::timeout($timeout)
            ->withToken($accessToken)
            ->get('https://api.twitter.com/2/users/me', [
                'user.fields' => 'id,name,username',
            ]);

        if (!$response->successful()) {
            throw new SocialMediaException('Failed to get user profile: ' . $response->body());
        }

        $data = $response->json();
        return $data['data'] ?? [];
    }

    /**
     * Refresh access token using refresh token.
     *
     * @param string $refreshToken The refresh token.
     * @return array Response containing new access token and refresh token.
     * @throws SocialMediaException
     */
    public static function refreshAccessToken(string $refreshToken): array
    {
        $enableLogging = config('social_media_publisher.enable_logging', true);
        
        if ($enableLogging) {
            Log::info('Twitter/X refresh token initiated', [
                'platform' => 'twitter',
                'has_refresh_token' => !empty($refreshToken),
            ]);
        }

        $clientId = config('social_media_publisher.x_client_id');
        $clientSecret = config('social_media_publisher.x_client_secret');

        if (!$clientId || !$clientSecret) {
            if ($enableLogging) {
                Log::error('Twitter/X refresh token failed: missing credentials', [
                    'platform' => 'twitter',
                    'has_client_id' => !empty($clientId),
                    'has_client_secret' => !empty($clientSecret),
                ]);
            }
            throw new SocialMediaException('X/Twitter Client ID and Client Secret must be configured.');
        }

        $tokenUrl = 'https://api.twitter.com/2/oauth2/token';
        
        if ($enableLogging) {
            Log::debug('Refreshing Twitter/X access token', [
                'platform' => 'twitter',
                'token_url' => $tokenUrl,
            ]);
        }
        
        $timeout = config('social_media_publisher.timeout', 30);
        $response = Http::timeout($timeout)
            ->withBasicAuth($clientId, $clientSecret)
            ->asForm()
            ->post($tokenUrl, [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
            ]);

        if (!$response->successful()) {
            if ($enableLogging) {
                Log::error('Twitter/X refresh token failed', [
                    'platform' => 'twitter',
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);
            }
            throw new SocialMediaException('Failed to refresh access token: ' . $response->body());
        }

        $tokenData = $response->json();
        
        if ($enableLogging) {
            Log::info('Twitter/X access token refreshed successfully', [
                'platform' => 'twitter',
                'token_type' => $tokenData['token_type'] ?? 'unknown',
                'expires_in' => $tokenData['expires_in'] ?? null,
            ]);
        }

        return $tokenData;
    }

    /**
     * Disconnect from Twitter/X (revoke access token).
     *
     * @param string $accessToken
     * @param string|null $accessTokenSecret Not used for OAuth 2.0, kept for compatibility
     * @return bool
     */
    public static function disconnect(string $accessToken, ?string $accessTokenSecret = null): bool
    {
        $enableLogging = config('social_media_publisher.enable_logging', true);
        
        try {
            $clientId = config('social_media_publisher.x_client_id');
            $clientSecret = config('social_media_publisher.x_client_secret');

            if (!$clientId || !$clientSecret) {
                if ($enableLogging) {
                    Log::error('Twitter/X disconnect failed: missing credentials', [
                        'platform' => 'twitter',
                    ]);
                }
                return false;
            }

            // Twitter/X OAuth 2.0 revoke endpoint
            $revokeUrl = 'https://api.twitter.com/2/oauth2/revoke';
            
            if ($enableLogging) {
                Log::info('Revoking Twitter/X access token', [
                    'platform' => 'twitter',
                ]);
            }
            
            $timeout = config('social_media_publisher.timeout', 30);
            $response = Http::timeout($timeout)
                ->withBasicAuth($clientId, $clientSecret)
                ->asForm()
                ->post($revokeUrl, [
                    'token' => $accessToken,
                    'token_type_hint' => 'access_token',
                ]);

            if ($response->successful()) {
                if ($enableLogging) {
                    Log::info('Twitter/X access token revoked successfully', [
                        'platform' => 'twitter',
                    ]);
                }
                return true;
            }

            if ($enableLogging) {
                Log::error('Failed to revoke Twitter/X access token', [
                    'platform' => 'twitter',
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);
            }
            return false;
        } catch (\Exception $e) {
            if ($enableLogging) {
                Log::error('Failed to disconnect Twitter/X', [
                    'platform' => 'twitter',
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                ]);
            }
            return false;
        }
    }

    /**
     * Share a text post with a URL to Twitter.
     *
     * @param string $caption The text content of the tweet.
     * @param string $url The URL to share.
     * @return array Response from the Twitter API.
     * @throws SocialMediaException
     */
    public function shareUrl(string $caption, string $url): array
    {
        $this->validateInput($caption, $url);
        
        $text = $this->formatTweetText($caption, $url);
        
        if (strlen($text) > 280) {
            throw new SocialMediaException('Tweet text exceeds 280 character limit.');
        }

        $url = $this->buildApiUrl('tweets');
        $params = [
            'text' => $text
        ];

        try {
            $response = $this->sendRequest($url, 'post', $params);
            $this->log('info', 'Twitter post shared successfully', [
                'platform' => 'twitter',
                'tweet_id' => $response['data']['id'] ?? null,
                'caption_length' => strlen($caption),
            ]);
            return $response;
        } catch (\Exception $e) {
            $this->log('error', 'Failed to share to Twitter', [
                'platform' => 'twitter',
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'url' => $url,
            ]);
            throw new SocialMediaException('Failed to share to Twitter: ' . $e->getMessage());
        }
    }

    /**
     * Share a text-only tweet to Twitter (without URL or image).
     *
     * @param string $caption The text content of the tweet.
     * @return array Response from the Twitter API.
     * @throws SocialMediaException
     */
    public function shareText(string $caption): array
    {
        if (empty(trim($caption))) {
            throw new SocialMediaException('Caption cannot be empty.');
        }
        
        if (strlen($caption) > 280) {
            throw new SocialMediaException('Tweet text exceeds 280 character limit.');
        }

        $url = $this->buildApiUrl('tweets');
        $params = [
            'text' => $caption
        ];

        try {
            $response = $this->sendRequest($url, 'post', $params);
            $this->log('info', 'Twitter text-only tweet shared successfully', [
                'platform' => 'twitter',
                'tweet_id' => $response['data']['id'] ?? null,
                'caption_length' => strlen($caption),
            ]);
            return $response;
        } catch (\Exception $e) {
            $this->log('error', 'Failed to share text-only tweet to Twitter', [
                'platform' => 'twitter',
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);
            throw new SocialMediaException('Failed to share text-only tweet to Twitter: ' . $e->getMessage());
        }
    }

    /**
     * Share an image post with a caption to Twitter.
     *
     * @param string $caption The caption to accompany the image.
     * @param string $image_url The URL of the image.
     * @return array Response from the Twitter API.
     * @throws SocialMediaException
     */
    public function shareImage(string $caption, string $image_url): array
    {
        $this->validateInput($caption, $image_url);
        
        try {
            // Step 1: Upload media
            $mediaId = $this->uploadMedia($image_url, 'image');
            
            // Step 2: Create tweet with media
            $url = $this->buildApiUrl('tweets');
            $params = [
                'text' => $caption,
                'media' => [
                    'media_ids' => [$mediaId]
                ]
            ];

            $response = $this->sendRequest($url, 'post', $params);
            $this->log('info', 'Twitter image post shared successfully', [
                'platform' => 'twitter',
                'tweet_id' => $response['data']['id'] ?? null,
                'caption_length' => strlen($caption),
            ]);
            return $response;
        } catch (\Exception $e) {
            $this->log('error', 'Failed to share image to Twitter', [
                'platform' => 'twitter',
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'image_url' => $image_url,
            ]);
            throw new SocialMediaException('Failed to share image to Twitter: ' . $e->getMessage());
        }
    }

    /**
     * Share a video post with a caption to Twitter.
     *
     * @param string $caption The caption to accompany the video.
     * @param string $video_url The URL of the video.
     * @return array Response from the Twitter API.
     * @throws SocialMediaException
     */
    public function shareVideo(string $caption, string $video_url): array
    {
        $this->validateInput($caption, $video_url);
        
        try {
            // Step 1: Upload media
            $mediaId = $this->uploadMedia($video_url, 'video');
            
            // Step 2: Create tweet with media
            $url = $this->buildApiUrl('tweets');
            $params = [
                'text' => $caption,
                'media' => [
                    'media_ids' => [$mediaId]
                ]
            ];

            $response = $this->sendRequest($url, 'post', $params);
            $this->log('info', 'Twitter video post shared successfully', [
                'platform' => 'twitter',
                'tweet_id' => $response['data']['id'] ?? null,
                'caption_length' => strlen($caption),
            ]);
            return $response;
        } catch (\Exception $e) {
            $this->log('error', 'Failed to share video to Twitter', [
                'platform' => 'twitter',
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'video_url' => $video_url,
            ]);
            throw new SocialMediaException('Failed to share video to Twitter: ' . $e->getMessage());
        }
    }

    /**
     * Get user's timeline tweets.
     *
     * @param int $limit Number of tweets to retrieve (max 100).
     * @return array Response from the Twitter API.
     * @throws SocialMediaException
     */
    public function getTimeline(int $limit = 10): array
    {
        try {
            $url = $this->buildApiUrl('users/me/tweets');
            $params = [
                'max_results' => min($limit, 100),
                'tweet.fields' => 'created_at,public_metrics'
            ];

            return $this->sendRequest($url, 'get', $params);
        } catch (\Exception $e) {
            $this->log('error', 'Failed to get Twitter timeline', [
                'platform' => 'twitter',
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);
            throw new SocialMediaException('Failed to get Twitter timeline: ' . $e->getMessage());
        }
    }

    /**
     * Get user profile information.
     *
     * @return array Response from the Twitter API.
     * @throws SocialMediaException
     */
    public function getUserInfo(): array
    {
        try {
            $url = $this->buildApiUrl('users/me');
            $params = [
                'user.fields' => 'public_metrics,verified,description'
            ];

            return $this->sendRequest($url, 'get', $params);
        } catch (\Exception $e) {
            $this->log('error', 'Failed to get Twitter user info', [
                'platform' => 'twitter',
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);
            throw new SocialMediaException('Failed to get Twitter user info: ' . $e->getMessage());
        }
    }

    /**
     * Upload media to Twitter.
     *
     * @param string $mediaUrl The URL of the media to upload.
     * @param string $type The type of media (image or video).
     * @return string The media ID.
     * @throws SocialMediaException
     */
    private function uploadMedia(string $mediaUrl, string $type): string
    {
        // Download media content
        $mediaContent = file_get_contents($mediaUrl);
        if ($mediaContent === false) {
            throw new SocialMediaException('Failed to download media from URL: ' . $mediaUrl);
        }

        // Upload to Twitter
        $url = 'https://upload.twitter.com/1.1/media/upload.json';
        $params = [
            'media' => base64_encode($mediaContent),
            'media_category' => $type === 'video' ? 'tweet_video' : 'tweet_image'
        ];

        $response = $this->sendRequest($url, 'post', $params);
        
        if (!isset($response['media_id_string'])) {
            throw new SocialMediaException('Failed to upload media to Twitter');
        }

        return $response['media_id_string'];
    }

    /**
     * Format tweet text with URL.
     *
     * @param string $caption The caption text.
     * @param string $url The URL to include.
     * @return string Formatted tweet text.
     */
    private function formatTweetText(string $caption, string $url): string
    {
        return $caption . ' ' . $url;
    }

    /**
     * Validate input parameters.
     *
     * @param string $caption The caption text.
     * @param string $url The URL.
     * @throws SocialMediaException
     */
    private function validateInput(string $caption, string $url): void
    {
        if (empty(trim($caption))) {
            throw new SocialMediaException('Caption cannot be empty.');
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new SocialMediaException('Invalid URL provided.');
        }
    }

    /**
     * Build Twitter API URL.
     *
     * @param string $endpoint The API endpoint.
     * @return string Complete API URL.
     */
    private function buildApiUrl(string $endpoint): string
    {
        return self::API_BASE_URL . '/' . $endpoint;
    }

    /**
     * Send authenticated request to Twitter API.
     *
     * @param string $url The API URL.
     * @param string $method The HTTP method.
     * @param array $params The request parameters.
     * @return array Response from the API.
     * @throws SocialMediaException
     */
    protected function sendRequest(string $url, string $method = 'post', array $params = [], array $headers = []): array
    {
        $defaultHeaders = [
            'Authorization' => 'Bearer ' . $this->bearer_token,
            'Content-Type' => 'application/json'
        ];
        
        $headers = array_merge($defaultHeaders, $headers);

        $response = \Illuminate\Support\Facades\Http::withHeaders($headers)
            ->{$method}($url, $params);

        if (!$response->successful()) {
            $errorMessage = $response->json()['detail'] ?? 'Unknown error occurred';
            throw new SocialMediaException("Twitter API error: {$errorMessage}");
        }

        return $response->json();
    }
}

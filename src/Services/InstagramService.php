<?php

namespace Mantix\LaravelSocialMediaPublisher\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mantix\LaravelSocialMediaPublisher\Contracts\ShareImagePostInterface;
use Mantix\LaravelSocialMediaPublisher\Contracts\ShareInterface;
use Mantix\LaravelSocialMediaPublisher\Contracts\ShareVideoPostInterface;
use Mantix\LaravelSocialMediaPublisher\Exceptions\SocialMediaException;

/**
 * Class InstagramService
 *
 * Service for managing and publishing content to Instagram using the Instagram Basic Display API and Instagram Graph API.
 *
 * Implements sharing of images and videos to Instagram.
 */
class InstagramService extends SocialMediaService implements ShareInterface, ShareImagePostInterface, ShareVideoPostInterface
{
    /**
     * @var string Instagram Access Token
     */
    private $access_token;

    /**
     * @var string Instagram Business Account ID
     */
    private $instagram_account_id;

    /**
     * @var InstagramService|null Singleton instance
     */
    private static ?InstagramService $instance = null;

    /**
     * Instagram API base URL
     */
    private const API_BASE_URL = 'https://graph.facebook.com/v20.0';

    /**
     * Private constructor to prevent direct instantiation.
     */
    private function __construct(
        string $accessToken,
        string $instagramAccountId
    ) {
        $this->access_token = $accessToken;
        $this->instagram_account_id = $instagramAccountId;
    }

    /**
     * Get instance - OAuth connection required.
     * 
     * @return InstagramService
     * @throws SocialMediaException
     * @deprecated Use forConnection() with a SocialMediaConnection instead
     */
    public static function getInstance(): InstagramService
    {
        throw new SocialMediaException('OAuth connection required. Please use forConnection() with a SocialMediaConnection or authenticate via OAuth first.');
    }

    /**
     * Create a new instance from a SocialMediaConnection.
     *
     * @param \mantix\LaravelSocialMediaPublisher\Models\SocialMediaConnection $connection
     * @return InstagramService
     * @throws SocialMediaException
     */
    public static function forConnection(\mantix\LaravelSocialMediaPublisher\Models\SocialMediaConnection $connection): InstagramService
    {
        if ($connection->platform !== 'instagram') {
            throw new SocialMediaException('Connection is not for Instagram platform.');
        }

        $accessToken = $connection->getDecryptedAccessToken();
        $metadata = $connection->metadata ?? [];
        $instagramAccountId = $connection->platform_user_id ?? $metadata['instagram_account_id'] ?? null;

        if (!$accessToken || !$instagramAccountId) {
            throw new SocialMediaException('Instagram connection is missing required credentials.');
        }

        return new self($accessToken, $instagramAccountId);
    }

    /**
     * Get the authorization URL for Instagram OAuth 2.0.
     * Note: Instagram uses Facebook's OAuth system, so this uses Facebook's authorization URL.
     *
     * @param string $redirectUri
     * @param string|null $state
     * @param array $scopes
     * @return string
     * @throws SocialMediaException
     */
    public static function getAuthorizationUrl(string $redirectUri, ?string $state = null, array $scopes = ['instagram_basic', 'instagram_content_publish', 'pages_show_list', 'pages_read_engagement']): string
    {
        $clientId = config('social_media_publisher.instagram_client_id') ?? config('social_media_publisher.facebook_client_id');
        $clientSecret = config('social_media_publisher.instagram_client_secret') ?? config('social_media_publisher.facebook_client_secret');

        if (!$clientId || !$clientSecret) {
            throw new SocialMediaException('Instagram Client ID and Client Secret must be configured for OAuth.');
        }

        $state = $state ?? bin2hex(random_bytes(16));
        $scopeString = implode(',', $scopes);

        $authUrl = sprintf(
            'https://www.facebook.com/v20.0/dialog/oauth?client_id=%s&redirect_uri=%s&scope=%s&state=%s&response_type=code',
            urlencode($clientId),
            urlencode($redirectUri),
            urlencode($scopeString),
            urlencode($state)
        );

        if (config('social_media_publisher.enable_logging', true)) {
            Log::info('Instagram OAuth authorization URL generated', [
                'platform' => 'instagram',
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
     * Note: Instagram uses Facebook's OAuth system, so this uses Facebook's token endpoint.
     *
     * @param string $code
     * @param string $redirectUri
     * @return array
     * @throws SocialMediaException
     */
    public static function handleCallback(string $code, string $redirectUri): array
    {
        $enableLogging = config('social_media_publisher.enable_logging', true);
        
        if ($enableLogging) {
            Log::info('Instagram OAuth callback initiated', [
                'platform' => 'instagram',
                'redirect_uri' => $redirectUri,
                'has_code' => !empty($code),
            ]);
        }

        $clientId = config('social_media_publisher.instagram_client_id') ?? config('social_media_publisher.facebook_client_id');
        $clientSecret = config('social_media_publisher.instagram_client_secret') ?? config('social_media_publisher.facebook_client_secret');

        if (!$clientId || !$clientSecret) {
            if ($enableLogging) {
                Log::error('Instagram OAuth callback failed: missing credentials', [
                    'platform' => 'instagram',
                    'has_client_id' => !empty($clientId),
                    'has_client_secret' => !empty($clientSecret),
                ]);
            }
            throw new SocialMediaException('Instagram Client ID and Client Secret must be configured for OAuth.');
        }

        // Exchange code for access token (using Facebook's endpoint)
        $tokenUrl = 'https://graph.facebook.com/v20.0/oauth/access_token';
        
        if ($enableLogging) {
            Log::debug('Exchanging Instagram OAuth code for access token', [
                'platform' => 'instagram',
                'token_url' => $tokenUrl,
                'redirect_uri' => $redirectUri,
            ]);
        }
        
        $timeout = config('social_media_publisher.timeout', 30);
        $response = Http::timeout($timeout)->asForm()->post($tokenUrl, [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri,
            'code' => $code,
        ]);

        if (!$response->successful()) {
            if ($enableLogging) {
                Log::error('Instagram OAuth token exchange failed', [
                    'platform' => 'instagram',
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);
            }
            throw new SocialMediaException('Failed to exchange code for access token: ' . $response->body());
        }

        $tokenData = $response->json();
        $accessToken = $tokenData['access_token'] ?? null;
        $expiresIn = $tokenData['expires_in'] ?? null;

        if (!$accessToken) {
            if ($enableLogging) {
                Log::error('Instagram OAuth callback failed: missing access token', [
                    'platform' => 'instagram',
                    'response' => $tokenData,
                ]);
            }
            throw new SocialMediaException('Failed to obtain access token from Instagram OAuth response.');
        }

        // Get user's Facebook pages (to find connected Instagram accounts)
        $pages = self::getFacebookPages($accessToken);
        
        // Find Instagram Business Account connected to a page
        $instagramAccount = null;
        foreach ($pages as $page) {
            $pageId = $page['id'] ?? null;
            if ($pageId) {
                $instagramAccounts = self::getInstagramAccounts($accessToken, $pageId);
                if (!empty($instagramAccounts)) {
                    $instagramAccount = $instagramAccounts[0];
                    break;
                }
            }
        }

        if (!$instagramAccount) {
            throw new SocialMediaException('No Instagram Business Account found. Please ensure your Facebook Page is connected to an Instagram Business Account.');
        }

        if ($enableLogging) {
            Log::info('Instagram OAuth callback completed successfully', [
                'platform' => 'instagram',
                'instagram_account_id' => $instagramAccount['id'] ?? null,
                'instagram_username' => $instagramAccount['username'] ?? null,
                'expires_in' => $expiresIn,
            ]);
        }

        return [
            'access_token' => $accessToken,
            'expires_in' => $expiresIn,
            'token_type' => $tokenData['token_type'] ?? 'bearer',
            'instagram_account' => $instagramAccount,
        ];
    }

    /**
     * Extend short-lived access token to long-lived access token.
     * Instagram uses Facebook's token extension system.
     *
     * @param string $shortLivedToken The short-lived access token to extend.
     * @return array Response containing long-lived access token and expiration.
     * @throws SocialMediaException
     */
    public static function extendAccessToken(string $shortLivedToken): array
    {
        $enableLogging = config('social_media_publisher.enable_logging', true);
        
        if ($enableLogging) {
            Log::info('Instagram extend token initiated', [
                'platform' => 'instagram',
                'has_token' => !empty($shortLivedToken),
            ]);
        }

        $clientId = config('social_media_publisher.instagram_client_id') ?? config('social_media_publisher.facebook_client_id');
        $clientSecret = config('social_media_publisher.instagram_client_secret') ?? config('social_media_publisher.facebook_client_secret');

        if (!$clientId || !$clientSecret) {
            if ($enableLogging) {
                Log::error('Instagram extend token failed: missing credentials', [
                    'platform' => 'instagram',
                    'has_client_id' => !empty($clientId),
                    'has_client_secret' => !empty($clientSecret),
                ]);
            }
            throw new SocialMediaException('Instagram/Facebook Client ID and Client Secret must be configured.');
        }

        $tokenUrl = 'https://graph.facebook.com/v20.0/oauth/access_token';
        
        if ($enableLogging) {
            Log::debug('Extending Instagram access token', [
                'platform' => 'instagram',
                'token_url' => $tokenUrl,
            ]);
        }
        
        $timeout = config('social_media_publisher.timeout', 30);
        $response = Http::timeout($timeout)->get($tokenUrl, [
            'grant_type' => 'fb_exchange_token',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'fb_exchange_token' => $shortLivedToken,
        ]);

        if (!$response->successful()) {
            if ($enableLogging) {
                Log::error('Instagram extend token failed', [
                    'platform' => 'instagram',
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);
            }
            throw new SocialMediaException('Failed to extend access token: ' . $response->body());
        }

        $tokenData = $response->json();
        
        if ($enableLogging) {
            Log::info('Instagram access token extended successfully', [
                'platform' => 'instagram',
                'expires_in' => $tokenData['expires_in'] ?? null,
            ]);
        }

        return $tokenData;
    }

    /**
     * Refresh access token using refresh token (alias for extendAccessToken for consistency).
     * Note: Instagram doesn't use refresh tokens, but uses Facebook's token extension instead.
     * This method is provided for API consistency with other platforms.
     *
     * @param string $shortLivedToken The short-lived access token to extend.
     * @return array Response containing long-lived access token and expiration.
     * @throws SocialMediaException
     */
    public static function refreshAccessToken(string $shortLivedToken): array
    {
        return self::extendAccessToken($shortLivedToken);
    }

    /**
     * Get Facebook pages for the authenticated user.
     *
     * @param string $accessToken
     * @return array
     * @throws SocialMediaException
     */
    private static function getFacebookPages(string $accessToken): array
    {
        $timeout = config('social_media_publisher.timeout', 30);
        $response = Http::timeout($timeout)
            ->get('https://graph.facebook.com/v20.0/me/accounts', [
                'access_token' => $accessToken,
                'fields' => 'id,name,access_token',
            ]);

        if (!$response->successful()) {
            throw new SocialMediaException('Failed to get Facebook pages: ' . $response->body());
        }

        $data = $response->json();
        return $data['data'] ?? [];
    }

    /**
     * Get Instagram Business Accounts connected to a Facebook Page.
     *
     * @param string $accessToken
     * @param string $pageId
     * @return array
     * @throws SocialMediaException
     */
    private static function getInstagramAccounts(string $accessToken, string $pageId): array
    {
        $timeout = config('social_media_publisher.timeout', 30);
        $response = Http::timeout($timeout)
            ->get("https://graph.facebook.com/v20.0/{$pageId}", [
                'access_token' => $accessToken,
                'fields' => 'instagram_business_account{id,username}',
            ]);

        if (!$response->successful()) {
            throw new SocialMediaException('Failed to get Instagram accounts: ' . $response->body());
        }

        $data = $response->json();
        $instagramAccount = $data['instagram_business_account'] ?? null;
        
        return $instagramAccount ? [$instagramAccount] : [];
    }

    /**
     * Disconnect from Instagram (revoke access token).
     *
     * @param string $accessToken
     * @return bool
     */
    public static function disconnect(string $accessToken): bool
    {
        $enableLogging = config('social_media_publisher.enable_logging', true);
        
        try {
            // Instagram uses Facebook's revoke endpoint
            $revokeUrl = 'https://graph.facebook.com/v20.0/me/permissions';
            
            if ($enableLogging) {
                Log::info('Revoking Instagram access token', [
                    'platform' => 'instagram',
                ]);
            }
            
            $timeout = config('social_media_publisher.timeout', 30);
            $response = Http::timeout($timeout)
                ->delete($revokeUrl, [
                    'access_token' => $accessToken,
                ]);

            if ($response->successful()) {
                if ($enableLogging) {
                    Log::info('Instagram access token revoked successfully', [
                        'platform' => 'instagram',
                    ]);
                }
                return true;
            }

            if ($enableLogging) {
                Log::error('Failed to revoke Instagram access token', [
                    'platform' => 'instagram',
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);
            }
            return false;
        } catch (\Exception $e) {
            if ($enableLogging) {
                Log::error('Failed to disconnect Instagram', [
                    'platform' => 'instagram',
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                ]);
            }
            return false;
        }
    }

    /**
     * Share a text post with a URL to Instagram (as a story or feed post).
     * Note: Instagram doesn't support direct URL sharing in feed posts, so this creates a story.
     *
     * @param string $caption The text content of the post.
     * @param string $url The URL to share.
     * @return array Response from the Instagram API.
     * @throws SocialMediaException
     */
    public function shareUrl(string $caption, string $url): array
    {
        $this->validateInput($caption, $url);
        
        try {
            // Instagram doesn't support direct URL sharing in feed posts
            // We'll create a story with the URL
            return $this->shareStory($caption, $url);
        } catch (\Exception $e) {
            $this->log('error', 'Failed to share to Instagram', [
                'platform' => 'instagram',
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);
            throw new SocialMediaException('Failed to share to Instagram: ' . $e->getMessage());
        }
    }

    /**
     * Share an image post with a caption to Instagram.
     *
     * @param string $caption The caption to accompany the image.
     * @param string $image_url The URL of the image.
     * @return array Response from the Instagram API.
     * @throws SocialMediaException
     */
    public function shareImage(string $caption, string $image_url): array
    {
        $this->validateInput($caption, $image_url);
        
        try {
            // Step 1: Create media container
            $containerUrl = $this->buildApiUrl($this->instagram_account_id . '/media');
            $containerParams = [
                'image_url' => $image_url,
                'caption' => $caption,
                'access_token' => $this->access_token
            ];

            $containerResponse = $this->sendRequest($containerUrl, 'post', $containerParams);
            $containerId = $containerResponse['id'];

            // Step 2: Publish the media
            $publishUrl = $this->buildApiUrl($this->instagram_account_id . '/media_publish');
            $publishParams = [
                'creation_id' => $containerId,
                'access_token' => $this->access_token
            ];

            $response = $this->sendRequest($publishUrl, 'post', $publishParams);
            $this->log('info', 'Instagram image post shared successfully', [
                'platform' => 'instagram',
                'media_id' => $response['id'] ?? null,
                'instagram_account_id' => $this->instagram_account_id,
                'caption_length' => strlen($caption),
            ]);
            return $response;
        } catch (\Exception $e) {
            $this->log('error', 'Failed to share image to Instagram', [
                'platform' => 'instagram',
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'instagram_account_id' => $this->instagram_account_id,
                'image_url' => $image_url,
            ]);
            throw new SocialMediaException('Failed to share image to Instagram: ' . $e->getMessage());
        }
    }

    /**
     * Share a video post with a caption to Instagram.
     *
     * @param string $caption The caption to accompany the video.
     * @param string $video_url The URL of the video.
     * @return array Response from the Instagram API.
     * @throws SocialMediaException
     */
    public function shareVideo(string $caption, string $video_url): array
    {
        $this->validateInput($caption, $video_url);
        
        try {
            // Step 1: Create media container
            $containerUrl = $this->buildApiUrl($this->instagram_account_id . '/media');
            $containerParams = [
                'media_type' => 'VIDEO',
                'video_url' => $video_url,
                'caption' => $caption,
                'access_token' => $this->access_token
            ];

            $containerResponse = $this->sendRequest($containerUrl, 'post', $containerParams);
            $containerId = $containerResponse['id'];

            // Step 2: Publish the media
            $publishUrl = $this->buildApiUrl($this->instagram_account_id . '/media_publish');
            $publishParams = [
                'creation_id' => $containerId,
                'access_token' => $this->access_token
            ];

            $response = $this->sendRequest($publishUrl, 'post', $publishParams);
            $this->log('info', 'Instagram video post shared successfully', [
                'platform' => 'instagram',
                'media_id' => $response['id'] ?? null,
                'instagram_account_id' => $this->instagram_account_id,
                'caption_length' => strlen($caption),
            ]);
            return $response;
        } catch (\Exception $e) {
            $this->log('error', 'Failed to share video to Instagram', [
                'platform' => 'instagram',
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'instagram_account_id' => $this->instagram_account_id,
                'video_url' => $video_url,
            ]);
            throw new SocialMediaException('Failed to share video to Instagram: ' . $e->getMessage());
        }
    }

    /**
     * Share a story with text and URL.
     *
     * @param string $caption The text content.
     * @param string $url The URL to share.
     * @return array Response from the Instagram API.
     * @throws SocialMediaException
     */
    public function shareStory(string $caption, string $url): array
    {
        $this->validateInput($caption, $url);
        
        try {
            // Create a story with text overlay
            $storyUrl = $this->buildApiUrl($this->instagram_account_id . '/media');
            $storyParams = [
                'media_type' => 'STORIES',
                'image_url' => $this->createTextImage($caption, $url),
                'access_token' => $this->access_token
            ];

            $response = $this->sendRequest($storyUrl, 'post', $storyParams);
            $this->log('info', 'Instagram story shared successfully', [
                'platform' => 'instagram',
                'media_id' => $response['id'] ?? null,
                'instagram_account_id' => $this->instagram_account_id,
            ]);
            return $response;
        } catch (\Exception $e) {
            $this->log('error', 'Failed to share story to Instagram', [
                'platform' => 'instagram',
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'instagram_account_id' => $this->instagram_account_id,
            ]);
            throw new SocialMediaException('Failed to share story to Instagram: ' . $e->getMessage());
        }
    }

    /**
     * Share a carousel post with multiple images.
     *
     * @param string $caption The caption for the carousel.
     * @param array $image_urls Array of image URLs.
     * @return array Response from the Instagram API.
     * @throws SocialMediaException
     */
    public function shareCarousel(string $caption, array $image_urls): array
    {
        if (empty($image_urls) || count($image_urls) < 2 || count($image_urls) > 10) {
            throw new SocialMediaException('Carousel must contain between 2 and 10 images.');
        }

        try {
            $children = [];

            // Step 1: Create media containers for each image
            foreach ($image_urls as $image_url) {
                $containerUrl = $this->buildApiUrl($this->instagram_account_id . '/media');
                $containerParams = [
                    'image_url' => $image_url,
                    'is_carousel_item' => true,
                    'access_token' => $this->access_token
                ];

                $containerResponse = $this->sendRequest($containerUrl, 'post', $containerParams);
                $children[] = $containerResponse['id'];
            }

            // Step 2: Create carousel container
            $carouselUrl = $this->buildApiUrl($this->instagram_account_id . '/media');
            $carouselParams = [
                'media_type' => 'CAROUSEL',
                'children' => implode(',', $children),
                'caption' => $caption,
                'access_token' => $this->access_token
            ];

            $carouselResponse = $this->sendRequest($carouselUrl, 'post', $carouselParams);
            $carouselId = $carouselResponse['id'];

            // Step 3: Publish the carousel
            $publishUrl = $this->buildApiUrl($this->instagram_account_id . '/media_publish');
            $publishParams = [
                'creation_id' => $carouselId,
                'access_token' => $this->access_token
            ];

            $response = $this->sendRequest($publishUrl, 'post', $publishParams);
            $this->log('info', 'Instagram carousel post shared successfully', [
                'platform' => 'instagram',
                'media_id' => $response['id'] ?? null,
                'instagram_account_id' => $this->instagram_account_id,
                'images_count' => count($image_urls),
            ]);
            return $response;
        } catch (\Exception $e) {
            $this->log('error', 'Failed to share carousel to Instagram', [
                'platform' => 'instagram',
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'instagram_account_id' => $this->instagram_account_id,
            ]);
            throw new SocialMediaException('Failed to share carousel to Instagram: ' . $e->getMessage());
        }
    }

    /**
     * Get Instagram account information.
     *
     * @return array Response from the Instagram API.
     * @throws SocialMediaException
     */
    public function getAccountInfo(): array
    {
        try {
            $url = $this->buildApiUrl($this->instagram_account_id);
            $params = [
                'fields' => 'id,username,account_type,media_count,followers_count,follows_count',
                'access_token' => $this->access_token
            ];

            return $this->sendRequest($url, 'get', $params);
        } catch (\Exception $e) {
            $this->log('error', 'Failed to get Instagram account info', [
                'platform' => 'instagram',
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'instagram_account_id' => $this->instagram_account_id,
            ]);
            throw new SocialMediaException('Failed to get Instagram account info: ' . $e->getMessage());
        }
    }

    /**
     * Get recent media from Instagram account.
     *
     * @param int $limit Number of media items to retrieve.
     * @return array Response from the Instagram API.
     * @throws SocialMediaException
     */
    public function getRecentMedia(int $limit = 25): array
    {
        try {
            $url = $this->buildApiUrl($this->instagram_account_id . '/media');
            $params = [
                'fields' => 'id,caption,media_type,media_url,thumbnail_url,permalink,timestamp',
                'limit' => min($limit, 25),
                'access_token' => $this->access_token
            ];

            return $this->sendRequest($url, 'get', $params);
        } catch (\Exception $e) {
            $this->log('error', 'Failed to get Instagram recent media', [
                'platform' => 'instagram',
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'instagram_account_id' => $this->instagram_account_id,
                'limit' => $limit,
            ]);
            throw new SocialMediaException('Failed to get Instagram recent media: ' . $e->getMessage());
        }
    }

    /**
     * Create a text image for stories.
     *
     * @param string $text The text to display.
     * @param string $url The URL to include.
     * @return string The URL of the generated image.
     * @throws SocialMediaException
     */
    private function createTextImage(string $text, string $url): string
    {
        // This is a simplified implementation
        // In a real scenario, you might want to use a service like Canva API or generate images programmatically
        $imageText = $text . "\n\n" . $url;
        
        // For now, return a placeholder image URL
        // In production, you should generate an actual image with the text
        return 'https://via.placeholder.com/1080x1920/000000/FFFFFF?text=' . urlencode($imageText);
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
     * Build Instagram API URL.
     *
     * @param string $endpoint The API endpoint.
     * @return string Complete API URL.
     */
    private function buildApiUrl(string $endpoint): string
    {
        return self::API_BASE_URL . '/' . $endpoint;
    }

    /**
     * Send authenticated request to Instagram API.
     *
     * @param string $url The API URL.
     * @param string $method The HTTP method.
     * @param array $params The request parameters.
     * @return array Response from the API.
     * @throws SocialMediaException
     */
    protected function sendRequest(string $url, string $method = 'post', array $params = [], array $headers = []): array
    {
        $response = \Illuminate\Support\Facades\Http::withHeaders($headers)->{$method}($url, $params);

        if (!$response->successful()) {
            $errorData = $response->json();
            $errorMessage = $errorData['error']['message'] ?? 'Unknown error occurred';
            throw new SocialMediaException("Instagram API error: {$errorMessage}");
        }

        return $response->json();
    }
}

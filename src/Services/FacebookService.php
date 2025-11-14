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
 * Class FacebookService
 *
 * Service for managing and publishing content to Facebook using the Graph API.
 *
 * Implements sharing of general posts, images, and videos to a Facebook page.
 */
class FacebookService extends SocialMediaService implements ShareInterface, ShareImagePostInterface, ShareVideoPostInterface {

    /**
     * @var string Facebook access token
     */
    private $access_token;

    /**
     * @var string Facebook page ID
     */
    private $page_id;

    /**
     * @var FacebookService|null Singleton instance
     */
    private static ?FacebookService $instance = null;
    /**
     * Facebook API version
     */
    private const API_VERSION = 'v20.0';


    /**
     * Private constructor to prevent direct instantiation.
     */

    private function __construct(string $accessToken, string $pageId) {
        $this->access_token = $accessToken;
        $this->page_id = $pageId;
    }

    /**
     * Get instance - OAuth connection required.
     * 
     * This method is deprecated. Use forConnection() instead.
     *
     * @return FacebookService
     * @throws SocialMediaException
     * @deprecated Use forConnection() with a SocialMediaConnection instead
     */
    public static function getInstance() {
        throw new SocialMediaException('OAuth connection required. Please use forConnection() with a SocialMediaConnection or authenticate via OAuth first.');
    }

    /**
     * Create a new instance with specific credentials.
     *
     * @param string $accessToken
     * @param string $pageId
     * @return FacebookService
     */
    public static function withCredentials(string $accessToken, string $pageId): FacebookService
    {
        return new self($accessToken, $pageId);
    }

    /**
     * Create a new instance from a SocialMediaConnection.
     *
     * @param SocialMediaConnection $connection
     * @return FacebookService
     * @throws SocialMediaException
     */
    public static function forConnection(SocialMediaConnection $connection): FacebookService
    {
        if ($connection->platform !== 'facebook') {
            throw new SocialMediaException('Connection is not for Facebook platform.');
        }

        $accessToken = $connection->getDecryptedAccessToken();
        $metadata = $connection->metadata ?? [];
        $pageId = $metadata['page_id'] ?? null;

        if (!$accessToken || !$pageId) {
            throw new SocialMediaException('Facebook connection is missing required credentials.');
        }

        return new self($accessToken, $pageId);
    }

    /**
     * Get the authorization URL for Facebook OAuth.
     *
     * @param string $redirectUri
     * @param array $scopes
     * @param string|null $state
     * @return string
     * @throws SocialMediaException
     */
    public static function getAuthorizationUrl(string $redirectUri, array $scopes = ['pages_manage_posts', 'pages_read_engagement'], ?string $state = null): string
    {
        $clientId = config('social_media_publisher.facebook_client_id');
        $clientSecret = config('social_media_publisher.facebook_client_secret');

        if (!$clientId || !$clientSecret) {
            throw new SocialMediaException('Facebook Client ID and Client Secret must be configured for OAuth.');
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
            Log::info('Facebook OAuth authorization URL generated', [
                'platform' => 'facebook',
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
     * @return array
     * @throws SocialMediaException
     */
    public static function handleCallback(string $code, string $redirectUri): array
    {
        $enableLogging = config('social_media_publisher.enable_logging', true);
        
        if ($enableLogging) {
            Log::info('Facebook OAuth callback initiated', [
                'platform' => 'facebook',
                'redirect_uri' => $redirectUri,
                'has_code' => !empty($code),
            ]);
        }

        $clientId = config('social_media_publisher.facebook_client_id');
        $clientSecret = config('social_media_publisher.facebook_client_secret');

        if (!$clientId || !$clientSecret) {
            if ($enableLogging) {
                Log::error('Facebook OAuth callback failed: missing credentials', [
                    'platform' => 'facebook',
                    'has_client_id' => !empty($clientId),
                    'has_client_secret' => !empty($clientSecret),
                ]);
            }
            throw new SocialMediaException('Facebook Client ID and Client Secret must be configured for OAuth.');
        }

        // Exchange code for access token
        $tokenUrl = 'https://graph.facebook.com/v20.0/oauth/access_token';
        
        if ($enableLogging) {
            Log::debug('Exchanging Facebook OAuth code for access token', [
                'platform' => 'facebook',
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
                Log::error('Facebook OAuth token exchange failed', [
                    'platform' => 'facebook',
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);
            }
            throw new SocialMediaException('Failed to exchange code for access token: ' . $response->body());
        }

        $tokenData = $response->json();
        $accessToken = $tokenData['access_token'] ?? null;

        if (!$accessToken) {
            if ($enableLogging) {
                Log::error('Facebook OAuth access token not found in response', [
                    'platform' => 'facebook',
                    'response_keys' => array_keys($tokenData),
                ]);
            }
            throw new SocialMediaException('Access token not found in response.');
        }

        if ($enableLogging) {
            Log::info('Facebook OAuth access token obtained', [
                'platform' => 'facebook',
                'token_type' => $tokenData['token_type'] ?? 'unknown',
                'expires_in' => $tokenData['expires_in'] ?? null,
            ]);
        }

        // Get long-lived token
        $longLivedTokenUrl = 'https://graph.facebook.com/v20.0/oauth/access_token';
        
        if ($enableLogging) {
            Log::debug('Requesting Facebook long-lived access token', [
                'platform' => 'facebook',
            ]);
        }
        
        $timeout = config('social_media_publisher.timeout', 30);
        $longLivedResponse = Http::timeout($timeout)->get($longLivedTokenUrl, [
            'grant_type' => 'fb_exchange_token',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'fb_exchange_token' => $accessToken,
        ]);

        if ($longLivedResponse->successful()) {
            $longLivedData = $longLivedResponse->json();
            $accessToken = $longLivedData['access_token'] ?? $accessToken;
            $expiresIn = $longLivedData['expires_in'] ?? null;
            
            if ($enableLogging) {
                Log::info('Facebook long-lived access token obtained', [
                    'platform' => 'facebook',
                    'expires_in' => $expiresIn,
                ]);
            }
        } else {
            if ($enableLogging) {
                Log::warning('Facebook long-lived token exchange failed, using short-lived token', [
                    'platform' => 'facebook',
                    'status' => $longLivedResponse->status(),
                ]);
            }
        }

        // Get user pages
        $pagesUrl = 'https://graph.facebook.com/v20.0/me/accounts';
        
        if ($enableLogging) {
            Log::debug('Fetching Facebook user pages', [
                'platform' => 'facebook',
            ]);
        }
        
        $timeout = config('social_media_publisher.timeout', 30);
        $pagesResponse = Http::timeout($timeout)->get($pagesUrl, [
            'access_token' => $accessToken,
        ]);

        $pages = [];
        if ($pagesResponse->successful()) {
            $pagesData = $pagesResponse->json();
            $pages = $pagesData['data'] ?? [];
            
            if ($enableLogging) {
                Log::info('Facebook user pages retrieved', [
                    'platform' => 'facebook',
                    'pages_count' => count($pages),
                    'page_ids' => array_column($pages, 'id'),
                ]);
            }
        } else {
            if ($enableLogging) {
                Log::warning('Failed to retrieve Facebook user pages', [
                    'platform' => 'facebook',
                    'status' => $pagesResponse->status(),
                ]);
            }
        }

        if ($enableLogging) {
            Log::info('Facebook OAuth callback completed successfully', [
                'platform' => 'facebook',
                'has_access_token' => !empty($accessToken),
                'pages_count' => count($pages),
            ]);
        }

        return [
            'access_token' => $accessToken,
            'expires_in' => $expiresIn ?? null,
            'token_type' => $tokenData['token_type'] ?? 'bearer',
            'pages' => $pages,
        ];
    }

    /**
     * Extend short-lived access token to long-lived access token.
     * Facebook uses token extension instead of refresh tokens.
     *
     * @param string $shortLivedToken The short-lived access token to extend.
     * @return array Response containing long-lived access token and expiration.
     * @throws SocialMediaException
     */
    public static function extendAccessToken(string $shortLivedToken): array
    {
        $enableLogging = config('social_media_publisher.enable_logging', true);
        
        if ($enableLogging) {
            Log::info('Facebook extend token initiated', [
                'platform' => 'facebook',
                'has_token' => !empty($shortLivedToken),
            ]);
        }

        $clientId = config('social_media_publisher.facebook_client_id');
        $clientSecret = config('social_media_publisher.facebook_client_secret');

        if (!$clientId || !$clientSecret) {
            if ($enableLogging) {
                Log::error('Facebook extend token failed: missing credentials', [
                    'platform' => 'facebook',
                    'has_client_id' => !empty($clientId),
                    'has_client_secret' => !empty($clientSecret),
                ]);
            }
            throw new SocialMediaException('Facebook Client ID and Client Secret must be configured.');
        }

        $tokenUrl = 'https://graph.facebook.com/v20.0/oauth/access_token';
        
        if ($enableLogging) {
            Log::debug('Extending Facebook access token', [
                'platform' => 'facebook',
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
                Log::error('Facebook extend token failed', [
                    'platform' => 'facebook',
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);
            }
            throw new SocialMediaException('Failed to extend access token: ' . $response->body());
        }

        $tokenData = $response->json();
        
        if ($enableLogging) {
            Log::info('Facebook access token extended successfully', [
                'platform' => 'facebook',
                'expires_in' => $tokenData['expires_in'] ?? null,
            ]);
        }

        return $tokenData;
    }

    /**
     * Refresh access token using refresh token (alias for extendAccessToken for consistency).
     * Note: Facebook doesn't use refresh tokens, but uses token extension instead.
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
     * Disconnect from Facebook by revoking the access token.
     *
     * @param string $accessToken
     * @return bool
     */
    public static function disconnect(string $accessToken): bool
    {
        $enableLogging = config('social_media_publisher.enable_logging', true);
        
        if ($enableLogging) {
            Log::info('Facebook OAuth disconnect initiated', [
                'platform' => 'facebook',
            ]);
        }
        
        try {
            $revokeUrl = 'https://graph.facebook.com/v20.0/me/permissions';
            $timeout = config('social_media_publisher.timeout', 30);
            $response = Http::timeout($timeout)->delete($revokeUrl, [
                'access_token' => $accessToken,
            ]);

            $success = $response->successful();
            
            if ($enableLogging) {
                if ($success) {
                    Log::info('Facebook OAuth disconnect successful', [
                        'platform' => 'facebook',
                        'status' => $response->status(),
                    ]);
                } else {
                    Log::error('Facebook OAuth disconnect failed', [
                        'platform' => 'facebook',
                        'status' => $response->status(),
                        'response' => $response->body(),
                    ]);
                }
            }

            return $success;
        } catch (\Exception $e) {
            if ($enableLogging) {
                Log::error('Facebook OAuth disconnect exception', [
                    'platform' => 'facebook',
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                ]);
            }
            return false;
        }
    }

    /**
     * Share an image post with a caption and an image URL to Facebook.
     *
     * @param string $caption The caption to accompany the image.
     * @param string $image_url The URL of the image.
     *
     * @return array Response from the Facebook API.
     * @throws SocialMediaException
     */
    public function shareImage(string $caption, string $image_url): array
    {
        $this->validateText($caption, 2000);
        $this->validateUrl($image_url);
        
        try {
            $url = $this->buildApiUrl('photos');
            $params = $this->buildParams([
                'url'     => $image_url,
                'caption' => $caption,
            ]);

            $response = $this->sendRequest($url, 'post', $params);
            if (config('social_media_publisher.enable_logging', true)) {
                Log::info('Facebook image post shared successfully', [
                    'platform' => 'facebook',
                    'post_id' => $response['id'] ?? null,
                    'page_id' => $this->page_id,
                    'caption_length' => strlen($caption),
                ]);
            }
            return $response;
        } catch (\Exception $e) {
            if (config('social_media_publisher.enable_logging', true)) {
                Log::error('Failed to share image to Facebook', [
                    'platform' => 'facebook',
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                    'page_id' => $this->page_id,
                    'image_url' => $image_url,
                ]);
            }
            throw new SocialMediaException('Failed to share image to Facebook: ' . $e->getMessage());
        }
    }

    /**
     * Share a text post with a caption and a URL to Facebook.
     *
     * @param string $caption The caption to accompany the post.
     * @param string $url The URL to share.
     *
     * @return array Response from the Facebook API.
     * @throws SocialMediaException
     */
    public function shareUrl(string $caption, string $url): array
    {
        $this->validateText($caption, 2000);
        $this->validateUrl($url);
        
        try {
            $feedUrl = $this->buildApiUrl('feed');
            $params = $this->buildParams([
                'message' => $caption,
                'link'    => $url,
            ]);

            $response = $this->sendRequest($feedUrl, 'post', $params);
            if (config('social_media_publisher.enable_logging', true)) {
                Log::info('Facebook post shared successfully', [
                    'platform' => 'facebook',
                    'post_id' => $response['id'] ?? null,
                    'page_id' => $this->page_id,
                    'caption_length' => strlen($caption),
                    'has_url' => !empty($url),
                ]);
            }
            return $response;
        } catch (\Exception $e) {
            if (config('social_media_publisher.enable_logging', true)) {
                Log::error('Failed to share to Facebook', [
                    'platform' => 'facebook',
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                    'page_id' => $this->page_id,
                    'url' => $url,
                ]);
            }
            throw new SocialMediaException('Failed to share to Facebook: ' . $e->getMessage());
        }
    }

    /**
     * Share a text-only post to Facebook (without URL or image).
     *
     * @param string $caption The text content of the post.
     * @return array Response from the Facebook API.
     * @throws SocialMediaException
     */
    public function shareText(string $caption): array
    {
        $this->validateText($caption, 2000);
        
        try {
            $feedUrl = $this->buildApiUrl('feed');
            $params = $this->buildParams([
                'message' => $caption,
            ]);

            $response = $this->sendRequest($feedUrl, 'post', $params);
            if (config('social_media_publisher.enable_logging', true)) {
                Log::info('Facebook text-only post shared successfully', [
                    'platform' => 'facebook',
                    'post_id' => $response['id'] ?? null,
                    'page_id' => $this->page_id,
                    'caption_length' => strlen($caption),
                ]);
            }
            return $response;
        } catch (\Exception $e) {
            if (config('social_media_publisher.enable_logging', true)) {
                Log::error('Failed to share text-only post to Facebook', [
                    'platform' => 'facebook',
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                    'page_id' => $this->page_id,
                ]);
            }
            throw new SocialMediaException('Failed to share text-only post to Facebook: ' . $e->getMessage());
        }
    }

    /**
     * Share a video post with a caption and a video URL to Facebook.
     *
     * @param string $caption The caption to accompany the video.
     * @param string $video_url The URL of the video (local file path or remote URL).
     *
     * @return mixed Response from the Facebook API.
     */
    public function shareVideo(string $caption, string $video_url): array {
        // Step 1: Check if the video URL is remote and download the file if necessary
        $video_path = $this->downloadIfRemote($video_url);

        if (!file_exists($video_path)) {
            return ['error' => 'Failed to download video or file does not exist.'];
        }

        // Step 2: Get the size of the video file
        $fileSize = filesize($video_path);

        // Step 3: Start the upload session
        $startUrl = $this->buildApiUrl('videos');
        $params = $this->buildParams([
            'upload_phase' => 'start',
            'file_size'    => $fileSize, // Total size of the video file
        ]);

        $response = $this->sendRequest($startUrl, 'post', $params);
        $uploadSessionId = $response['upload_session_id'] ?? null;

        if (!$uploadSessionId) {
            return ['error' => 'Failed to start video upload session.'];
        }

        // Step 4: Upload the video in chunks (if required)
        $startOffset = $response['start_offset'] ?? 0;
        $endOffset = $response['end_offset'] ?? $fileSize;

        while ($startOffset < $endOffset) {
            $chunkPath = $this->saveVideoChunk($video_path, $startOffset, $endOffset);

            // Ensure the chunk was saved successfully
            if (!file_exists($chunkPath)) {
                return ['error' => 'Failed to save video chunk.'];
            }

            // Transfer phase - upload the chunk
            $params = $this->buildParams([
                'upload_phase'      => 'transfer',
                'upload_session_id' => $uploadSessionId,
                'start_offset'      => $startOffset,
                'video_file_chunk'  => new \CURLFile($chunkPath) // Pass the chunk as a CURLFile
            ]);

            $transferResponse = $this->sendRequest($startUrl, 'post', $params);
            $startOffset = $transferResponse['start_offset'] ?? $endOffset;
            $endOffset = $transferResponse['end_offset'] ?? $fileSize;
        }

        // Step 5: Complete the video upload
        return $this->completeVideoUpload($uploadSessionId, $caption);
    }

    /**
     * Helper to download the video file if it's a remote URL.
     *
     * @param string $video_url The remote URL or local file path of the video.
     *
     * @return string Local file path of the video (either the original path or downloaded file).
     */
    private function downloadIfRemote(string $video_url): string {
        // Check if the URL is a remote URL
        if (filter_var($video_url, FILTER_VALIDATE_URL)) {
            // Download the remote file and save it locally
            $tempPath = sys_get_temp_dir() . '/' . basename($video_url);
            file_put_contents($tempPath, fopen($video_url, 'r'));
            return $tempPath; // Return the path to the downloaded file
        }

        // If it's already a local file, just return the same path
        return $video_url;
    }

    /**
     * Helper to save a chunk of the video file for transfer.
     *
     * @param string $video_path Path to the video file.
     * @param int $start_offset The start byte for the chunk.
     * @param int $end_offset The end byte for the chunk.
     *
     * @return string The path to the saved chunk file.
     */
    private function saveVideoChunk(string $video_path, int $start_offset, int $end_offset): string {
        $chunkPath = sys_get_temp_dir() . '/' . uniqid('video_chunk_') . '.mp4';

        $handle = fopen($video_path, 'rb');
        fseek($handle, $start_offset);
        $chunkData = fread($handle, $end_offset - $start_offset);
        fclose($handle);

        // Save the chunk data to a temporary file
        file_put_contents($chunkPath, $chunkData);

        return $chunkPath;
    }

    /**
     * Complete the video upload process.
     *
     * @param string $uploadSessionId The upload session ID.
     * @param string $caption The caption to accompany the video.
     *
     * @return mixed Response from the Facebook API.
     */
    private function completeVideoUpload(string $uploadSessionId, string $caption) {
        $completeUrl = $this->buildApiUrl('videos');
        $params = $this->buildParams([
            'upload_phase'      => 'finish',
            'upload_session_id' => $uploadSessionId,
            'description'       => $caption,
            'title'             => $caption,
            'published'         => true,
        ]);

        return $this->sendRequest($completeUrl, 'post', $params);
    }



    /**
     * Retrieve insights for the Facebook page.
     *
     * @return mixed Response from the Facebook API.
     */
    public function getPageInsights(array $metrics = [], array $additionalParams = []): array {
        $url = $this->buildApiUrl('insights');
        $params = $this->buildParams(array_merge([
            'metric' => implode(',', $metrics),
        ], $additionalParams));

        return $this->sendRequest($url, 'get', $params);
    }


    /**
     * Retrieve information about the Facebook page.
     *
     * @return mixed Response from the Facebook API.
     */
    public function getPageInfo() {
        $url = $this->buildApiUrl();
        $params = $this->buildParams();

        return $this->sendRequest($url, 'get', $params);
    }


    /**
     * Helper to build Facebook Graph API URL.
     *
     * @param string $endpoint
     *
     * @return string
     */
    private function buildApiUrl(string $endpoint = ''): string {
        $apiVersion = config('social_media_publisher.facebook_api_version');
        return 'https://graph.facebook.com/' . $apiVersion . '/' . $this->page_id . '/' . $endpoint;
    }

    /**
     * Helper to build request parameters.
     *
     * @param array $params
     *
     * @return array
     */
    private function buildParams(array $params = []): array {
        return array_merge($params, ['access_token' => $this->access_token]);
    }
}
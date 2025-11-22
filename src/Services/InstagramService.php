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
 * Class InstagramService
 *
 * Service for managing and publishing content to Instagram Business/Creator Accounts via the Graph API.
 *
 * @package Mantix\LaravelSocialMediaPublisher\Services
 */
class InstagramService extends SocialMediaService implements ShareInterface, ShareImagePostInterface, ShareVideoPostInterface {
    /** @var string Facebook/Instagram User Access Token */
    private string $accessToken;

    /** @var string The Instagram Business Account ID (IG User ID) */
    private string $accountId;

    /** @var string API Version */
    private const API_VERSION = 'v20.0';

    /** @var string Base Graph URL */
    private const GRAPH_URL = 'https://graph.facebook.com';

    /**
     * InstagramService Constructor.
     *
     * @param string $accessToken
     * @param string $accountId
     */
    public function __construct(string $accessToken, string $accountId) {
        $this->accessToken = $accessToken;
        $this->accountId = $accountId;
    }

    /**
     * Create a new instance from a SocialMediaConnection.
     *
     * @param SocialMediaConnection $connection
     * @return self
     * @throws SocialMediaException
     */
    public static function forConnection(SocialMediaConnection $connection): self {
        if ($connection->platform !== 'instagram') {
            throw new SocialMediaException('Connection is not for the Instagram platform.');
        }

        $token = $connection->getDecryptedAccessToken();

        // For Instagram, the platform_user_id MUST be the Instagram Business Account ID
        $accountId = $connection->platform_user_id;

        if (!$token || !$accountId) {
            throw new SocialMediaException('Instagram connection is missing credentials.');
        }

        return new self($token, $accountId);
    }

    /* --------------------------------------------------------------------------
     * AUTHENTICATION & DISCOVERY
     * -------------------------------------------------------------------------- */

    /**
     * Get the authorization URL (Uses Facebook Login).
     *
     * @param string $redirectUri
     * @param array $scopes
     * @param string|null $state
     * @return string
     * @throws SocialMediaException
     */
    public static function getAuthorizationUrl(
        string $redirectUri,
        array $scopes = ['instagram_basic', 'instagram_content_publish', 'pages_show_list', 'pages_read_engagement'],
        ?string $state = null
    ): string {
        // Reuse Facebook Service logic or config, as Instagram uses Facebook OAuth
        return FacebookService::getAuthorizationUrl($redirectUri, $scopes, $state);
    }

    /**
     * Handle Callback (Exchange code for token).
     *
     * @param string $code
     * @param string $redirectUri
     * @return array
     */
    public static function handleCallback(string $code, string $redirectUri): array {
        // Reuse Facebook Service logic because the endpoint is identical
        return FacebookService::handleCallback($code, $redirectUri);
    }

    /**
     * Get a list of Instagram Business Accounts available to the user.
     * Use this to allow the user to select which IG account to connect.
     *
     * @return array List of ['id' => '...', 'username' => '...', 'name' => '...']
     * @throws SocialMediaException
     */
    public function getBusinessAccounts(): array {
        // 1. Get User's Facebook Pages
        $response = $this->sendRequest('get', 'me/accounts', [
            'fields' => 'name,instagram_business_account{id,username,name,profile_picture_url}'
        ]);

        $accounts = [];

        if (isset($response['data'])) {
            foreach ($response['data'] as $page) {
                // We only care about pages that have an IG Business Account linked
                if (isset($page['instagram_business_account'])) {
                    $ig = $page['instagram_business_account'];
                    $accounts[] = [
                        'id' => $ig['id'],
                        'username' => $ig['username'] ?? '',
                        'name' => $ig['name'] ?? $ig['username'],
                        'profile_picture' => $ig['profile_picture_url'] ?? null,
                        'facebook_page_name' => $page['name'] // Useful for UI context
                    ];
                }
            }
        }

        return $accounts;
    }

    /* --------------------------------------------------------------------------
     * PUBLISHING METHODS
     * -------------------------------------------------------------------------- */

    /**
     * Share a single image to the Feed.
     *
     * @param string $caption
     * @param string $imageUrl Must be a public URL (JPEG).
     * @return array
     * @throws SocialMediaException
     */
    public function shareImage(string $caption, string $imageUrl): array {
        // 1. Create Media Container
        $container = $this->sendRequest('post', "{$this->accountId}/media", [
            'image_url' => $imageUrl,
            'caption' => $caption,
            'is_carousel_item' => false
        ]);

        // 2. Publish
        return $this->publishContainer($container['id']);
    }

    /**
     * Share a video to Reels/Feed.
     *
     * @param string $caption
     * @param string $videoUrl Must be a public URL (MP4/MOV).
     * @return array
     * @throws SocialMediaException
     */
    public function shareVideo(string $caption, string $videoUrl): array {
        // 1. Create Media Container
        $container = $this->sendRequest('post', "{$this->accountId}/media", [
            'media_type' => 'VIDEO',
            'video_url' => $videoUrl,
            'caption' => $caption
        ]);

        // 2. Wait for Processing (Crucial for Video)
        $this->waitForContainer($container['id']);

        // 3. Publish
        return $this->publishContainer($container['id']);
    }

    /**
     * Share a Carousel (Album).
     *
     * @param string $caption
     * @param array $mediaUrls Array of image URLs.
     * @return array
     * @throws SocialMediaException
     */
    public function shareCarousel(string $caption, array $mediaUrls): array {
        if (count($mediaUrls) < 2 || count($mediaUrls) > 10) {
            throw new SocialMediaException("Instagram Carousels require between 2 and 10 items.");
        }

        $childrenIds = [];

        // 1. Create containers for each item
        foreach ($mediaUrls as $url) {
            $child = $this->sendRequest('post', "{$this->accountId}/media", [
                'image_url' => $url,
                'is_carousel_item' => true
            ]);
            $childrenIds[] = $child['id'];
        }

        // 2. Create Carousel Container
        $carouselContainer = $this->sendRequest('post', "{$this->accountId}/media", [
            'media_type' => 'CAROUSEL',
            'children' => implode(',', $childrenIds),
            'caption' => $caption
        ]);

        // 3. Publish
        return $this->publishContainer($carouselContainer['id']);
    }

    /**
     * Share an Image Story.
     *
     * @param string $imageUrl Public URL of image (9:16 aspect ratio recommended).
     * @return array
     * @throws SocialMediaException
     */
    public function shareStoryImage(string $imageUrl): array {
        $container = $this->sendRequest('post', "{$this->accountId}/media", [
            'image_url' => $imageUrl,
            'media_type' => 'STORIES'
        ]);

        return $this->publishContainer($container['id']);
    }

    /**
     * Share a Video Story.
     *
     * @param string $videoUrl Public URL of video.
     * @return array
     * @throws SocialMediaException
     */
    public function shareStoryVideo(string $videoUrl): array {
        $container = $this->sendRequest('post', "{$this->accountId}/media", [
            'video_url' => $videoUrl,
            'media_type' => 'STORIES'
        ]);

        $this->waitForContainer($container['id']);

        return $this->publishContainer($container['id']);
    }

    /**
     * Placeholder for Interface Compliance.
     * Instagram does not support text-only posts or "URL" posts in the traditional sense.
     */
    public function shareText(string $caption): array {
        throw new SocialMediaException("Instagram does not support text-only posts.");
    }

    /**
     * Placeholder for Interface Compliance.
     */
    public function shareUrl(string $caption, string $url): array {
        throw new SocialMediaException("Instagram does not support direct URL sharing via API. Please use shareImage/shareStory with the link burned into the media or bio.");
    }

    /* --------------------------------------------------------------------------
     * INTERNAL HELPERS
     * -------------------------------------------------------------------------- */

    /**
     * Publish a Media Container.
     *
     * @param string $creationId
     * @return array
     * @throws SocialMediaException
     */
    private function publishContainer(string $creationId): array {
        return $this->sendRequest('post', "{$this->accountId}/media_publish", [
            'creation_id' => $creationId
        ]);
    }

    /**
     * Poll the status of a container until it is ready to publish.
     *
     * @param string $containerId
     * @param int $maxRetries
     * @return void
     * @throws SocialMediaException
     */
    private function waitForContainer(string $containerId, int $maxRetries = 10): void {
        $attempts = 0;

        do {
            $attempts++;
            sleep(3); // Wait 3 seconds between checks

            $status = $this->sendRequest('get', $containerId, [
                'fields' => 'status_code,status'
            ]);

            $code = $status['status_code'] ?? 'UNKNOWN';

            if ($code === 'FINISHED') {
                return;
            }

            if ($code === 'ERROR') {
                throw new SocialMediaException("Instagram Media Processing Failed: " . ($status['status'] ?? 'Unknown Error'));
            }

            if ($attempts >= $maxRetries) {
                throw new SocialMediaException("Instagram Media Processing Timed Out.");
            }
        } while ($code === 'IN_PROGRESS' || $code === 'EXPIRED');
    }

    /**
     * Send request to Graph API.
     */
    protected function sendRequest(string $method, string $endpoint, array $params = [], array $headers = []): array {
        $url = self::GRAPH_URL . '/' . self::API_VERSION . '/' . $endpoint;

        $params['access_token'] = $this->accessToken;

        $response = Http::timeout(60)->$method($url, $params);

        if (!$response->successful()) {
            $error = $response->json()['error']['message'] ?? $response->body();
            Log::error("Instagram API Error [{$endpoint}]", ['error' => $error]);
            throw new SocialMediaException("Instagram API Error: $error");
        }

        return $response->json();
    }
}

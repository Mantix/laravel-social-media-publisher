<?php

namespace Mantix\LaravelSocialMediaPublisher\Services;

use Mantix\LaravelSocialMediaPublisher\Contracts\ShareImagePostInterface;
use Mantix\LaravelSocialMediaPublisher\Contracts\ShareInterface;
use Mantix\LaravelSocialMediaPublisher\Contracts\ShareVideoPostInterface;
use Mantix\LaravelSocialMediaPublisher\Exceptions\SocialMediaException;
use Illuminate\Support\Facades\Log;

/**
 * Class PinterestService
 *
 * Service for managing and publishing content to Pinterest using the Pinterest API v5.
 *
 * Implements sharing of images and videos to Pinterest.
 */
class PinterestService extends SocialMediaService implements ShareInterface, ShareImagePostInterface, ShareVideoPostInterface
{
    /**
     * @var string Pinterest Access Token
     */
    private $access_token;

    /**
     * @var string Pinterest Board ID
     */
    private $board_id;

    /**
     * @var PinterestService|null Singleton instance
     */
    private static ?PinterestService $instance = null;

    /**
     * Pinterest API base URL
     */
    private const API_BASE_URL = 'https://api.pinterest.com/v5';

    /**
     * Private constructor to prevent direct instantiation.
     */
    private function __construct(
        string $accessToken,
        string $boardId
    ) {
        $this->access_token = $accessToken;
        $this->board_id = $boardId;
    }

    /**
     * Get instance - OAuth connection required.
     * 
     * @return PinterestService
     * @throws SocialMediaException
     * @deprecated Use forConnection() with a SocialMediaConnection instead
     */
    public static function getInstance(): PinterestService
    {
        throw new SocialMediaException('OAuth connection required. Please use forConnection() with a SocialMediaConnection or authenticate via OAuth first.');
    }

    /**
     * Create a new instance from a SocialMediaConnection.
     *
     * @param \mantix\LaravelSocialMediaPublisher\Models\SocialMediaConnection $connection
     * @return PinterestService
     * @throws SocialMediaException
     */
    public static function forConnection(\mantix\LaravelSocialMediaPublisher\Models\SocialMediaConnection $connection): PinterestService
    {
        if ($connection->platform !== 'pinterest') {
            throw new SocialMediaException('Connection is not for Pinterest platform.');
        }

        $accessToken = $connection->getDecryptedAccessToken();
        $metadata = $connection->metadata ?? [];
        $boardId = $metadata['board_id'] ?? null;

        if (!$accessToken || !$boardId) {
            throw new SocialMediaException('Pinterest connection is missing required credentials.');
        }

        return new self($accessToken, $boardId);
    }

    /**
     * Share a text post with a URL to Pinterest.
     * Note: Pinterest doesn't support direct text posts, so this creates a pin with the URL as the link.
     *
     * @param string $caption The text content of the post.
     * @param string $url The URL to share.
     * @return array Response from the Pinterest API.
     * @throws SocialMediaException
     */
    public function shareUrl(string $caption, string $url): array
    {
        $this->validateInput($caption, $url);
        
        try {
            // Pinterest doesn't support direct text posts
            // We'll create a pin with the URL as the link
            return $this->createPin($caption, $url, 'link');
        } catch (\Exception $e) {
            $this->log('error', 'Failed to share to Pinterest', [
                'platform' => 'pinterest',
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'url' => $url,
            ]);
            throw new SocialMediaException('Failed to share to Pinterest: ' . $e->getMessage());
        }
    }

    /**
     * Share an image post with a caption to Pinterest.
     *
     * @param string $caption The caption to accompany the image.
     * @param string $image_url The URL of the image.
     * @return array Response from the Pinterest API.
     * @throws SocialMediaException
     */
    public function shareImage(string $caption, string $image_url): array
    {
        $this->validateInput($caption, $image_url);
        
        try {
            return $this->createPin($caption, $image_url, 'image');
        } catch (\Exception $e) {
            $this->log('error', 'Failed to share image to Pinterest', [
                'platform' => 'pinterest',
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'image_url' => $image_url,
            ]);
            throw new SocialMediaException('Failed to share image to Pinterest: ' . $e->getMessage());
        }
    }

    /**
     * Share a video post with a caption to Pinterest.
     *
     * @param string $caption The caption to accompany the video.
     * @param string $video_url The URL of the video.
     * @return array Response from the Pinterest API.
     * @throws SocialMediaException
     */
    public function shareVideo(string $caption, string $video_url): array
    {
        $this->validateInput($caption, $video_url);
        
        try {
            return $this->createPin($caption, $video_url, 'video');
        } catch (\Exception $e) {
            $this->log('error', 'Failed to share video to Pinterest', [
                'platform' => 'pinterest',
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'video_url' => $video_url,
            ]);
            throw new SocialMediaException('Failed to share video to Pinterest: ' . $e->getMessage());
        }
    }

    /**
     * Create a pin on Pinterest.
     *
     * @param string $note The pin description.
     * @param string $mediaUrl The URL of the media.
     * @param string $mediaType The type of media (image, video, link).
     * @return array Response from the Pinterest API.
     * @throws SocialMediaException
     */
    public function createPin(string $note, string $mediaUrl, string $mediaType = 'image'): array
    {
        try {
            $url = $this->buildApiUrl('pins');
            $params = [
                'board_id' => $this->board_id,
                'media_source' => [
                    'source_type' => 'url',
                    'url' => $mediaUrl
                ],
                'note' => $note,
                'title' => $this->extractTitleFromNote($note)
            ];

            // Add link if it's a link type pin
            if ($mediaType === 'link') {
                $params['link'] = $mediaUrl;
            }

            $response = $this->sendRequest($url, 'post', $params);
            $this->log('info', 'Pinterest pin created successfully', [
                'platform' => 'pinterest',
                'pin_id' => $response['id'] ?? null,
                'board_id' => $this->board_id,
            ]);
            return $response;
        } catch (\Exception $e) {
            $this->log('error', 'Failed to create Pinterest pin', [
                'platform' => 'pinterest',
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'board_id' => $this->board_id,
            ]);
            throw new SocialMediaException('Failed to create Pinterest pin: ' . $e->getMessage());
        }
    }

    /**
     * Create a board on Pinterest.
     *
     * @param string $name The board name.
     * @param string $description The board description.
     * @param string $privacy The privacy setting (PUBLIC or SECRET).
     * @return array Response from the Pinterest API.
     * @throws SocialMediaException
     */
    public function createBoard(string $name, string $description = '', string $privacy = 'PUBLIC'): array
    {
        try {
            $url = $this->buildApiUrl('boards');
            $params = [
                'name' => $name,
                'description' => $description,
                'privacy' => $privacy
            ];

            $response = $this->sendRequest($url, 'post', $params);
            $this->log('info', 'Pinterest board created successfully', [
                'platform' => 'pinterest',
                'board_id' => $response['id'] ?? null,
                'board_name' => $name,
            ]);
            return $response;
        } catch (\Exception $e) {
            $this->log('error', 'Failed to create Pinterest board', [
                'platform' => 'pinterest',
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'board_name' => $name,
            ]);
            throw new SocialMediaException('Failed to create Pinterest board: ' . $e->getMessage());
        }
    }

    /**
     * Get user's boards.
     *
     * @param int $pageSize Number of boards to retrieve per page.
     * @return array Response from the Pinterest API.
     * @throws SocialMediaException
     */
    public function getBoards(int $pageSize = 25): array
    {
        try {
            $url = $this->buildApiUrl('boards');
            $params = [
                'page_size' => min($pageSize, 250)
            ];

            return $this->sendRequest($url, 'get', $params);
        } catch (\Exception $e) {
            $this->log('error', 'Failed to get Pinterest boards', [
                'platform' => 'pinterest',
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'limit' => $limit,
            ]);
            throw new SocialMediaException('Failed to get Pinterest boards: ' . $e->getMessage());
        }
    }

    /**
     * Get pins from a board.
     *
     * @param string $boardId The board ID.
     * @param int $pageSize Number of pins to retrieve per page.
     * @return array Response from the Pinterest API.
     * @throws SocialMediaException
     */
    public function getBoardPins(string $boardId, int $pageSize = 25): array
    {
        try {
            $url = $this->buildApiUrl("boards/{$boardId}/pins");
            $params = [
                'page_size' => min($pageSize, 250)
            ];

            return $this->sendRequest($url, 'get', $params);
        } catch (\Exception $e) {
            $this->log('error', 'Failed to get Pinterest board pins', [
                'platform' => 'pinterest',
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'board_id' => $boardId,
                'limit' => $limit,
            ]);
            throw new SocialMediaException('Failed to get Pinterest board pins: ' . $e->getMessage());
        }
    }

    /**
     * Get user profile information.
     *
     * @return array Response from the Pinterest API.
     * @throws SocialMediaException
     */
    public function getUserInfo(): array
    {
        try {
            $url = $this->buildApiUrl('user_account');
            $params = [
                'fields' => 'id,username,account_type,profile_image,website_url,bio,pin_count,board_count,follower_count,following_count'
            ];

            return $this->sendRequest($url, 'get', $params);
        } catch (\Exception $e) {
            $this->log('error', 'Failed to get Pinterest user info', [
                'platform' => 'pinterest',
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);
            throw new SocialMediaException('Failed to get Pinterest user info: ' . $e->getMessage());
        }
    }

    /**
     * Get pin analytics.
     *
     * @param string $pinId The Pinterest pin ID.
     * @return array Response from the Pinterest API.
     * @throws SocialMediaException
     */
    public function getPinAnalytics(string $pinId): array
    {
        try {
            $url = $this->buildApiUrl("pins/{$pinId}/analytics");
            $params = [
                'start_date' => date('Y-m-d', strtotime('-30 days')),
                'end_date' => date('Y-m-d'),
                'metric_types' => 'IMPRESSION,SAVE,CLICKTHROUGH'
            ];

            return $this->sendRequest($url, 'get', $params);
        } catch (\Exception $e) {
            $this->log('error', 'Failed to get Pinterest pin analytics', [
                'platform' => 'pinterest',
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'pin_id' => $pinId,
            ]);
            throw new SocialMediaException('Failed to get Pinterest pin analytics: ' . $e->getMessage());
        }
    }

    /**
     * Search for pins.
     *
     * @param string $query The search query.
     * @param int $pageSize Number of pins to retrieve per page.
     * @return array Response from the Pinterest API.
     * @throws SocialMediaException
     */
    public function searchPins(string $query, int $pageSize = 25): array
    {
        try {
            $url = $this->buildApiUrl('search/pins');
            $params = [
                'query' => $query,
                'page_size' => min($pageSize, 250)
            ];

            return $this->sendRequest($url, 'get', $params);
        } catch (\Exception $e) {
            $this->log('error', 'Failed to search Pinterest pins', [
                'platform' => 'pinterest',
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'query' => $query,
            ]);
            throw new SocialMediaException('Failed to search Pinterest pins: ' . $e->getMessage());
        }
    }

    /**
     * Extract title from note.
     *
     * @param string $note The pin note/description.
     * @return string The extracted title.
     */
    private function extractTitleFromNote(string $note): string
    {
        $lines = explode("\n", $note);
        $title = trim($lines[0]);
        
        // Limit title to 100 characters
        if (strlen($title) > 100) {
            $title = substr($title, 0, 97) . '...';
        }
        
        return $title ?: 'Shared Pin';
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
     * Build Pinterest API URL.
     *
     * @param string $endpoint The API endpoint.
     * @return string Complete API URL.
     */
    private function buildApiUrl(string $endpoint): string
    {
        return self::API_BASE_URL . '/' . $endpoint;
    }

    /**
     * Send authenticated request to Pinterest API.
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
            'Authorization' => 'Bearer ' . $this->access_token,
            'Content-Type' => 'application/json'
        ];
        
        $headers = array_merge($defaultHeaders, $headers);

        $response = \Illuminate\Support\Facades\Http::withHeaders($headers)
            ->{$method}($url, $params);

        if (!$response->successful()) {
            $errorData = $response->json();
            $errorMessage = $errorData['message'] ?? 'Unknown error occurred';
            throw new SocialMediaException("Pinterest API error: {$errorMessage}");
        }

        return $response->json();
    }
}

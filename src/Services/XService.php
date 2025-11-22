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
 * Class TwitterService
 *
 * Service for publishing to X (Twitter) using API v2 for Tweets and API v1.1 for Media Uploads.
 *
 * @package Mantix\LaravelSocialMediaPublisher\Services
 */
class TwitterService extends SocialMediaService implements ShareInterface, ShareImagePostInterface, ShareVideoPostInterface {
    /** @var string OAuth 2.0 Access Token */
    private string $accessToken;

    /** @var string API v2 Base URL */
    private const API_URL = 'https://api.twitter.com/2';

    /** @var string Upload API v1.1 Base URL */
    private const UPLOAD_URL = 'https://upload.twitter.com/1.1/media/upload.json';

    /**
     * TwitterService Constructor.
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
        if ($connection->platform !== 'twitter' && $connection->platform !== 'x') {
            throw new SocialMediaException('Connection is not for the Twitter/X platform.');
        }

        $token = $connection->getDecryptedAccessToken();

        if (!$token) {
            throw new SocialMediaException('X connection is missing the Access Token.');
        }

        return new self($token);
    }

    /* --------------------------------------------------------------------------
     * AUTHENTICATION (OAuth 2.0 PKCE)
     * -------------------------------------------------------------------------- */

    /**
     * Get Authorization URL.
     *
     * @param string $redirectUri
     * @param array $scopes
     * @param string|null $state
     * @return array ['url' => string, 'code_verifier' => string]
     */
    public static function getAuthorizationUrl(
        string $redirectUri,
        array $scopes = ['tweet.read', 'tweet.write', 'users.read', 'offline.access', 'media.write'],
        ?string $state = null
    ): array {
        $clientId = config('social_media_publisher.x_client_id');

        if (!$clientId) {
            throw new SocialMediaException('X Client ID is not configured.');
        }

        $state = $state ?? bin2hex(random_bytes(16));
        $codeVerifier = bin2hex(random_bytes(32));
        $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');

        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'scope' => implode(' ', $scopes),
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256'
        ]);

        return [
            'url' => "https://twitter.com/i/oauth2/authorize?{$query}",
            'code_verifier' => $codeVerifier
        ];
    }

    /**
     * Handle Callback.
     *
     * @param string $code
     * @param string $redirectUri
     * @param string $codeVerifier Required for PKCE
     * @return array
     */
    public static function handleCallback(string $code, string $redirectUri, string $codeVerifier): array {
        $clientId = config('social_media_publisher.x_client_id');
        $clientSecret = config('social_media_publisher.x_client_secret'); // Optional for Public Clients, required for Confidential

        $response = Http::asForm()->withBasicAuth($clientId, $clientSecret ?? '')->post('https://api.twitter.com/2/oauth2/token', [
            'code' => $code,
            'grant_type' => 'authorization_code',
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'code_verifier' => $codeVerifier,
        ]);

        if (!$response->successful()) {
            throw new SocialMediaException('Failed to exchange X token: ' . $response->body());
        }

        $data = $response->json();

        // Fetch User Profile
        $service = new self($data['access_token']);
        $profile = $service->getUserInfo();

        return array_merge($data, ['profile' => $profile['data'] ?? []]);
    }

    /**
     * Refresh Token.
     *
     * @param string $refreshToken
     * @return array
     */
    public static function refreshAccessToken(string $refreshToken): array {
        $clientId = config('social_media_publisher.x_client_id');
        $clientSecret = config('social_media_publisher.x_client_secret');

        $response = Http::asForm()->withBasicAuth($clientId, $clientSecret ?? '')->post('https://api.twitter.com/2/oauth2/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => $clientId,
        ]);

        if (!$response->successful()) {
            throw new SocialMediaException('Failed to refresh X token: ' . $response->body());
        }

        return $response->json();
    }

    /* --------------------------------------------------------------------------
     * PUBLISHING METHODS
     * -------------------------------------------------------------------------- */

    /**
     * Share Text Tweet.
     *
     * @param string $caption
     * @return array
     */
    public function shareText(string $caption): array {
        $this->validateText($caption, 280);

        return $this->postTweet(['text' => $caption]);
    }

    /**
     * Share URL (Appended to text).
     *
     * @param string $caption
     * @param string $url
     * @return array
     */
    public function shareUrl(string $caption, string $url): array {
        // URLs count as 23 characters in Twitter
        $content = $caption . ' ' . $url;
        // Simple validation (approximation)
        if (mb_strlen($caption) > 257) {
            throw new SocialMediaException('Tweet text is too long (URLs take 23 chars).');
        }

        return $this->postTweet(['text' => $content]);
    }

    /**
     * Share Image.
     *
     * @param string $caption
     * @param string $imageUrl
     * @return array
     */
    public function shareImage(string $caption, string $imageUrl): array {
        $this->validateText($caption, 280);

        // 1. Upload Media (v1.1)
        $mediaId = $this->uploadMediaSimple($imageUrl);

        // 2. Post Tweet (v2)
        return $this->postTweet([
            'text' => $caption,
            'media' => ['media_ids' => [(string)$mediaId]]
        ]);
    }

    /**
     * Share Video.
     * 
     *
     * @param string $caption
     * @param string $videoUrl
     * @return array
     */
    public function shareVideo(string $caption, string $videoUrl): array {
        $this->validateText($caption, 280);

        // 1. Download video to local temp path for processing
        $localPath = $this->getTemporaryFilePath($videoUrl);

        try {
            // 2. Chunked Upload (INIT -> APPEND -> FINALIZE)
            $mediaId = $this->uploadMediaChunked($localPath, 'video/mp4');

            // 3. Post Tweet
            return $this->postTweet([
                'text' => $caption,
                'media' => ['media_ids' => [(string)$mediaId]]
            ]);
        } finally {
            if (file_exists($localPath)) {
                @unlink($localPath);
            }
        }
    }

    /* --------------------------------------------------------------------------
     * READ METHODS
     * -------------------------------------------------------------------------- */

    public function getUserInfo(): array {
        return $this->sendRequest('get', 'users/me', [
            'user.fields' => 'profile_image_url,public_metrics,username,name,id'
        ]);
    }

    /* --------------------------------------------------------------------------
     * MEDIA UPLOAD LOGIC (The Complex Part)
     * -------------------------------------------------------------------------- */

    /**
     * Simple Upload for Images (< 5MB).
     */
    private function uploadMediaSimple(string $fileUrl): string {
        $content = Http::get($fileUrl)->body();

        // Send as Multipart form data
        $response = Http::withToken($this->accessToken)
            ->attach('media', $content, 'image.jpg')
            ->post(self::UPLOAD_URL, [
                'media_category' => 'tweet_image'
            ]);

        if (!$response->successful()) {
            throw new SocialMediaException("X Media Upload Failed: " . $response->body());
        }

        return $response->json()['media_id_string'];
    }

    /**
     * Chunked Upload for Videos.
     */
    private function uploadMediaChunked(string $filePath, string $mimeType): string {
        $fileSize = filesize($filePath);

        // PHASE 1: INIT
        $initResponse = Http::withToken($this->accessToken)->post(self::UPLOAD_URL, [
            'command' => 'INIT',
            'media_type' => $mimeType,
            'total_bytes' => $fileSize,
            'media_category' => 'tweet_video'
        ]);

        if (!$initResponse->successful()) throw new SocialMediaException("X Upload INIT failed.");

        $mediaId = $initResponse['media_id_string'];

        // PHASE 2: APPEND
        $handle = fopen($filePath, 'rb');
        $segmentIndex = 0;
        $chunkSize = 2 * 1024 * 1024; // 2MB chunks

        while (!feof($handle)) {
            $chunk = fread($handle, $chunkSize);

            $appendResponse = Http::withToken($this->accessToken)
                ->attach('media', $chunk, 'blob')
                ->post(self::UPLOAD_URL, [
                    'command' => 'APPEND',
                    'media_id' => $mediaId,
                    'segment_index' => $segmentIndex
                ]);

            if (!$appendResponse->successful()) {
                fclose($handle);
                throw new SocialMediaException("X Upload APPEND failed at segment {$segmentIndex}.");
            }

            $segmentIndex++;
        }
        fclose($handle);

        // PHASE 3: FINALIZE
        $finalizeResponse = Http::withToken($this->accessToken)->post(self::UPLOAD_URL, [
            'command' => 'FINALIZE',
            'media_id' => $mediaId
        ]);

        if (!$finalizeResponse->successful()) throw new SocialMediaException("X Upload FINALIZE failed.");

        $finalizeData = $finalizeResponse->json();

        // PHASE 4: STATUS CHECK (Only if processing info is present)
        if (isset($finalizeData['processing_info'])) {
            $this->waitForProcessing($mediaId);
        }

        return $mediaId;
    }

    /**
     * Poll status until succeeded.
     */
    private function waitForProcessing(string $mediaId): void {
        $attempts = 0;
        do {
            sleep(2); // Wait before check
            $response = Http::withToken($this->accessToken)->get(self::UPLOAD_URL, [
                'command' => 'STATUS',
                'media_id' => $mediaId
            ]);

            $data = $response->json();
            $state = $data['processing_info']['state'] ?? 'succeeded';

            if ($state === 'failed') {
                throw new SocialMediaException("X Video Processing Failed: " . ($data['processing_info']['error']['message'] ?? 'Unknown'));
            }

            $attempts++;
        } while ($state === 'in_progress' || $state === 'pending' && $attempts < 20);
    }

    /* --------------------------------------------------------------------------
     * INTERNAL HELPERS
     * -------------------------------------------------------------------------- */

    private function postTweet(array $payload): array {
        // Uses API v2
        $response = $this->sendRequest('post', 'tweets', $payload);

        if (!isset($response['data']['id'])) {
            throw new SocialMediaException("Failed to post Tweet.");
        }

        $this->log('info', 'Tweet posted', ['id' => $response['data']['id']]);
        return $response;
    }

    /**
     * Override sendRequest to handle specific v2 URL vs v1.1 URL logic.
     * This method specifically handles v2 requests.
     */
    protected function sendRequest(string $method, string $endpoint, array $params = [], array $headers = []): array {
        $url = self::API_URL . '/' . $endpoint;
        $headers['Authorization'] = 'Bearer ' . $this->accessToken;
        $headers['Content-Type'] = 'application/json';

        return parent::sendRequest($method, $url, $params, $headers);
    }
}

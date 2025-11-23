<?php

namespace Mantix\LaravelSocialMediaPublisher\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mantix\LaravelSocialMediaPublisher\Contracts\ShareImagePostInterface;
use Mantix\LaravelSocialMediaPublisher\Contracts\ShareInterface;
use Mantix\LaravelSocialMediaPublisher\Contracts\ShareVideoPostInterface;
use Mantix\LaravelSocialMediaPublisher\Exceptions\SocialMediaException;
use Mantix\LaravelSocialMediaPublisher\Models\SocialMediaConnection;

/**
 * Class LinkedInService
 *
 * Service for managing and publishing content to LinkedIn (Profiles and Organization Pages).
 *
 * @package Mantix\LaravelSocialMediaPublisher\Services
 */
class LinkedInService extends SocialMediaService implements ShareInterface, ShareImagePostInterface, ShareVideoPostInterface {
    /** @var string LinkedIn API base URL */
    private const API_BASE_URL = 'https://api.linkedin.com/v2';

    /** @var string The active Access Token */
    private string $accessToken;

    /** @var string The URN of the entity posting (urn:li:person:ID or urn:li:organization:ID) */
    private string $authorUrn;

    /**
     * LinkedInService Constructor.
     * 
     * @return self
     */
    public function __construct() {
        // Empty constructor
    }

    /**
     * Set the credentials for the LinkedInService.
     *
     * @param string $accessToken
     * @param string $authorUrn The URN of the entity authoring the post.
     * @return self
     */
    public function setCredentials(string $accessToken, string $authorUrn): self {
        $this->accessToken = $accessToken;
        $this->authorUrn = $authorUrn;
        return $this;
    }

    /**
     * Create a service instance from a saved SocialMediaConnection.
     *
     * This method automatically determines if the author is a Person or an Organization
     * based on the connection_type stored in the database.
     *
     * @param SocialMediaConnection $connection
     * @return self
     * @throws SocialMediaException
     */
    public static function forConnection(SocialMediaConnection $connection): self {
        if ($connection->platform !== 'linkedin') {
            throw new SocialMediaException('Connection is not for the LinkedIn platform.');
        }

        $token = $connection->getDecryptedAccessToken();

        if (!$token) {
            throw new SocialMediaException('LinkedIn connection is missing a valid access token.');
        }

        // Determine the Author URN based on connection type
        // If it's a company page connection, the platform_user_id should hold the Organization ID/URN
        // If it's a profile connection, it holds the Person ID/URN
        $id = $connection->platform_user_id;

        // Ensure ID is formatted as a URN
        if ($connection->connection_type === 'company' || $connection->connection_type === 'page') {
            $urn = str_starts_with($id, 'urn:li:organization:') ? $id : "urn:li:organization:{$id}";
        } else {
            $urn = str_starts_with($id, 'urn:li:person:') ? $id : "urn:li:person:{$id}";
        }

        return new self($token, $urn);
    }

    /**
     * Generate the OAuth Authorization URL.
     *
     * @param string $redirectUri
     * @param array $scopes
     * @param string|null $state
     * @return string
     * @throws SocialMediaException
     */
    public static function getAuthorizationUrl(
        string $redirectUri,
        array $scopes = ['r_liteprofile', 'r_emailaddress', 'w_member_social', 'rw_organization_admin', 'w_organization_social'],
        ?string $state = null
    ): string {
        $clientId = config('social_media_publisher.linkedin_client_id');

        if (!$clientId) {
            throw new SocialMediaException('LinkedIn Client ID is not configured.');
        }

        $state = $state ?? bin2hex(random_bytes(16));
        $scopeString = implode(' ', $scopes);

        return sprintf(
            'https://www.linkedin.com/oauth/v2/authorization?response_type=code&client_id=%s&redirect_uri=%s&state=%s&scope=%s',
            urlencode($clientId),
            urlencode($redirectUri),
            urlencode($state),
            urlencode($scopeString)
        );
    }

    /**
     * Exchange Authorization Code for Access Token.
     *
     * @param string $code
     * @param string $redirectUri
     * @return array Contains access_token, expires_in, and person_urn (id)
     * @throws SocialMediaException
     */
    public static function handleCallback(string $code, string $redirectUri): array {
        $clientId = config('social_media_publisher.linkedin_client_id');
        $clientSecret = config('social_media_publisher.linkedin_client_secret');

        $response = Http::asForm()->post('https://www.linkedin.com/oauth/v2/accessToken', [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ]);

        if (!$response->successful()) {
            throw new SocialMediaException('Failed to retrieve access token: ' . $response->body());
        }

        $data = $response->json();

        // Immediately fetch the user's profile ID (Person URN) to return with the token
        // We create a temporary instance just to fetch the ID
        $tempService = new self($data['access_token'], 'urn:li:unknown');
        $profile = $tempService->getUserProfile();

        return array_merge($data, ['profile' => $profile]);
    }

    /* --------------------------------------------------------------------------
     * READ METHODS (Profiles & Organizations)
     * -------------------------------------------------------------------------- */

    /**
     * Get the authenticated user's profile.
     *
     * @return array
     * @throws SocialMediaException
     */
    public function getUserProfile(): array {
        return $this->get('me', ['projection' => '(id,localizedFirstName,localizedLastName,profilePicture(displayImage~:playableStreams))']);
    }

    /**
     * Get a list of companies (organizations) the user is an Administrator of.
     * * Used to let the user select which company page they want to connect.
     *
     * @return array List of organizations formatted as ['id' => '123', 'name' => 'Acme Corp', 'urn' => '...']
     * @throws SocialMediaException
     */
    public function getManagedOrganizations(): array {
        // 1. Get Access Control List for the user
        $response = $this->get('organizationAcls', [
            'q' => 'roleAssignee',
            'role' => 'ADMINISTRATOR',
            'state' => 'APPROVED',
            'projection' => '(elements*(organization~(id,localizedName,logoV2(original~:playableStreams))))'
        ]);

        $companies = [];

        if (isset($response['elements'])) {
            foreach ($response['elements'] as $element) {
                if (isset($element['organization'])) {
                    $org = $element['organization'];

                    // Parse the ID (handle both URN string and raw ID)
                    $urn = $element['organizationUrn'] ?? ($org['id'] ?? null); // Raw response structure varies by projection

                    // Sometimes ID is embedded in the URN in the 'id' field depending on projection
                    // We sanitize to ensure we get the numeric ID
                    $rawId = $this->extractIdFromUrn($urn ?? $org['id']);

                    $companies[] = [
                        'id' => $rawId,
                        'name' => $org['localizedName'] ?? 'Unknown Company',
                        'urn' => "urn:li:organization:{$rawId}",
                        'logo' => $org['logoV2']['original~']['elements'][0]['identifiers'][0]['identifier'] ?? null
                    ];
                }
            }
        }

        return $companies;
    }

    /* --------------------------------------------------------------------------
     * PUBLISHING METHODS
     * -------------------------------------------------------------------------- */

    /**
     * Share a text post.
     *
     * @param string $caption
     * @return array
     * @throws SocialMediaException
     */
    public function shareText(string $caption): array {
        return $this->createShare($caption);
    }

    /**
     * Share a link with a caption.
     *
     * @param string $caption
     * @param string $url
     * @return array
     * @throws SocialMediaException
     */
    public function shareUrl(string $caption, string $url): array {
        return $this->createShare($caption, 'ARTICLE', $url);
    }

    /**
     * Share an image with a caption.
     *
     * @param string $caption
     * @param string $imageUrl
     * @return array
     * @throws SocialMediaException
     */
    public function shareImage(string $caption, string $imageUrl): array {
        $assetUrn = $this->uploadMedia($imageUrl, 'image');
        return $this->createShare($caption, 'IMAGE', null, $assetUrn);
    }

    /**
     * Share a video with a caption.
     *
     * @param string $caption
     * @param string $videoUrl
     * @return array
     * @throws SocialMediaException
     */
    public function shareVideo(string $caption, string $videoUrl): array {
        $assetUrn = $this->uploadMedia($videoUrl, 'video');
        return $this->createShare($caption, 'VIDEO', null, $assetUrn);
    }

    /* --------------------------------------------------------------------------
     * INTERNAL HELPERS
     * -------------------------------------------------------------------------- */

    /**
     * Main method to construct the UGC Post body.
     * * @param string $text
     * @param string $mediaCategory NONE, ARTICLE, IMAGE, VIDEO
     * @param string|null $url Required for ARTICLE
     * @param string|null $mediaUrn Required for IMAGE or VIDEO
     * @return array
     */
    private function createShare(string $text, string $mediaCategory = 'NONE', ?string $url = null, ?string $mediaUrn = null): array {
        $body = [
            'author' => $this->authorUrn,
            'lifecycleState' => 'PUBLISHED',
            'specificContent' => [
                'com.linkedin.ugc.ShareContent' => [
                    'shareCommentary' => [
                        'text' => $text
                    ],
                    'shareMediaCategory' => $mediaCategory
                ]
            ],
            'visibility' => [
                'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC'
            ]
        ];

        // Add media specifics
        if ($mediaCategory !== 'NONE') {
            $mediaData = [
                'status' => 'READY',
                'description' => ['text' => substr($text, 0, 200)], // Description is optional
            ];

            if ($mediaCategory === 'ARTICLE' && $url) {
                $mediaData['originalUrl'] = $url;
            } elseif (($mediaCategory === 'IMAGE' || $mediaCategory === 'VIDEO') && $mediaUrn) {
                $mediaData['media'] = $mediaUrn;
            }

            $body['specificContent']['com.linkedin.ugc.ShareContent']['media'] = [$mediaData];
        }

        return $this->post('ugcPosts', $body);
    }

    /**
     * Upload Image or Video.
     *
     * @param string $fileUrl
     * @param string $type 'image' or 'video'
     * @return string The Asset URN
     * @throws SocialMediaException
     */
    private function uploadMedia(string $fileUrl, string $type): string {
        $recipe = $type === 'image'
            ? 'urn:li:digitalmediaRecipe:feedshare-image'
            : 'urn:li:digitalmediaRecipe:feedshare-video';

        // 1. Register Upload
        $register = $this->post('assets?action=registerUpload', [
            'registerUploadRequest' => [
                'recipes' => [$recipe],
                'owner' => $this->authorUrn,
                'serviceRelationships' => [
                    ['relationshipType' => 'OWNER', 'identifier' => 'urn:li:userGeneratedContent']
                ]
            ]
        ]);

        $uploadUrl = $register['value']['uploadMechanism']['com.linkedin.digitalmedia.uploading.MediaUploadHttpRequest']['uploadUrl']
            ?? null;
        $assetUrn = $register['value']['asset'] ?? null;

        if (!$uploadUrl || !$assetUrn) {
            throw new SocialMediaException("Failed to register LinkedIn {$type} upload.");
        }

        // 2. Upload binary data
        $fileContent = @file_get_contents($fileUrl);
        if (!$fileContent) {
            throw new SocialMediaException("Could not read file from URL: {$fileUrl}");
        }

        $upload = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->accessToken
        ])->put($uploadUrl, $fileContent);

        if (!$upload->successful()) {
            throw new SocialMediaException("Failed to upload binary data to LinkedIn.");
        }

        return $assetUrn;
    }

    /**
     * Send a GET request.
     */
    private function get(string $endpoint, array $params = []): array {
        return $this->sendRequest('get', $endpoint, $params);
    }

    /**
     * Send a POST request.
     */
    private function post(string $endpoint, array $data = []): array {
        return $this->sendRequest('post', $endpoint, $data);
    }

    /**
     * Centralized Request Handler.
     */
    protected function sendRequest(string $method, string $endpoint, array $data = [], array $headers = []): array {
        $url = self::API_BASE_URL . '/' . ltrim($endpoint, '/');

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->accessToken,
            'Content-Type' => 'application/json',
            'X-Restli-Protocol-Version' => '2.0.0',
        ])->$method($url, $data);

        if (!$response->successful()) {
            Log::error('LinkedIn API Error', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => $response->json()
            ]);
            throw new SocialMediaException("LinkedIn Error: " . ($response->json()['message'] ?? $response->body()));
        }

        return $response->json();
    }

    /**
     * Helper to clean URNs to IDs.
     */
    private function extractIdFromUrn(string $urn): string {
        $parts = explode(':', $urn);
        return end($parts);
    }
}

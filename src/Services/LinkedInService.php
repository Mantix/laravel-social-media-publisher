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
 * Class LinkedInService
 *
 * Service for managing and publishing content to LinkedIn using the LinkedIn API.
 *
 * Implements sharing of general posts, images, and videos to LinkedIn.
 */
class LinkedInService extends SocialMediaService implements ShareInterface, ShareImagePostInterface, ShareVideoPostInterface
{
    /**
     * @var string LinkedIn Access Token
     */
    private $access_token;

    /**
     * @var string LinkedIn Person URN
     */
    private $person_urn;

    /**
     * @var string LinkedIn Organization URN
     */
    private $organization_urn;

    /**
     * @var LinkedInService|null Singleton instance
     */
    private static ?LinkedInService $instance = null;

    /**
     * LinkedIn API base URL
     */
    private const API_BASE_URL = 'https://api.linkedin.com/v2';

    /**
     * Private constructor to prevent direct instantiation.
     */
    private function __construct(
        string $accessToken,
        string $personUrn,
        string $organizationUrn = null
    ) {
        $this->access_token = $accessToken;
        $this->person_urn = $personUrn;
        $this->organization_urn = $organizationUrn;
    }

    /**
     * Get instance - OAuth connection required.
     * 
     * @return LinkedInService
     * @throws SocialMediaException
     * @deprecated Use forConnection() with a SocialMediaConnection instead
     */
    public static function getInstance(): LinkedInService
    {
        throw new SocialMediaException('OAuth connection required. Please use forConnection() with a SocialMediaConnection or authenticate via OAuth first.');
    }

    /**
     * Create a new instance with specific credentials.
     *
     * @param string $accessToken
     * @param string $personUrn
     * @param string|null $organizationUrn
     * @return LinkedInService
     */
    public static function withCredentials(string $accessToken, string $personUrn, ?string $organizationUrn = null): LinkedInService
    {
        return new self($accessToken, $personUrn, $organizationUrn);
    }

    /**
     * Create a new instance from a SocialMediaConnection.
     *
     * @param SocialMediaConnection $connection
     * @return LinkedInService
     * @throws SocialMediaException
     */
    public static function forConnection(SocialMediaConnection $connection): LinkedInService
    {
        if ($connection->platform !== 'linkedin') {
            throw new SocialMediaException('Connection is not for LinkedIn platform.');
        }

        $accessToken = $connection->getDecryptedAccessToken();
        $metadata = $connection->metadata ?? [];
        $personUrn = $metadata['person_urn'] ?? $connection->platform_user_id;
        $organizationUrn = $metadata['organization_urn'] ?? null;

        if (!$accessToken || !$personUrn) {
            throw new SocialMediaException('LinkedIn connection is missing required credentials.');
        }

        return new self($accessToken, $personUrn, $organizationUrn);
    }

    /**
     * Get the authorization URL for LinkedIn OAuth.
     *
     * @param string $redirectUri
     * @param array $scopes
     * @param string|null $state
     * @param bool $usePkce Enable PKCE flow
     * @param string|null $codeVerifier Optional code verifier (will generate if null and PKCE is enabled)
     * @return string|array Returns string URL if PKCE is disabled, or array with 'url' and 'code_verifier' if PKCE is enabled
     * @throws SocialMediaException
     */
    public static function getAuthorizationUrl(
        string $redirectUri, 
        array $scopes = ['r_liteprofile', 'r_emailaddress', 'w_member_social'], 
        ?string $state = null,
        bool $usePkce = false,
        ?string $codeVerifier = null
    ) {
        $clientId = config('social_media_publisher.linkedin_client_id');

        if (!$clientId) {
            throw new SocialMediaException('LinkedIn Client ID must be configured for OAuth.');
        }

        $state = $state ?? bin2hex(random_bytes(16));
        $scopeString = implode(' ', $scopes);

        $authUrl = sprintf(
            'https://www.linkedin.com/oauth/v2/authorization?response_type=code&client_id=%s&redirect_uri=%s&state=%s&scope=%s',
            urlencode($clientId),
            urlencode($redirectUri),
            urlencode($state),
            urlencode($scopeString)
        );

        // Add PKCE support if enabled
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
                Log::info('LinkedIn OAuth authorization URL generated with PKCE', [
                    'platform' => 'linkedin',
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
            Log::info('LinkedIn OAuth authorization URL generated', [
                'platform' => 'linkedin',
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
     * @param string|null $codeVerifier PKCE code verifier (required if PKCE was used in authorization)
     * @return array
     * @throws SocialMediaException
     */
    public static function handleCallback(string $code, string $redirectUri, ?string $codeVerifier = null): array
    {
        $enableLogging = config('social_media_publisher.enable_logging', true);
        
        if ($enableLogging) {
            Log::info('LinkedIn OAuth callback initiated', [
                'platform' => 'linkedin',
                'redirect_uri' => $redirectUri,
                'has_code' => !empty($code),
                'has_code_verifier' => !empty($codeVerifier),
            ]);
        }

        $clientId = config('social_media_publisher.linkedin_client_id');
        $clientSecret = config('social_media_publisher.linkedin_client_secret');

        if (!$clientId || !$clientSecret) {
            if ($enableLogging) {
                Log::error('LinkedIn OAuth callback failed: missing credentials', [
                    'platform' => 'linkedin',
                    'has_client_id' => !empty($clientId),
                    'has_client_secret' => !empty($clientSecret),
                ]);
            }
            throw new SocialMediaException('LinkedIn Client ID and Client Secret must be configured for OAuth.');
        }

        // Exchange code for access token
        $tokenUrl = 'https://www.linkedin.com/oauth/v2/accessToken';
        
        if ($enableLogging) {
            Log::debug('Exchanging LinkedIn OAuth code for access token', [
                'platform' => 'linkedin',
                'token_url' => $tokenUrl,
                'redirect_uri' => $redirectUri,
                'using_pkce' => !empty($codeVerifier),
            ]);
        }
        
        $timeout = config('social_media_publisher.timeout', 30);
        $params = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ];

        // Add PKCE code verifier if provided
        if ($codeVerifier !== null) {
            $params['code_verifier'] = $codeVerifier;
        }

        $response = Http::timeout($timeout)->asForm()->post($tokenUrl, $params);

        if (!$response->successful()) {
            if ($enableLogging) {
                Log::error('LinkedIn OAuth token exchange failed', [
                    'platform' => 'linkedin',
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
                Log::error('LinkedIn OAuth access token not found in response', [
                    'platform' => 'linkedin',
                    'response_keys' => array_keys($tokenData),
                ]);
            }
            throw new SocialMediaException('Access token not found in response.');
        }

        if ($enableLogging) {
            Log::info('LinkedIn OAuth access token obtained', [
                'platform' => 'linkedin',
                'token_type' => $tokenData['token_type'] ?? 'unknown',
                'expires_in' => $tokenData['expires_in'] ?? null,
            ]);
        }

        // Get user profile
        $profileUrl = 'https://api.linkedin.com/v2/me';
        
        if ($enableLogging) {
            Log::debug('Fetching LinkedIn user profile', [
                'platform' => 'linkedin',
            ]);
        }
        
        $timeout = config('social_media_publisher.timeout', 30);
        $profileResponse = Http::timeout($timeout)->withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
        ])->get($profileUrl);

        $profile = [];
        if ($profileResponse->successful()) {
            $profile = $profileResponse->json();
            
            if ($enableLogging) {
                Log::info('LinkedIn user profile retrieved', [
                    'platform' => 'linkedin',
                    'profile_id' => $profile['id'] ?? null,
                ]);
            }
        } else {
            if ($enableLogging) {
                Log::warning('Failed to retrieve LinkedIn user profile', [
                    'platform' => 'linkedin',
                    'status' => $profileResponse->status(),
                ]);
            }
        }

        if ($enableLogging) {
            Log::info('LinkedIn OAuth callback completed successfully', [
                'platform' => 'linkedin',
                'has_access_token' => !empty($accessToken),
                'has_profile' => !empty($profile),
            ]);
        }

        return [
            'access_token' => $accessToken,
            'expires_in' => $tokenData['expires_in'] ?? null,
            'token_type' => $tokenData['token_type'] ?? 'bearer',
            'profile' => $profile,
        ];
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
            Log::info('LinkedIn refresh token initiated', [
                'platform' => 'linkedin',
                'has_refresh_token' => !empty($refreshToken),
            ]);
        }

        $clientId = config('social_media_publisher.linkedin_client_id');
        $clientSecret = config('social_media_publisher.linkedin_client_secret');

        if (!$clientId || !$clientSecret) {
            if ($enableLogging) {
                Log::error('LinkedIn refresh token failed: missing credentials', [
                    'platform' => 'linkedin',
                    'has_client_id' => !empty($clientId),
                    'has_client_secret' => !empty($clientSecret),
                ]);
            }
            throw new SocialMediaException('LinkedIn Client ID and Client Secret must be configured.');
        }

        $tokenUrl = 'https://www.linkedin.com/oauth/v2/accessToken';
        
        if ($enableLogging) {
            Log::debug('Refreshing LinkedIn access token', [
                'platform' => 'linkedin',
                'token_url' => $tokenUrl,
            ]);
        }
        
        $timeout = config('social_media_publisher.timeout', 30);
        $response = Http::timeout($timeout)->asForm()->post($tokenUrl, [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ]);

        if (!$response->successful()) {
            if ($enableLogging) {
                Log::error('LinkedIn refresh token failed', [
                    'platform' => 'linkedin',
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);
            }
            throw new SocialMediaException('Failed to refresh access token: ' . $response->body());
        }

        $tokenData = $response->json();
        
        if ($enableLogging) {
            Log::info('LinkedIn access token refreshed successfully', [
                'platform' => 'linkedin',
                'token_type' => $tokenData['token_type'] ?? 'unknown',
                'expires_in' => $tokenData['expires_in'] ?? null,
            ]);
        }

        return $tokenData;
    }

    /**
     * Disconnect from LinkedIn by revoking the access token.
     *
     * @param string $accessToken
     * @return bool
     */
    public static function disconnect(string $accessToken): bool
    {
        try {
            $revokeUrl = 'https://www.linkedin.com/oauth/v2/revoke';
            $timeout = config('social_media_publisher.timeout', 30);
            $response = Http::timeout($timeout)->asForm()->post($revokeUrl, [
                'token' => $accessToken,
                'client_id' => config('social_media_publisher.linkedin_client_id'),
                'client_secret' => config('social_media_publisher.linkedin_client_secret'),
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            if (config('social_media_publisher.enable_logging', true)) {
                Log::error('Failed to disconnect LinkedIn', [
                    'platform' => 'linkedin',
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                ]);
            }
            return false;
        }
    }

    /**
     * Share a text post with a URL to LinkedIn.
     *
     * @param string $caption The text content of the post.
     * @param string $url The URL to share.
     * @return array Response from the LinkedIn API.
     * @throws SocialMediaException
     */
    public function shareUrl(string $caption, string $url): array
    {
        $this->validateInput($caption, $url);
        
        try {
            // Use organization_urn if available, otherwise fall back to person_urn
            $author = $this->organization_urn ?? $this->person_urn;
            
            $url_endpoint = $this->buildApiUrl('ugcPosts');
            $params = [
                'author' => $author,
                'lifecycleState' => 'PUBLISHED',
                'specificContent' => [
                    'com.linkedin.ugc.ShareContent' => [
                        'shareCommentary' => [
                            'text' => $caption
                        ],
                        'shareMediaCategory' => 'ARTICLE',
                        'media' => [
                            [
                                'status' => 'READY',
                                'description' => [
                                    'text' => $caption
                                ],
                                'media' => $url,
                                'title' => [
                                    'text' => $this->extractTitleFromUrl($url)
                                ]
                            ]
                        ]
                    ]
                ],
                'visibility' => [
                    'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC'
                ]
            ];

            $response = $this->sendRequest($url_endpoint, 'post', $params);
            $this->log('info', 'LinkedIn post shared successfully', [
                'platform' => 'linkedin',
                'post_id' => $response['id'] ?? null,
                'author' => $author,
                'organization_urn' => $this->organization_urn,
                'person_urn' => $this->person_urn,
                'caption_length' => strlen($caption),
            ]);
            return $response;
        } catch (\Exception $e) {
            $this->log('error', 'Failed to share to LinkedIn', [
                'platform' => 'linkedin',
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'url' => $url,
            ]);
            throw new SocialMediaException('Failed to share to LinkedIn: ' . $e->getMessage());
        }
    }

    /**
     * Share a text-only post to LinkedIn (without URL or image).
     *
     * @param string $caption The text content of the post.
     * @return array Response from the LinkedIn API.
     * @throws SocialMediaException
     */
    public function shareText(string $caption): array
    {
        if (empty(trim($caption))) {
            throw new SocialMediaException('Caption cannot be empty.');
        }
        
        try {
            // Use organization_urn if available, otherwise fall back to person_urn
            $author = $this->organization_urn ?? $this->person_urn;
            
            $url = $this->buildApiUrl('ugcPosts');
            $params = [
                'author' => $author,
                'lifecycleState' => 'PUBLISHED',
                'specificContent' => [
                    'com.linkedin.ugc.ShareContent' => [
                        'shareCommentary' => [
                            'text' => $caption
                        ],
                        'shareMediaCategory' => 'NONE',
                    ]
                ],
                'visibility' => [
                    'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC'
                ]
            ];

            $response = $this->sendRequest($url, 'post', $params);
            $this->log('info', 'LinkedIn text-only post shared successfully', [
                'platform' => 'linkedin',
                'post_id' => $response['id'] ?? null,
                'author' => $author,
                'organization_urn' => $this->organization_urn,
                'person_urn' => $this->person_urn,
                'caption_length' => strlen($caption),
            ]);
            return $response;
        } catch (\Exception $e) {
            $this->log('error', 'Failed to share text-only post to LinkedIn', [
                'platform' => 'linkedin',
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);
            throw new SocialMediaException('Failed to share text-only post to LinkedIn: ' . $e->getMessage());
        }
    }

    /**
     * Share an image post with a caption to LinkedIn.
     *
     * @param string $caption The caption to accompany the image.
     * @param string $image_url The URL of the image.
     * @return array Response from the LinkedIn API.
     * @throws SocialMediaException
     */
    public function shareImage(string $caption, string $image_url): array
    {
        $this->validateInput($caption, $image_url);
        
        try {
            // Use organization_urn if available, otherwise fall back to person_urn
            $author = $this->organization_urn ?? $this->person_urn;
            
            // Step 1: Upload image
            $imageUrn = $this->uploadImage($image_url);
            
            // Step 2: Create post with image
            $url = $this->buildApiUrl('ugcPosts');
            $params = [
                'author' => $author,
                'lifecycleState' => 'PUBLISHED',
                'specificContent' => [
                    'com.linkedin.ugc.ShareContent' => [
                        'shareCommentary' => [
                            'text' => $caption
                        ],
                        'shareMediaCategory' => 'IMAGE',
                        'media' => [
                            [
                                'status' => 'READY',
                                'description' => [
                                    'text' => $caption
                                ],
                                'media' => $imageUrn,
                                'title' => [
                                    'text' => 'Image Post'
                                ]
                            ]
                        ]
                    ]
                ],
                'visibility' => [
                    'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC'
                ]
            ];

            $response = $this->sendRequest($url, 'post', $params);
            $this->log('info', 'LinkedIn image post shared successfully', [
                'platform' => 'linkedin',
                'post_id' => $response['id'] ?? null,
                'author' => $author,
                'organization_urn' => $this->organization_urn,
                'person_urn' => $this->person_urn,
                'caption_length' => strlen($caption),
            ]);
            return $response;
        } catch (\Exception $e) {
            $this->log('error', 'Failed to share image to LinkedIn', [
                'platform' => 'linkedin',
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'image_url' => $image_url,
            ]);
            throw new SocialMediaException('Failed to share image to LinkedIn: ' . $e->getMessage());
        }
    }

    /**
     * Share a video post with a caption to LinkedIn.
     *
     * @param string $caption The caption to accompany the video.
     * @param string $video_url The URL of the video.
     * @return array Response from the LinkedIn API.
     * @throws SocialMediaException
     */
    public function shareVideo(string $caption, string $video_url): array
    {
        $this->validateInput($caption, $video_url);
        
        try {
            // Use organization_urn if available, otherwise fall back to person_urn
            $author = $this->organization_urn ?? $this->person_urn;
            
            // Step 1: Upload video
            $videoUrn = $this->uploadVideo($video_url);
            
            // Step 2: Create post with video
            $url = $this->buildApiUrl('ugcPosts');
            $params = [
                'author' => $author,
                'lifecycleState' => 'PUBLISHED',
                'specificContent' => [
                    'com.linkedin.ugc.ShareContent' => [
                        'shareCommentary' => [
                            'text' => $caption
                        ],
                        'shareMediaCategory' => 'VIDEO',
                        'media' => [
                            [
                                'status' => 'READY',
                                'description' => [
                                    'text' => $caption
                                ],
                                'media' => $videoUrn,
                                'title' => [
                                    'text' => 'Video Post'
                                ]
                            ]
                        ]
                    ]
                ],
                'visibility' => [
                    'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC'
                ]
            ];

            $response = $this->sendRequest($url, 'post', $params);
            $this->log('info', 'LinkedIn video post shared successfully', [
                'platform' => 'linkedin',
                'post_id' => $response['id'] ?? null,
                'author' => $author,
                'organization_urn' => $this->organization_urn,
                'person_urn' => $this->person_urn,
                'caption_length' => strlen($caption),
            ]);
            return $response;
        } catch (\Exception $e) {
            $this->log('error', 'Failed to share video to LinkedIn', [
                'platform' => 'linkedin',
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'video_url' => $video_url,
            ]);
            throw new SocialMediaException('Failed to share video to LinkedIn: ' . $e->getMessage());
        }
    }

    /**
     * Share to LinkedIn Company Page.
     *
     * @param string $caption The text content of the post.
     * @param string $url The URL to share.
     * @return array Response from the LinkedIn API.
     * @throws SocialMediaException
     */
    public function shareToCompanyPage(string $caption, string $url): array
    {
        if (!$this->organization_urn) {
            throw new SocialMediaException('Organization URN not configured for company page publishing.');
        }

        $this->validateInput($caption, $url);
        
        try {
            $url_endpoint = $this->buildApiUrl('ugcPosts');
            $params = [
                'author' => $this->organization_urn,
                'lifecycleState' => 'PUBLISHED',
                'specificContent' => [
                    'com.linkedin.ugc.ShareContent' => [
                        'shareCommentary' => [
                            'text' => $caption
                        ],
                        'shareMediaCategory' => 'ARTICLE',
                        'media' => [
                            [
                                'status' => 'READY',
                                'description' => [
                                    'text' => $caption
                                ],
                                'media' => $url,
                                'title' => [
                                    'text' => $this->extractTitleFromUrl($url)
                                ]
                            ]
                        ]
                    ]
                ],
                'visibility' => [
                    'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC'
                ]
            ];

            $response = $this->sendRequest($url_endpoint, 'post', $params);
            $this->log('info', 'LinkedIn company page post shared successfully', [
                'platform' => 'linkedin',
                'post_id' => $response['id'] ?? null,
                'organization_urn' => $this->organization_urn,
                'caption_length' => strlen($caption),
            ]);
            return $response;
        } catch (\Exception $e) {
            $this->log('error', 'Failed to share to LinkedIn company page', [
                'platform' => 'linkedin',
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'organization_urn' => $this->organization_urn,
                'url' => $url,
            ]);
            throw new SocialMediaException('Failed to share to LinkedIn company page: ' . $e->getMessage());
        }
    }

    /**
     * Get user profile information.
     *
     * @param string|null $projection Optional custom projection. If not provided, uses default projection.
     * @return array Response from the LinkedIn API.
     * @throws SocialMediaException
     */
    public function getUserInfo(?string $projection = null): array
    {
        try {
            $url = $this->buildApiUrl('people/~');
            
            // Use provided projection or default
            $defaultProjection = '(id,firstName,lastName,profilePicture(displayImage~:playableStreams))';
            $projection = $projection ?? $defaultProjection;
            
            $params = [
                'projection' => $projection
            ];

            return $this->sendRequest($url, 'get', $params);
        } catch (\Exception $e) {
            $this->log('error', 'Failed to get LinkedIn user info', [
                'platform' => 'linkedin',
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);
            throw new SocialMediaException('Failed to get LinkedIn user info: ' . $e->getMessage());
        }
    }

    /**
     * Get user profile with specific projection (alias for getUserInfo with projection).
     *
     * @param string|null $projection Optional custom projection. If not provided, uses simple projection.
     * @return array Response from the LinkedIn API.
     * @throws SocialMediaException
     */
    public function getUserProfile(?string $projection = null): array
    {
        // Default to simple projection if not provided
        $defaultProjection = '(id,localizedFirstName,localizedLastName)';
        $projection = $projection ?? $defaultProjection;
        
        return $this->getUserInfo($projection);
    }

    /**
     * Get administered company pages for the authenticated user.
     *
     * @return array Array of company pages with 'id' and 'name' keys.
     * @throws SocialMediaException
     */
    public function getAdministeredCompanyPages(): array
    {
        try {
            $pages = [];
            
            // Try organizationAcls endpoint with projection first
            try {
                $url = $this->buildApiUrl('organizationAcls');
                $params = [
                    'q' => 'roleAssignee',
                    'role' => 'ADMINISTRATOR',
                    'projection' => '(elements*(organization~(id,localizedName)))',
                ];

                $response = $this->sendRequest($url, 'get', $params);
                
                if (isset($response['elements']) && is_array($response['elements'])) {
                    foreach ($response['elements'] as $element) {
                        if (isset($element['organization'])) {
                            $org = $element['organization'];
                            $orgId = $org['id'] ?? null;
                            $orgName = $org['localizedName'] ?? null;
                            
                            if ($orgId) {
                                // Extract numeric ID from URN if needed (e.g., "urn:li:organization:12345" -> "12345")
                                if (strpos($orgId, 'urn:li:organization:') === 0) {
                                    $orgId = str_replace('urn:li:organization:', '', $orgId);
                                }
                                
                                $pages[] = [
                                    'id' => $orgId,
                                    'name' => $orgName ?? 'Unknown Organization',
                                ];
                            }
                        }
                    }
                    
                    if (!empty($pages)) {
                        $this->log('info', 'LinkedIn administered company pages retrieved', [
                            'platform' => 'linkedin',
                            'count' => count($pages),
                        ]);
                        return $pages;
                    }
                }
            } catch (\Exception $e) {
                $this->log('warning', 'Failed to get company pages via organizationAcls with projection', [
                    'platform' => 'linkedin',
                    'error' => $e->getMessage(),
                ]);
            }

            // Fallback: Try organizationAcls without projection
            try {
                $url = $this->buildApiUrl('organizationAcls');
                $params = [
                    'q' => 'roleAssignee',
                    'role' => 'ADMINISTRATOR',
                ];

                $response = $this->sendRequest($url, 'get', $params);
                
                if (isset($response['elements']) && is_array($response['elements'])) {
                    foreach ($response['elements'] as $element) {
                        if (isset($element['organization'])) {
                            $orgUrn = $element['organization'];
                            
                            // Extract organization ID from URN
                            if (strpos($orgUrn, 'urn:li:organization:') === 0) {
                                $orgId = str_replace('urn:li:organization:', '', $orgUrn);
                                
                                // Try to get organization name
                                try {
                                    $orgInfo = $this->getOrganizationInfo($orgId);
                                    $orgName = $orgInfo['localizedName'] ?? 'Unknown Organization';
                                } catch (\Exception $e) {
                                    $orgName = 'Unknown Organization';
                                }
                                
                                $pages[] = [
                                    'id' => $orgId,
                                    'name' => $orgName,
                                ];
                            }
                        }
                    }
                    
                    if (!empty($pages)) {
                        $this->log('info', 'LinkedIn administered company pages retrieved (fallback)', [
                            'platform' => 'linkedin',
                            'count' => count($pages),
                        ]);
                        return $pages;
                    }
                }
            } catch (\Exception $e) {
                $this->log('warning', 'Failed to get company pages via organizationAcls', [
                    'platform' => 'linkedin',
                    'error' => $e->getMessage(),
                ]);
            }

            // Final fallback: Try organizationEntityPermissions
            try {
                $url = $this->buildApiUrl('organizationEntityPermissions');
                $params = [
                    'q' => 'roleAssignee',
                    'role' => 'ADMINISTRATOR',
                ];

                $response = $this->sendRequest($url, 'get', $params);
                
                if (isset($response['elements']) && is_array($response['elements'])) {
                    foreach ($response['elements'] as $element) {
                        if (isset($element['organization'])) {
                            $orgUrn = $element['organization'];
                            
                            // Extract organization ID from URN
                            if (strpos($orgUrn, 'urn:li:organization:') === 0) {
                                $orgId = str_replace('urn:li:organization:', '', $orgUrn);
                                
                                // Try to get organization name
                                try {
                                    $orgInfo = $this->getOrganizationInfo($orgId);
                                    $orgName = $orgInfo['localizedName'] ?? 'Unknown Organization';
                                } catch (\Exception $e) {
                                    $orgName = 'Unknown Organization';
                                }
                                
                                $pages[] = [
                                    'id' => $orgId,
                                    'name' => $orgName,
                                ];
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                $this->log('warning', 'Failed to get company pages via organizationEntityPermissions', [
                    'platform' => 'linkedin',
                    'error' => $e->getMessage(),
                ]);
            }

            if (empty($pages)) {
                $this->log('warning', 'No administered company pages found', [
                    'platform' => 'linkedin',
                ]);
            } else {
                $this->log('info', 'LinkedIn administered company pages retrieved (final fallback)', [
                    'platform' => 'linkedin',
                    'count' => count($pages),
                ]);
            }

            return $pages;
        } catch (\Exception $e) {
            $this->log('error', 'Failed to get LinkedIn administered company pages', [
                'platform' => 'linkedin',
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);
            throw new SocialMediaException('Failed to get LinkedIn administered company pages: ' . $e->getMessage());
        }
    }

    /**
     * Get organization information by organization ID.
     *
     * @param string $orgId The organization ID (numeric ID, not URN).
     * @return array Response from the LinkedIn API.
     * @throws SocialMediaException
     */
    public function getOrganizationInfo(string $orgId): array
    {
        try {
            $url = $this->buildApiUrl("organizations/{$orgId}");

            $response = $this->sendRequest($url, 'get');
            
            $this->log('info', 'LinkedIn organization info retrieved', [
                'platform' => 'linkedin',
                'organization_id' => $orgId,
            ]);
            
            return $response;
        } catch (\Exception $e) {
            $this->log('error', 'Failed to get LinkedIn organization info', [
                'platform' => 'linkedin',
                'organization_id' => $orgId,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);
            throw new SocialMediaException('Failed to get LinkedIn organization info: ' . $e->getMessage());
        }
    }

    /**
     * Upload image to LinkedIn.
     *
     * @param string $imageUrl The URL of the image to upload.
     * @return string The image URN.
     * @throws SocialMediaException
     */
    private function uploadImage(string $imageUrl): string
    {
        // Use organization_urn if available, otherwise fall back to person_urn
        $owner = $this->organization_urn ?? $this->person_urn;
        
        // Step 1: Register upload
        $registerUrl = $this->buildApiUrl('assets?action=registerUpload');
        $registerParams = [
            'registerUploadRequest' => [
                'recipes' => ['urn:li:digitalmediaRecipe:feedshare-image'],
                'owner' => $owner,
                'serviceRelationships' => [
                    [
                        'relationshipType' => 'OWNER',
                        'identifier' => 'urn:li:userGeneratedContent'
                    ]
                ]
            ]
        ];

        $registerResponse = $this->sendRequest($registerUrl, 'post', $registerParams);
        $uploadUrl = $registerResponse['value']['uploadMechanism']['com.linkedin.digitalmedia.uploading.MediaUploadHttpRequest']['uploadUrl'];
        $asset = $registerResponse['value']['asset'];

        // Step 2: Upload image
        $imageContent = file_get_contents($imageUrl);
        if ($imageContent === false) {
            throw new SocialMediaException('Failed to download image from URL: ' . $imageUrl);
        }

        $uploadResponse = \Illuminate\Support\Facades\Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->access_token
        ])->put($uploadUrl, $imageContent);

        if (!$uploadResponse->successful()) {
            throw new SocialMediaException('Failed to upload image to LinkedIn');
        }

        return $asset;
    }

    /**
     * Upload video to LinkedIn.
     *
     * @param string $videoUrl The URL of the video to upload.
     * @return string The video URN.
     * @throws SocialMediaException
     */
    private function uploadVideo(string $videoUrl): string
    {
        // Use organization_urn if available, otherwise fall back to person_urn
        $owner = $this->organization_urn ?? $this->person_urn;
        
        // Step 1: Register upload
        $registerUrl = $this->buildApiUrl('assets?action=registerUpload');
        $registerParams = [
            'registerUploadRequest' => [
                'recipes' => ['urn:li:digitalmediaRecipe:feedshare-video'],
                'owner' => $owner,
                'serviceRelationships' => [
                    [
                        'relationshipType' => 'OWNER',
                        'identifier' => 'urn:li:userGeneratedContent'
                    ]
                ]
            ]
        ];

        $registerResponse = $this->sendRequest($registerUrl, 'post', $registerParams);
        $uploadUrl = $registerResponse['value']['uploadMechanism']['com.linkedin.digitalmedia.uploading.MediaUploadHttpRequest']['uploadUrl'];
        $asset = $registerResponse['value']['asset'];

        // Step 2: Upload video
        $videoContent = file_get_contents($videoUrl);
        if ($videoContent === false) {
            throw new SocialMediaException('Failed to download video from URL: ' . $videoUrl);
        }

        $uploadResponse = \Illuminate\Support\Facades\Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->access_token
        ])->put($uploadUrl, $videoContent);

        if (!$uploadResponse->successful()) {
            throw new SocialMediaException('Failed to upload video to LinkedIn');
        }

        return $asset;
    }

    /**
     * Extract title from URL.
     *
     * @param string $url The URL to extract title from.
     * @return string The extracted title.
     */
    private function extractTitleFromUrl(string $url): string
    {
        $parsedUrl = parse_url($url);
        $host = $parsedUrl['host'] ?? 'Link';
        return 'Shared from ' . $host;
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
     * Build LinkedIn API URL.
     *
     * @param string $endpoint The API endpoint.
     * @return string Complete API URL.
     */
    private function buildApiUrl(string $endpoint): string
    {
        return self::API_BASE_URL . '/' . $endpoint;
    }

    /**
     * Send authenticated request to LinkedIn API.
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
            'Content-Type' => 'application/json',
            'X-Restli-Protocol-Version' => '2.0.0'
        ];
        
        $headers = array_merge($defaultHeaders, $headers);

        $response = \Illuminate\Support\Facades\Http::withHeaders($headers)
            ->{$method}($url, $params);

        if (!$response->successful()) {
            $errorMessage = $response->json()['message'] ?? 'Unknown error occurred';
            throw new SocialMediaException("LinkedIn API error: {$errorMessage}");
        }

        return $response->json();
    }
}

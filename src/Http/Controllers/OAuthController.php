<?php

namespace Mantix\LaravelSocialMediaPublisher\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use mantix\LaravelSocialMediaPublisher\Exceptions\SocialMediaException;
use mantix\LaravelSocialMediaPublisher\Models\SocialMediaConnection;
use mantix\LaravelSocialMediaPublisher\Services\FacebookService;
use mantix\LaravelSocialMediaPublisher\Services\InstagramService;
use mantix\LaravelSocialMediaPublisher\Services\LinkedInService;
use mantix\LaravelSocialMediaPublisher\Services\TwitterService;

/**
 * OAuth Controller
 *
 * Handles OAuth callbacks from social media platforms.
 * This controller can be extended or replaced by publishing it to your app.
 */
class OAuthController
{
    /**
     * Handle Facebook OAuth callback.
     */
    public function handleFacebookCallback(Request $request)
    {
        $code = $request->get('code');
        $error = $request->get('error');
        
        if ($error) {
            return $this->handleError('facebook', $error, $request->get('error_description'));
        }

        if (!$code) {
            return $this->handleError('facebook', 'no_code', 'Authorization code not provided');
        }

        try {
            $redirectUri = route('social-media.facebook.callback');
            $tokenData = FacebookService::handleCallback($code, $redirectUri);

            // Get authenticated user (you may need to adjust this based on your auth setup)
            $user = auth()->user();
            
            if (!$user) {
                return $this->handleError('facebook', 'not_authenticated', 'User must be authenticated to connect social media accounts');
            }

            // Save connection
            SocialMediaConnection::updateOrCreate(
                [
                    'owner_id' => $user->id,
                    'owner_type' => get_class($user),
                    'platform' => 'facebook',
                    'connection_type' => 'page',
                ],
                [
                    'platform_user_id' => $tokenData['pages'][0]['id'] ?? null,
                    'platform_username' => $tokenData['pages'][0]['name'] ?? null,
                    'access_token' => $tokenData['access_token'],
                    'expires_at' => $tokenData['expires_in'] ? now()->addSeconds($tokenData['expires_in']) : null,
                    'metadata' => [
                        'page_id' => $tokenData['pages'][0]['id'] ?? null,
                        'pages' => $tokenData['pages'] ?? [],
                    ],
                    'is_active' => true,
                ]
            );

            return $this->handleSuccess('facebook', 'Facebook connected successfully!');
        } catch (SocialMediaException $e) {
            return $this->handleError('facebook', 'callback_failed', $e->getMessage());
        } catch (\Exception $e) {
            if (config('social_media_publisher.enable_logging', true)) {
                Log::error('Facebook OAuth callback exception', [
                    'platform' => 'facebook',
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                ]);
            }
            return $this->handleError('facebook', 'exception', $e->getMessage());
        }
    }

    /**
     * Handle LinkedIn OAuth callback.
     */
    public function handleLinkedInCallback(Request $request)
    {
        $code = $request->get('code');
        $error = $request->get('error');
        
        if ($error) {
            return $this->handleError('linkedin', $error, $request->get('error_description'));
        }

        if (!$code) {
            return $this->handleError('linkedin', 'no_code', 'Authorization code not provided');
        }

        try {
            $redirectUri = route('social-media.linkedin.callback');
            
            // Retrieve PKCE code verifier from session if PKCE was used
            $codeVerifier = session('linkedin_code_verifier');
            
            $tokenData = LinkedInService::handleCallback($code, $redirectUri, $codeVerifier);

            $user = auth()->user();
            
            if (!$user) {
                return $this->handleError('linkedin', 'not_authenticated', 'User must be authenticated to connect social media accounts');
            }

            // Clear code verifier from session after use
            session()->forget('linkedin_code_verifier');

            // Save connection
            SocialMediaConnection::updateOrCreate(
                [
                    'owner_id' => $user->id,
                    'owner_type' => get_class($user),
                    'platform' => 'linkedin',
                    'connection_type' => 'profile',
                ],
                [
                    'platform_user_id' => $tokenData['profile']['id'] ?? null,
                    'access_token' => $tokenData['access_token'],
                    'refresh_token' => $tokenData['refresh_token'] ?? null,
                    'expires_at' => $tokenData['expires_in'] ? now()->addSeconds($tokenData['expires_in']) : null,
                    'metadata' => [
                        'person_urn' => $tokenData['profile']['id'] ?? null,
                    ],
                    'is_active' => true,
                ]
            );

            return $this->handleSuccess('linkedin', 'LinkedIn connected successfully!');
        } catch (SocialMediaException $e) {
            return $this->handleError('linkedin', 'callback_failed', $e->getMessage());
        } catch (\Exception $e) {
            if (config('social_media_publisher.enable_logging', true)) {
                Log::error('LinkedIn OAuth callback exception', [
                    'platform' => 'linkedin',
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                ]);
            }
            return $this->handleError('linkedin', 'exception', $e->getMessage());
        }
    }

    /**
     * Handle Twitter/X OAuth callback.
     */
    public function handleXCallback(Request $request)
    {
        $code = $request->get('code');
        $error = $request->get('error');
        
        if ($error) {
            return $this->handleError('x', $error, $request->get('error_description'));
        }

        if (!$code) {
            return $this->handleError('x', 'no_code', 'Authorization code not provided');
        }

        try {
            $redirectUri = route('social-media.x.callback');
            
            // Retrieve PKCE code verifier from session (recommended approach)
            $codeVerifier = session('twitter_code_verifier');
            
            // Fallback: Try to extract from state for backward compatibility
            // (This is less secure and should be avoided in production)
            if (!$codeVerifier && $request->has('state')) {
                $state = $request->get('state');
                $stateData = json_decode(base64_decode($state), true);
                $codeVerifier = $stateData['code_verifier'] ?? null;
            }
            
            $tokenData = TwitterService::handleCallback($code, $redirectUri, $codeVerifier);

            $user = auth()->user();
            
            if (!$user) {
                return $this->handleError('x', 'not_authenticated', 'User must be authenticated to connect social media accounts');
            }

            // Clear code verifier from session after use
            session()->forget('twitter_code_verifier');

            // Save connection
            SocialMediaConnection::updateOrCreate(
                [
                    'owner_id' => $user->id,
                    'owner_type' => get_class($user),
                    'platform' => 'twitter',
                    'connection_type' => 'profile',
                ],
                [
                    'platform_user_id' => $tokenData['user_profile']['id'] ?? null,
                    'platform_username' => $tokenData['user_profile']['username'] ?? null,
                    'access_token' => $tokenData['access_token'],
                    'refresh_token' => $tokenData['refresh_token'] ?? null,
                    'expires_at' => $tokenData['expires_in'] ? now()->addSeconds($tokenData['expires_in']) : null,
                    'metadata' => [
                        'username' => $tokenData['user_profile']['username'] ?? null,
                        'name' => $tokenData['user_profile']['name'] ?? null,
                        'scope' => $tokenData['scope'] ?? null,
                    ],
                    'is_active' => true,
                ]
            );

            return $this->handleSuccess('x', 'Twitter/X connected successfully!');
        } catch (SocialMediaException $e) {
            return $this->handleError('x', 'callback_failed', $e->getMessage());
        } catch (\Exception $e) {
            if (config('social_media_publisher.enable_logging', true)) {
                Log::error('Twitter/X OAuth callback exception', [
                    'platform' => 'x',
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                ]);
            }
            return $this->handleError('x', 'exception', $e->getMessage());
        }
    }

    /**
     * Handle Instagram OAuth callback.
     */
    public function handleInstagramCallback(Request $request)
    {
        $code = $request->get('code');
        $error = $request->get('error');
        
        if ($error) {
            return $this->handleError('instagram', $error, $request->get('error_description'));
        }

        if (!$code) {
            return $this->handleError('instagram', 'no_code', 'Authorization code not provided');
        }

        try {
            $redirectUri = route('social-media.instagram.callback');
            $tokenData = InstagramService::handleCallback($code, $redirectUri);

            $user = auth()->user();
            
            if (!$user) {
                return $this->handleError('instagram', 'not_authenticated', 'User must be authenticated to connect social media accounts');
            }

            // Save connection
            SocialMediaConnection::updateOrCreate(
                [
                    'owner_id' => $user->id,
                    'owner_type' => get_class($user),
                    'platform' => 'instagram',
                    'connection_type' => 'profile',
                ],
                [
                    'platform_user_id' => $tokenData['instagram_account']['id'] ?? null,
                    'platform_username' => $tokenData['instagram_account']['username'] ?? null,
                    'access_token' => $tokenData['access_token'],
                    'expires_at' => $tokenData['expires_in'] ? now()->addSeconds($tokenData['expires_in']) : null,
                    'metadata' => [
                        'instagram_account_id' => $tokenData['instagram_account']['id'] ?? null,
                        'username' => $tokenData['instagram_account']['username'] ?? null,
                    ],
                    'is_active' => true,
                ]
            );

            return $this->handleSuccess('instagram', 'Instagram connected successfully!');
        } catch (SocialMediaException $e) {
            return $this->handleError('instagram', 'callback_failed', $e->getMessage());
        } catch (\Exception $e) {
            if (config('social_media_publisher.enable_logging', true)) {
                Log::error('Instagram OAuth callback exception', [
                    'platform' => 'instagram',
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                ]);
            }
            return $this->handleError('instagram', 'exception', $e->getMessage());
        }
    }

    /**
     * Handle TikTok OAuth callback.
     */
    public function handleTikTokCallback(Request $request)
    {
        // TODO: Implement when TikTok OAuth is implemented
        return $this->handleError('tiktok', 'not_implemented', 'TikTok OAuth callback is not yet implemented');
    }

    /**
     * Handle YouTube OAuth callback.
     */
    public function handleYouTubeCallback(Request $request)
    {
        // TODO: Implement when YouTube OAuth is implemented
        return $this->handleError('youtube', 'not_implemented', 'YouTube OAuth callback is not yet implemented');
    }

    /**
     * Handle Pinterest OAuth callback.
     */
    public function handlePinterestCallback(Request $request)
    {
        // TODO: Implement when Pinterest OAuth is implemented
        return $this->handleError('pinterest', 'not_implemented', 'Pinterest OAuth callback is not yet implemented');
    }

    /**
     * Handle successful OAuth connection.
     */
    protected function handleSuccess(string $platform, string $message)
    {
        // You can customize this redirect based on your application's needs
        $redirectRoute = config('social_media_publisher.oauth_redirect_route', 'dashboard');
        
        return redirect()->route($redirectRoute)->with('success', $message);
    }

    /**
     * Handle OAuth errors.
     */
    protected function handleError(string $platform, string $error, ?string $description = null)
    {
        $message = "Failed to connect {$platform}: " . ($description ?? $error);
        
        if (config('social_media_publisher.enable_logging', true)) {
            Log::error('OAuth callback error', [
                'platform' => $platform,
                'error' => $error,
                'description' => $description,
            ]);
        }

        $redirectRoute = config('social_media_publisher.oauth_redirect_route', 'dashboard');
        
        return redirect()->route($redirectRoute)->with('error', $message);
    }
}


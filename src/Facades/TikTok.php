<?php

namespace Mantix\LaravelSocialMediaPublisher\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Class TikTok
 *
 * Facade for the TikTokService.
 *
 * @method static string getAuthorizationUrl(string $redirectUri, array $scopes = [], ?string $state = null)
 * @method static array handleCallback(string $code, string $redirectUri)
 * @method static array refreshAccessToken(string $refreshToken)
 * @method static bool disconnect(string $accessToken)
 * @method static \Mantix\LaravelSocialMediaPublisher\Services\TikTokService forConnection(\Mantix\LaravelSocialMediaPublisher\Models\SocialMediaConnection $connection)
 *
 * @see \Mantix\LaravelSocialMediaPublisher\Services\TikTokService
 */
class TikTok extends Facade {
    protected static function getFacadeAccessor() {
        return \Mantix\LaravelSocialMediaPublisher\Services\TikTokService::class;
    }
}

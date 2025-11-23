<?php

namespace Mantix\LaravelSocialMediaPublisher\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Class YouTube
 *
 * Facade for the YouTubeService.
 *
 * @method static string getAuthorizationUrl(string $redirectUri, array $scopes = [], ?string $state = null)
 * @method static array handleCallback(string $code, string $redirectUri)
 * @method static array refreshAccessToken(string $refreshToken)
 * @method static bool disconnect(string $accessToken)
 * @method static \Mantix\LaravelSocialMediaPublisher\Services\YouTubeService forConnection(\Mantix\LaravelSocialMediaPublisher\Models\SocialMediaConnection $connection)
 *
 * @see \Mantix\LaravelSocialMediaPublisher\Services\YouTubeService
 */
class YouTube extends Facade {
    protected static function getFacadeAccessor() {
        return \Mantix\LaravelSocialMediaPublisher\Services\YouTubeService::class;
    }
}

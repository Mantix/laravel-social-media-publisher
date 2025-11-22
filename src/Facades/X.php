<?php

namespace Mantix\LaravelSocialMediaPublisher\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Class X
 *
 * Facade for TwitterService (X).
 *
 * @method static string getAuthorizationUrl(string $redirectUri, array $scopes = [], ?string $state = null)
 * @method static array handleCallback(string $code, string $redirectUri, string $codeVerifier)
 * @method static \Mantix\LaravelSocialMediaPublisher\Services\TwitterService forConnection(\Mantix\LaravelSocialMediaPublisher\Models\SocialMediaConnection $connection)
 *
 * @see \Mantix\LaravelSocialMediaPublisher\Services\TwitterService
 */
class X extends Facade {
    protected static function getFacadeAccessor() {
        return \Mantix\LaravelSocialMediaPublisher\Services\TwitterService::class;
    }
}

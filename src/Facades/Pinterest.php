<?php

namespace Mantix\LaravelSocialMediaPublisher\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Class Pinterest
 *
 * Facade for the PinterestService.
 *
 * @method static string getAuthorizationUrl(string $redirectUri, array $scopes = [], ?string $state = null)
 * @method static array handleCallback(string $code, string $redirectUri)
 * @method static bool disconnect(string $accessToken)
 * @method static \Mantix\LaravelSocialMediaPublisher\Services\PinterestService forConnection(\Mantix\LaravelSocialMediaPublisher\Models\SocialMediaConnection $connection)
 *
 * @see \Mantix\LaravelSocialMediaPublisher\Services\PinterestService
 */
class Pinterest extends Facade {
    protected static function getFacadeAccessor() {
        return \Mantix\LaravelSocialMediaPublisher\Services\PinterestService::class;
    }
}

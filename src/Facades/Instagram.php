<?php

namespace Mantix\LaravelSocialMediaPublisher\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Class Instagram
 *
 * Facade for the InstagramService.
 * Acts as a factory to instantiate the service for a specific connection.
 *
 * @method static string getAuthorizationUrl(string $redirectUri, array $scopes = [], ?string $state = null)
 * @method static array handleCallback(string $code, string $redirectUri)
 * @method static \Mantix\LaravelSocialMediaPublisher\Services\InstagramService forConnection(\Mantix\LaravelSocialMediaPublisher\Models\SocialMediaConnection $connection)
 *
 * @see \Mantix\LaravelSocialMediaPublisher\Services\InstagramService
 */
class Instagram extends Facade {
    protected static function getFacadeAccessor() {
        return \Mantix\LaravelSocialMediaPublisher\Services\InstagramService::class;
    }
}

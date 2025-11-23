<?php

namespace Mantix\LaravelSocialMediaPublisher\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Class Facebook
 *
 * Facade for the FacebookService.
 * Acts as a factory to instantiate the service for a specific connection.
 *
 * @method static string getAuthorizationUrl(string $redirectUri, array $scopes = [], ?string $state = null)
 * @method static array handleCallback(string $code, string $redirectUri)
 * @method static bool disconnect(string $accessToken)
 * @method static \Mantix\LaravelSocialMediaPublisher\Services\FacebookService forConnection(\Mantix\LaravelSocialMediaPublisher\Models\SocialMediaConnection $connection)
 *
 * @see \Mantix\LaravelSocialMediaPublisher\Services\FacebookService
 */
class Facebook extends Facade {
    protected static function getFacadeAccessor() {
        return \Mantix\LaravelSocialMediaPublisher\Services\FacebookService::class;
    }
}

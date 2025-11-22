<?php

namespace Mantix\LaravelSocialMediaPublisher\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Class LinkedIn
 *
 * This Facade acts as the entry point for LinkedIn Oauth and Connection handling.
 * * Usage:
 * 1. Oauth: LinkedIn::getAuthorizationUrl(...)
 * 2. Publishing: LinkedIn::forConnection($connection)->shareText(...)
 *
 * @method static string getAuthorizationUrl(string $redirectUri, array $scopes = [], ?string $state = null)
 * @method static array handleCallback(string $code, string $redirectUri)
 * @method static \Mantix\LaravelSocialMediaPublisher\Services\LinkedInService forConnection(\Mantix\LaravelSocialMediaPublisher\Models\SocialMediaConnection $connection)
 *
 * @see \Mantix\LaravelSocialMediaPublisher\Services\LinkedInService
 */
class LinkedIn extends Facade {
    protected static function getFacadeAccessor() {
        return \Mantix\LaravelSocialMediaPublisher\Services\LinkedInService::class;
    }
}

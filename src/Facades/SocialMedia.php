<?php

namespace Mantix\LaravelSocialMediaPublisher\Facades;

use Illuminate\Support\Facades\Facade;
use mantix\LaravelSocialMediaPublisher\Services\SocialMediaManager;

/**
 * Class SocialMedia
 *
 * Unified facade for publishing to multiple social media platforms simultaneously.
 *
 * @method static array shareText($owner, array $platforms, string $caption, ?int $ownerId = null)
 * @method static array shareUrl($owner, array $platforms, string $caption, string $url, ?int $ownerId = null)
 * @method static array shareImage($owner, array $platforms, string $caption, string $image_url, ?int $ownerId = null)
 * @method static array shareVideo($owner, array $platforms, string $caption, string $video_url, ?int $ownerId = null)
 * @method static SocialMediaManager platform(string $platform, $owner = null, ?int $ownerId = null, ?string $connectionType = 'profile')
 * @method static SocialMediaManager facebook()
 * @method static SocialMediaManager twitter()
 * @method static SocialMediaManager linkedin()
 * @method static SocialMediaManager instagram()
 * @method static SocialMediaManager tiktok()
 * @method static SocialMediaManager youtube()
 * @method static SocialMediaManager pinterest()
 * @method static SocialMediaManager telegram()
 *
 * @see \mantix\LaravelSocialMediaPublisher\Services\SocialMediaManager
 */
class SocialMedia extends Facade
{
    protected static function getFacadeAccessor()
    {
        return SocialMediaManager::class;
    }
}

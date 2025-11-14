<?php

namespace Mantix\LaravelSocialMediaPublisher\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Class Instagram
 *
 * @method static mixed share(string $caption, string $url)
 * @method static mixed shareImage(string $caption, string $image_url)
 * @method static mixed shareVideo(string $caption, string $video_url)
 * @method static mixed shareStory(string $caption, string $url)
 * @method static mixed shareCarousel(string $caption, array $image_urls)
 * @method static mixed getAccountInfo()
 * @method static mixed getRecentMedia(int $limit = 25)
 *
 * @see \mantix\LaravelSocialMediaPublisher\Services\InstagramService
 */
class Instagram extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \mantix\LaravelSocialMediaPublisher\Services\InstagramService::class;
    }
}

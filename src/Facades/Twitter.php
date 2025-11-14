<?php

namespace Mantix\LaravelSocialMediaPublisher\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Class Twitter
 *
 * @method static mixed share(string $caption, string $url)
 * @method static mixed shareImage(string $caption, string $image_url)
 * @method static mixed shareVideo(string $caption, string $video_url)
 * @method static mixed getTimeline(int $limit = 10)
 * @method static mixed getUserInfo()
 *
 * @see \mantix\LaravelSocialMediaPublisher\Services\TwitterService
 */
class Twitter extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \mantix\LaravelSocialMediaPublisher\Services\TwitterService::class;
    }
}

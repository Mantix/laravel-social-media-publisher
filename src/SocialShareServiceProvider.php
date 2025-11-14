<?php

namespace mantix\LaravelSocialMediaPublisher;

use Illuminate\Support\ServiceProvider;
use mantix\LaravelSocialMediaPublisher\Services\FacebookService;
use mantix\LaravelSocialMediaPublisher\Services\InstagramService;
use mantix\LaravelSocialMediaPublisher\Services\LinkedInService;
use mantix\LaravelSocialMediaPublisher\Services\PinterestService;
use mantix\LaravelSocialMediaPublisher\Services\SocialMediaManager;
use mantix\LaravelSocialMediaPublisher\Services\TelegramService;
use mantix\LaravelSocialMediaPublisher\Services\TikTokService;
use mantix\LaravelSocialMediaPublisher\Services\TwitterService;
use mantix\LaravelSocialMediaPublisher\Services\YouTubeService;

class SocialShareServiceProvider extends ServiceProvider {

    public function boot() {
        $this->publishes([
             __DIR__. '/config/social-media-publisher.php' => config_path('social-media-publisher.php'),
        ], 'social-media-publisher-config');

        // Publish migrations
        $this->publishes([
            __DIR__.'/database/migrations' => database_path('migrations'),
        ], 'social-media-publisher-migrations');

        // Load OAuth routes (automatically excluded from CSRF)
        $this->loadRoutesFrom(__DIR__ . '/routes/oauth.php');
    }

    public function register() {
        // Register Telegram service (Bot API - no OAuth required)
        $this->app->singleton(TelegramService::class, function ($app) {
            return TelegramService::getInstance();
        });

        // Register SocialMediaManager
        $this->app->singleton(SocialMediaManager::class, function ($app) {
            return new SocialMediaManager();
        });

        // Register service aliases (only for services that can be instantiated)
        $this->app->alias(TelegramService::class, 'telegram');
        $this->app->alias(SocialMediaManager::class, 'socialmedia');

        // Register config file
        $this->mergeConfigFrom( __DIR__. '/config/social-media-publisher.php', 'social_media_publisher');
    }

}
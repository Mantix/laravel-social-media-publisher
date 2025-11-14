<?php

namespace mantix\LaravelSocialMediaPublisher\Tests\Feature;

use mantix\LaravelSocialMediaPublisher\SocialShareServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase {

    protected function getPackageProviders($app) {
        return [
            SocialShareServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app) {
        // Setup default config for testing
        $app['config']->set('social_media_publisher.facebook_access_token', 'fake-facebook-token');
        $app['config']->set('social_media_publisher.facebook_page_id', 'fake-facebook-page-id');
        $app['config']->set('social_media_publisher.facebook_api_version', 'v20.0');

        $app['config']->set('social_media_publisher.telegram_bot_token', 'fake-telegram-token');
        $app['config']->set('social_media_publisher.telegram_chat_id', 'fake-telegram-chat-id');
        $app['config']->set('social_media_publisher.telegram_api_base_url', 'https://api.telegram.org/bot');
    }
}
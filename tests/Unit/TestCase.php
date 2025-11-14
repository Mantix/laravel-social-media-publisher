<?php

namespace Mantix\LaravelSocialMediaPublisher\Tests\Unit;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Mantix\LaravelSocialMediaPublisher\Tests\Helpers\TestHelpers;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends OrchestraTestCase
{
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up test configuration
        config([
            'social_media_publisher.facebook_client_id' => 'test_facebook_client_id',
            'social_media_publisher.facebook_client_secret' => 'test_facebook_client_secret',
            'social_media_publisher.facebook_api_version' => 'v20.0',
            'social_media_publisher.x_client_id' => 'test_x_client_id',
            'social_media_publisher.x_client_secret' => 'test_x_client_secret',
            'social_media_publisher.x_api_key' => 'test_x_api_key',
            'social_media_publisher.x_api_secret_key' => 'test_x_api_secret_key',
            'social_media_publisher.linkedin_client_id' => 'test_linkedin_client_id',
            'social_media_publisher.linkedin_client_secret' => 'test_linkedin_client_secret',
            'social_media_publisher.instagram_client_id' => 'test_instagram_client_id',
            'social_media_publisher.instagram_client_secret' => 'test_instagram_client_secret',
            'social_media_publisher.tiktok_client_id' => 'test_tiktok_client_id',
            'social_media_publisher.tiktok_client_secret' => 'test_tiktok_client_secret',
            'social_media_publisher.youtube_client_id' => 'test_youtube_client_id',
            'social_media_publisher.youtube_client_secret' => 'test_youtube_client_secret',
            'social_media_publisher.pinterest_client_id' => 'test_pinterest_client_id',
            'social_media_publisher.pinterest_client_secret' => 'test_pinterest_client_secret',
            'social_media_publisher.telegram_bot_token' => 'test_telegram_token',
            'social_media_publisher.telegram_chat_id' => 'test_chat_id',
            'social_media_publisher.enable_logging' => false, // Disable logging in tests unless testing logging
            'social_media_publisher.timeout' => 30,
            'social_media_publisher.retry_attempts' => 3,
        ]);

        // Set up database
        $this->setUpDatabase();
    }

    protected function setUpDatabase(): void
    {
        Schema::create('social_media_connections', function ($table) {
            $table->id();
            $table->morphs('owner');
            $table->string('platform');
            $table->string('connection_type')->default('profile');
            $table->string('platform_user_id')->nullable();
            $table->string('platform_username')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->text('token_secret')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['owner_id', 'owner_type', 'platform']);
            $table->index(['owner_id', 'owner_type', 'platform', 'connection_type']);
        });
    }

    protected function getPackageProviders($app)
    {
        return [
            \mantix\LaravelSocialMediaPublisher\SocialShareServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
}

<?php

namespace Mantix\LaravelSocialMediaPublisher\Tests\Unit;

use mantix\LaravelSocialMediaPublisher\Tests\Unit\TestCase;
use mantix\LaravelSocialMediaPublisher\Services\FacebookService;
use mantix\LaravelSocialMediaPublisher\Exceptions\SocialMediaException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FacebookServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        config([
            'social_media_publisher.facebook_access_token' => 'test_facebook_token',
            'social_media_publisher.facebook_page_id' => 'test_page_id',
            'social_media_publisher.facebook_api_version' => 'v20.0',
        ]);
    }

    public function testGetInstanceThrowsException()
    {
        $this->expectException(SocialMediaException::class);
        $this->expectExceptionMessage('OAuth connection required');
        
        FacebookService::getInstance();
    }

    public function testForConnection()
    {
        $connection = $this->createFacebookConnection();
        
        $service = FacebookService::forConnection($connection);
        
        $this->assertInstanceOf(FacebookService::class, $service);
    }

    public function testForConnectionWithWrongPlatform()
    {
        $connection = $this->createLinkedInConnection();
        
        $this->expectException(SocialMediaException::class);
        $this->expectExceptionMessage('Connection is not for Facebook platform');
        
        FacebookService::forConnection($connection);
    }

    public function testForConnectionWithMissingCredentials()
    {
        $connection = $this->createConnection([
            'platform' => 'facebook',
            'access_token' => null,
            'metadata' => [],
        ]);
        
        $this->expectException(SocialMediaException::class);
        $this->expectExceptionMessage('Facebook connection is missing required credentials');
        
        FacebookService::forConnection($connection);
    }

    public function testShareSuccess()
    {
        Http::fake([
            'https://graph.facebook.com/v20.0/test_page_id/feed' => Http::response(['id' => '123'], 200),
        ]);

        $connection = $this->createFacebookConnection();
        $service = FacebookService::forConnection($connection);
        $result = $service->share('Test post', 'https://example.com');

        $this->assertArrayHasKey('id', $result);
        $this->assertEquals('123', $result['id']);
    }

    public function testShareImageSuccess()
    {
        Http::fake([
            'https://graph.facebook.com/v20.0/test_page_id/photos' => Http::response(['id' => '456'], 200),
        ]);

        $connection = $this->createFacebookConnection();
        $service = FacebookService::forConnection($connection);
        $result = $service->shareImage('Test image', 'https://example.com/image.jpg');

        $this->assertArrayHasKey('id', $result);
        $this->assertEquals('456', $result['id']);
    }

    public function testShareVideoSuccess()
    {
        Http::fake([
            'https://graph.facebook.com/v20.0/test_page_id/videos' => Http::response(['id' => '789'], 200),
        ]);

        $connection = $this->createFacebookConnection();
        $service = FacebookService::forConnection($connection);
        $result = $service->shareVideo('Test video', 'https://example.com/video.mp4');

        $this->assertArrayHasKey('id', $result);
        $this->assertEquals('789', $result['id']);
    }

    public function testGetPageInfoSuccess()
    {
        Http::fake([
            'https://graph.facebook.com/v20.0/test_page_id' => Http::response([
                'id' => 'test_page_id',
                'name' => 'Test Page',
                'category' => 'Business',
                'fan_count' => 1000
            ], 200),
        ]);

        $connection = $this->createFacebookConnection();
        $service = FacebookService::forConnection($connection);
        $result = $service->getPageInfo();

        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('name', $result);
        $this->assertEquals('Test Page', $result['name']);
    }

    public function testGetPageInsightsSuccess()
    {
        Http::fake([
            'https://graph.facebook.com/v20.0/test_page_id/insights' => Http::response([
                'data' => [
                    [
                        'name' => 'page_impressions',
                        'values' => [['value' => 1000]]
                    ]
                ]
            ], 200),
        ]);

        $connection = $this->createFacebookConnection();
        $service = FacebookService::forConnection($connection);
        $result = $service->getPageInsights(['page_impressions']);

        $this->assertArrayHasKey('data', $result);
        $this->assertCount(1, $result['data']);
        $this->assertEquals('page_impressions', $result['data'][0]['name']);
    }

    public function testGetPageInsightsWithDateRange()
    {
        Http::fake([
            'https://graph.facebook.com/v20.0/test_page_id/insights' => Http::response([
                'data' => [
                    [
                        'name' => 'page_impressions',
                        'values' => [['value' => 1000]]
                    ]
                ]
            ], 200),
        ]);

        $connection = $this->createFacebookConnection();
        $service = FacebookService::forConnection($connection);
        $result = $service->getPageInsights(
            ['page_impressions'],
            ['since' => '2024-01-01', 'until' => '2024-01-31']
        );

        $this->assertArrayHasKey('data', $result);
    }

    public function testShareWithEmptyCaption()
    {
        $connection = $this->createFacebookConnection();
        $service = FacebookService::forConnection($connection);
        
        $this->expectException(SocialMediaException::class);
        $this->expectExceptionMessage('Caption cannot be empty');
        
        $service->share('', 'https://example.com');
    }

    public function testShareWithInvalidUrl()
    {
        $connection = $this->createFacebookConnection();
        $service = FacebookService::forConnection($connection);
        
        $this->expectException(SocialMediaException::class);
        $this->expectExceptionMessage('Invalid URL provided');
        
        $service->share('Test post', 'invalid-url');
    }

    public function testShareWithApiError()
    {
        Http::fake([
            'https://graph.facebook.com/v20.0/test_page_id/feed' => Http::response([
                'error' => ['message' => 'Invalid access token']
            ], 400),
        ]);

        $connection = $this->createFacebookConnection();
        $service = FacebookService::forConnection($connection);
        
        $this->expectException(SocialMediaException::class);
        $this->expectExceptionMessage('Failed to share to Facebook');
        
        $service->share('Test post', 'https://example.com');
    }

    public function testShareImageWithApiError()
    {
        Http::fake([
            'https://graph.facebook.com/v20.0/test_page_id/photos' => Http::response([
                'error' => ['message' => 'Invalid image URL']
            ], 400),
        ]);

        $connection = $this->createFacebookConnection();
        $service = FacebookService::forConnection($connection);
        
        $this->expectException(SocialMediaException::class);
        $this->expectExceptionMessage('Failed to share image to Facebook');
        
        $service->shareImage('Test image', 'https://example.com/image.jpg');
    }

    public function testShareVideoWithApiError()
    {
        Http::fake([
            'https://graph.facebook.com/v20.0/test_page_id/videos' => Http::response([
                'error' => ['message' => 'Invalid video URL']
            ], 400),
        ]);

        $connection = $this->createFacebookConnection();
        $service = FacebookService::forConnection($connection);
        
        $this->expectException(SocialMediaException::class);
        $this->expectExceptionMessage('Failed to share video to Facebook');
        
        $service->shareVideo('Test video', 'https://example.com/video.mp4');
    }

    public function testGetPageInfoWithApiError()
    {
        Http::fake([
            'https://graph.facebook.com/v20.0/test_page_id' => Http::response([
                'error' => ['message' => 'Invalid page ID']
            ], 400),
        ]);

        $connection = $this->createFacebookConnection();
        $service = FacebookService::forConnection($connection);
        
        $this->expectException(SocialMediaException::class);
        $this->expectExceptionMessage('Failed to get Facebook page info');
        
        $service->getPageInfo();
    }

    public function testGetPageInsightsWithApiError()
    {
        Http::fake([
            'https://graph.facebook.com/v20.0/test_page_id/insights' => Http::response([
                'error' => ['message' => 'Invalid insights request']
            ], 400),
        ]);

        $connection = $this->createFacebookConnection();
        $service = FacebookService::forConnection($connection);
        
        $this->expectException(SocialMediaException::class);
        $this->expectExceptionMessage('Failed to get Facebook page insights');
        
        $service->getPageInsights(['page_impressions']);
    }

    public function testLoggingOnSuccess()
    {
        Log::shouldReceive('info')
            ->once()
            ->with('Facebook post shared successfully', \Mockery::type('array'));

        Http::fake([
            'https://graph.facebook.com/v20.0/test_page_id/feed' => Http::response(['id' => '123'], 200),
        ]);

        $connection = $this->createFacebookConnection();
        $service = FacebookService::forConnection($connection);
        $service->share('Test post', 'https://example.com');
    }

    public function testLoggingOnError()
    {
        Log::shouldReceive('error')
            ->once()
            ->with('Failed to share to Facebook', \Mockery::type('array'));

        Http::fake([
            'https://graph.facebook.com/v20.0/test_page_id/feed' => Http::response([
                'error' => ['message' => 'API Error']
            ], 400),
        ]);

        $connection = $this->createFacebookConnection();
        $service = FacebookService::forConnection($connection);
        
        $this->expectException(SocialMediaException::class);
        $service->share('Test post', 'https://example.com');
    }

    public function testRetryLogic()
    {
        Http::fake([
            'https://graph.facebook.com/v20.0/test_page_id/feed' => Http::sequence()
                ->push(['error' => ['message' => 'Rate limited']], 429)
                ->push(['error' => ['message' => 'Rate limited']], 429)
                ->push(['id' => '123'], 200),
        ]);

        $connection = $this->createFacebookConnection();
        $service = FacebookService::forConnection($connection);
        $result = $service->share('Test post', 'https://example.com');

        $this->assertArrayHasKey('id', $result);
        $this->assertEquals('123', $result['id']);
    }

    public function testTimeoutConfiguration()
    {
        config(['social_media_publisher.timeout' => 60]);

        Http::fake([
            'https://graph.facebook.com/v20.0/test_page_id/feed' => Http::response(['id' => '123'], 200),
        ]);

        $connection = $this->createFacebookConnection();
        $service = FacebookService::forConnection($connection);
        $service->share('Test post', 'https://example.com');

        Http::assertSent(function ($request) {
            return $request->timeout() === 60;
        });
    }
}

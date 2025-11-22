<?php

namespace Mantix\LaravelSocialMediaPublisher\Tests\Unit;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mantix\LaravelSocialMediaPublisher\Exceptions\SocialMediaException;
use Mantix\LaravelSocialMediaPublisher\Services\XService;
use Mantix\LaravelSocialMediaPublisher\Tests\Unit\TestCase;

class XServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        config([
            'social_media_publisher.x_bearer_token' => 'test_bearer_token',
            'social_media_publisher.x_api_key' => 'test_api_key',
            'social_media_publisher.x_api_secret' => 'test_api_secret',
            'social_media_publisher.x_access_token' => 'test_access_token',
            'social_media_publisher.x_access_token_secret' => 'test_access_token_secret',
        ]);
    }

    public function testXServiceSingleton()
    {
        $service1 = XService::getInstance();
        $service2 = XService::getInstance();
        
        $this->assertSame($service1, $service2);
    }

    public function testXServiceWithMissingCredentials()
    {
        config(['social_media_publisher.x_bearer_token' => null]);
        
        $this->expectException(SocialMediaException::class);
        $this->expectExceptionMessage('X (Twitter) API credentials are not fully configured');
        
        XService::getInstance();
    }

    public function testShareSuccess()
    {
        Http::fake([
            'https://api.twitter.com/2/tweets' => Http::response(['data' => ['id' => '123']], 200),
        ]);

        $service = XService::getInstance();
        $result = $service->share('Test tweet', 'https://example.com');

        $this->assertArrayHasKey('data', $result);
        $this->assertEquals('123', $result['data']['id']);
    }

    public function testShareImageSuccess()
    {
        Http::fake([
            'https://upload.twitter.com/1.1/media/upload.json' => Http::response(['media_id_string' => 'media123'], 200),
            'https://api.twitter.com/2/tweets' => Http::response(['data' => ['id' => '456']], 200),
        ]);

        $service = XService::getInstance();
        $result = $service->shareImage('Test image tweet', 'https://example.com/image.jpg');

        $this->assertArrayHasKey('data', $result);
        $this->assertEquals('456', $result['data']['id']);
    }

    public function testShareVideoSuccess()
    {
        Http::fake([
            'https://upload.twitter.com/1.1/media/upload.json' => Http::response(['media_id_string' => 'media123'], 200),
            'https://api.twitter.com/2/tweets' => Http::response(['data' => ['id' => '789']], 200),
        ]);

        $service = XService::getInstance();
        $result = $service->shareVideo('Test video tweet', 'https://example.com/video.mp4');

        $this->assertArrayHasKey('data', $result);
        $this->assertEquals('789', $result['data']['id']);
    }

    public function testGetTimelineSuccess()
    {
        Http::fake([
            'https://api.twitter.com/2/users/me/tweets' => Http::response([
                'data' => [
                    ['id' => 'tweet1', 'text' => 'Tweet 1'],
                    ['id' => 'tweet2', 'text' => 'Tweet 2']
                ]
            ], 200),
        ]);

        $service = XService::getInstance();
        $result = $service->getTimeline(5);

        $this->assertArrayHasKey('data', $result);
        $this->assertCount(2, $result['data']);
    }

    public function testGetUserInfoSuccess()
    {
        Http::fake([
            'https://api.twitter.com/2/users/me' => Http::response([
                'data' => [
                    'id' => 'user123',
                    'username' => 'testuser',
                    'name' => 'Test User'
                ]
            ], 200),
        ]);

        $service = XService::getInstance();
        $result = $service->getUserInfo();

        $this->assertArrayHasKey('data', $result);
        $this->assertEquals('testuser', $result['data']['username']);
    }

    public function testShareWithEmptyCaption()
    {
        $service = XService::getInstance();
        
        $this->expectException(SocialMediaException::class);
        $this->expectExceptionMessage('Caption cannot be empty');
        
        $service->share('', 'https://example.com');
    }

    public function testShareWithCaptionTooLong()
    {
        $service = XService::getInstance();
        $longCaption = str_repeat('a', 281); // Over 280 character limit
        
        $this->expectException(SocialMediaException::class);
        $this->expectExceptionMessage('Text content exceeds maximum length of 280 characters');
        
        $service->share($longCaption, 'https://example.com');
    }

    public function testShareWithInvalidUrl()
    {
        $service = XService::getInstance();
        
        $this->expectException(SocialMediaException::class);
        $this->expectExceptionMessage('Invalid URL provided');
        
        $service->share('Test tweet', 'invalid-url');
    }

    public function testShareWithApiError()
    {
        Http::fake([
            'https://api.twitter.com/2/tweets' => Http::response([
                'errors' => [['message' => 'Invalid access token']]
            ], 401),
        ]);

        $service = XService::getInstance();
        
        $this->expectException(SocialMediaException::class);
        $this->expectExceptionMessage('Failed to share to X (Twitter)');
        
        $service->share('Test tweet', 'https://example.com');
    }

    public function testShareImageWithApiError()
    {
        Http::fake([
            'https://upload.twitter.com/1.1/media/upload.json' => Http::response([
                'errors' => [['message' => 'Invalid image']]
            ], 400),
        ]);

        $service = XService::getInstance();
        
        $this->expectException(SocialMediaException::class);
        $this->expectExceptionMessage('Failed to share image to X (Twitter)');
        
        $service->shareImage('Test image tweet', 'https://example.com/image.jpg');
    }

    public function testShareVideoWithApiError()
    {
        Http::fake([
            'https://upload.twitter.com/1.1/media/upload.json' => Http::response([
                'errors' => [['message' => 'Invalid video']]
            ], 400),
        ]);

        $service = XService::getInstance();
        
        $this->expectException(SocialMediaException::class);
        $this->expectExceptionMessage('Failed to share video to X (Twitter)');
        
        $service->shareVideo('Test video tweet', 'https://example.com/video.mp4');
    }

    public function testGetTimelineWithApiError()
    {
        Http::fake([
            'https://api.twitter.com/2/users/me/tweets' => Http::response([
                'errors' => [['message' => 'Invalid request']]
            ], 400),
        ]);

        $service = XService::getInstance();
        
        $this->expectException(SocialMediaException::class);
        $this->expectExceptionMessage('Failed to get X (Twitter) timeline');
        
        $service->getTimeline(5);
    }

    public function testGetUserInfoWithApiError()
    {
        Http::fake([
            'https://api.twitter.com/2/users/me' => Http::response([
                'errors' => [['message' => 'Invalid user']]
            ], 400),
        ]);

        $service = XService::getInstance();
        
        $this->expectException(SocialMediaException::class);
        $this->expectExceptionMessage('Failed to get X (Twitter) user info');
        
        $service->getUserInfo();
    }

    public function testLoggingOnSuccess()
    {
        Log::shouldReceive('info')
            ->once()
            ->with('X (Twitter) post shared successfully', \Mockery::type('array'));

        Http::fake([
            'https://api.twitter.com/2/tweets' => Http::response(['data' => ['id' => '123']], 200),
        ]);

        $service = XService::getInstance();
        $service->share('Test tweet', 'https://example.com');
    }

    public function testLoggingOnError()
    {
        Log::shouldReceive('error')
            ->once()
            ->with('Failed to share to X (Twitter)', \Mockery::type('array'));

        Http::fake([
            'https://api.twitter.com/2/tweets' => Http::response([
                'errors' => [['message' => 'API Error']]
            ], 400),
        ]);

        $service = XService::getInstance();
        
        $this->expectException(SocialMediaException::class);
        $service->share('Test tweet', 'https://example.com');
    }

    public function testRetryLogic()
    {
        Http::fake([
            'https://api.twitter.com/2/tweets' => Http::sequence()
                ->push(['errors' => [['message' => 'Rate limited']]], 429)
                ->push(['errors' => [['message' => 'Rate limited']]], 429)
                ->push(['data' => ['id' => '123']], 200),
        ]);

        $service = XService::getInstance();
        $result = $service->share('Test tweet', 'https://example.com');

        $this->assertArrayHasKey('data', $result);
        $this->assertEquals('123', $result['data']['id']);
    }

    public function testMediaUploadWithChunkedVideo()
    {
        Http::fake([
            'https://upload.twitter.com/1.1/media/upload.json' => Http::response(['media_id_string' => 'media123'], 200),
            'https://api.twitter.com/2/tweets' => Http::response(['data' => ['id' => '456']], 200),
        ]);

        $service = XService::getInstance();
        $result = $service->shareVideo('Test video tweet', 'https://example.com/large-video.mp4');

        $this->assertArrayHasKey('data', $result);
        $this->assertEquals('456', $result['data']['id']);
    }

    public function testTimeoutConfiguration()
    {
        config(['social_media_publisher.timeout' => 60]);

        Http::fake([
            'https://api.twitter.com/2/tweets' => Http::response(['data' => ['id' => '123']], 200),
        ]);

        $service = XService::getInstance();
        $service->share('Test tweet', 'https://example.com');

        Http::assertSent(function ($request) {
            return $request->timeout() === 60;
        });
    }
}

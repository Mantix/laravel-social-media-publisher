<?php

namespace Mantix\LaravelSocialMediaPublisher\Tests\Unit;

use Mantix\LaravelSocialMediaPublisher\Services\FacebookService;
use Mantix\LaravelSocialMediaPublisher\Exceptions\SocialMediaException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FacebookOAuthTest extends TestCase
{
    public function testGetAuthorizationUrl()
    {
        config(['social_media_publisher.enable_logging' => false]);
        
        $redirectUri = 'https://example.com/callback';
        $url = FacebookService::getAuthorizationUrl($redirectUri);

        $this->assertStringContainsString('https://www.facebook.com/v20.0/dialog/oauth', $url);
        $this->assertStringContainsString('client_id=test_facebook_client_id', $url);
        $this->assertStringContainsString('redirect_uri=' . urlencode($redirectUri), $url);
        $this->assertStringContainsString('scope=', $url);
    }

    public function testGetAuthorizationUrlWithCustomScopes()
    {
        config(['social_media_publisher.enable_logging' => false]);
        
        $redirectUri = 'https://example.com/callback';
        $scopes = ['pages_manage_posts', 'pages_read_engagement', 'pages_show_list'];
        $url = FacebookService::getAuthorizationUrl($redirectUri, $scopes);

        $this->assertStringContainsString('scope=' . urlencode(implode(',', $scopes)), $url);
    }

    public function testGetAuthorizationUrlWithState()
    {
        config(['social_media_publisher.enable_logging' => false]);
        
        $redirectUri = 'https://example.com/callback';
        $state = 'test_state_123';
        $url = FacebookService::getAuthorizationUrl($redirectUri, [], $state);

        $this->assertStringContainsString('state=' . $state, $url);
    }

    public function testGetAuthorizationUrlThrowsExceptionWhenClientIdMissing()
    {
        config(['social_media_publisher.facebook_client_id' => null]);
        
        $this->expectException(SocialMediaException::class);
        $this->expectExceptionMessage('Facebook Client ID and Client Secret must be configured');
        
        FacebookService::getAuthorizationUrl('https://example.com/callback');
    }

    public function testHandleCallback()
    {
        config(['social_media_publisher.enable_logging' => false]);
        
        Http::fake([
            'https://graph.facebook.com/v20.0/oauth/access_token' => Http::response([
                'access_token' => 'test_access_token',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ], 200),
            'https://graph.facebook.com/v20.0/me/accounts*' => Http::response([
                'data' => [
                    [
                        'id' => 'test_page_id',
                        'name' => 'Test Page',
                        'access_token' => 'test_page_token',
                    ]
                ]
            ], 200),
        ]);

        $code = 'test_auth_code';
        $redirectUri = 'https://example.com/callback';
        
        $result = FacebookService::handleCallback($code, $redirectUri);

        $this->assertArrayHasKey('access_token', $result);
        $this->assertArrayHasKey('pages', $result);
        $this->assertEquals('test_access_token', $result['access_token']);
        $this->assertCount(1, $result['pages']);
        $this->assertEquals('test_page_id', $result['pages'][0]['id']);
    }

    public function testHandleCallbackThrowsExceptionWhenCodeMissing()
    {
        config(['social_media_publisher.enable_logging' => false]);
        
        Http::fake([
            'https://graph.facebook.com/v20.0/oauth/access_token' => Http::response([
                'error' => ['message' => 'Invalid code']
            ], 400),
        ]);

        $this->expectException(SocialMediaException::class);
        
        FacebookService::handleCallback('invalid_code', 'https://example.com/callback');
    }

    public function testDisconnect()
    {
        config(['social_media_publisher.enable_logging' => false]);
        
        Http::fake([
            'https://graph.facebook.com/v20.0/me/permissions*' => Http::response(['success' => true], 200),
        ]);

        $result = FacebookService::disconnect('test_access_token');

        $this->assertTrue($result);
    }

    public function testDisconnectReturnsFalseOnFailure()
    {
        config(['social_media_publisher.enable_logging' => false]);
        
        Http::fake([
            'https://graph.facebook.com/v20.0/me/permissions*' => Http::response(['error' => ['message' => 'Invalid token']], 400),
        ]);

        $result = FacebookService::disconnect('invalid_token');

        $this->assertFalse($result);
    }
}


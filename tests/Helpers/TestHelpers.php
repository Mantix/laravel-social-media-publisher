<?php

namespace mantix\LaravelSocialMediaPublisher\Tests\Helpers;

use Illuminate\Support\Facades\Crypt;
use mantix\LaravelSocialMediaPublisher\Models\SocialMediaConnection;

trait TestHelpers
{
    /**
     * Create a mock user for testing.
     */
    protected function createTestUser($id = 1)
    {
        return new class($id) {
            public $id;
            
            public function __construct($id)
            {
                $this->id = $id;
            }
        };
    }

    /**
     * Create a mock company for testing.
     */
    protected function createTestCompany($id = 1)
    {
        return new class($id) {
            public $id;
            
            public function __construct($id)
            {
                $this->id = $id;
            }
        };
    }

    /**
     * Create a SocialMediaConnection for testing.
     */
    protected function createConnection(array $attributes = []): SocialMediaConnection
    {
        $defaults = [
            'owner_id' => 1,
            'owner_type' => 'App\Models\User',
            'platform' => 'facebook',
            'connection_type' => 'page',
            'platform_user_id' => 'test_page_id',
            'platform_username' => 'Test Page',
            'expires_at' => now()->addDays(30),
            'metadata' => ['page_id' => 'test_page_id'],
            'is_active' => true,
        ];

        $attributes = array_merge($defaults, $attributes);

        $connection = new SocialMediaConnection();
        
        // Set attributes that need encryption via mutators
        if (isset($attributes['access_token'])) {
            $connection->access_token = $attributes['access_token'];
            unset($attributes['access_token']);
        } else {
            $connection->access_token = 'test_access_token';
        }
        
        if (isset($attributes['refresh_token'])) {
            $connection->refresh_token = $attributes['refresh_token'];
            unset($attributes['refresh_token']);
        }
        
        if (isset($attributes['token_secret'])) {
            $connection->token_secret = $attributes['token_secret'];
            unset($attributes['token_secret']);
        }
        
        // Set other attributes
        foreach ($attributes as $key => $value) {
            $connection->$key = $value;
        }
        
        $connection->save();
        
        return $connection;
    }

    /**
     * Create a Facebook connection.
     */
    protected function createFacebookConnection($owner = null, array $attributes = []): SocialMediaConnection
    {
        if ($owner === null) {
            $owner = $this->createTestUser();
        }

        return $this->createConnection(array_merge([
            'owner_id' => $owner->id,
            'owner_type' => get_class($owner),
            'platform' => 'facebook',
            'connection_type' => 'page',
            'platform_user_id' => 'test_page_id',
            'metadata' => ['page_id' => 'test_page_id'],
        ], $attributes));
    }

    /**
     * Create a LinkedIn connection.
     */
    protected function createLinkedInConnection($owner = null, array $attributes = []): SocialMediaConnection
    {
        if ($owner === null) {
            $owner = $this->createTestUser();
        }

        return $this->createConnection(array_merge([
            'owner_id' => $owner->id,
            'owner_type' => get_class($owner),
            'platform' => 'linkedin',
            'connection_type' => 'profile',
            'platform_user_id' => 'test_person_urn',
            'metadata' => ['person_urn' => 'test_person_urn'],
        ], $attributes));
    }

    /**
     * Create a Twitter connection.
     */
    protected function createTwitterConnection($owner = null, array $attributes = []): SocialMediaConnection
    {
        if ($owner === null) {
            $owner = $this->createTestUser();
        }

        $connection = $this->createConnection(array_merge([
            'owner_id' => $owner->id,
            'owner_type' => get_class($owner),
            'platform' => 'twitter',
            'connection_type' => 'profile',
            'platform_user_id' => 'test_user_id',
            'metadata' => [
                'bearer_token' => 'test_bearer_token',
                'api_key' => 'test_api_key',
                'api_secret' => 'test_api_secret',
            ],
        ], $attributes));
        
        // Set token_secret via mutator
        if (!isset($attributes['token_secret'])) {
            $connection->token_secret = 'test_token_secret';
            $connection->save();
        }
        
        return $connection;
    }

    /**
     * Create an Instagram connection.
     */
    protected function createInstagramConnection($owner = null, array $attributes = []): SocialMediaConnection
    {
        if ($owner === null) {
            $owner = $this->createTestUser();
        }

        return $this->createConnection(array_merge([
            'owner_id' => $owner->id,
            'owner_type' => get_class($owner),
            'platform' => 'instagram',
            'connection_type' => 'profile',
            'platform_user_id' => 'test_instagram_account_id',
            'metadata' => [
                'instagram_account_id' => 'test_instagram_account_id',
                'facebook_page_id' => 'test_facebook_page_id',
            ],
        ], $attributes));
    }

    /**
     * Create a TikTok connection.
     */
    protected function createTikTokConnection($owner = null, array $attributes = []): SocialMediaConnection
    {
        if ($owner === null) {
            $owner = $this->createTestUser();
        }

        return $this->createConnection(array_merge([
            'owner_id' => $owner->id,
            'owner_type' => get_class($owner),
            'platform' => 'tiktok',
            'connection_type' => 'profile',
            'platform_user_id' => 'test_open_id',
            'metadata' => [
                'client_key' => 'test_client_key',
                'client_secret' => 'test_client_secret',
            ],
        ], $attributes));
    }

    /**
     * Create a YouTube connection.
     */
    protected function createYouTubeConnection($owner = null, array $attributes = []): SocialMediaConnection
    {
        if ($owner === null) {
            $owner = $this->createTestUser();
        }

        return $this->createConnection(array_merge([
            'owner_id' => $owner->id,
            'owner_type' => get_class($owner),
            'platform' => 'youtube',
            'connection_type' => 'channel',
            'platform_user_id' => 'test_channel_id',
            'metadata' => [
                'api_key' => 'test_api_key',
                'channel_id' => 'test_channel_id',
            ],
        ], $attributes));
    }

    /**
     * Create a Pinterest connection.
     */
    protected function createPinterestConnection($owner = null, array $attributes = []): SocialMediaConnection
    {
        if ($owner === null) {
            $owner = $this->createTestUser();
        }

        return $this->createConnection(array_merge([
            'owner_id' => $owner->id,
            'owner_type' => get_class($owner),
            'platform' => 'pinterest',
            'connection_type' => 'profile',
            'platform_user_id' => 'test_user_id',
            'metadata' => [
                'board_id' => 'test_board_id',
            ],
        ], $attributes));
    }
}


<?php

namespace mantix\LaravelSocialMediaPublisher\Tests\Unit;

use mantix\LaravelSocialMediaPublisher\Models\SocialMediaConnection;
use Illuminate\Support\Facades\Crypt;

class SocialMediaConnectionTest extends TestCase
{
    public function testConnectionCreation()
    {
        $connection = $this->createFacebookConnection();

        $this->assertInstanceOf(SocialMediaConnection::class, $connection);
        $this->assertEquals('facebook', $connection->platform);
        $this->assertEquals('page', $connection->connection_type);
        $this->assertTrue($connection->is_active);
    }

    public function testTokenEncryption()
    {
        $connection = $this->createConnection([
            'access_token' => 'test_token',
        ]);

        // Access token should be encrypted in database
        $this->assertNotEquals('test_token', $connection->getAttributes()['access_token']);
        
        // But decrypted when accessed via getter
        $this->assertEquals('test_token', $connection->getDecryptedAccessToken());
    }

    public function testRefreshTokenEncryption()
    {
        $connection = $this->createConnection([
            'refresh_token' => 'test_refresh_token',
        ]);

        $this->assertNotEquals('test_refresh_token', $connection->getAttributes()['refresh_token']);
        $this->assertEquals('test_refresh_token', $connection->getDecryptedRefreshToken());
    }

    public function testTokenSecretEncryption()
    {
        $connection = $this->createTwitterConnection();

        $this->assertNotEquals('test_token_secret', $connection->getAttributes()['token_secret']);
        $this->assertEquals('test_token_secret', $connection->getDecryptedTokenSecret());
    }

    public function testIsExpired()
    {
        $expiredConnection = $this->createConnection([
            'expires_at' => now()->subDay(),
        ]);

        $this->assertTrue($expiredConnection->isExpired());

        $activeConnection = $this->createConnection([
            'expires_at' => now()->addDay(),
        ]);

        $this->assertFalse($activeConnection->isExpired());
    }

    public function testIsExpiredWithNoExpiration()
    {
        $connection = $this->createConnection([
            'expires_at' => null,
        ]);

        $this->assertFalse($connection->isExpired());
    }

    public function testActiveScope()
    {
        $this->createConnection(['is_active' => true]);
        $this->createConnection(['is_active' => false]);
        $this->createConnection(['is_active' => true]);

        $activeConnections = SocialMediaConnection::active()->get();

        $this->assertCount(2, $activeConnections);
        $this->assertTrue($activeConnections->every(fn($c) => $c->is_active));
    }

    public function testForPlatformScope()
    {
        $this->createFacebookConnection();
        $this->createLinkedInConnection();
        $this->createFacebookConnection();

        $facebookConnections = SocialMediaConnection::forPlatform('facebook')->get();

        $this->assertCount(2, $facebookConnections);
        $this->assertTrue($facebookConnections->every(fn($c) => $c->platform === 'facebook'));
    }

    public function testForOwnerScopeWithModel()
    {
        $user1 = $this->createTestUser(1);
        $user2 = $this->createTestUser(2);

        $this->createFacebookConnection($user1);
        $this->createFacebookConnection($user1);
        $this->createFacebookConnection($user2);

        $user1Connections = SocialMediaConnection::forOwner($user1)->get();

        $this->assertCount(2, $user1Connections);
        $this->assertTrue($user1Connections->every(fn($c) => $c->owner_id === 1));
    }

    public function testForOwnerScopeWithClassName()
    {
        $user1 = $this->createTestUser(1);
        $user2 = $this->createTestUser(2);

        $this->createFacebookConnection($user1);
        $this->createFacebookConnection($user2);

        $connections = SocialMediaConnection::forOwner('App\Models\User', 1)->get();

        $this->assertCount(1, $connections);
        $this->assertEquals(1, $connections->first()->owner_id);
    }

    public function testPolymorphicOwnerRelationship()
    {
        $user = $this->createTestUser(1);
        $company = $this->createTestCompany(1);

        $userConnection = $this->createFacebookConnection($user);
        $companyConnection = $this->createFacebookConnection($company);

        $this->assertEquals('App\Models\User', $userConnection->owner_type);
        $this->assertEquals(1, $userConnection->owner_id);

        $this->assertEquals('App\Models\Company', $companyConnection->owner_type);
        $this->assertEquals(1, $companyConnection->owner_id);
    }

    public function testMetadataJsonCast()
    {
        $metadata = ['page_id' => '123', 'extra' => 'data'];
        $connection = $this->createConnection(['metadata' => $metadata]);

        $this->assertIsArray($connection->metadata);
        $this->assertEquals('123', $connection->metadata['page_id']);
        $this->assertEquals('data', $connection->metadata['extra']);
    }

    public function testExpiresAtDateTimeCast()
    {
        $expiresAt = now()->addDays(30);
        $connection = $this->createConnection(['expires_at' => $expiresAt]);

        $this->assertInstanceOf(\Carbon\Carbon::class, $connection->expires_at);
        $this->assertEquals($expiresAt->format('Y-m-d H:i:s'), $connection->expires_at->format('Y-m-d H:i:s'));
    }

    public function testIsActiveBooleanCast()
    {
        $connection = $this->createConnection(['is_active' => 1]);
        $this->assertTrue($connection->is_active);

        $connection = $this->createConnection(['is_active' => 0]);
        $this->assertFalse($connection->is_active);
    }
}


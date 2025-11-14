<?php

namespace Mantix\LaravelSocialMediaPublisher\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Crypt;

class SocialMediaConnection extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'social_media_connections';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'owner_id',
        'owner_type',
        'platform',
        'connection_type',
        'platform_user_id',
        'platform_username',
        'access_token',
        'refresh_token',
        'token_secret',
        'expires_at',
        'metadata',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'metadata' => 'array',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * Get the owner of the connection (polymorphic relationship).
     * Can be User, Company, or any other model.
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the decrypted access token.
     *
     * @return string|null
     */
    public function getDecryptedAccessToken(): ?string
    {
        return $this->access_token ? Crypt::decryptString($this->access_token) : null;
    }

    /**
     * Get the decrypted refresh token.
     *
     * @return string|null
     */
    public function getDecryptedRefreshToken(): ?string
    {
        return $this->refresh_token ? Crypt::decryptString($this->refresh_token) : null;
    }

    /**
     * Get the decrypted token secret.
     *
     * @return string|null
     */
    public function getDecryptedTokenSecret(): ?string
    {
        return $this->token_secret ? Crypt::decryptString($this->token_secret) : null;
    }

    /**
     * Set the encrypted access token.
     *
     * @param string|null $value
     * @return void
     */
    public function setAccessTokenAttribute(?string $value): void
    {
        $this->attributes['access_token'] = $value ? Crypt::encryptString($value) : null;
    }

    /**
     * Set the encrypted refresh token.
     *
     * @param string|null $value
     * @return void
     */
    public function setRefreshTokenAttribute(?string $value): void
    {
        $this->attributes['refresh_token'] = $value ? Crypt::encryptString($value) : null;
    }

    /**
     * Set the encrypted token secret.
     *
     * @param string|null $value
     * @return void
     */
    public function setTokenSecretAttribute(?string $value): void
    {
        $this->attributes['token_secret'] = $value ? Crypt::encryptString($value) : null;
    }

    /**
     * Check if the token is expired.
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        if (!$this->expires_at) {
            return false; // No expiration set
        }

        return $this->expires_at->isPast();
    }

    /**
     * Scope to get active connections.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get connections for a specific platform.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $platform
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForPlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }

    /**
     * Scope to get connections for a specific owner (polymorphic).
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param mixed $owner Model instance or class name
     * @param int|null $ownerId Optional owner ID if passing class name
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForOwner($query, $owner, ?int $ownerId = null)
    {
        if (is_object($owner)) {
            return $query->where('owner_type', get_class($owner))
                ->where('owner_id', $owner->id);
        }

        return $query->where('owner_type', $owner)
            ->where('owner_id', $ownerId);
    }
}


<?php

namespace Mantix\LaravelSocialMediaPublisher\Traits;

use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Mantix\LaravelSocialMediaPublisher\Models\SocialMediaConnection;

trait HasSocialMediaConnections
{
    /**
     * Get all social media connections for this model.
     *
     * @return MorphMany
     */
    public function social_media_connections(): MorphMany
    {
        return $this->morphMany(SocialMediaConnection::class, 'owner');
    }

    /**
     * Get the latest LinkedIn connection for this model.
     *
     * @return HasOne
     */
    public function social_connection_linkedin(): HasOne
    {
        return $this->hasOne(SocialMediaConnection::class, 'owner_id')
            ->where('owner_type', self::class)
            ->where('platform', 'linkedin')
            ->latestOfMany('created_at');
    }

    /**
     * Get the latest Instagram connection for this model.
     *
     * @return HasOne
     */
    public function social_connection_instagram(): HasOne
    {
        return $this->hasOne(SocialMediaConnection::class, 'owner_id')
            ->where('owner_type', self::class)
            ->where('platform', 'instagram')
            ->latestOfMany('created_at');
    }

    /**
     * Get the latest Facebook connection for this model.
     *
     * @return HasOne
     */
    public function social_connection_facebook(): HasOne
    {
        return $this->hasOne(SocialMediaConnection::class, 'owner_id')
            ->where('owner_type', self::class)
            ->where('platform', 'facebook')
            ->latestOfMany('created_at');
    }

    /**
     * Get the latest X (Twitter) connection for this model.
     *
     * @return HasOne
     */
    public function social_connection_x(): HasOne
    {
        return $this->hasOne(SocialMediaConnection::class, 'owner_id')
            ->where('owner_type', self::class)
            ->where('platform', 'x')
            ->latestOfMany('created_at');
    }

    /**
     * Get active social connection for a specific platform.
     *
     * @param string $platform
     * @return SocialMediaConnection|null
     */
    public function getSocialConnection(string $platform): ?SocialMediaConnection
    {
        return $this->social_media_connections()
            ->forPlatform($platform)
            ->active()
            ->first();
    }

    /**
     * Check if model has active connection for platform.
     *
     * @param string $platform
     * @return bool
     */
    public function hasSocialConnection(string $platform): bool
    {
        return $this->getSocialConnection($platform) !== null;
    }
}


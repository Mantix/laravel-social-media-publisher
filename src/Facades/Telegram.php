<?php

namespace Mantix\LaravelSocialMediaPublisher\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Class Telegram
 *
 * Facade for the TelegramService.
 *
 * @method static mixed verifyBotToken(string $token)
 * @method static mixed getUpdates(string $token)
 * @method static bool disconnect(string $accessToken)
 * @method static \Mantix\LaravelSocialMediaPublisher\Services\TelegramService forConnection(\Mantix\LaravelSocialMediaPublisher\Models\SocialMediaConnection $connection)
 *
 * @see \Mantix\LaravelSocialMediaPublisher\Services\TelegramService
 */
class Telegram extends Facade {
    protected static function getFacadeAccessor() {
        return \Mantix\LaravelSocialMediaPublisher\Services\TelegramService::class;
    }
}

<?php

namespace Mantix\LaravelSocialMediaPublisher\Tests\Feature;

use Mantix\LaravelSocialMediaPublisher\Facades\Telegram;
use Illuminate\Support\Facades\Http;

class TelegramTest extends TestCase {

    public function testShareFacade() {
        Http::fake([
            'https://api.telegram.org/bot*' => Http::response(['ok' => true], 200),
        ]);

        $response = Telegram::share('Test Message', 'http://example.com');

        $this->assertArrayHasKey('ok', $response);
        $this->assertTrue($response['ok']);
    }

}
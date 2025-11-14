<?php

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;
use mantix\LaravelSocialMediaPublisher\Http\Controllers\OAuthController;

/*
|--------------------------------------------------------------------------
| OAuth Callback Routes
|--------------------------------------------------------------------------
|
| These routes handle OAuth callbacks from social media platforms.
| They are automatically excluded from CSRF protection since they are
| called by external services (Facebook, LinkedIn, etc.).
|
*/

Route::withoutMiddleware([VerifyCsrfToken::class])
    ->prefix('auth')
    ->name('social-media.')
    ->group(function () {
        // Facebook OAuth Callback
        Route::get('/facebook/callback', [OAuthController::class, 'handleFacebookCallback'])
            ->name('facebook.callback');

        // LinkedIn OAuth Callback
        Route::get('/linkedin/callback', [OAuthController::class, 'handleLinkedInCallback'])
            ->name('linkedin.callback');

        // Twitter/X OAuth Callback
        Route::get('/x/callback', [OAuthController::class, 'handleXCallback'])
            ->name('x.callback');

        // Instagram OAuth Callback
        Route::get('/instagram/callback', [OAuthController::class, 'handleInstagramCallback'])
            ->name('instagram.callback');

        // TikTok OAuth Callback
        Route::get('/tiktok/callback', [OAuthController::class, 'handleTikTokCallback'])
            ->name('tiktok.callback');

        // YouTube OAuth Callback
        Route::get('/youtube/callback', [OAuthController::class, 'handleYouTubeCallback'])
            ->name('youtube.callback');

        // Pinterest OAuth Callback
        Route::get('/pinterest/callback', [OAuthController::class, 'handlePinterestCallback'])
            ->name('pinterest.callback');
    });


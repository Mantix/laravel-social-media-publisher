<?php

return [
    // Facebook Configuration (OAuth 2.0)
    'facebook_client_id'     => env('FACEBOOK_CLIENT_ID'),
    'facebook_client_secret'  => env('FACEBOOK_CLIENT_SECRET'),
    'facebook_api_version'   => env('FACEBOOK_API_VERSION', 'v20.0'),

    // Twitter/X Configuration (OAuth 2.0)
    'x_client_id'        => env('X_CLIENT_ID'),
    'x_client_secret'    => env('X_CLIENT_SECRET'),
    'x_api_key'          => env('X_API_KEY'),
    'x_api_secret_key'   => env('X_API_SECRET_KEY'),

    // LinkedIn Configuration (OAuth 2.0)
    'linkedin_client_id'     => env('LINKEDIN_CLIENT_ID'),
    'linkedin_client_secret' => env('LINKEDIN_CLIENT_SECRET'),

    // Instagram Configuration (OAuth 2.0)
    'instagram_client_id'     => env('INSTAGRAM_CLIENT_ID'),
    'instagram_client_secret' => env('INSTAGRAM_CLIENT_SECRET'),

    // TikTok Configuration (OAuth 2.0)
    'tiktok_client_id'     => env('TIKTOK_CLIENT_ID'),
    'tiktok_client_secret' => env('TIKTOK_CLIENT_SECRET'),

    // YouTube Configuration (OAuth 2.0)
    'youtube_client_id'     => env('YOUTUBE_CLIENT_ID'),
    'youtube_client_secret' => env('YOUTUBE_CLIENT_SECRET'),

    // Pinterest Configuration (OAuth 2.0)
    'pinterest_client_id'     => env('PINTEREST_CLIENT_ID'),
    'pinterest_client_secret' => env('PINTEREST_CLIENT_SECRET'),

    // Telegram Configuration (Bot API - No OAuth)
    'telegram_bot_token'    => env('TELEGRAM_BOT_TOKEN'),
    'telegram_chat_id'      => env('TELEGRAM_CHAT_ID'),
    'telegram_api_base_url' => env('TELEGRAM_API_BASE_URL', 'https://api.telegram.org/bot'),

    // General Configuration
    'enable_logging'    => env('SOCIAL_MEDIA_LOGGING', true),
    'timeout'           => env('SOCIAL_MEDIA_TIMEOUT', 30),
    'retry_attempts'    => env('SOCIAL_MEDIA_RETRY_ATTEMPTS', 3),
    
    // OAuth Configuration
    'oauth_redirect_route' => env('SOCIAL_MEDIA_OAUTH_REDIRECT_ROUTE', 'dashboard'),
];




# Laravel Social Media Publisher

[![Latest Version on Packagist](https://img.shields.io/packagist/v/mantix/laravel-social-media-publisher.svg?style=flat-square)](https://packagist.org/packages/mantix/laravel-social-media-publisher)
[![Total Downloads](https://img.shields.io/packagist/dt/mantix/laravel-social-media-publisher.svg?style=flat-square)](https://packagist.org/packages/mantix/laravel-social-media-publisher)
[![License](https://img.shields.io/packagist/l/mantix/laravel-social-media-publisher.svg?style=flat-square)](https://packagist.org/packages/mantix/laravel-social-media-publisher)
[![PHP Version](https://img.shields.io/packagist/php-v/mantix/laravel-social-media-publisher.svg?style=flat-square)](https://packagist.org/packages/mantix/laravel-social-media-publisher)

A comprehensive Laravel package for automatic social media publishing across **8 major platforms**: Facebook, Twitter/X, LinkedIn, Instagram, TikTok, YouTube, Pinterest, and Telegram. Post to one platform or all platforms simultaneously with a unified API.

## 🌟 Features

- **8 Social Media Platforms**: Facebook, Twitter/X, LinkedIn, Instagram, TikTok, YouTube, Pinterest, Telegram
- **Multi-User & Multi-Entity Support**: Users, Companies, or any model can connect their own social media accounts and post on their behalf (polymorphic relationships)
- **OAuth Integration**: Built-in OAuth flows for Facebook, LinkedIn, and Twitter
- **Unified API**: Post to multiple platforms with a single command
- **Individual Platform Access**: Direct access to each platform's specific features
- **Comprehensive Content Types**: Text, Images, Videos, Documents, Stories, Carousels
- **Advanced Analytics**: Facebook Page Insights, Twitter Analytics, LinkedIn Metrics
- **Production Ready**: Error handling, retry logic, rate limiting, logging
- **Laravel Native**: Perfect integration with Laravel ecosystem
- **Extensible**: Easy to add new platforms and features

## 📋 Table of Contents

- [Installation](#installation)
- [Configuration](#configuration)
- [Quick Start](#quick-start)
- [Usage](#usage)
  - [Unified API](#unified-api)
  - [Individual Platforms](#individual-platforms)
  - [Platform-Specific Features](#platform-specific-features)
- [Advanced Features](#advanced-features)
- [Error Handling](#error-handling)
- [Testing](#testing)
- [Examples](#examples)
- [API Reference](#api-reference)
- [Contributing](#contributing)
- [License](#license)

## 🚀 Installation

### Requirements

- PHP 8.1 or higher
- Laravel 11.0 or higher
- Composer

### Install via Composer

```bash
composer require mantix/laravel-social-media-publisher
```

### Publish Configuration and Migrations

```bash
# Publish configuration
php artisan vendor:publish --provider="mantix\LaravelSocialMediaPublisher\SocialShareServiceProvider" --tag=social-media-publisher-config

# Publish migrations
php artisan vendor:publish --provider="mantix\LaravelSocialMediaPublisher\SocialShareServiceProvider" --tag=social-media-publisher-migrations

# Run migrations
php artisan migrate
```

## ⚙️ Configuration

### Environment Variables

Add the following OAuth credentials to your `.env` file. These are required for users to authenticate their own social media accounts:

```env
# Facebook (OAuth 2.0)
FACEBOOK_CLIENT_ID=your_facebook_client_id
FACEBOOK_CLIENT_SECRET=your_facebook_client_secret

# Twitter/X (OAuth 2.0)
X_CLIENT_ID=your_x_client_id
X_CLIENT_SECRET=your_x_client_secret
X_API_KEY=your_x_api_key
X_API_SECRET_KEY=your_x_api_secret_key

# LinkedIn (OAuth 2.0)
LINKEDIN_CLIENT_ID=your_linkedin_client_id
LINKEDIN_CLIENT_SECRET=your_linkedin_client_secret

# Instagram (OAuth 2.0)
INSTAGRAM_CLIENT_ID=your_instagram_client_id
INSTAGRAM_CLIENT_SECRET=your_instagram_client_secret

# TikTok (OAuth 2.0)
TIKTOK_CLIENT_ID=your_tiktok_client_id
TIKTOK_CLIENT_SECRET=your_tiktok_client_secret

# YouTube (OAuth 2.0)
YOUTUBE_CLIENT_ID=your_youtube_client_id
YOUTUBE_CLIENT_SECRET=your_youtube_client_secret

# Pinterest (OAuth 2.0)
PINTEREST_CLIENT_ID=your_pinterest_client_id
PINTEREST_CLIENT_SECRET=your_pinterest_client_secret

# Telegram (Bot API - No OAuth required)
TELEGRAM_BOT_TOKEN=your_telegram_bot_token
TELEGRAM_CHAT_ID=your_telegram_chat_id
```

### OAuth Routes Setup

**Important**: OAuth callback routes are automatically registered by the package and excluded from CSRF protection. You only need to configure the callback URLs in each platform's developer portal.

#### OAuth Authorization Routes

You need to create authorization routes that redirect users to the OAuth provider. Add these to your `routes/web.php`:

```php
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Instagram;

// OAuth Authorization Routes
Route::get('/auth/facebook', function () {
    $redirectUri = route('social-media.facebook.callback');
    return redirect(FacebookService::getAuthorizationUrl($redirectUri));
})->name('social-media.facebook.authorize')->middleware('auth');

Route::get('/auth/linkedin', function () {
    $redirectUri = route('social-media.linkedin.callback');
    return redirect(LinkedInService::getAuthorizationUrl($redirectUri));
})->name('social-media.linkedin.authorize')->middleware('auth');

// Add similar routes for other platforms...
```

**Note**: The callback routes (`/auth/facebook/callback`, `/auth/linkedin/callback`, etc.) are automatically registered by the package and excluded from CSRF protection. You don't need to define them manually.

#### Customizing OAuth Callbacks

The package includes a default `OAuthController` that handles callbacks. To customize the behavior, you can:

1. **Publish the controller** (if needed in future versions):
   ```bash
   php artisan vendor:publish --tag=social-media-publisher-controller
   ```

2. **Configure redirect route** after OAuth success/error:
   ```env
   SOCIAL_MEDIA_OAUTH_REDIRECT_ROUTE=dashboard
   ```

3. **Extend the controller** in your application if you need custom logic.

#### Default OAuth Controller Behavior

The default controller:
- Automatically saves connections to the `social_media_connections` table
- Requires authenticated users (uses `auth()->user()`)
- Redirects to the configured route (default: `dashboard`) with success/error messages
- Handles errors gracefully with logging

### OAuth Callback URLs Configuration

**Important**: You must configure these callback URLs in each platform's developer portal. The callback URLs must match exactly your route URLs (e.g., `https://yourdomain.com/auth/facebook/callback`).

#### Facebook
1. Go to [Facebook Developers](https://developers.facebook.com/)
2. Select your app → Settings → Basic
3. Add your callback URL to "Valid OAuth Redirect URIs"
4. Example: `https://yourdomain.com/auth/facebook/callback`

#### Twitter/X
1. Go to [Twitter Developer Portal](https://developer.twitter.com/)
2. Select your app → Settings → User authentication settings
3. Add your callback URL to "Callback URI / Redirect URL"
4. Example: `https://yourdomain.com/auth/x/callback`

#### LinkedIn
1. Go to [LinkedIn Developers](https://www.linkedin.com/developers/)
2. Select your app → Auth tab
3. Add your callback URL to "Authorized redirect URLs for your app"
4. Example: `https://yourdomain.com/auth/linkedin/callback`

#### Instagram
1. Go to [Facebook Developers](https://developers.facebook.com/) (Instagram uses Facebook's platform)
2. Select your app → Products → Instagram → Basic Display
3. Add your callback URL to "Valid OAuth Redirect URIs"
4. Example: `https://yourdomain.com/auth/instagram/callback`

#### TikTok
1. Go to [TikTok Developers](https://developers.tiktok.com/)
2. Select your app → Basic Information
3. Add your callback URL to "Redirect URI"
4. Example: `https://yourdomain.com/auth/tiktok/callback`

#### YouTube
1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Select your project → APIs & Services → Credentials
3. Edit your OAuth 2.0 Client ID
4. Add your callback URL to "Authorized redirect URIs"
5. Example: `https://yourdomain.com/auth/youtube/callback`

#### Pinterest
1. Go to [Pinterest Developers](https://developers.pinterest.com/)
2. Select your app → Settings
3. Add your callback URL to "Redirect URIs"
4. Example: `https://yourdomain.com/auth/pinterest/callback`


### Configuration File

The published `config/social-media-publisher.php` file contains all configuration options:

```php
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
    
    // ... other platform configurations
    
    // General settings
    'enable_logging' => env('SOCIAL_MEDIA_LOGGING', true),
    'timeout' => env('SOCIAL_MEDIA_TIMEOUT', 30),
    'retry_attempts' => env('SOCIAL_MEDIA_RETRY_ATTEMPTS', 3),
    
    // OAuth settings
    'oauth_redirect_route' => env('SOCIAL_MEDIA_OAUTH_REDIRECT_ROUTE', 'dashboard'),
];
```

## 🎯 Quick Start

### Basic Usage

**Note**: All posting requires OAuth 2.0 connections. Users must authenticate their social media accounts through OAuth before posting. This is the only supported authentication method (except Telegram which uses Bot API).

```php
use mantix\LaravelSocialMediaPublisher\Exceptions\SocialMediaException;

// Post to multiple platforms (requires OAuth connections)
$user = User::find(1);

// Text-only posts
$result = SocialMedia::shareText($user, ['facebook', 'twitter'], 'Hello World!');

// Posts with URL
$result = SocialMedia::shareUrl($user, ['facebook', 'twitter', 'linkedin'], 'Hello World!', 'https://example.com');

// Share images
$result = SocialMedia::shareImage($user, ['instagram', 'pinterest'], 'Check this out!', 'https://example.com/image.jpg');

// Share videos
$result = SocialMedia::shareVideo($user, ['youtube', 'tiktok'], 'Watch this!', 'https://example.com/video.mp4');
```

### Multi-User & Multi-Entity Support

The package supports polymorphic relationships, allowing any model (User, Company, etc.) to have social media connections:

```php
use mantix\LaravelSocialMediaPublisher\Facades\SocialMedia;

// Post on behalf of a User
$user = User::find(1);
$result = SocialMedia::shareUrl($user, ['facebook', 'twitter'], 'Hello World!', 'https://example.com');

// Post on behalf of a Company
$company = Company::find(1);
$result = SocialMedia::shareUrl($company, ['facebook', 'linkedin'], 'Company Update!', 'https://example.com');

// Get owner-specific platform service
$facebookService = SocialMedia::platform('facebook', $user);
$facebookService->shareUrl('Hello', 'https://example.com');

// Or using class name and ID
$result = SocialMedia::shareUrl(Company::class, ['facebook'], 'Update', 'https://example.com', $companyId);
```

**Note**: All posting requires OAuth 2.0 connections. Users must authenticate their social media accounts through OAuth before posting. OAuth is the only supported authentication method (except Telegram which uses Bot API).

### Individual Platform Access

**Note**: All platform services require OAuth connections. Use `SocialMedia::platform()` with an owner to get a service instance.

```php
use mantix\LaravelSocialMediaPublisher\Facades\SocialMedia;

// Get platform service for a user with OAuth connection
$user = User::find(1);

// Facebook
$facebookService = SocialMedia::platform('facebook', $user);
$facebookService->shareText('Hello Facebook!');
$facebookService->shareUrl('Hello Facebook!', 'https://example.com');
$facebookService->shareImage('Check this image!', 'https://example.com/image.jpg');

// Twitter
$twitterService = SocialMedia::platform('twitter', $user);
$twitterService->shareText('Hello Twitter!');
$twitterService->shareUrl('Hello Twitter!', 'https://example.com');

// LinkedIn
$linkedinService = SocialMedia::platform('linkedin', $user);
$linkedinService->shareText('Hello LinkedIn!');
$linkedinService->shareUrl('Hello LinkedIn!', 'https://example.com');
$linkedinService->shareToCompanyPage('Company update!', 'https://example.com');
```

## 📖 Usage

### Multi-User OAuth Flow

#### 1. Get Authorization URL

```php
use mantix\LaravelSocialMediaPublisher\Services\FacebookService;
use mantix\LaravelSocialMediaPublisher\Services\FacebookService;

// Facebook OAuth
$redirectUri = route('social-media.facebook.callback');
$authUrl = FacebookService::getAuthorizationUrl($redirectUri);
return redirect($authUrl);

// LinkedIn OAuth
$redirectUri = route('social-media.linkedin.callback');
$authUrl = LinkedInService::getAuthorizationUrl($redirectUri);
return redirect($authUrl);
```

#### 2. OAuth Callbacks (Automatic)

**The package automatically handles OAuth callbacks!** When users authorize your app, they'll be redirected back to your application, and the connection will be automatically saved to the `social_media_connections` table.

The default `OAuthController` handles:
- Token exchange
- Connection saving
- Error handling
- Redirects with success/error messages

**No additional code needed** - just configure the callback URLs in each platform's developer portal (see below).

#### 3. Disconnect from Platform

```php
use mantix\LaravelSocialMediaPublisher\Services\FacebookService;
use mantix\LaravelSocialMediaPublisher\Services\FacebookService;

// Disconnect for a user
$user = User::find($userId);
$connection = SocialMediaConnection::forOwner($user)
    ->where('platform', 'facebook')
    ->first();

// Or disconnect for a company
$company = Company::find($companyId);
$connection = SocialMediaConnection::forOwner($company)
    ->where('platform', 'facebook')
    ->first();

if ($connection) {
    // Revoke access token
    FacebookService::disconnect($connection->getDecryptedAccessToken());
    
    // Delete connection
    $connection->delete();
}
```

### Unified API

The `SocialMedia` facade provides a unified interface for publishing to multiple platforms:

#### Share to Multiple Platforms

**Note**: All methods require an owner with OAuth connections. The owner is always the first parameter.

```php
use mantix\LaravelSocialMediaPublisher\Facades\SocialMedia;

// Post to specific platforms (requires owner with OAuth connections)
$user = User::find(1);

// Text-only posts
$result = SocialMedia::shareText($user, ['facebook', 'twitter'], 'Hello World!');

// Posts with URL
$result = SocialMedia::shareUrl($user, ['facebook', 'twitter', 'linkedin'], 'Content', 'https://example.com');

// Share images to visual platforms
$result = SocialMedia::shareImage($user, ['instagram', 'pinterest'], 'Caption', 'https://example.com/image.jpg');

// Share videos to video platforms
$result = SocialMedia::shareVideo($user, ['youtube', 'tiktok'], 'Caption', 'https://example.com/video.mp4');
```

#### Share to All Platforms

```php
// Post to all available platforms (requires owner with OAuth connections)
$user = User::find(1);
$allPlatforms = SocialMedia::getAvailablePlatforms();

// Text-only posts to all platforms
$result = SocialMedia::shareText($user, $allPlatforms, 'Hello World!');

// Posts with URL to all platforms
$result = SocialMedia::shareUrl($user, $allPlatforms, 'Content', 'https://example.com');

// Share images to all platforms
$result = SocialMedia::shareImage($user, $allPlatforms, 'Caption', 'https://example.com/image.jpg');

// Share videos to all platforms
$result = SocialMedia::shareVideo($user, $allPlatforms, 'Caption', 'https://example.com/video.mp4');
```

#### Platform-Specific Access

```php
// Access individual platforms (requires owner with OAuth connection)
$user = User::find(1);
$facebookService = SocialMedia::platform('facebook', $user);
$twitterService = SocialMedia::platform('twitter', $user);
$linkedinService = SocialMedia::platform('linkedin', $user);

// Use platform-specific methods
$result = SocialMedia::platform('linkedin', $user)->shareToCompanyPage('Content', 'https://example.com');
$result = SocialMedia::platform('instagram', $user)->shareCarousel('Caption', ['img1.jpg', 'img2.jpg']);
$result = SocialMedia::platform('facebook', $user)->shareText('Text-only post!');
```

### Individual Platforms

**Note**: All platform services require OAuth connections. Use `SocialMedia::platform($platform, $owner)` to get a service instance for a specific owner.

Each platform service provides specific methods:

#### Facebook

```php
use mantix\LaravelSocialMediaPublisher\Facades\SocialMedia;

// Get service for a user (requires OAuth connection)
$user = User::find(1);
$facebook = SocialMedia::platform('facebook', $user);

// Basic publishing
$facebook->shareText('Text-only post!');
$facebook->shareUrl('Content', 'https://example.com');
$facebook->shareImage('Caption', 'https://example.com/image.jpg');
$facebook->shareVideo('Caption', 'https://example.com/video.mp4');

// Analytics
$insights = $facebook->getPageInsights(['page_impressions', 'page_engaged_users']);
$pageInfo = $facebook->getPageInfo();
```

#### Twitter/X

```php
use mantix\LaravelSocialMediaPublisher\Facades\SocialMedia;

// Get service for a user (requires OAuth connection)
$user = User::find(1);
$twitter = SocialMedia::platform('twitter', $user);

// Publishing
$twitter->shareText('Text-only tweet!');
$twitter->shareUrl('Content', 'https://example.com');
$twitter->shareImage('Caption', 'https://example.com/image.jpg');
$twitter->shareVideo('Caption', 'https://example.com/video.mp4');

// Analytics
$timeline = $twitter->getTimeline(10);
$userInfo = $twitter->getUserInfo();
```

#### LinkedIn

```php
use mantix\LaravelSocialMediaPublisher\Facades\SocialMedia;

// Get service for a user (requires OAuth connection)
$user = User::find(1);
$linkedin = SocialMedia::platform('linkedin', $user);

// Personal posts
$linkedin->shareText('Text-only post!');
$linkedin->shareUrl('Content', 'https://example.com');
$linkedin->shareImage('Caption', 'https://example.com/image.jpg');
$linkedin->shareVideo('Caption', 'https://example.com/video.mp4');

// Company page posts
$linkedin->shareToCompanyPage('Content', 'https://example.com');

// User info
$userInfo = $linkedin->getUserInfo();
```

#### Instagram

```php
use mantix\LaravelSocialMediaPublisher\Facades\SocialMedia;

// Get service for a user (requires OAuth connection)
$user = User::find(1);
$instagram = SocialMedia::platform('instagram', $user);

// Posts
$instagram->shareImage('Caption', 'https://example.com/image.jpg');
$instagram->shareVideo('Caption', 'https://example.com/video.mp4');

// Carousel posts
$instagram->shareCarousel('Caption', ['img1.jpg', 'img2.jpg', 'img3.jpg']);

// Stories
$instagram->shareStory('Caption', 'https://example.com');

// Analytics
$accountInfo = $instagram->getAccountInfo();
$recentMedia = $instagram->getRecentMedia(25);
```

#### TikTok

```php
use mantix\LaravelSocialMediaPublisher\Facades\SocialMedia;

// Get service for a user (requires OAuth connection)
$user = User::find(1);
$tiktok = SocialMedia::platform('tiktok', $user);

// Video publishing
$tiktok->shareVideo('Caption', 'https://example.com/video.mp4');

// Analytics
$userInfo = $tiktok->getUserInfo();
$userVideos = $tiktok->getUserVideos(20);
```

#### YouTube

```php
use mantix\LaravelSocialMediaPublisher\Facades\SocialMedia;

// Get service for a user (requires OAuth connection)
$user = User::find(1);
$youtube = SocialMedia::platform('youtube', $user);

// Video uploads
$youtube->shareVideo('Title', 'https://example.com/video.mp4');

// Community posts
$youtube->createCommunityPost('Content', 'https://example.com');

// Analytics
$channelInfo = $youtube->getChannelInfo();
$channelVideos = $youtube->getChannelVideos(25);
$videoAnalytics = $youtube->getVideoAnalytics('video_id');
```

#### Pinterest

```php
use mantix\LaravelSocialMediaPublisher\Facades\SocialMedia;

// Get service for a user (requires OAuth connection)
$user = User::find(1);
$pinterest = SocialMedia::platform('pinterest', $user);

// Pins
$pinterest->shareUrl('Caption', 'https://example.com');
$pinterest->shareImage('Caption', 'https://example.com/image.jpg');
$pinterest->shareVideo('Caption', 'https://example.com/video.mp4');

// Boards
$pinterest->createBoard('Board Name', 'Description');

// Analytics
$userInfo = $pinterest->getUserInfo();
$boards = $pinterest->getBoards(25);
$boardPins = $pinterest->getBoardPins('board_id', 25);
$pinAnalytics = $pinterest->getPinAnalytics('pin_id');
```

#### Telegram

```php
use mantix\LaravelSocialMediaPublisher\Facades\SocialMedia;

// Get service for a user (requires OAuth connection)
$user = User::find(1);
$telegram = SocialMedia::platform('telegram', $user);

// Messages
$telegram->shareText('Text-only message!');
$telegram->shareUrl('Content', 'https://example.com');
$telegram->shareImage('Caption', 'https://example.com/image.jpg');
$telegram->shareVideo('Caption', 'https://example.com/video.mp4');
$telegram->shareDocument('Caption', 'https://example.com/document.pdf');

// Bot updates
$updates = $telegram->getUpdates();
```

### Platform-Specific Features

#### Facebook Analytics

```php
use mantix\LaravelSocialMediaPublisher\Facades\SocialMedia;

$user = User::find(1);
$facebook = SocialMedia::platform('facebook', $user);

// Get page insights
$insights = $facebook->getPageInsights([
    'page_impressions',
    'page_engaged_users',
    'page_fan_adds'
]);

// Get insights for specific date range
$insights = $facebook->getPageInsights(
    ['page_impressions', 'page_engaged_users'],
    ['since' => '2024-01-01', 'until' => '2024-01-31']
);
```

#### Instagram Carousels

```php
use mantix\LaravelSocialMediaPublisher\Facades\SocialMedia;

$user = User::find(1);
$instagram = SocialMedia::platform('instagram', $user);

// Create carousel with multiple images
$images = [
    'https://example.com/image1.jpg',
    'https://example.com/image2.jpg',
    'https://example.com/image3.jpg'
];
$result = $instagram->shareCarousel('Check out our products!', $images);
```

#### LinkedIn Company Pages

```php
use mantix\LaravelSocialMediaPublisher\Facades\SocialMedia;

$user = User::find(1);
$linkedin = SocialMedia::platform('linkedin', $user);

// Post to company page (requires organization URN)
$linkedin->shareToCompanyPage('Company update: We\'re hiring!', 'https://example.com/careers');
```

#### YouTube Community Posts

```php
use mantix\LaravelSocialMediaPublisher\Facades\SocialMedia;

$user = User::find(1);
$youtube = SocialMedia::platform('youtube', $user);

// Create community post
$youtube->createCommunityPost('What would you like to see in our next video?', 'https://example.com/poll');
```

#### Pinterest Boards

```php
use mantix\LaravelSocialMediaPublisher\Facades\SocialMedia;

$user = User::find(1);
$pinterest = SocialMedia::platform('pinterest', $user);

// Create board
$pinterest->createBoard('My Recipes', 'Collection of amazing recipes', 'PUBLIC');

// Get board pins
$pins = $pinterest->getBoardPins('board_id', 25);
```

## 🔧 Advanced Features

### Error Handling

The package provides comprehensive error handling:

```php
use mantix\LaravelSocialMediaPublisher\Facades\SocialMedia;
use mantix\LaravelSocialMediaPublisher\Exceptions\SocialMediaException;

try {
    $user = User::find(1);
    $result = SocialMedia::shareUrl($user, ['facebook', 'twitter'], 'Content', 'https://example.com');
    
    // Check results
    if ($result['error_count'] > 0) {
        foreach ($result['errors'] as $platform => $error) {
            echo "Error on {$platform}: {$error}\n";
        }
    }
    
} catch (SocialMediaException $e) {
    echo "Social media error: " . $e->getMessage();
}
```

### Retry Logic

The package automatically retries failed requests with exponential backoff:

```php
// Configure timeout
config(['social_media_publisher.timeout' => 60]);
```

### Logging

All operations are automatically logged:

```php
// Enable/disable logging
config(['social_media_publisher.enable_logging' => true]);

// Check Laravel logs for detailed information
tail -f storage/logs/laravel.log
```

### Input Validation

The package validates all inputs:

```php
// Validates URLs
// Validates text length
// Validates required parameters
// Throws SocialMediaException for invalid inputs
```

## 🧪 Testing

### Run Tests

```bash
# Run all tests
./vendor/bin/phpunit

# Run specific test suite
./vendor/bin/phpunit tests/Unit/
./vendor/bin/phpunit tests/Feature/

# Run with coverage
./vendor/bin/phpunit --coverage-html coverage/
```

### Test Configuration

```php
// In your test setup
config([
    'social_media_publisher.facebook_access_token' => 'test_token',
    'social_media_publisher.facebook_page_id' => 'test_page_id',
    // ... other test configurations
]);
```

### Mocking APIs

```php
use Illuminate\Support\Facades\Http;

Http::fake([
    'https://graph.facebook.com/v20.0/*' => Http::response(['id' => '123'], 200),
    'https://api.twitter.com/2/*' => Http::response(['data' => ['id' => '456']], 200),
]);
```

## 📚 Examples

Check the `examples/` directory for comprehensive usage examples:

- **Basic Usage**: Single platform, multi-platform, error handling
- **Advanced Usage**: Content scheduling, analytics, bulk operations
- **Platform-Specific**: Facebook analytics, Instagram carousels, LinkedIn company pages
- **Integration**: Laravel commands, queue jobs, event listeners
- **Testing**: Unit tests, feature tests, API mocking

### Quick Examples

```bash
# Run basic examples
php examples/basic-usage/single-platform.php
php examples/basic-usage/multi-platform.php
php examples/basic-usage/error-handling.php

# Run platform-specific examples
php examples/platform-specific/facebook-examples.php
php examples/platform-specific/instagram-examples.php
```

## 📖 API Reference

### SocialMedia Facade

| Method | Description | Parameters |
|--------|-------------|------------|
| `shareText($owner, $platforms, $caption, $ownerId = null)` | Share text-only content to multiple platforms | `mixed $owner, array $platforms, string $caption, ?int $ownerId` |
| `shareUrl($owner, $platforms, $caption, $url, $ownerId = null)` | Share content with URL to multiple platforms | `mixed $owner, array $platforms, string $caption, string $url, ?int $ownerId` |
| `shareImage($owner, $platforms, $caption, $image_url, $ownerId = null)` | Share image to multiple platforms | `mixed $owner, array $platforms, string $caption, string $image_url, ?int $ownerId` |
| `shareVideo($owner, $platforms, $caption, $video_url, $ownerId = null)` | Share video to multiple platforms | `mixed $owner, array $platforms, string $caption, string $video_url, ?int $ownerId` |
| `platform($platform, $owner = null, $ownerId = null, $connectionType = 'profile')` | Get platform service for owner | `string $platform, mixed $owner, ?int $ownerId, ?string $connectionType` |
| `getAvailablePlatforms()` | Get list of available platforms | - |

### Platform Facades

Each platform facade provides methods specific to that platform. See the individual platform documentation above for detailed method signatures.

### SocialMediaManager

| Method | Description | Parameters |
|--------|-------------|------------|
| `getAvailablePlatforms()` | Get list of available platforms | - |
| `isPlatformAvailable($platform)` | Check if platform is available | `string $platform` |
| `getPlatformService($platform)` | Get platform service class | `string $platform` |

## 🤝 Contributing

We welcome contributions! Please see our [Contributing Guide](CONTRIBUTING.md) for details.

### Development Setup

```bash
# Clone the repository
git clone https://github.com/mantix/laravel-social-media-publisher.git

# Install dependencies
composer install

# Run tests
./vendor/bin/phpunit

# Run examples
php examples/basic-usage/single-platform.php
```

### Pull Request Process

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests for new functionality
5. Ensure all tests pass
6. Submit a pull request

## 📄 License

This package is licensed under the [MIT License](LICENSE).

## 🆘 Support

- **Documentation**: [GitHub Wiki](https://github.com/mantix/laravel-social-media-publisher/wiki)
- **Issues**: [GitHub Issues](https://github.com/mantix/laravel-social-media-publisher/issues)
- **Discussions**: [GitHub Discussions](https://github.com/mantix/laravel-social-media-publisher/discussions)
- **Email**: support@mantix.nl

## 🙏 Acknowledgments

- Laravel Framework
- All social media platform APIs
- The open-source community

---

**Made with ❤️ by [mantix](https://github.com/mantix)**

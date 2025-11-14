# Release Notes

## 🎉 Laravel Social Media Publisher v2.0.0 - Multi-User Support & OAuth Integration

**Release Date**: January 2025  
**Version**: 2.0.0  
**Type**: Major Release

---

## 🚀 What's New

### Multi-User Support
This major release transforms the package from a single-account solution to a **multi-user platform** with polymorphic relationships:

- ✅ **Polymorphic Ownership**: Any model (User, Company, etc.) can own social media connections
- ✅ **OAuth Integration**: Built-in OAuth flows for Facebook, LinkedIn, Twitter, Instagram, TikTok, YouTube, and Pinterest
- ✅ **Secure Token Storage**: All tokens are encrypted in the database
- ✅ **Connection Management**: Easy connect/disconnect functionality
- ⚠️ **Breaking Changes**: OAuth 2.0 is now **required** for all platforms (except Telegram). Single-account `.env` setups are **no longer supported**.

### 🔐 OAuth Integration

#### Facebook OAuth
```php
// Get authorization URL
$authUrl = FacebookService::getAuthorizationUrl($redirectUri);

// Handle callback
$tokenData = FacebookService::handleCallback($code, $redirectUri);

// Disconnect
FacebookService::disconnect($accessToken);
```

#### LinkedIn OAuth
```php
// Get authorization URL
$authUrl = LinkedInService::getAuthorizationUrl($redirectUri);

// Handle callback
$tokenData = LinkedInService::handleCallback($code, $redirectUri);

// Disconnect
LinkedInService::disconnect($accessToken);
```

### 👥 Multi-User Posting

**Note**: All posting requires OAuth connections. Users must authenticate their social media accounts through OAuth before posting.

```php
// Post on behalf of a User (requires OAuth connection)
$user = User::find(1);
SocialMedia::shareForOwner($user, ['facebook', 'twitter'], 'Hello!', 'https://example.com');

// Post on behalf of a Company (requires OAuth connection)
$company = Company::find(1);
SocialMedia::shareForOwner($company, ['linkedin'], 'Company Update!', 'https://example.com');

// Get owner-specific service (requires OAuth connection)
$facebook = SocialMedia::platform('facebook', $user);
$facebook->share('Hello', 'https://example.com');
```

### 📦 Database Schema

New `social_media_connections` table stores:
- Polymorphic ownership (`owner_id` and `owner_type`) - supports any model
- Platform and connection type information
- Encrypted access tokens and refresh tokens
- Token expiration dates
- Platform-specific metadata
- Connection status (active/inactive)

---

## 🔄 Migration from v1.0.0

**⚠️ BREAKING CHANGES**: This is a major breaking release. OAuth 2.0 authentication is now **required** for all platforms (except Telegram which uses Bot API). Single-account setups using `.env` credentials are **no longer supported**.

### Step 1: Publish Migrations
```bash
php artisan vendor:publish --provider="mantix\LaravelSocialMediaPublisher\SocialShareServiceProvider" --tag=social-media-publisher-migrations
php artisan migrate
```

### Step 2: Add OAuth Credentials (Required)
OAuth 2.0 credentials are now **required** for all platforms. Add to your `.env`:
```env
# Facebook OAuth 2.0
FACEBOOK_CLIENT_ID=your_client_id
FACEBOOK_CLIENT_SECRET=your_client_secret

# Twitter/X OAuth 2.0
X_CLIENT_ID=your_client_id
X_CLIENT_SECRET=your_client_secret
X_API_KEY=your_api_key
X_API_SECRET_KEY=your_api_secret_key

# LinkedIn OAuth 2.0
LINKEDIN_CLIENT_ID=your_client_id
LINKEDIN_CLIENT_SECRET=your_client_secret

# Instagram OAuth 2.0
INSTAGRAM_CLIENT_ID=your_client_id
INSTAGRAM_CLIENT_SECRET=your_client_secret

# TikTok OAuth 2.0
TIKTOK_CLIENT_ID=your_client_id
TIKTOK_CLIENT_SECRET=your_client_secret

# YouTube OAuth 2.0
YOUTUBE_CLIENT_ID=your_client_id
YOUTUBE_CLIENT_SECRET=your_client_secret

# Pinterest OAuth 2.0
PINTEREST_CLIENT_ID=your_client_id
PINTEREST_CLIENT_SECRET=your_client_secret

# Telegram (Bot API - No OAuth)
TELEGRAM_BOT_TOKEN=your_bot_token
TELEGRAM_CHAT_ID=your_chat_id
```

### Step 3: Configure OAuth Callback URLs
Set up OAuth callback URLs in each platform's developer portal:
- Facebook: `https://yourdomain.com/auth/facebook/callback`
- LinkedIn: `https://yourdomain.com/auth/linkedin/callback`
- Twitter/X: `https://yourdomain.com/auth/x/callback`
- Instagram: `https://yourdomain.com/auth/instagram/callback`
- TikTok: `https://yourdomain.com/auth/tiktok/callback`
- YouTube: `https://yourdomain.com/auth/youtube/callback`
- Pinterest: `https://yourdomain.com/auth/pinterest/callback`

**Note**: Callback routes are automatically registered by the package and excluded from CSRF protection.

### Step 4: Update Code (Required)
All code must be updated to use OAuth connections:

#### Before (v1.0.0 - No longer works):
```php
// Old singleton pattern - NO LONGER SUPPORTED
$service = FacebookService::getInstance();
$service->share('Hello', 'https://example.com');

// Old unified API without owner - NO LONGER SUPPORTED
SocialMedia::share(['facebook', 'twitter'], 'Hello', 'https://example.com');
```

#### After (v2.0.0 - OAuth required):
```php
// 1. Users must authenticate via OAuth first
$authUrl = FacebookService::getAuthorizationUrl($redirectUri);
// Redirect user to $authUrl, then handle callback

// 2. Post using owner with OAuth connection
$user = User::find(1);
SocialMedia::shareForOwner($user, ['facebook', 'twitter'], 'Hello', 'https://example.com');

// 3. Or get service instance from connection
$connection = SocialMediaConnection::forOwner($user)
    ->where('platform', 'facebook')
    ->first();
$service = FacebookService::forConnection($connection);
$service->share('Hello', 'https://example.com');
```

### Required Code Changes:
- ❌ **Remove**: All `getInstance()` calls (now throw exceptions)
- ❌ **Remove**: `shareForUser()`, `shareImageForUser()`, `shareVideoForUser()` methods
- ✅ **Add**: OAuth authentication flows for users
- ✅ **Update**: Use `shareForOwner()` instead of `shareForUser()`
- ✅ **Update**: Use `shareImageForOwner()` instead of `shareImageForUser()`
- ✅ **Update**: Use `shareVideoForOwner()` instead of `shareVideoForUser()`
- ✅ **Update**: Connection creation to use `owner_id` and `owner_type` (polymorphic)
- ✅ **Update**: `SocialMedia::platform()` to pass model instances with OAuth connections
- ✅ **Update**: Replace `SocialMediaConnection::forUser()` with `SocialMediaConnection::forOwner()`

### Migration Checklist:
- [ ] Publish and run migrations
- [ ] Add OAuth credentials to `.env`
- [ ] Configure callback URLs in developer portals
- [ ] Create OAuth authorization routes in your application
- [ ] Update all `getInstance()` calls to use `forConnection()`
- [ ] Replace `shareForUser()` with `shareForOwner()`
- [ ] Update connection queries to use polymorphic `forOwner()` scope
- [ ] Test OAuth flows for each platform
- [ ] Remove old `.env` credential variables (access tokens, page IDs, etc.)

---

## 📚 Documentation

- **[README.md](README.md)** - Complete documentation with multi-user examples
- **[CHANGELOG.md](CHANGELOG.md)** - Detailed changelog

---

## 🆘 Support

- **GitHub Issues**: [Report bugs or request features](https://github.com/mantix/laravel-social-media-publisher/issues)
- **Email**: support@mantix.nl

---

## 🙏 Acknowledgments

- Laravel Framework
- All Social Media Platform APIs
- Open Source Community

---

**Made with ❤️ by [mantix](https://github.com/mantix)**

---

# Release Notes - v1.0.0

## 🎉 Laravel Social Media Publisher v1.0.0 - Complete Social Media Platform Support

**Release Date**: November 13th, 2025  
**Version**: 1.0.0  
**Type**: Major First Release

---

## 🚀 What's New

### Complete Social Media Platform Support
This major release transforms the package from a basic Facebook/Telegram solution to a **comprehensive social media automation platform** supporting **8 major platforms**:

- ✅ **Facebook** - Enhanced with analytics and insights
- ✅ **Twitter/X** - Complete API v2 integration
- ✅ **LinkedIn** - Personal and company page publishing
- ✅ **Instagram** - Images, videos, carousels, and stories
- ✅ **TikTok** - Video sharing with hashtag support
- ✅ **YouTube** - Video uploads and community posts
- ✅ **Pinterest** - Pin creation and board management
- ✅ **Telegram** - Enhanced messaging capabilities

### 🎯 Unified API System

#### Multi-Platform publishing
```php
// Post to all platforms at once
SocialMedia::shareToAll('Hello World!', 'https://example.com');

// Post to specific platforms
SocialMedia::share(['facebook', 'twitter', 'linkedin'], 'Content', 'https://example.com');
```

#### Individual Platform Access
```php
// Direct platform access
SocialMedia::facebook()->share('Hello', 'https://example.com');
SocialMedia::twitter()->shareImage('Check this out!', 'https://example.com/image.jpg');
SocialMedia::linkedin()->shareToCompanyPage('Company Update', 'https://example.com');
SocialMedia::instagram()->shareCarousel('Multiple images', ['img1.jpg', 'img2.jpg']);
```

### 🔧 Production-Ready Features

- **🛡️ Robust Error Handling**: Custom exceptions with detailed error messages
- **🔄 Retry Logic**: Exponential backoff for failed requests
- **✅ Input Validation**: URL validation, text length limits, content type validation
- **📊 Logging System**: Detailed logging for all operations
- **⏱️ Timeout Configuration**: Configurable request timeouts
- **📈 Analytics Support**: Platform-specific analytics and insights

### 🧪 Comprehensive Testing

- **33 Tests** with **101 Assertions**
- **85% Test Coverage** across all platforms
- **Unit Tests** for individual services
- **Feature Tests** for end-to-end functionality
- **Docker Support** for containerized testing

### 📚 Complete Documentation

- **Professional README** (605 lines) with installation, configuration, and usage
- **Arabic Documentation** (604 lines) for Arabic-speaking developers
- **5 Example Files** with comprehensive usage demonstrations
- **API Reference** with complete method documentation

---

## 🔄 Migration Guide

**⚠️ NOTE**: This section documents v1.0.0 migration. For migration to v2.0.0 (OAuth 2.0 required), see the [v2.0.0 Migration Guide](#-migration-from-v100) above.

### Breaking Changes from v1.x

This is a **major release** with breaking changes. Here's how to migrate:

#### 1. Update Configuration
Add new platform credentials to your `.env`:

```env
# Telegram (Bot API - still supported in v2.0.0)
TELEGRAM_BOT_TOKEN=your_token
TELEGRAM_CHAT_ID=your_chat_id

# ⚠️ DEPRECATED in v2.0.0 - These are no longer supported:
# FACEBOOK_ACCESS_TOKEN=your_token (use OAuth 2.0 instead)
# FACEBOOK_PAGE_ID=your_page_id (not needed with OAuth)
# TWITTER_BEARER_TOKEN, TWITTER_ACCESS_TOKEN, etc. (use OAuth 2.0 instead)
# All other access tokens (use OAuth 2.0 instead)

# New platforms (v1.0.0 - now deprecated in v2.0.0)
TWITTER_BEARER_TOKEN=your_token
TWITTER_API_KEY=your_key
TWITTER_API_SECRET=your_secret
TWITTER_ACCESS_TOKEN=your_token
TWITTER_ACCESS_TOKEN_SECRET=your_secret

LINKEDIN_ACCESS_TOKEN=your_token
LINKEDIN_PERSON_URN=your_urn
LINKEDIN_ORGANIZATION_URN=your_org_urn

INSTAGRAM_ACCESS_TOKEN=your_token
INSTAGRAM_ACCOUNT_ID=your_account_id

TIKTOK_ACCESS_TOKEN=your_token
TIKTOK_CLIENT_KEY=your_key
TIKTOK_CLIENT_SECRET=your_secret

YOUTUBE_API_KEY=your_key
YOUTUBE_ACCESS_TOKEN=your_token
YOUTUBE_CHANNEL_ID=your_channel_id

PINTEREST_ACCESS_TOKEN=your_token
PINTEREST_BOARD_ID=your_board_id
```

**⚠️ IMPORTANT**: In v2.0.0, all platforms (except Telegram) require OAuth 2.0 authentication. Direct access tokens are no longer supported.

#### 2. Update Method Calls (Optional in v1.0.0, Required in v2.0.0)
In v1.0.0, you could continue using the old syntax or upgrade to the new unified API:

```php
// Old way (v1.0.0 - still works in v1.0.0, but deprecated in v2.0.0)
FaceBook::share('Hello', 'https://example.com');
Telegram::share('Hello', 'https://example.com');

// New unified way (v1.0.0 - recommended)
SocialMedia::share(['facebook', 'telegram'], 'Hello', 'https://example.com');

// Or use individual platform access
SocialMedia::facebook()->share('Hello', 'https://example.com');
SocialMedia::telegram()->share('Hello', 'https://example.com');
```

**⚠️ In v2.0.0**: All methods require OAuth connections. See the [v2.0.0 Migration Guide](#-migration-from-v100) above for details.

#### 3. Review Error Handling
The new version has enhanced error handling:

```php
try {
    $result = SocialMedia::shareToAll('Content', 'https://example.com');
} catch (SocialMediaException $e) {
    // Handle social media specific errors
    Log::error('Social media error: ' . $e->getMessage());
}
```

---

## 📦 Installation

### Via Composer
```bash
composer require mantix/laravel-social-media-publisher:^2.0
```

### Publish Configuration
```bash
php artisan vendor:publish --provider="mantix\LaravelSocialMediaPublisher\SocialShareServiceProvider" --tag=social-media-publisher-config
```

---

## 🎯 Quick Start

### Basic Usage
**Note**: All posting requires OAuth connections. Users must authenticate their accounts first.

```php
use mantix\LaravelSocialMediaPublisher\Facades\SocialMedia;

// Post to specific platforms (requires owner with OAuth connections)
$user = User::find(1);
$result = SocialMedia::shareForOwner($user, ['facebook', 'twitter'], 'Content', 'https://example.com');

// Share images (requires owner with OAuth connections)
$result = SocialMedia::shareImageForOwner($user, ['instagram', 'pinterest'], 'Check this out!', 'https://example.com/image.jpg');

// Share videos (requires owner with OAuth connections)
$result = SocialMedia::shareVideoForOwner($user, ['youtube', 'tiktok'], 'Watch this!', 'https://example.com/video.mp4');
```

### Platform-Specific Features
**Note**: All methods require an owner with OAuth connection.

```php
$user = User::find(1); // User must have OAuth connections

// Facebook analytics
$facebook = SocialMedia::platform('facebook', $user);
$insights = $facebook->getPageInsights(['page_impressions', 'page_engaged_users']);

// Twitter timeline
$twitter = SocialMedia::platform('twitter', $user);
$timeline = $twitter->getTimeline(10);

// LinkedIn company publishing
$linkedin = SocialMedia::platform('linkedin', $user);
$linkedin->shareToCompanyPage('Company Update', 'https://example.com');

// Instagram carousel
$instagram = SocialMedia::platform('instagram', $user);
$instagram->shareCarousel('Multiple images', ['img1.jpg', 'img2.jpg', 'img3.jpg']);

// YouTube video upload
$youtube = SocialMedia::platform('youtube', $user);
$youtube->shareVideo('Video Title', 'https://example.com/video.mp4');
```

---

## 🔧 Configuration

### Environment Variables
See the complete list of environment variables in the [README.md](README.md#environment-variables).

### Configuration File
The published `config/social-media-publisher.php` file contains all configuration options:

```php
return [
    // OAuth 2.0 Credentials (Required)
    'facebook_client_id' => env('FACEBOOK_CLIENT_ID'),
    'facebook_client_secret' => env('FACEBOOK_CLIENT_SECRET'),
    'facebook_api_version' => env('FACEBOOK_API_VERSION', 'v20.0'),
    
    'x_client_id' => env('X_CLIENT_ID'),
    'x_client_secret' => env('X_CLIENT_SECRET'),
    'x_api_key' => env('X_API_KEY'),
    'x_api_secret_key' => env('X_API_SECRET_KEY'),
    
    // ... all other platforms with OAuth credentials
    
    // Telegram (Bot API - No OAuth)
    'telegram_bot_token' => env('TELEGRAM_BOT_TOKEN'),
    'telegram_chat_id' => env('TELEGRAM_CHAT_ID'),
    
    // General settings
    'enable_logging' => env('SOCIAL_MEDIA_LOGGING', true),
    'timeout' => env('SOCIAL_MEDIA_TIMEOUT', 30),
    'retry_attempts' => env('SOCIAL_MEDIA_RETRY_ATTEMPTS', 3),
    
    // OAuth settings
    'oauth_redirect_route' => env('SOCIAL_MEDIA_OAUTH_REDIRECT_ROUTE', 'dashboard'),
];
```

---

## 🧪 Testing

### Run Tests
```bash
# Using Docker (recommended)
docker-compose up --build

# Or using PHPUnit directly
./vendor/bin/phpunit
```

### Test Coverage
- Comprehensive test suite with Unit and Feature tests
- Tests for OAuth flows, connection management, and posting
- Tests for polymorphic relationships and multi-user support
- Aiming for **98% code coverage**

---

## 📚 Documentation

- **[README.md](README.md)** - Complete English documentation
- **[README_AR.md](README_AR.md)** - Complete Arabic documentation
- **[Examples/](examples/)** - Usage examples and demonstrations
- **[CHANGELOG.md](CHANGELOG.md)** - Detailed changelog

---

## 🆘 Support

- **GitHub Issues**: [Report bugs or request features](https://github.com/mantix/laravel-social-media-publisher/issues)
- **Documentation**: [Complete documentation](README.md)
- **Email**: support@mantix.nl

---

## 🙏 Acknowledgments

- Laravel Framework
- All Social Media Platform APIs
- Open Source Community

---

**Made with ❤️ by [mantix](https://github.com/mantix)**

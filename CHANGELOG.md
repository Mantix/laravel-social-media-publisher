# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.2] - 2025-11-14

### 🔄 Changed

#### Method Renaming for API Clarity
- **BREAKING**: Renamed `share()` to `shareUrl()` in all service classes and interfaces
  - Updated `ShareInterface` to use `shareUrl()` method signature
  - Updated all platform services (LinkedIn, Facebook, Twitter, Telegram, Instagram, Pinterest, TikTok, YouTube)
  - Updated all facade docblocks to reflect new method names
- **BREAKING**: Removed "ForOwner" suffix from all `SocialMediaManager` methods since owners are always required
  - `shareTextForOwner()` → `shareText()`
  - `shareUrlForOwner()` → `shareUrl()`
  - `shareImageForOwner()` → `shareImage()`
  - `shareVideoForOwner()` → `shareVideo()`

### ❌ Removed

- **BREAKING**: Removed deprecated methods from `SocialMediaManager`:
  - `shareUrl()` (old version without owner) - Use `shareUrl($owner, ...)` instead
  - `shareImage()` (old version without owner) - Use `shareImage($owner, ...)` instead
  - `shareVideo()` (old version without owner) - Use `shareVideo($owner, ...)` instead
  - `shareUrlToAll()` - Use `shareUrl()` with all platforms instead
  - `shareImageToAll()` - Use `shareImage()` with all platforms instead
  - `shareVideoToAll()` - Use `shareVideo()` with all platforms instead

### ✨ Added

- Added `shareText()` method to `SocialMediaManager` for text-only posts (owners always required)
- Added `shareText()` method to FacebookService, TwitterService, TelegramService, and LinkedInService for text-only posts
- Updated LinkedInService `shareUrl()` method to support organization_urn

### 📝 Documentation

- Updated RELEASE_NOTES.md with v2.0.2 migration guide
- Updated all facade docblocks with new method signatures
- Clarified that all methods now require owner authentication

### 🔧 Technical Details

This change improves API clarity and enforces OAuth authentication by:
- Making it explicit that all sharing methods require an owner: `shareText()`, `shareUrl()`, `shareImage()`, `shareVideo()`
- Removing methods that don't require owners (which would fail anyway in v2.0.0+)
- Removing "ForOwner" suffix since owners are always required, resulting in cleaner method names

**Migration Required**: All code using deprecated methods must be updated:
- `share()` → `shareUrl()` (on service level)
- `shareForOwner()` / `shareUrlForOwner()` → `shareUrl($owner, ...)` (on manager level)
- `shareToAll()` → `shareUrl($owner, ...)` with all platforms
- Add `shareText($owner, ...)` for text-only posts

## [2.0.0] - 2025-11-13

### 🚀 Major Release - Multi-User Support & OAuth Integration

**⚠️ BREAKING CHANGES**: This is a breaking release. The package now requires OAuth 2.0 authentication for all platforms (except Telegram which uses Bot API). All connections must be established through OAuth flows.

This major release adds comprehensive multi-user support with polymorphic relationships, allowing any model (User, Company, etc.) to connect their own social media accounts and post on their behalf.

### ✨ Added

#### Multi-User & Multi-Entity Support
- **SocialMediaConnection Model**: New Eloquent model with polymorphic relationships for managing social media connections
- **Polymorphic Ownership**: Connections can belong to any model (User, Company, etc.) using `owner_id` and `owner_type`
- **Database Migration**: Migration for storing connections, tokens, and metadata with polymorphic support
- **Owner-Specific Services**: Services can now be instantiated with owner-specific credentials
- **Connection Management**: Methods to create, retrieve, and delete connections for any model type

#### OAuth Integration
- **Facebook OAuth**: Complete OAuth 2.0 flow with `getAuthorizationUrl()` and `handleCallback()` methods
- **LinkedIn OAuth**: OAuth 2.0 integration for LinkedIn personal and company pages
- **Twitter OAuth**: OAuth 1.0a support (requires signature implementation)
- **Disconnect Methods**: Ability to revoke access tokens and disconnect from platforms

#### Enhanced Services
- **withCredentials()**: Static method to create service instances with specific credentials
- **forConnection()**: Static method to create service instances from SocialMediaConnection models
- **OAuth-Only Authentication**: All platforms (except Telegram) require OAuth 2.0 authentication. No `.env` credential fallbacks.

#### SocialMediaManager Updates
- **shareForOwner()**: Post to multiple platforms on behalf of any owner (User, Company, etc.)
- **shareImageForOwner()**: Share images on behalf of any owner
- **shareVideoForOwner()**: Share videos on behalf of any owner
- **platform()**: Enhanced to accept optional owner (model instance or class name)
- **Removed**: `shareForUser()`, `shareImageForUser()`, `shareVideoForUser()` methods removed (use `shareForOwner()` instead)

#### Configuration
- **OAuth Credentials**: Added Facebook App ID/Secret, LinkedIn Client ID/Secret, Twitter Client ID/Secret
- **Migration Publishing**: New tag for publishing database migrations

### 🔧 Enhanced

- **Service Provider**: Updated to publish migrations alongside configuration
- **Token Encryption**: All access tokens and secrets are encrypted in the database
- **Connection Scopes**: Query scopes for filtering connections by owner (polymorphic), platform, and status
- **Polymorphic Relationships**: Full support for any model type owning social media connections

### 📦 Database Changes

New `social_media_connections` table with:
- Polymorphic ownership (`owner_id` and `owner_type`) - supports User, Company, or any model
- Platform and connection type
- Encrypted access tokens and refresh tokens
- Token expiration tracking
- Metadata JSON field for platform-specific data
- Active/inactive status

### 🔄 Migration Guide

#### From v1.0.0 to v2.0.0

**⚠️ BREAKING CHANGES**: This release requires code updates if you're using multi-user features.

1. **Publish and Run Migrations**:
   ```bash
   php artisan vendor:publish --provider="mantix\LaravelSocialMediaPublisher\SocialShareServiceProvider" --tag=social-media-publisher-migrations
   php artisan migrate
   ```

2. **Add OAuth Credentials** (required for multi-user):
   ```env
   FACEBOOK_APP_ID=your_app_id
   FACEBOOK_APP_SECRET=your_app_secret
   LINKEDIN_CLIENT_ID=your_client_id
   LINKEDIN_CLIENT_SECRET=your_client_secret
   ```

3. **Update Code** (required for multi-user):
   - Replace `shareForUser()` with `shareForOwner()`
   - Replace `shareImageForUser()` with `shareImageForOwner()`
   - Replace `shareVideoForUser()` with `shareVideoForOwner()`
   - Update connection creation to use `owner_id` and `owner_type` instead of `user_id`
   - Replace `SocialMediaConnection::forUser()` with `SocialMediaConnection::forOwner()`
   - Update `SocialMedia::platform()` calls to pass model instances instead of user IDs

4. **OAuth Required**:
   - All platforms (except Telegram) now require OAuth 2.0 authentication
   - Users must authenticate their accounts through OAuth before posting
   - No `.env` credential fallbacks - OAuth is the only supported method

## [1.0.0] - 2025-11-13

### 🚀 Major Release - Complete Social Media Platform Support

This is a major release that transforms the package from a basic Facebook/Telegram solution to a comprehensive social media automation platform supporting 8 major platforms.

### ✨ Added

#### New Social Media Platforms
- **Twitter/X Integration**: Complete Twitter API v2 support with tweet publishing, image sharing, and timeline access
- **LinkedIn Integration**: Personal and company page publishing with asset upload support
- **Instagram Integration**: Image/video publishing, carousel posts, and story sharing
- **TikTok Integration**: Video sharing with hashtag support and user analytics
- **YouTube Integration**: Video uploads, community posts, and channel analytics
- **Pinterest Integration**: Pin creation, board management, and analytics

#### Unified API System
- **SocialMedia Facade**: Single entry point for multi-platform publishing
- **SocialMediaManager**: Orchestrates publishing across multiple platforms
- **Batch Operations**: Post to multiple platforms simultaneously
- **Platform-Specific Access**: Direct access to individual platform features

#### Advanced Features
- **Comprehensive Error Handling**: Custom exceptions with detailed error messages
- **Retry Logic**: Exponential backoff for failed requests
- **Input Validation**: URL validation, text length limits, content type validation
- **Logging System**: Detailed logging for all operations
- **Timeout Configuration**: Configurable request timeouts
- **Analytics Support**: Platform-specific analytics and insights

#### Testing & Quality
- **Complete Test Suite**: 33 tests with 101 assertions covering all platforms
- **Unit Tests**: Individual service testing with mocking
- **Feature Tests**: End-to-end functionality testing
- **85% Test Coverage**: Comprehensive test coverage across all components

#### Documentation & Examples
- **Professional Documentation**: Complete README with installation, configuration, and usage
- **Arabic Documentation**: Full Arabic translation (README_AR.md)
- **Example Directory**: 5 comprehensive example files
- **API Reference**: Complete method documentation and signatures

### 🔧 Enhanced

#### Existing Platforms
- **Facebook Service**: Enhanced with better error handling and validation
- **Telegram Service**: Improved method signatures and error handling
- **Service Provider**: Updated to register all new services and facades

#### Code Quality
- **Interface Compliance**: All services implement proper interfaces
- **Type Safety**: Complete type hints and return type declarations
- **PSR Standards**: Full PSR-4 autoloading compliance
- **Laravel Integration**: Native Laravel service provider and facade integration

### 🐛 Fixed

- **Method Signatures**: Fixed interface compatibility issues
- **Import Statements**: Added missing Log and Exception imports
- **Service Registration**: Proper singleton registration for all services
- **Configuration**: Complete configuration file with all platform settings

### 📦 Package Structure

```
src/
├── config/social-media-publisher.php              # Complete configuration
├── Contracts/                       # Interface definitions
├── Enums/FacebookMetrics.php         # Facebook analytics enums
├── Exceptions/SocialMediaException.php # Custom exception
├── Facades/                         # All platform facades
│   ├── FaceBook.php
│   ├── Telegram.php
│   ├── Twitter.php
│   ├── LinkedIn.php
│   ├── Instagram.php
│   ├── TikTok.php
│   ├── YouTube.php
│   ├── Pinterest.php
│   └── SocialMedia.php             # Unified facade
├── Services/                        # All platform services
│   ├── SocialMediaService.php      # Base service
│   ├── SocialMediaManager.php      # Multi-platform manager
│   ├── FacebookService.php
│   ├── TelegramService.php
│   ├── TwitterService.php
│   ├── LinkedInService.php
│   ├── InstagramService.php
│   ├── TikTokService.php
│   ├── YouTubeService.php
│   └── PinterestService.php
└── SocialShareServiceProvider.php   # Laravel service provider
```

### 🧪 Testing

- **33 Tests**: Complete test coverage
- **101 Assertions**: Comprehensive validation
- **15 Test Files**: Unit and feature tests
- **Docker Support**: Containerized testing environment

### 📚 Documentation

- **README.md**: 605 lines of comprehensive documentation
- **README_AR.md**: 604 lines of Arabic documentation
- **Examples**: 5 example files with usage demonstrations
- **API Reference**: Complete method documentation

### 🔄 Migration from v1.x

This is a **breaking change** release. Users upgrading from v1.x will need to:

1. Update their configuration to include new platform credentials
2. Update method calls to use new unified API (optional)
3. Review new error handling patterns

### 🎯 What's New for Users

```php
// Unified API - Post to all platforms
SocialMedia::shareToAll('Hello World!', 'https://example.com');

// Individual platform access
SocialMedia::facebook()->share('Hello', 'https://example.com');
SocialMedia::twitter()->share('Hello', 'https://example.com');
SocialMedia::linkedin()->shareToCompanyPage('Update', 'https://example.com');
SocialMedia::instagram()->shareCarousel('Check this out!', ['img1.jpg', 'img2.jpg']);

// Platform-specific features
$insights = SocialMedia::facebook()->getPageInsights(['page_impressions']);
$timeline = SocialMedia::twitter()->getTimeline(10);
$analytics = SocialMedia::youtube()->getVideoAnalytics('video_id');
```

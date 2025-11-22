# Changelog

All notable changes to the **Laravel Social Media Publisher** package will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.1.0] - 2025-11-22

### 🚀 Added
- **Polymorphic Relationships:** Introduced `SocialMediaConnection` model allowing `User`, `Company`, or any model to own connections via the `owner_id` and `owner_type` columns.
- **Unified Facade:** Added `SocialMedia` facade for bulk publishing across multiple platforms simultaneously.
- **Trait:** Added `HasSocialMediaConnections` trait for easy integration with Eloquent models.
- **YouTube Support:** Added `YouTubeService` with **Resumable Upload Protocol** for handling large video files reliably.
- **TikTok Support:** Added `TikTokService` utilizing the new **TikTok API v2** for direct video publishing.
- **Pinterest Support:** Added `PinterestService` using **API v5**, supporting Pins and Boards.
- **Telegram Support:** Added `TelegramService` for Bot API integration (Channels/Groups) with file support.
- **Retry Logic:** Implemented generic `SocialMediaService` base class with automatic exponential backoff retry logic for failed API requests.
- **Temp File Handling:** Added secure handling for remote file URLs (S3/CDN) by downloading to temporary local storage before uploading to APIs that require binary streams.

### ⚡ Changed
- **Twitter Rebranding:** Renamed `Twitter` facade to `X`. Updated authentication to use **OAuth 2.0 with PKCE** by default.
- **LinkedIn Architecture:** Refactored `LinkedInService` to unify Profile and Company Page logic. The "Author URN" is now determined at instantiation time.
- **Facebook/Instagram Logic:** Updated to Graph API **v20.0**. Added logic to fetch specific "Page Access Tokens" rather than posting as a user on a page.
- **Instagram Publishing:** Added "Status Polling" to `shareVideo` and `shareCarousel`. The system now waits for the media container to be `FINISHED` before attempting to publish.
- **Exceptions:** Standardized all exceptions to throw `SocialMediaException` with unified error messaging.

### 🐛 Fixed
- Fixed issue where Instagram Video uploads would fail if published immediately before transcoding was complete.
- Fixed large video upload failures on X (Twitter) by implementing chunked media upload (INIT/APPEND/FINALIZE).
- Fixed issue where Facebook Page Tokens were not being saved correctly during the OAuth callback.

### 🔒 Security
- Enforced PKCE (Proof Key for Code Exchange) for Twitter and LinkedIn OAuth flows.
- Access Tokens and Refresh Tokens are now encrypted at rest in the database using Laravel's `Crypt` facade.

## [2.0.5] - 2025-11-14

### ✨ Added

Fixed some typo's.

## [2.0.4] - 2025-11-14

### ✨ Added

#### Model Trait for Easy Integration
- **HasSocialMediaConnections Trait**: New trait to easily add social media connection functionality to custom models
  - `social_media_connections()` - MorphMany relationship for all connections
  - `social_connection_linkedin()` - HasOne relationship for latest LinkedIn connection
  - `social_connection_instagram()` - HasOne relationship for latest Instagram connection
  - `social_connection_facebook()` - HasOne relationship for latest Facebook connection
  - `social_connection_x()` - HasOne relationship for latest X (Twitter) connection
  - `getSocialConnection(string $platform)` - Get active connection for a specific platform
  - `hasSocialConnection(string $platform)` - Check if model has active connection for platform
  - Simply use `use HasSocialMediaConnections;` in your model to enable all functionality

## [2.0.3] - 2025-11-14

### ✨ Added

#### OAuth Enhancements
- **PKCE Support for LinkedIn**: Optional PKCE (Proof Key for Code Exchange) support in `getAuthorizationUrl()` and `handleCallback()` methods
  - `getAuthorizationUrl()` now accepts `$usePkce` and `$codeVerifier` parameters
  - Returns array with `['url' => ..., 'code_verifier' => ...]` when PKCE is enabled
  - Maintains backward compatibility (returns string when PKCE is disabled)
- **Improved PKCE for Twitter/X**: Enhanced PKCE implementation with better security
  - Made PKCE optional (defaults to `true` for Twitter/X)
  - Returns array with code verifier instead of embedding in state parameter
  - Better session-based storage for code verifier
- **Refresh Token Methods**: Added `refreshAccessToken()` methods for platforms with refresh tokens
  - `LinkedInService::refreshAccessToken(string $refreshToken): array`
  - `TwitterService::refreshAccessToken(string $refreshToken): array`
- **Token Extension Methods**: Added token extension methods for Facebook and Instagram
  - `FacebookService::extendAccessToken(string $shortLivedToken): array`
  - `FacebookService::refreshAccessToken(string $shortLivedToken): array` (alias for consistency)
  - `InstagramService::extendAccessToken(string $shortLivedToken): array`
  - `InstagramService::refreshAccessToken(string $shortLivedToken): array` (alias for consistency)

#### LinkedIn Service Enhancements
- **Organization Methods**: Added methods for managing LinkedIn company pages
  - `getAdministeredCompanyPages(): array` - Get all company pages user administers (with fallback logic)
  - `getOrganizationInfo(string $orgId): array` - Get organization information by ID
- **Enhanced User Profile**: Improved user profile methods
  - `getUserInfo(?string $projection = null): array` - Now accepts optional custom projection parameter
  - `getUserProfile(?string $projection = null): array` - New method with simple default projection

### 🔄 Changed

#### OAuth Controller Updates
- **LinkedIn Callback**: Updated to support PKCE code verifier from session
- **Twitter/X Callback**: Updated to use session storage for code verifier (more secure)
- **Refresh Token Storage**: All callbacks now properly save refresh tokens to database

### 📝 Documentation

- **README Updates**: Added comprehensive PKCE examples and refresh token usage guides
- **OAuth Implementation Plan**: Created detailed `OAUTH_IMPLEMENTATION_PLAN.md` document
  - Complete feature comparison matrix
  - Platform-specific OAuth mechanisms explained
  - Code examples for all scenarios
  - Security best practices
  - Testing recommendations

### 🔧 Technical Details

#### PKCE Implementation
- Code verifier generation: 32-byte random hex string
- Code challenge: SHA256 hash with Base64 URL encoding (S256 method)
- Session-based storage for code verifiers (recommended)
- Backward compatibility maintained for non-PKCE flows

#### Token Management
- **Refresh Tokens**: LinkedIn and Twitter/X use standard OAuth 2.0 refresh token flow
- **Token Extension**: Facebook and Instagram use `fb_exchange_token` grant type
- **Token Storage**: All tokens encrypted in database via `SocialMediaConnection` model

### 🎯 Migration Notes

#### For PKCE Usage (Optional but Recommended)

**Before (v2.0.2)**:
```php
// LinkedIn - no PKCE support
$authUrl = LinkedInService::getAuthorizationUrl($redirectUri);
return redirect($authUrl);

// Twitter/X - PKCE embedded in state (less secure)
$authUrl = TwitterService::getAuthorizationUrl($redirectUri);
return redirect($authUrl);
```

**After (v2.0.3)**:
```php
// LinkedIn - with PKCE (recommended)
$authData = LinkedInService::getAuthorizationUrl($redirectUri, [], null, true);
session(['linkedin_code_verifier' => $authData['code_verifier']]);
return redirect($authData['url']);

// Twitter/X - PKCE enabled by default, returns array
$authData = TwitterService::getAuthorizationUrl($redirectUri);
if (is_array($authData)) {
    session(['twitter_code_verifier' => $authData['code_verifier']]);
    return redirect($authData['url']);
}
```

#### For Token Refresh

**New Methods Available**:
```php
// LinkedIn & Twitter/X - Refresh tokens
$newTokens = LinkedInService::refreshAccessToken($refreshToken);
$newTokens = TwitterService::refreshAccessToken($refreshToken);

// Facebook & Instagram - Extend tokens
$longLivedTokens = FacebookService::extendAccessToken($shortLivedToken);
$longLivedTokens = InstagramService::extendAccessToken($shortLivedToken);
```

**Note**: This is a non-breaking release. All existing code continues to work. PKCE is optional and backward compatible.

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

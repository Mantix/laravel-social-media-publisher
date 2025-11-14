# Release Notes

## 🎉 Laravel Social Media Publisher v2.0.4 - Model Trait for Easy Integration

**Release Date**: November 14, 2025  
**Version**: 2.0.4  
**Type**: Minor Release

---

## ✨ What's New

### HasSocialMediaConnections Trait

A new trait that makes it incredibly easy to add social media connection functionality to any custom model:

- ✅ **Simple Integration**: Just add `use HasSocialMediaConnections;` to your model
- ✅ **Relationship Methods**: Automatic polymorphic relationships for all connections
- ✅ **Platform-Specific Methods**: Convenient methods for LinkedIn, Instagram, Facebook, and X
- ✅ **Helper Methods**: Easy-to-use methods for checking and retrieving connections

### Why Use the Trait?

Instead of manually defining relationships and methods in each model, you can now simply use the trait:

```php
use Illuminate\Database\Eloquent\Model;
use Mantix\LaravelSocialMediaPublisher\Traits\HasSocialMediaConnections;

class User extends Model
{
    use HasSocialMediaConnections;
    
    // That's it! All methods are now available
}
```

### Available Methods

#### Relationship Methods

- **`social_media_connections()`**: Get all social media connections (MorphMany)
- **`social_connection_linkedin()`**: Get latest LinkedIn connection (HasOne)
- **`social_connection_instagram()`**: Get latest Instagram connection (HasOne)
- **`social_connection_facebook()`**: Get latest Facebook connection (HasOne)
- **`social_connection_x()`**: Get latest X (Twitter) connection (HasOne)

#### Helper Methods

- **`getSocialConnection(string $platform)`**: Get active connection for a specific platform
- **`hasSocialConnection(string $platform)`**: Check if model has active connection for platform

### Usage Examples

```php
$user = User::find(1);

// Get all connections
$connections = $user->social_media_connections;

// Get platform-specific connections
$linkedinConnection = $user->social_connection_linkedin;
$facebookConnection = $user->social_connection_facebook;

// Check if user has active connection
if ($user->hasSocialConnection('linkedin')) {
    $linkedinService = SocialMedia::platform('linkedin', $user);
    $linkedinService->shareUrl('Hello LinkedIn!', 'https://example.com');
}

// Get active connection for a platform
$facebookConnection = $user->getSocialConnection('facebook');

// Use in queries
$usersWithFacebook = User::whereHas('social_media_connections', function ($query) {
    $query->where('platform', 'facebook')->where('is_active', true);
})->get();
```

### Benefits

1. **DRY Principle**: No need to duplicate relationship definitions across models
2. **Consistency**: All models using the trait have the same interface
3. **Maintainability**: Updates to connection logic only need to be made in one place
4. **Type Safety**: Full type hints and return types for better IDE support
5. **Query Optimization**: Relationships are optimized for efficient database queries

---

## 🎯 Migration Guide

### For Existing Users

**No migration required!** This is a non-breaking release. The trait is completely optional.

### Adding the Trait to Your Models

If you want to use the trait in your existing models:

1. **Add the trait to your model**:
   ```php
   use Mantix\LaravelSocialMediaPublisher\Traits\HasSocialMediaConnections;
   
   class User extends Model
   {
       use HasSocialMediaConnections;
   }
   ```

2. **Remove any duplicate relationship definitions** (if you have them):
   ```php
   // Remove these if you have them - the trait provides them
   // public function social_media_connections() { ... }
   // public function social_connection_linkedin() { ... }
   ```

3. **Update your code to use the trait methods** (optional):
   ```php
   // Before (if you had custom methods)
   $connection = $user->getFacebookConnection();
   
   // After (using trait method)
   $connection = $user->getSocialConnection('facebook');
   ```

---

## 📚 Documentation

### Updated Documentation

- **README.md**: Added comprehensive trait usage guide with examples
- **CHANGELOG.md**: Complete list of all changes

---

## 🎉 Laravel Social Media Publisher v2.0.3 - OAuth Security & Token Management Enhancements

**Release Date**: January 2025  
**Version**: 2.0.3  
**Type**: Minor Release

---

## 🔐 What's New

### PKCE Support (Proof Key for Code Exchange)

Enhanced OAuth security with optional PKCE support for LinkedIn and improved implementation for Twitter/X:

- ✅ **LinkedIn PKCE**: Optional PKCE support for enhanced security
- ✅ **Twitter/X PKCE**: Improved implementation with better code verifier storage
- ✅ **Backward Compatible**: All existing code continues to work without changes

### Token Management

Complete token refresh and extension support across all platforms:

- ✅ **Refresh Tokens**: LinkedIn and Twitter/X now support token refresh
- ✅ **Token Extension**: Facebook and Instagram support token extension (short-lived to long-lived)
- ✅ **Automatic Storage**: Refresh tokens automatically saved during OAuth callbacks

### LinkedIn Enhancements

New methods for managing LinkedIn company pages and user profiles:

- ✅ **Company Pages**: Get administered company pages with fallback logic
- ✅ **Organization Info**: Get detailed organization information
- ✅ **Custom Projections**: Enhanced user profile methods with custom projection support

---

## 🔄 OAuth Improvements

### PKCE Implementation

PKCE (Proof Key for Code Exchange) adds an extra layer of security to OAuth flows by preventing authorization code interception attacks.

#### LinkedIn PKCE (Optional)

```php
// Enable PKCE for LinkedIn
$authData = LinkedInService::getAuthorizationUrl(
    $redirectUri,
    $scopes,
    $state,
    true,  // Enable PKCE
    null   // Auto-generate code verifier
);

// Store code verifier in session
session(['linkedin_code_verifier' => $authData['code_verifier']]);

// Redirect to authorization URL
return redirect($authData['url']);
```

#### Twitter/X PKCE (Enabled by Default)

```php
// PKCE is enabled by default for Twitter/X
$authData = TwitterService::getAuthorizationUrl($redirectUri);

// Store code verifier in session
if (is_array($authData)) {
    session(['twitter_code_verifier' => $authData['code_verifier']]);
    return redirect($authData['url']);
}
```

### Token Refresh

#### For LinkedIn and Twitter/X

```php
// Refresh LinkedIn token
$newTokens = LinkedInService::refreshAccessToken($refreshToken);

// Refresh Twitter/X token
$newTokens = TwitterService::refreshAccessToken($refreshToken);

// Update connection with new tokens
$connection->update([
    'access_token' => $newTokens['access_token'],
    'refresh_token' => $newTokens['refresh_token'] ?? $refreshToken,
    'expires_at' => now()->addSeconds($newTokens['expires_in'] ?? 3600),
]);
```

#### For Facebook and Instagram

```php
// Extend Facebook token (short-lived to long-lived, 60 days)
$longLivedTokens = FacebookService::extendAccessToken($shortLivedToken);
// OR use alias for consistency
$longLivedTokens = FacebookService::refreshAccessToken($shortLivedToken);

// Extend Instagram token (same as Facebook)
$longLivedTokens = InstagramService::extendAccessToken($shortLivedToken);
```

**Note**: Facebook and Instagram don't use refresh tokens. When long-lived tokens expire (after 60 days), users must re-authenticate.

---

## 📚 New LinkedIn Methods

### Get Administered Company Pages

```php
$linkedinService = LinkedInService::forConnection($connection);
$companyPages = $linkedinService->getAdministeredCompanyPages();

// Returns array of pages:
// [
//     ['id' => '12345', 'name' => 'My Company'],
//     ['id' => '67890', 'name' => 'Another Company'],
// ]
```

### Get Organization Info

```php
$orgInfo = $linkedinService->getOrganizationInfo('12345');
// Returns full organization details from LinkedIn API
```

### Enhanced User Profile

```php
// Default projection
$userInfo = $linkedinService->getUserInfo();

// Custom projection
$userInfo = $linkedinService->getUserInfo('(id,localizedFirstName,localizedLastName)');

// Simple profile method
$profile = $linkedinService->getUserProfile();
```

---

## 🔧 OAuth Controller Updates

The default `OAuthController` has been updated to:

- ✅ Support PKCE code verifier retrieval from session
- ✅ Properly save refresh tokens to database
- ✅ Clear code verifiers from session after use
- ✅ Maintain backward compatibility with non-PKCE flows

**No changes required** - The controller automatically handles both PKCE and non-PKCE flows.

---

## 📖 Documentation

### New Documentation

- **OAuth Implementation Plan**: Comprehensive guide (`OAUTH_IMPLEMENTATION_PLAN.md`)
  - Feature comparison matrix
  - Platform-specific OAuth mechanisms
  - Complete code examples
  - Security best practices
  - Testing recommendations

### Updated Documentation

- **README.md**: Added PKCE examples and token refresh guides
- **CHANGELOG.md**: Complete list of all changes
- **Code Examples**: All examples updated with new methods

---

## 🎯 Migration Guide

### For Existing Users

**No migration required!** This is a non-breaking release. All existing code continues to work.

### Optional: Enable PKCE

If you want to enable PKCE for enhanced security:

1. **Update Authorization Routes**:
   ```php
   // LinkedIn with PKCE
   $authData = LinkedInService::getAuthorizationUrl($redirectUri, [], null, true);
   session(['linkedin_code_verifier' => $authData['code_verifier']]);
   return redirect($authData['url']);
   ```

2. **OAuthController automatically handles PKCE** - no changes needed to callbacks

### Using Token Refresh

If you want to implement automatic token refresh:

```php
// Check if token is expired
if ($connection->isExpired() && $connection->refresh_token) {
    $refreshToken = $connection->getDecryptedRefreshToken();
    
    try {
        $newTokens = LinkedInService::refreshAccessToken($refreshToken);
        $connection->update([
            'access_token' => $newTokens['access_token'],
            'refresh_token' => $newTokens['refresh_token'] ?? $refreshToken,
            'expires_at' => now()->addSeconds($newTokens['expires_in'] ?? 3600),
        ]);
    } catch (\Exception $e) {
        // Handle refresh failure - may need to re-authenticate
    }
}
```

---

## 🔐 Security Improvements

### PKCE Benefits

- **Prevents Authorization Code Interception**: Even if authorization code is intercepted, attacker cannot exchange it without code verifier
- **No Client Secret Required**: PKCE allows public clients (mobile apps, SPAs) to securely use OAuth
- **Industry Standard**: Recommended by OAuth 2.1 specification

### Best Practices

1. **Always use PKCE** when available (LinkedIn, Twitter/X)
2. **Store code verifiers securely** (session, encrypted cache, database)
3. **Never embed code verifier in state parameter** (security risk)
4. **Clear code verifiers after use** (OAuthController does this automatically)

---

## 📊 Platform Support Matrix

| Platform | PKCE Support | Refresh Token | Token Extension | Status |
|----------|-------------|---------------|----------------|--------|
| LinkedIn | ✅ Optional | ✅ Yes | ❌ No | ✅ Complete |
| Twitter/X | ✅ Optional (default) | ✅ Yes | ❌ No | ✅ Complete |
| Facebook | ❌ No | ❌ No | ✅ Yes | ✅ Complete |
| Instagram | ❌ No | ❌ No | ✅ Yes | ✅ Complete |

---

## 🐛 Bug Fixes

- Fixed Twitter/X callback to properly retrieve code verifier from session
- Fixed LinkedIn callback to support PKCE code verifier
- Fixed refresh token storage in OAuthController

---

## 📝 Full Changelog

See [CHANGELOG.md](./CHANGELOG.md) for complete list of changes.

---

## 🎉 Laravel Social Media Publisher v2.0.2 - API Cleanup & Method Naming Improvements

**Release Date**: November 14, 2025  
**Version**: 2.0.2  
**Type**: Minor Release

---

## 🔄 What's Changed

### Method Renaming for Clarity
To improve API clarity and consistency, the following methods have been renamed:

- ✅ **`share()` → `shareUrl()`**: Renamed across all services to clearly indicate URL sharing
- ✅ **Removed deprecated methods**: All non-owner methods have been removed from `SocialMediaManager`
- ✅ **Removed "ForOwner" suffix**: Since owners are always required, cleaner method names without suffix
- ✅ **Added `shareText()`**: New method for text-only posts (owners always required)

### Why This Change?
The new naming makes it clear that:
- `shareText()` - Shares text-only content (no URL) for a specific owner
- `shareUrl()` - Shares content with a URL for a specific owner
- `shareImage()` - Shares images for a specific owner
- `shareVideo()` - Shares videos for a specific owner

Since owners are always required (OAuth connections), the "ForOwner" suffix has been removed for cleaner API naming.

### Removed Deprecated Methods
The following methods have been **removed** from `SocialMediaManager`:
- ❌ `shareUrl()` (old version without owner) - Use `shareUrl($owner, ...)` instead
- ❌ `shareImage()` (old version without owner) - Use `shareImage($owner, ...)` instead
- ❌ `shareVideo()` (old version without owner) - Use `shareVideo($owner, ...)` instead
- ❌ `shareUrlToAll()` - Use `shareUrl()` with all platforms
- ❌ `shareImageToAll()` - Use `shareImage()` with all platforms
- ❌ `shareVideoToAll()` - Use `shareVideo()` with all platforms

### Migration Guide

**⚠️ BREAKING CHANGE**: If you're using the old method names, you'll need to update your code.

#### Before (v2.0.0 - v2.0.1):
```php
// Old method names (NO LONGER WORK)
$service->share('Hello', 'https://example.com');
SocialMedia::share(['facebook', 'twitter'], 'Hello', 'https://example.com');
SocialMedia::shareToAll('Hello', 'https://example.com');
SocialMedia::shareForOwner($user, ['facebook'], 'Hello', 'https://example.com');
```

#### After (v2.0.2+):
```php
// New method names - ALL require owner (no "ForOwner" suffix needed)
$user = User::find(1);

// Text-only posts
SocialMedia::shareText($user, ['facebook', 'twitter'], 'Hello World!');

// URL posts
SocialMedia::shareUrl($user, ['facebook', 'twitter'], 'Hello', 'https://example.com');

// Image posts
SocialMedia::shareImage($user, ['instagram', 'pinterest'], 'Check this out!', 'https://example.com/image.jpg');

// Video posts
SocialMedia::shareVideo($user, ['youtube', 'tiktok'], 'Watch this!', 'https://example.com/video.mp4');
```

### Updated Services
All platform services have been updated:
- ✅ LinkedInService - `share()` → `shareUrl()`, added `shareText()`
- ✅ FacebookService - `share()` → `shareUrl()`, added `shareText()`
- ✅ TwitterService - `share()` → `shareUrl()`, added `shareText()`
- ✅ TelegramService - `share()` → `shareUrl()`, added `shareText()`
- ✅ InstagramService - `share()` → `shareUrl()`
- ✅ PinterestService - `share()` → `shareUrl()`
- ✅ TikTokService - `share()` → `shareUrl()`
- ✅ YouTubeService - `share()` → `shareUrl()`

### Updated Facades
All facades have been updated with new method signatures:
- ✅ SocialMedia facade - Clean method names without "ForOwner" suffix (owners always required)
- ✅ All platform facades - Updated method signatures

---

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

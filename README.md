# Laravel Social Media Publisher

[![Latest Version on Packagist](https://img.shields.io/packagist/v/mantix/laravel-social-media-publisher.svg?style=flat-square)](https://packagist.org/packages/mantix/laravel-social-media-publisher)
[![Total Downloads](https://img.shields.io/packagist/dt/mantix/laravel-social-media-publisher.svg?style=flat-square)](https://packagist.org/packages/mantix/laravel-social-media-publisher)
[![License](https://img.shields.io/packagist/l/mantix/laravel-social-media-publisher.svg?style=flat-square)](https://packagist.org/packages/mantix/laravel-social-media-publisher)

A production-ready Laravel package for omnichannel social media publishing across **8 major platforms**. Post to one platform or all simultaneously with a unified API, handling complex media uploads (chunked, resumable) automatically.

## 🌟 Features

- **8 Supported Platforms**: Facebook, X (Twitter), LinkedIn, Instagram, TikTok, YouTube, Pinterest, Telegram.
- **Polymorphic Connections**: Users, Companies, or *any* Eloquent model can connect their own social accounts.
- **Production Ready Media**:
  - **YouTube**: Resumable Upload Protocol for large video files.
  - **X (Twitter)**: Chunked Media Uploads (INIT -> APPEND -> FINALIZE).
  - **TikTok**: Binary stream uploading via API v2.
  - **Instagram**: Async Container Status Polling for reliable publishing.
- **Unified & Specific APIs**: Use the generic `SocialMedia` facade for bulk posting, or platform-specific facades (e.g., `YouTube`) for deep features like analytics.
- **Security**: Built-in OAuth 2.0 with **PKCE** support (required for X/LinkedIn). Tokens are encrypted at rest.

## 📊 Supported Features Matrix

Not all platforms support all content types via API. This package enforces these limits to prevent API errors.

| Platform | Text Post | Link Post | Image Post | Video Post | Notes |
| :--- | :---: | :---: | :---: | :---: | :--- |
| **Facebook** | ✅ | ✅ | ✅ | ✅ | Pages only (Profiles deprecated by Meta). |
| **X (Twitter)** | ✅ | ✅ | ✅ | ✅ | Video uses chunked uploads. |
| **LinkedIn** | ✅ | ✅ | ✅ | ✅ | Personal Profiles & Company Pages. |
| **Telegram** | ✅ | ✅ | ✅ | ✅ | Channels & Groups via Bot API. |
| **Pinterest** | ❌ | ❌ | ✅ | ✅ | Requires Image/Video. Must select Board. |
| **Instagram** | ❌ | ❌ | ✅ | ✅ | Business/Creator Accounts only. |
| **TikTok** | ❌ | ❌ | ❌ | ✅ | Strict Video-only API. |
| **YouTube** | ❌ | ❌ | ❌ | ✅ | Strict Video-only API. |

---

## 🚀 Installation

### 1. Install via Composer

```bash
composer require mantix/laravel-social-media-publisher
````

### 2\. Publish Configuration & Migrations

```bash
php artisan vendor:publish --provider="Mantix\LaravelSocialMediaPublisher\SocialShareServiceProvider"
php artisan migrate
```

This creates the `social_media_connections` table which stores encrypted access tokens linked to your models.

### 3\. Configure `.env`

Add your client credentials. **Note:** Telegram uses a Bot Token; all others use OAuth 2.0 Client IDs.

```env
# Facebook / Instagram
FACEBOOK_CLIENT_ID=your_app_id
FACEBOOK_CLIENT_SECRET=your_app_secret

# X (Twitter)
X_CLIENT_ID=your_client_id
X_CLIENT_SECRET=your_client_secret

# LinkedIn
LINKEDIN_CLIENT_ID=your_client_id
LINKEDIN_CLIENT_SECRET=your_client_secret

# TikTok
TIKTOK_CLIENT_ID=your_client_key
TIKTOK_CLIENT_SECRET=your_client_secret

# YouTube
YOUTUBE_CLIENT_ID=your_client_id
YOUTUBE_CLIENT_SECRET=your_client_secret

# Pinterest
PINTEREST_CLIENT_ID=your_app_id
PINTEREST_CLIENT_SECRET=your_app_secret

# Telegram
TELEGRAM_BOT_TOKEN=your_bot_token
```

-----

## 🔑 Authentication (OAuth Flow)

You must create routes to let users connect their accounts. This package handles the complexity of PKCE and Token Exchange automatically.

**Add to `routes/web.php`:**

```php
use Illuminate\Support\Facades\Route;
use Mantix\LaravelSocialMediaPublisher\Facades\X;
use Mantix\LaravelSocialMediaPublisher\Facades\LinkedIn;

// 1. X (Twitter) - Requires PKCE
Route::get('/auth/twitter', function () {
    $redirectUri = route('social-media.x.callback');
    
    // Returns array: ['url' => '...', 'code_verifier' => '...']
    $authData = X::getAuthorizationUrl($redirectUri);
    
    // IMPORTANT: Store verifier in session for the callback
    session(['x_code_verifier' => $authData['code_verifier']]);
    
    return redirect($authData['url']);
})->name('social-media.twitter.authorize');

// 2. LinkedIn - PKCE Recommended
Route::get('/auth/linkedin', function () {
    $redirectUri = route('social-media.linkedin.callback');
    
    $authData = LinkedIn::getAuthorizationUrl($redirectUri, [], null, true); // true = enable PKCE
    session(['linkedin_code_verifier' => $authData['code_verifier']]);
    
    return redirect($authData['url']);
})->name('social-media.linkedin.authorize');

// ... Repeat for other platforms using their respective Facades
```

**Note:** The callback routes (e.g., `/auth/x/callback`) are **automatically registered** by the package. They handle the token exchange and save the connection to the database linked to the currently authenticated user (`auth()->user()`).

-----

## 📖 Usage

### 1\. Unified Publishing (The "Share Everywhere" Button)

Use the `SocialMedia` facade to post to multiple platforms at once. Be mindful of platform limitations (e.g., don't send text-only content to TikTok).

```php
use Mantix\LaravelSocialMediaPublisher\Facades\SocialMedia;

$user = User::find(1); // The owner of the connections

// A. Text & Link Posts
// Supported: Facebook, X, LinkedIn, Telegram
$textPlatforms = ['facebook', 'twitter', 'linkedin', 'telegram'];
SocialMedia::shareUrl($user, $textPlatforms, 'Check out our new feature!', '[https://example.com/feature](https://example.com/feature)');

// B. Image Posts
// Supported: Facebook, X, LinkedIn, Instagram, Pinterest, Telegram
// Note: YouTube and TikTok will throw exceptions if included here.
$imagePlatforms = ['facebook', 'twitter', 'instagram', 'pinterest'];
SocialMedia::shareImage($user, $imagePlatforms, 'Visual Update', '[https://example.com/image.jpg](https://example.com/image.jpg)');

// C. Video Posts
// Supported: ALL Platforms
// Note: Large videos are automatically chunked/resumed.
$allPlatforms = SocialMedia::getAvailablePlatforms();
SocialMedia::shareVideo($user, $allPlatforms, 'Watch our launch video', '[https://example.com/video.mp4](https://example.com/video.mp4)');
```

### 2\. Specific Platform Features

Sometimes you need specific features (Analytics, Carousels, Company Pages). Use the `SocialMedia::platform()` factory or the specific Facade with `forConnection()`.

#### 📸 Instagram (Business/Creator)

```php
use Mantix\LaravelSocialMediaPublisher\Facades\Instagram;

$user = User::find(1);
$instagram = SocialMedia::platform('instagram', $user);

// Publish Carousel
$instagram->shareCarousel('My Album', [
    '[https://site.com/1.jpg](https://site.com/1.jpg)',
    '[https://site.com/2.jpg](https://site.com/2.jpg)'
]);

// Publish Story
$instagram->shareStoryImage('[https://site.com/story.jpg](https://site.com/story.jpg)');
```

#### 💼 LinkedIn (Profile vs. Page)

```php
use Mantix\LaravelSocialMediaPublisher\Facades\LinkedIn;

$user = User::find(1);
$linkedin = SocialMedia::platform('linkedin', $user);

// Post to Personal Profile
$linkedin->shareText('My personal thoughts...');

// Post to Company Page (If connection is set to organization)
// Or use shareToCompanyPage if you have the URN handy
$linkedin->shareToCompanyPage('Official Company Update', '[https://company.com](https://company.com)');
```

#### 📺 YouTube (Resumable Uploads)

```php
use Mantix\LaravelSocialMediaPublisher\Facades\YouTube;

$user = User::find(1);
$youtube = SocialMedia::platform('youtube', $user);

// Uploads video using Resumable Protocol (handles 1GB+ files)
$youtube->shareVideo('My Vlog', '/local/path/to/video.mp4');

// Get Analytics
$stats = $youtube->getChannelInfo();
```

#### 📌 Pinterest (Boards)

```php
use Mantix\LaravelSocialMediaPublisher\Facades\Pinterest;

$user = User::find(1);
$pinterest = SocialMedia::platform('pinterest', $user);

// Create a specific Pin with destination link
// Note: Pinterest API v5 requires a Board ID
$pinterest->createPin([
    'title' => 'Delicious Recipe',
    'description' => 'Try this out!',
    'link' => '[https://recipes.com/pasta](https://recipes.com/pasta)',
    'media_source' => [
        'source_type' => 'image_url',
        'url' => '[https://recipes.com/img/pasta.jpg](https://recipes.com/img/pasta.jpg)'
    ]
], 'specific_board_id');
```

#### 🎵 TikTok (Videos Only)

```php
use Mantix\LaravelSocialMediaPublisher\Facades\TikTok;

$user = User::find(1);
$tiktok = SocialMedia::platform('tiktok', $user);

// Uploads via Binary Stream
$tiktok->shareVideo('Dance challenge!', '[https://site.com/video.mp4](https://site.com/video.mp4)');
```

#### 🐦 X (formerly Twitter)

```php
use Mantix\LaravelSocialMediaPublisher\Facades\X;

$user = User::find(1);
$x = SocialMedia::platform('twitter', $user);

// Post Tweet
$x->shareText('Hello World from Laravel!');

// Post Video (Chunked Upload handled automatically)
$x->shareVideo('My Video', '[https://site.com/video.mp4](https://site.com/video.mp4)');
```

-----

## 🛠 Advanced Usage

### Using the Trait

Add the `HasSocialMediaConnections` trait to your User or Company models to easily access their connections.

```php
use Mantix\LaravelSocialMediaPublisher\Traits\HasSocialMediaConnections;

class User extends Authenticatable {
    use HasSocialMediaConnections;
}

// Usage
$user->hasSocialConnection('facebook'); // true/false
$connection = $user->getSocialConnection('youtube'); // Returns SocialMediaConnection model
```

### Error Handling

The package throws `Mantix\LaravelSocialMediaPublisher\Exceptions\SocialMediaException` for all known API errors.

```php
try {
    SocialMedia::shareText($user, ['twitter'], 'Too long...');
} catch (SocialMediaException $e) {
    // Logs are also automatically written to storage/logs/laravel.log
    return back()->with('error', $e->getMessage());
}
```

### Manual Service Instantiation

If you need to manually instantiate a service (instead of using `forConnection()` or `SocialMedia::platform()`), you must call `setCredentials()` after construction:

```php
use Mantix\LaravelSocialMediaPublisher\Services\LinkedInService;

// Create instance
$linkedin = new LinkedInService();

// Set credentials
$linkedin->setCredentials($accessToken, $authorUrn);

// Now you can use the service
$linkedin->shareText('Hello LinkedIn!');
```

**Note:** The recommended approach is to use `forConnection()` or `SocialMedia::platform()`, which handle credential setting automatically.

### Disconnecting Social Media Accounts

You can disconnect social media accounts by revoking tokens and deleting connection records:

```php
use Mantix\LaravelSocialMediaPublisher\Models\SocialMediaConnection;

// Get a connection
$connection = SocialMediaConnection::where('platform', 'linkedin')
    ->where('owner_id', $user->id)
    ->first();

// Disconnect (revokes token on platform and deletes connection)
$connection->disconnect();

// Or disconnect without revoking token (just delete local record)
$connection->disconnect(revokeToken: false);

// Or use the service directly to revoke a token
use Mantix\LaravelSocialMediaPublisher\Facades\LinkedIn;

LinkedIn::disconnect($accessToken);
```

**Note:** The `disconnect()` method on `SocialMediaConnection` automatically:
1. Revokes the access token on the platform (if supported)
2. Deletes the connection record from your database
3. Handles errors gracefully (e.g., if token is already invalid)

-----

## 📄 License

This package is open-sourced software licensed under the [MIT license](https://www.google.com/search?q=LICENSE).
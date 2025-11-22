# Laravel Social Media Publisher - Examples

This directory contains comprehensive examples demonstrating how to use the Laravel Social Media Publisher package effectively.

## 📁 Directory Structure

```
examples/
├── README.md                    # This file
├── basic-usage/                 # Basic usage examples
│   ├── single-platform.php     # Single platform publishing
│   ├── multi-platform.php      # Multi-platform publishing
│   └── error-handling.php      # Error handling examples
├── advanced-usage/              # Advanced features
│   ├── content-scheduling.php  # Content scheduling
│   ├── analytics.php           # Analytics and insights
│   └── bulk-operations.php     # Bulk operations
├── platform-specific/          # Platform-specific examples
│   ├── facebook-examples.php   # Facebook-specific features
│   ├── instagram-examples.php  # Instagram-specific features
│   ├── linkedin-examples.php   # LinkedIn-specific features
│   └── youtube-examples.php    # YouTube-specific features
├── integration/                 # Integration examples
│   ├── laravel-commands.php    # Laravel Artisan commands
│   ├── laravel-jobs.php        # Laravel queue jobs
│   └── laravel-events.php      # Laravel event listeners
└── testing/                     # Testing examples
    ├── unit-tests.php          # Unit test examples
    ├── feature-tests.php       # Feature test examples
    └── mock-examples.php       # Mock API examples
```

## 🚀 Quick Start

1. **Basic Single Platform publishing**
   ```bash
   php examples/basic-usage/single-platform.php
   ```

2. **Multi-Platform publishing**
   ```bash
   php examples/basic-usage/multi-platform.php
   ```

3. **Error Handling**
   ```bash
   php examples/basic-usage/error-handling.php
   ```

## 📚 Example Categories

### Basic Usage
- Single platform publishing
- Multi-platform publishing
- Error handling and validation

### Advanced Usage
- Content scheduling
- Analytics and insights
- Bulk operations

### Platform-Specific
- Facebook Pages and Analytics
- Instagram Stories and Carousels
- LinkedIn Company Pages
- YouTube Community Posts

### Integration
- Laravel Artisan Commands
- Laravel Queue Jobs
- Laravel Event Listeners

### Testing
- Unit tests
- Feature tests
- API mocking

## 🔧 Prerequisites

Before running the examples, ensure you have:

1. **Laravel Application** with the package installed
2. **Environment Variables** configured for your social media accounts
3. **API Credentials** for the platforms you want to test
4. **Composer Dependencies** installed

## 📝 Environment Setup

Create a `.env.example` file with all required credentials:

```env
# Facebook
FACEBOOK_ACCESS_TOKEN=your_facebook_access_token
FACEBOOK_PAGE_ID=your_facebook_page_id

# X (Twitter)
X_BEARER_TOKEN=your_x_bearer_token
X_API_KEY=your_x_api_key
X_API_SECRET=your_x_api_secret
X_ACCESS_TOKEN=your_x_access_token
X_ACCESS_TOKEN_SECRET=your_x_access_token_secret

# LinkedIn
LINKEDIN_ACCESS_TOKEN=your_linkedin_access_token
LINKEDIN_PERSON_URN=your_linkedin_person_urn
LINKEDIN_ORGANIZATION_URN=your_linkedin_organization_urn

# Instagram
INSTAGRAM_ACCESS_TOKEN=your_instagram_access_token
INSTAGRAM_ACCOUNT_ID=your_instagram_account_id

# TikTok
TIKTOK_ACCESS_TOKEN=your_tiktok_access_token
TIKTOK_CLIENT_KEY=your_tiktok_client_key
TIKTOK_CLIENT_SECRET=your_tiktok_client_secret

# YouTube
YOUTUBE_API_KEY=your_youtube_api_key
YOUTUBE_ACCESS_TOKEN=your_youtube_access_token
YOUTUBE_CHANNEL_ID=your_youtube_channel_id

# Pinterest
PINTEREST_ACCESS_TOKEN=your_pinterest_access_token
PINTEREST_BOARD_ID=your_pinterest_board_id

# Telegram
TELEGRAM_BOT_TOKEN=your_telegram_bot_token
TELEGRAM_CHAT_ID=your_telegram_chat_id
```

## ⚠️ Important Notes

- **API Limits**: Be aware of API rate limits for each platform
- **Test Environment**: Use test accounts for development
- **Credentials**: Never commit real API credentials to version control
- **Permissions**: Ensure your API tokens have the necessary permissions

## 🤝 Contributing

If you have additional examples or improvements, please submit a pull request!

## 📞 Support

For questions or issues with the examples, please:
1. Check the main package documentation
2. Review the test files for usage patterns
3. Open an issue on the GitHub repository

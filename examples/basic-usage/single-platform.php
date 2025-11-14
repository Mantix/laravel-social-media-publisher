<?php

/**
 * Single Platform Publishing Examples
 * 
 * This file demonstrates how to post content to individual social media platforms
 * using the Laravel Social Media Publisher package.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Mantix\LaravelSocialMediaPublisher\Facades\FaceBook;
use Mantix\LaravelSocialMediaPublisher\Facades\Twitter;
use Mantix\LaravelSocialMediaPublisher\Facades\LinkedIn;
use Mantix\LaravelSocialMediaPublisher\Facades\Instagram;
use Mantix\LaravelSocialMediaPublisher\Facades\TikTok;
use Mantix\LaravelSocialMediaPublisher\Facades\YouTube;
use Mantix\LaravelSocialMediaPublisher\Facades\Pinterest;
use Mantix\LaravelSocialMediaPublisher\Facades\Telegram;
use Mantix\LaravelSocialMediaPublisher\Exceptions\SocialMediaException;

echo "🚀 Laravel Social Media Publisher - Single Platform Examples\n";
echo "====================================================\n\n";

// Example 1: Facebook Publishing
echo "📘 Facebook Examples\n";
echo "-------------------\n";

try {
    // Basic text post with URL
    $result = FaceBook::share('Check out our latest blog post!', 'https://example.com/blog/latest');
    echo "✅ Facebook post created: " . ($result['id'] ?? 'Unknown ID') . "\n";
    
    // Image post
    $result = FaceBook::shareImage('Amazing sunset from our office!', 'https://example.com/images/sunset.jpg');
    echo "✅ Facebook image post created: " . ($result['id'] ?? 'Unknown ID') . "\n";
    
    // Get page insights
    $insights = FaceBook::getPageInsights(['page_impressions', 'page_engaged_users']);
    echo "✅ Facebook insights retrieved: " . count($insights['data'] ?? []) . " metrics\n";
    
} catch (SocialMediaException $e) {
    echo "❌ Facebook error: " . $e->getMessage() . "\n";
}

echo "\n";

// Example 2: Twitter Publishing
echo "🐦 Twitter Examples\n";
echo "------------------\n";

try {
    // Basic tweet
    $result = Twitter::share('Just launched our new feature! 🚀', 'https://example.com/feature');
    echo "✅ Twitter post created: " . ($result['data']['id'] ?? 'Unknown ID') . "\n";
    
    // Tweet with image
    $result = Twitter::shareImage('Behind the scenes of our development process', 'https://example.com/images/dev.jpg');
    echo "✅ Twitter image post created: " . ($result['data']['id'] ?? 'Unknown ID') . "\n";
    
    // Get user timeline
    $timeline = Twitter::getTimeline(5);
    echo "✅ Twitter timeline retrieved: " . count($timeline['data'] ?? []) . " tweets\n";
    
} catch (SocialMediaException $e) {
    echo "❌ Twitter error: " . $e->getMessage() . "\n";
}

echo "\n";

// Example 3: LinkedIn Publishing
echo "💼 LinkedIn Examples\n";
echo "-------------------\n";

try {
    // Personal post
    $result = LinkedIn::share('Excited to share our company milestone!', 'https://example.com/milestone');
    echo "✅ LinkedIn post created: " . ($result['id'] ?? 'Unknown ID') . "\n";
    
    // Company page post
    $result = LinkedIn::shareToCompanyPage('Company update: We\'re hiring!', 'https://example.com/careers');
    echo "✅ LinkedIn company post created: " . ($result['id'] ?? 'Unknown ID') . "\n";
    
    // Get user info
    $userInfo = LinkedIn::getUserInfo();
    echo "✅ LinkedIn user info retrieved: " . ($userInfo['firstName'] ?? 'Unknown') . " " . ($userInfo['lastName'] ?? 'User') . "\n";
    
} catch (SocialMediaException $e) {
    echo "❌ LinkedIn error: " . $e->getMessage() . "\n";
}

echo "\n";

// Example 4: Instagram Publishing
echo "📸 Instagram Examples\n";
echo "--------------------\n";

try {
    // Image post
    $result = Instagram::shareImage('Beautiful morning at the office ☀️', 'https://example.com/images/morning.jpg');
    echo "✅ Instagram image post created: " . ($result['id'] ?? 'Unknown ID') . "\n";
    
    // Carousel post
    $images = [
        'https://example.com/images/img1.jpg',
        'https://example.com/images/img2.jpg',
        'https://example.com/images/img3.jpg'
    ];
    $result = Instagram::shareCarousel('Our team working on amazing projects!', $images);
    echo "✅ Instagram carousel created: " . ($result['id'] ?? 'Unknown ID') . "\n";
    
    // Get account info
    $accountInfo = Instagram::getAccountInfo();
    echo "✅ Instagram account info retrieved: " . ($accountInfo['username'] ?? 'Unknown') . "\n";
    
} catch (SocialMediaException $e) {
    echo "❌ Instagram error: " . $e->getMessage() . "\n";
}

echo "\n";

// Example 5: TikTok Publishing
echo "🎵 TikTok Examples\n";
echo "-----------------\n";

try {
    // Video post
    $result = TikTok::shareVideo('Quick tutorial on our new feature!', 'https://example.com/videos/tutorial.mp4');
    echo "✅ TikTok video post created: " . ($result['data']['video_id'] ?? 'Unknown ID') . "\n";
    
    // Get user info
    $userInfo = TikTok::getUserInfo();
    echo "✅ TikTok user info retrieved: " . ($userInfo['data']['display_name'] ?? 'Unknown') . "\n";
    
} catch (SocialMediaException $e) {
    echo "❌ TikTok error: " . $e->getMessage() . "\n";
}

echo "\n";

// Example 6: YouTube Publishing
echo "📺 YouTube Examples\n";
echo "------------------\n";

try {
    // Video upload
    $result = YouTube::shareVideo('Complete guide to our new feature', 'https://example.com/videos/guide.mp4');
    echo "✅ YouTube video uploaded: " . ($result['id'] ?? 'Unknown ID') . "\n";
    
    // Community post
    $result = YouTube::createCommunityPost('What would you like to see in our next video?', 'https://example.com/poll');
    echo "✅ YouTube community post created: " . ($result['id'] ?? 'Unknown ID') . "\n";
    
    // Get channel info
    $channelInfo = YouTube::getChannelInfo();
    echo "✅ YouTube channel info retrieved: " . ($channelInfo['items'][0]['snippet']['title'] ?? 'Unknown') . "\n";
    
} catch (SocialMediaException $e) {
    echo "❌ YouTube error: " . $e->getMessage() . "\n";
}

echo "\n";

// Example 7: Pinterest Publishing
echo "📌 Pinterest Examples\n";
echo "--------------------\n";

try {
    // Create pin
    $result = Pinterest::createPin('Amazing recipe for chocolate cake!', 'https://example.com/images/cake.jpg');
    echo "✅ Pinterest pin created: " . ($result['id'] ?? 'Unknown ID') . "\n";
    
    // Create board
    $result = Pinterest::createBoard('My Recipes', 'Collection of amazing recipes');
    echo "✅ Pinterest board created: " . ($result['id'] ?? 'Unknown ID') . "\n";
    
    // Get user info
    $userInfo = Pinterest::getUserInfo();
    echo "✅ Pinterest user info retrieved: " . ($userInfo['username'] ?? 'Unknown') . "\n";
    
} catch (SocialMediaException $e) {
    echo "❌ Pinterest error: " . $e->getMessage() . "\n";
}

echo "\n";

// Example 8: Telegram Publishing
echo "📱 Telegram Examples\n";
echo "-------------------\n";

try {
    // Send message
    $result = Telegram::share('New blog post is live!', 'https://example.com/blog/new');
    echo "✅ Telegram message sent: " . ($result['result']['message_id'] ?? 'Unknown ID') . "\n";
    
    // Send image
    $result = Telegram::shareImage('Check out this amazing photo!', 'https://example.com/images/photo.jpg');
    echo "✅ Telegram image sent: " . ($result['result']['message_id'] ?? 'Unknown ID') . "\n";
    
    // Send document
    $result = Telegram::shareDocument('Important company document', 'https://example.com/docs/report.pdf');
    echo "✅ Telegram document sent: " . ($result['result']['message_id'] ?? 'Unknown ID') . "\n";
    
} catch (SocialMediaException $e) {
    echo "❌ Telegram error: " . $e->getMessage() . "\n";
}

echo "\n";
echo "🎉 Single platform examples completed!\n";
echo "=====================================\n";

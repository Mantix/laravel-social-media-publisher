<?php

/**
 * Multi-Platform Publishing Examples
 * 
 * This file demonstrates how to post content to multiple social media platforms
 * simultaneously using the unified SocialMedia facade.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use mantix\LaravelSocialMediaPublisher\Exceptions\SocialMediaException;
use mantix\LaravelSocialMediaPublisher\Facades\SocialMedia;

echo "🚀 Laravel Social Media Publisher - Multi-Platform Examples\n";
echo "====================================================\n\n";

// Example 1: Post to Specific Platforms
echo "📱 Post to Specific Platforms\n";
echo "-----------------------------\n";

try {
    $platforms = ['facebook', 'twitter', 'linkedin'];
    $result = SocialMedia::share($platforms, 'Exciting news! We just launched our new feature! 🚀', 'https://example.com/feature');
    
    echo "✅ Posted to " . $result['success_count'] . " out of " . $result['total_platforms'] . " platforms\n";
    
    foreach ($result['results'] as $platform => $platformResult) {
        if ($platformResult['success']) {
            echo "   ✅ {$platform}: Success\n";
        } else {
            echo "   ❌ {$platform}: " . $platformResult['error'] . "\n";
        }
    }
    
    if (!empty($result['errors'])) {
        echo "\n⚠️  Errors encountered:\n";
        foreach ($result['errors'] as $platform => $error) {
            echo "   - {$platform}: {$error}\n";
        }
    }
    
} catch (SocialMediaException $e) {
    echo "❌ Multi-platform error: " . $e->getMessage() . "\n";
}

echo "\n";

// Example 2: Post to All Available Platforms
echo "🌍 Post to All Available Platforms\n";
echo "----------------------------------\n";

try {
    $result = SocialMedia::shareToAll('Weekly company update: Great progress this week! 📈', 'https://example.com/weekly-update');
    
    echo "✅ Posted to " . $result['success_count'] . " out of " . $result['total_platforms'] . " platforms\n";
    
    $successfulPlatforms = [];
    $failedPlatforms = [];
    
    foreach ($result['results'] as $platform => $platformResult) {
        if ($platformResult['success']) {
            $successfulPlatforms[] = $platform;
        } else {
            $failedPlatforms[] = $platform;
        }
    }
    
    if (!empty($successfulPlatforms)) {
        echo "✅ Successful platforms: " . implode(', ', $successfulPlatforms) . "\n";
    }
    
    if (!empty($failedPlatforms)) {
        echo "❌ Failed platforms: " . implode(', ', $failedPlatforms) . "\n";
    }
    
} catch (SocialMediaException $e) {
    echo "❌ Share to all error: " . $e->getMessage() . "\n";
}

echo "\n";

// Example 3: Share Images to Visual Platforms
echo "🖼️  Share Images to Visual Platforms\n";
echo "------------------------------------\n";

try {
    $visualPlatforms = ['facebook', 'instagram', 'pinterest'];
    $result = SocialMedia::shareImage($visualPlatforms, 'Beautiful sunset from our office window! 🌅', 'https://example.com/images/sunset.jpg');
    
    echo "✅ Image shared to " . $result['success_count'] . " out of " . $result['total_platforms'] . " platforms\n";
    
    foreach ($result['results'] as $platform => $platformResult) {
        if ($platformResult['success']) {
            $postId = $platformResult['data']['id'] ?? 'Unknown ID';
            echo "   ✅ {$platform}: Post ID {$postId}\n";
        } else {
            echo "   ❌ {$platform}: " . $platformResult['error'] . "\n";
        }
    }
    
} catch (SocialMediaException $e) {
    echo "❌ Image sharing error: " . $e->getMessage() . "\n";
}

echo "\n";

// Example 4: Share Videos to Video Platforms
echo "🎥 Share Videos to Video Platforms\n";
echo "----------------------------------\n";

try {
    $videoPlatforms = ['youtube', 'tiktok', 'facebook'];
    $result = SocialMedia::shareVideo($videoPlatforms, 'Quick tutorial on how to use our new feature!', 'https://example.com/videos/tutorial.mp4');
    
    echo "✅ Video shared to " . $result['success_count'] . " out of " . $result['total_platforms'] . " platforms\n";
    
    foreach ($result['results'] as $platform => $platformResult) {
        if ($platformResult['success']) {
            $videoId = $platformResult['data']['id'] ?? $platformResult['data']['video_id'] ?? 'Unknown ID';
            echo "   ✅ {$platform}: Video ID {$videoId}\n";
        } else {
            echo "   ❌ {$platform}: " . $platformResult['error'] . "\n";
        }
    }
    
} catch (SocialMediaException $e) {
    echo "❌ Video sharing error: " . $e->getMessage() . "\n";
}

echo "\n";

// Example 5: Platform-Specific Operations
echo "🎯 Platform-Specific Operations\n";
echo "-------------------------------\n";

try {
    // LinkedIn company page post
    $linkedinResult = SocialMedia::linkedin()->shareToCompanyPage('Company milestone achieved! 🎉', 'https://example.com/milestone');
    echo "✅ LinkedIn company post: " . ($linkedinResult['id'] ?? 'Unknown ID') . "\n";
    
    // Instagram carousel
    $carouselImages = [
        'https://example.com/images/img1.jpg',
        'https://example.com/images/img2.jpg',
        'https://example.com/images/img3.jpg'
    ];
    $instagramResult = SocialMedia::instagram()->shareCarousel('Behind the scenes of our development process', $carouselImages);
    echo "✅ Instagram carousel: " . ($instagramResult['id'] ?? 'Unknown ID') . "\n";
    
    // YouTube community post
    $youtubeResult = SocialMedia::youtube()->createCommunityPost('What topics would you like us to cover next?', 'https://example.com/survey');
    echo "✅ YouTube community post: " . ($youtubeResult['id'] ?? 'Unknown ID') . "\n";
    
    // Pinterest board creation
    $pinterestResult = SocialMedia::pinterest()->createBoard('Company Updates', 'Latest news and updates from our company');
    echo "✅ Pinterest board: " . ($pinterestResult['id'] ?? 'Unknown ID') . "\n";
    
} catch (SocialMediaException $e) {
    echo "❌ Platform-specific error: " . $e->getMessage() . "\n";
}

echo "\n";

// Example 6: Error Handling and Recovery
echo "🛡️  Error Handling and Recovery\n";
echo "-------------------------------\n";

try {
    // Try to post to platforms with some potentially failing
    $platforms = ['facebook', 'twitter', 'nonexistent_platform', 'linkedin'];
    $result = SocialMedia::share($platforms, 'Testing error handling!', 'https://example.com/test');
    
    echo "📊 Results Summary:\n";
    echo "   Total platforms: " . $result['total_platforms'] . "\n";
    echo "   Successful: " . $result['success_count'] . "\n";
    echo "   Failed: " . $result['error_count'] . "\n";
    
    if ($result['error_count'] > 0) {
        echo "\n⚠️  Failed platforms:\n";
        foreach ($result['errors'] as $platform => $error) {
            echo "   - {$platform}: {$error}\n";
        }
    }
    
    if ($result['success_count'] > 0) {
        echo "\n✅ Successful platforms:\n";
        foreach ($result['results'] as $platform => $platformResult) {
            if ($platformResult['success']) {
                echo "   - {$platform}: Posted successfully\n";
            }
        }
    }
    
} catch (SocialMediaException $e) {
    echo "❌ Error handling test failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Example 7: Platform Availability Check
echo "🔍 Platform Availability Check\n";
echo "------------------------------\n";

try {
    $manager = app(\mantix\LaravelSocialMediaPublisher\Services\SocialMediaManager::class);
    
    $availablePlatforms = $manager->getAvailablePlatforms();
    echo "📋 Available platforms: " . implode(', ', $availablePlatforms) . "\n";
    
    $testPlatforms = ['facebook', 'twitter', 'linkedin', 'instagram', 'tiktok', 'youtube', 'pinterest', 'telegram', 'nonexistent'];
    
    echo "\n🔍 Platform availability check:\n";
    foreach ($testPlatforms as $platform) {
        $isAvailable = $manager->isPlatformAvailable($platform);
        $status = $isAvailable ? '✅ Available' : '❌ Not available';
        echo "   {$platform}: {$status}\n";
    }
    
} catch (Exception $e) {
    echo "❌ Platform availability check failed: " . $e->getMessage() . "\n";
}

echo "\n";
echo "🎉 Multi-platform examples completed!\n";
echo "====================================\n";

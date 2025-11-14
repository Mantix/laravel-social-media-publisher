<?php

/**
 * Facebook-Specific Examples
 * 
 * This file demonstrates Facebook-specific features including Pages, Analytics,
 * Video uploads, and advanced publishing options.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Mantix\LaravelSocialMediaPublisher\Facades\FaceBook;
use Mantix\LaravelSocialMediaPublisher\Exceptions\SocialMediaException;

echo "📘 Facebook-Specific Examples\n";
echo "============================\n\n";

// Example 1: Basic Page Publishing
echo "📝 Basic Page Publishing\n";
echo "---------------------\n";

try {
    // Text post with link
    $result = FaceBook::share('Check out our latest blog post about Laravel best practices!', 'https://example.com/blog/laravel-best-practices');
    echo "✅ Facebook post created: " . ($result['id'] ?? 'Unknown ID') . "\n";
    
    // Image post
    $result = FaceBook::shareImage('Beautiful sunset from our office! 🌅', 'https://example.com/images/sunset.jpg');
    echo "✅ Facebook image post created: " . ($result['id'] ?? 'Unknown ID') . "\n";
    
    // Video post
    $result = FaceBook::shareVideo('Quick tutorial on our new feature!', 'https://example.com/videos/tutorial.mp4');
    echo "✅ Facebook video post created: " . ($result['id'] ?? 'Unknown ID') . "\n";
    
} catch (SocialMediaException $e) {
    echo "❌ Facebook publishing error: " . $e->getMessage() . "\n";
}

echo "\n";

// Example 2: Page Analytics and Insights
echo "📊 Page Analytics and Insights\n";
echo "-----------------------------\n";

try {
    // Get basic page information
    $pageInfo = FaceBook::getPageInfo();
    echo "📋 Page Information:\n";
    echo "   Name: " . ($pageInfo['name'] ?? 'Unknown') . "\n";
    echo "   Category: " . ($pageInfo['category'] ?? 'Unknown') . "\n";
    echo "   Followers: " . ($pageInfo['fan_count'] ?? 'Unknown') . "\n";
    echo "   Likes: " . ($pageInfo['likes'] ?? 'Unknown') . "\n";
    
    // Get page insights
    $insights = FaceBook::getPageInsights(['page_impressions', 'page_engaged_users', 'page_fan_adds']);
    echo "\n📈 Page Insights:\n";
    if (isset($insights['data'])) {
        foreach ($insights['data'] as $insight) {
            $name = $insight['name'] ?? 'Unknown';
            $values = $insight['values'] ?? [];
            $latestValue = end($values);
            $value = $latestValue['value'] ?? 'No data';
            echo "   {$name}: {$value}\n";
        }
    }
    
    // Get specific date range insights
    $dateRangeInsights = FaceBook::getPageInsights(
        ['page_impressions', 'page_engaged_users'],
        ['since' => '2024-01-01', 'until' => '2024-01-31']
    );
    echo "\n📅 January 2024 Insights:\n";
    if (isset($dateRangeInsights['data'])) {
        foreach ($dateRangeInsights['data'] as $insight) {
            $name = $insight['name'] ?? 'Unknown';
            $values = $insight['values'] ?? [];
            $totalValue = array_sum(array_column($values, 'value'));
            echo "   {$name}: {$totalValue}\n";
        }
    }
    
} catch (SocialMediaException $e) {
    echo "❌ Facebook analytics error: " . $e->getMessage() . "\n";
}

echo "\n";

// Example 3: Advanced Video Upload
echo "🎥 Advanced Video Upload\n";
echo "------------------------\n";

try {
    // Upload a large video file
    $videoUrl = 'https://example.com/videos/large-video.mp4';
    $result = FaceBook::shareVideo('Amazing product demonstration video!', $videoUrl);
    echo "✅ Large video uploaded: " . ($result['id'] ?? 'Unknown ID') . "\n";
    
    // The package handles chunked uploads automatically for large files
    echo "ℹ️  Video upload uses chunked upload for files larger than 10MB\n";
    
} catch (SocialMediaException $e) {
    echo "❌ Video upload error: " . $e->getMessage() . "\n";
}

echo "\n";

// Example 4: Post Engagement Tracking
echo "👥 Post Engagement Tracking\n";
echo "---------------------------\n";

try {
    // Create a post and track its engagement
    $result = FaceBook::share('Engagement test post - please like and share! 👍', 'https://example.com/engagement-test');
    $postId = $result['id'] ?? null;
    
    if ($postId) {
        echo "✅ Post created with ID: {$postId}\n";
        echo "ℹ️  You can track engagement using Facebook Graph API with post ID: {$postId}\n";
        
        // In a real application, you would store the post ID and periodically check engagement
        echo "💡 Tip: Store post IDs to track engagement over time\n";
    }
    
} catch (SocialMediaException $e) {
    echo "❌ Engagement tracking error: " . $e->getMessage() . "\n";
}

echo "\n";

// Example 5: Scheduled Publishing (Conceptual)
echo "⏰ Scheduled Publishing (Conceptual)\n";
echo "--------------------------------\n";

try {
    // Note: Facebook's API doesn't support direct scheduling, but you can implement this
    // using Laravel's task scheduler or queue system
    
    $scheduledPosts = [
        [
            'content' => 'Good morning! Start your day with our latest blog post.',
            'url' => 'https://example.com/blog/morning-motivation',
            'scheduled_time' => '2024-01-15 09:00:00'
        ],
        [
            'content' => 'Lunch break reading: 5 tips for better productivity.',
            'url' => 'https://example.com/blog/productivity-tips',
            'scheduled_time' => '2024-01-15 12:00:00'
        ],
        [
            'content' => 'End of day wrap-up: What we accomplished today.',
            'url' => 'https://example.com/blog/daily-wrap-up',
            'scheduled_time' => '2024-01-15 17:00:00'
        ]
    ];
    
    echo "📅 Scheduled Posts Queue:\n";
    foreach ($scheduledPosts as $index => $post) {
        echo "   " . ($index + 1) . ". {$post['scheduled_time']}: {$post['content']}\n";
    }
    
    echo "\n💡 Implementation Tip:\n";
    echo "   Use Laravel's task scheduler or queue system to post at scheduled times\n";
    echo "   Store scheduled posts in database and process them with cron jobs\n";
    
} catch (Exception $e) {
    echo "❌ Scheduling error: " . $e->getMessage() . "\n";
}

echo "\n";

// Example 6: Error Handling for Facebook-Specific Issues
echo "🛡️  Facebook-Specific Error Handling\n";
echo "-----------------------------------\n";

try {
    // Test various error scenarios
    $testCases = [
        [
            'description' => 'Empty caption',
            'caption' => '',
            'url' => 'https://example.com'
        ],
        [
            'description' => 'Invalid URL',
            'caption' => 'Test post',
            'url' => 'invalid-url'
        ],
        [
            'description' => 'Caption too long',
            'caption' => str_repeat('This is a very long caption. ', 100),
            'url' => 'https://example.com'
        ]
    ];
    
    foreach ($testCases as $testCase) {
        try {
            $result = FaceBook::share($testCase['caption'], $testCase['url']);
            echo "❌ {$testCase['description']}: Should have failed but didn't\n";
        } catch (SocialMediaException $e) {
            echo "✅ {$testCase['description']}: Caught error - " . $e->getMessage() . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error handling test failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Example 7: Facebook Page Management
echo "⚙️  Facebook Page Management\n";
echo "---------------------------\n";

try {
    // Get comprehensive page information
    $pageInfo = FaceBook::getPageInfo();
    
    echo "📋 Page Management Information:\n";
    echo "   Page ID: " . ($pageInfo['id'] ?? 'Unknown') . "\n";
    echo "   Page Name: " . ($pageInfo['name'] ?? 'Unknown') . "\n";
    echo "   Username: " . ($pageInfo['username'] ?? 'Not set') . "\n";
    echo "   Category: " . ($pageInfo['category'] ?? 'Unknown') . "\n";
    echo "   Website: " . ($pageInfo['website'] ?? 'Not set') . "\n";
    echo "   About: " . substr($pageInfo['about'] ?? 'Not set', 0, 100) . "...\n";
    
    // Get follower demographics (if available)
    $demographics = FaceBook::getPageInsights(['page_fans_by_age_gender', 'page_fans_by_country']);
    echo "\n👥 Follower Demographics:\n";
    if (isset($demographics['data'])) {
        foreach ($demographics['data'] as $demographic) {
            $name = $demographic['name'] ?? 'Unknown';
            echo "   {$name}: Available\n";
        }
    }
    
} catch (SocialMediaException $e) {
    echo "❌ Page management error: " . $e->getMessage() . "\n";
}

echo "\n";

// Example 8: Content Optimization Tips
echo "💡 Content Optimization Tips\n";
echo "----------------------------\n";

echo "📝 Best Practices for Facebook Posts:\n";
echo "   • Keep captions under 40 characters for better engagement\n";
echo "   • Use high-quality images (1200x630px recommended)\n";
echo "   • Post when your audience is most active\n";
echo "   • Use relevant hashtags (but don't overdo it)\n";
echo "   • Ask questions to encourage engagement\n";
echo "   • Share behind-the-scenes content\n";
echo "   • Use video content for higher reach\n";

echo "\n📊 Engagement Optimization:\n";
echo "   • Post images get 2.3x more engagement than text posts\n";
echo "   • Videos get 1.5x more engagement than images\n";
echo "   • Posts with questions get 23% more engagement\n";
echo "   • Posts with emojis get 33% more engagement\n";

echo "\n⏰ Optimal Publishing Times:\n";
echo "   • Best days: Tuesday, Wednesday, Thursday\n";
echo "   • Best times: 9:00 AM, 1:00 PM, 3:00 PM\n";
echo "   • Avoid: Early morning (before 8 AM) and late night (after 8 PM)\n";

echo "\n";
echo "🎉 Facebook-specific examples completed!\n";
echo "=======================================\n";

<?php

/**
 * Error Handling Examples
 * 
 * This file demonstrates proper error handling when using the Laravel Social Media Publisher package.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Mantix\LaravelSocialMediaPublisher\Exceptions\SocialMediaException;
use Mantix\LaravelSocialMediaPublisher\Facades\FaceBook;
use Mantix\LaravelSocialMediaPublisher\Facades\SocialMedia;
use Mantix\LaravelSocialMediaPublisher\Facades\X;

echo "🛡️  Laravel Social Media Publisher - Error Handling Examples\n";
echo "=====================================================\n\n";

// Example 1: Basic Error Handling
echo "🔧 Basic Error Handling\n";
echo "-----------------------\n";

try {
    $result = FaceBook::share('Test post', 'https://example.com');
    echo "✅ Facebook post successful: " . ($result['id'] ?? 'Unknown ID') . "\n";
} catch (SocialMediaException $e) {
    echo "❌ Facebook error: " . $e->getMessage() . "\n";
    echo "   Error code: " . $e->getCode() . "\n";
    echo "   File: " . $e->getFile() . ":" . $e->getLine() . "\n";
} catch (Exception $e) {
    echo "❌ General error: " . $e->getMessage() . "\n";
}

echo "\n";

// Example 2: Multi-Platform Error Handling
echo "🌍 Multi-Platform Error Handling\n";
echo "--------------------------------\n";

try {
    $platforms = ['facebook', 'x', 'linkedin'];
    $result = SocialMedia::share($platforms, 'Test multi-platform post', 'https://example.com');
    
    echo "📊 Results Summary:\n";
    echo "   Total platforms: " . $result['total_platforms'] . "\n";
    echo "   Successful: " . $result['success_count'] . "\n";
    echo "   Failed: " . $result['error_count'] . "\n";
    
    // Check individual platform results
    foreach ($result['results'] as $platform => $platformResult) {
        if ($platformResult['success']) {
            echo "   ✅ {$platform}: Success\n";
        } else {
            echo "   ❌ {$platform}: " . $platformResult['error'] . "\n";
        }
    }
    
    // Handle errors
    if (!empty($result['errors'])) {
        echo "\n⚠️  Error Details:\n";
        foreach ($result['errors'] as $platform => $error) {
            echo "   - {$platform}: {$error}\n";
        }
    }
    
} catch (SocialMediaException $e) {
    echo "❌ Multi-platform error: " . $e->getMessage() . "\n";
}

echo "\n";

// Example 3: Input Validation Errors
echo "✅ Input Validation Errors\n";
echo "-------------------------\n";

// Test empty caption
try {
    $result = X::share('', 'https://example.com');
    echo "❌ Should have failed with empty caption\n";
} catch (SocialMediaException $e) {
    echo "✅ Caught empty caption error: " . $e->getMessage() . "\n";
}

// Test invalid URL
try {
    $result = X::share('Test post', 'invalid-url');
    echo "❌ Should have failed with invalid URL\n";
} catch (SocialMediaException $e) {
    echo "✅ Caught invalid URL error: " . $e->getMessage() . "\n";
}

// Test caption too long
try {
    $longCaption = str_repeat('This is a very long caption. ', 100); // Over 280 characters
    $result = X::share($longCaption, 'https://example.com');
    echo "❌ Should have failed with caption too long\n";
} catch (SocialMediaException $e) {
    echo "✅ Caught caption too long error: " . $e->getMessage() . "\n";
}

echo "\n";

// Example 4: API Authentication Errors
echo "🔐 API Authentication Errors\n";
echo "----------------------------\n";

// This would typically happen with invalid tokens
try {
    // Simulate invalid token by using a mock service
    $result = FaceBook::share('Test post', 'https://example.com');
    echo "✅ Facebook post successful (or handled gracefully)\n";
} catch (SocialMediaException $e) {
    if (strpos($e->getMessage(), 'token') !== false || strpos($e->getMessage(), 'auth') !== false) {
        echo "✅ Caught authentication error: " . $e->getMessage() . "\n";
    } else {
        echo "❌ Unexpected error: " . $e->getMessage() . "\n";
    }
}

echo "\n";

// Example 5: Network and Timeout Errors
echo "🌐 Network and Timeout Errors\n";
echo "-----------------------------\n";

try {
    // This would typically happen with network issues
    $result = X::share('Test post', 'https://example.com');
    echo "✅ X post successful (or handled gracefully)\n";
} catch (SocialMediaException $e) {
    if (strpos($e->getMessage(), 'timeout') !== false || 
        strpos($e->getMessage(), 'network') !== false ||
        strpos($e->getMessage(), 'connection') !== false) {
        echo "✅ Caught network/timeout error: " . $e->getMessage() . "\n";
    } else {
        echo "❌ Unexpected error: " . $e->getMessage() . "\n";
    }
}

echo "\n";

// Example 6: Rate Limiting Errors
echo "⏱️  Rate Limiting Errors\n";
echo "-----------------------\n";

try {
    // This would typically happen when hitting rate limits
    $result = LinkedIn::share('Test post', 'https://example.com');
    echo "✅ LinkedIn post successful (or handled gracefully)\n";
} catch (SocialMediaException $e) {
    if (strpos($e->getMessage(), 'rate') !== false || 
        strpos($e->getMessage(), 'limit') !== false ||
        strpos($e->getMessage(), '429') !== false) {
        echo "✅ Caught rate limiting error: " . $e->getMessage() . "\n";
    } else {
        echo "❌ Unexpected error: " . $e->getMessage() . "\n";
    }
}

echo "\n";

// Example 7: Graceful Degradation
echo "🔄 Graceful Degradation\n";
echo "----------------------\n";

function postWithFallback($platforms, $caption, $url) {
    $results = [];
    $errors = [];
    
    foreach ($platforms as $platform) {
        try {
            $result = SocialMedia::platform($platform)->share($caption, $url);
            $results[$platform] = $result;
            echo "✅ {$platform}: Posted successfully\n";
        } catch (SocialMediaException $e) {
            $errors[$platform] = $e->getMessage();
            echo "❌ {$platform}: " . $e->getMessage() . "\n";
            
            // Try alternative approach for some platforms
            if ($platform === 'facebook') {
                try {
                    echo "   🔄 Trying Facebook image post as fallback...\n";
                    $result = SocialMedia::facebook()->shareImage($caption, 'https://via.placeholder.com/800x600');
                    $results[$platform . '_fallback'] = $result;
                    echo "   ✅ Facebook fallback: Success\n";
                } catch (SocialMediaException $fallbackError) {
                    echo "   ❌ Facebook fallback: " . $fallbackError->getMessage() . "\n";
                }
            }
        }
    }
    
    return ['results' => $results, 'errors' => $errors];
}

$platforms = ['facebook', 'x', 'linkedin'];
$fallbackResults = postWithFallback($platforms, 'Test post with fallback', 'https://example.com');

echo "\n📊 Fallback Results Summary:\n";
echo "   Successful posts: " . count($fallbackResults['results']) . "\n";
echo "   Failed posts: " . count($fallbackResults['errors']) . "\n";

echo "\n";

// Example 8: Logging and Monitoring
echo "📝 Logging and Monitoring\n";
echo "-------------------------\n";

try {
    // The package automatically logs all operations
    $result = SocialMedia::share(['facebook', 'x'], 'Test post for logging', 'https://example.com');
    
    echo "✅ Post completed - check your Laravel logs for detailed information\n";
    echo "   Look for log entries with 'Social media API request' or 'Failed to post'\n";
    
} catch (SocialMediaException $e) {
    echo "❌ Error occurred - check logs for details: " . $e->getMessage() . "\n";
}

echo "\n";

// Example 9: Custom Error Handling
echo "🎯 Custom Error Handling\n";
echo "------------------------\n";

class SocialMediaErrorHandler {
    public static function handlePost($platforms, $caption, $url) {
        $results = [];
        $retryQueue = [];
        
        foreach ($platforms as $platform) {
            try {
                $result = SocialMedia::platform($platform)->share($caption, $url);
                $results[$platform] = $result;
                echo "✅ {$platform}: Posted successfully\n";
            } catch (SocialMediaException $e) {
                echo "❌ {$platform}: " . $e->getMessage() . "\n";
                
                // Determine if we should retry
                if (self::shouldRetry($e)) {
                    $retryQueue[] = $platform;
                    echo "   🔄 Added to retry queue\n";
                } else {
                    echo "   ⏹️  Not retryable\n";
                }
            }
        }
        
        // Retry failed posts
        if (!empty($retryQueue)) {
            echo "\n🔄 Retrying failed posts...\n";
            foreach ($retryQueue as $platform) {
                try {
                    $result = SocialMedia::platform($platform)->share($caption, $url);
                    $results[$platform] = $result;
                    echo "✅ {$platform}: Retry successful\n";
                } catch (SocialMediaException $e) {
                    echo "❌ {$platform}: Retry failed - " . $e->getMessage() . "\n";
                }
            }
        }
        
        return $results;
    }
    
    private static function shouldRetry(SocialMediaException $e): bool {
        $message = strtolower($e->getMessage());
        
        // Retry on network errors, timeouts, and rate limits
        return strpos($message, 'timeout') !== false ||
               strpos($message, 'network') !== false ||
               strpos($message, 'connection') !== false ||
               strpos($message, 'rate') !== false ||
               strpos($message, '429') !== false;
    }
}

$customResults = SocialMediaErrorHandler::handlePost(['facebook', 'x'], 'Custom error handling test', 'https://example.com');

echo "\n📊 Custom Error Handling Results:\n";
echo "   Total successful posts: " . count($customResults) . "\n";

echo "\n";
echo "🎉 Error handling examples completed!\n";
echo "====================================\n";

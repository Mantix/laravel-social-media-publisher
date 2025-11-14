<?php

namespace mantix\LaravelSocialMediaPublisher\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use mantix\LaravelSocialMediaPublisher\Exceptions\SocialMediaException;

abstract class SocialMediaService
{
    /**
     * Check if logging is enabled.
     *
     * @return bool
     */
    protected function isLoggingEnabled(): bool
    {
        return config('social_media_publisher.enable_logging', true);
    }

    /**
     * Log a message if logging is enabled.
     *
     * @param string $level The log level (info, warning, error, debug).
     * @param string $message The log message.
     * @param array $context Additional context data.
     * @return void
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        if (!$this->isLoggingEnabled()) {
            return;
        }

        Log::{$level}($message, $context);
    }

    /**
     * Send HTTP request with error handling and retry logic.
     *
     * @param string $url The request URL.
     * @param string $method The HTTP method.
     * @param array $params The request parameters.
     * @param array $headers Additional headers.
     * @return array Response data.
     * @throws SocialMediaException
     */
    protected function sendRequest(string $url, string $method = 'post', array $params = [], array $headers = []): array
    {
        $maxRetries = config('social_media_publisher.retry_attempts', 3);
        $timeout = config('social_media_publisher.timeout', 30);
        
        $this->log('debug', 'Initiating social media API request', [
            'url' => $url,
            'method' => strtoupper($method),
            'has_params' => !empty($params),
            'has_headers' => !empty($headers),
        ]);
        
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $response = Http::timeout($timeout)
                    ->withHeaders($headers)
                    ->{$method}($url, $params);

                if (!$response->successful()) {
                    $errorMessage = $this->extractErrorMessage($response);
                    
                    if ($attempt === $maxRetries) {
                        $this->log('error', 'Social media API request failed after all retries', [
                            'url' => $url,
                            'method' => strtoupper($method),
                            'status' => $response->status(),
                            'error' => $errorMessage,
                            'attempts' => $attempt,
                            'response_body' => $response->body(),
                        ]);
                        
                        throw new SocialMediaException("API request failed: {$errorMessage}");
                    }
                    
                    $this->log('warning', 'Social media API request failed, retrying', [
                        'url' => $url,
                        'status' => $response->status(),
                        'error' => $errorMessage,
                        'attempt' => $attempt,
                        'max_attempts' => $maxRetries,
                    ]);
                    
                    // Wait before retry (exponential backoff)
                    sleep(pow(2, $attempt - 1));
                    continue;
                }

                $data = $response->json();
                
                $this->log('info', 'Social media API request successful', [
                    'url' => $url,
                    'method' => strtoupper($method),
                    'status' => $response->status(),
                    'attempt' => $attempt,
                ]);
                
                return $data;
                
            } catch (\Exception $e) {
                if ($attempt === $maxRetries) {
                    $this->log('error', 'Social media API request failed with exception', [
                        'url' => $url,
                        'method' => strtoupper($method),
                        'error' => $e->getMessage(),
                        'exception' => get_class($e),
                        'attempts' => $attempt,
                        'trace' => $e->getTraceAsString(),
                    ]);
                    
                    throw new SocialMediaException("Request failed: " . $e->getMessage());
                }
                
                $this->log('warning', 'Social media API request failed with exception, retrying', [
                    'url' => $url,
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                    'attempt' => $attempt,
                    'max_attempts' => $maxRetries,
                ]);
                
                // Wait before retry
                sleep(pow(2, $attempt - 1));
            }
        }
        
        throw new SocialMediaException('Request failed after all retry attempts');
    }

    /**
     * Extract error message from response.
     *
     * @param \Illuminate\Http\Client\Response $response The HTTP response.
     * @return string The error message.
     */
    private function extractErrorMessage($response): string
    {
        $data = $response->json();
        
        if (isset($data['error']['message'])) {
            return $data['error']['message'];
        }
        
        if (isset($data['message'])) {
            return $data['message'];
        }
        
        if (isset($data['error'])) {
            return is_string($data['error']) ? $data['error'] : json_encode($data['error']);
        }
        
        return "HTTP {$response->status()}: {$response->body()}";
    }

    /**
     * Validate URL format.
     *
     * @param string $url The URL to validate.
     * @throws SocialMediaException
     */
    protected function validateUrl(string $url): void
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new SocialMediaException('Invalid URL provided: ' . $url);
        }
    }

    /**
     * Validate text content.
     *
     * @param string $text The text to validate.
     * @param int $maxLength Maximum allowed length.
     * @throws SocialMediaException
     */
    protected function validateText(string $text, int $maxLength = 1000): void
    {
        if (empty(trim($text))) {
            throw new SocialMediaException('Text content cannot be empty.');
        }
        
        if (strlen($text) > $maxLength) {
            throw new SocialMediaException("Text content exceeds maximum length of {$maxLength} characters.");
        }
    }

    /**
     * Download file from URL with error handling.
     *
     * @param string $url The file URL.
     * @return string The downloaded file content.
     * @throws SocialMediaException
     */
    protected function downloadFile(string $url): string
    {
        $this->validateUrl($url);
        
        $context = stream_context_create([
            'http' => [
                'timeout' => config('social_media_publisher.timeout', 30),
                'user_agent' => 'Laravel Social Media Publisher Package'
            ]
        ]);
        
        $content = file_get_contents($url, false, $context);
        
        if ($content === false) {
            throw new SocialMediaException('Failed to download file from URL: ' . $url);
        }
        
        return $content;
    }
}
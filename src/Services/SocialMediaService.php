<?php

namespace Mantix\LaravelSocialMediaPublisher\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mantix\LaravelSocialMediaPublisher\Exceptions\SocialMediaException;

abstract class SocialMediaService {
    /**
     * Helper to log messages based on config configuration.
     *
     * @param string $level
     * @param string $message
     * @param array $context
     */
    protected function log(string $level, string $message, array $context = []): void {
        if (config('social_media_publisher.enable_logging', true)) {
            Log::log($level, $message, $context);
        }
    }

    /**
     * Send an HTTP request with built-in retry logic and standardized error handling.
     *
     * @param string $method get, post, put, delete
     * @param string $url Full URL
     * @param array $params Query parameters or Body fields
     * @param array $headers Additional headers
     * @return array
     * @throws SocialMediaException
     */
    protected function sendRequest(string $method, string $url, array $params = [], array $headers = []): array {
        $maxRetries = (int) config('social_media_publisher.retry_attempts', 3);
        $timeout = (int) config('social_media_publisher.timeout', 60);
        $sleepMs = 1000; // Wait 1s between retries (exponential backoff handled by Laravel/Guzzle usually)

        $this->log('debug', "API Request: [{$method}] {$url}", [
            'params_count' => count($params),
        ]);

        try {
            // Use Laravel's built-in retry mechanism
            $response = Http::timeout($timeout)
                ->retry($maxRetries, $sleepMs, function ($exception, $request) {
                    return $exception instanceof \Illuminate\Http\Client\ConnectionException ||
                        $exception->response->status() >= 500 ||
                        $exception->response->status() === 429;
                })
                ->withHeaders($headers)
                ->$method($url, $params);

            // Throw exception for 400/500 errors
            if (!$response->successful()) {
                $this->handleRequestError($response, $url);
            }

            return $response->json() ?? [];
        } catch (\Exception $e) {
            // If it's already our custom exception, rethrow it
            if ($e instanceof SocialMediaException) {
                throw $e;
            }

            // Log unexpected errors (connection issues, etc)
            $this->log('error', "API Connection Failed: {$url}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw new SocialMediaException("Connection failed: " . $e->getMessage());
        }
    }

    /**
     * Handle API errors and throw standardized exception.
     * * @param \Illuminate\Http\Client\Response $response
     * @param string $url
     * @throws SocialMediaException
     */
    protected function handleRequestError($response, string $url): void {
        $status = $response->status();
        $body = $response->json();

        // Attempt to extract a readable message from various API standards
        $message = $body['error']['message']
            ?? $body['message']
            ?? $body['error_description']
            ?? $response->body();

        // Recursively clean message if it is an array
        if (is_array($message)) {
            $message = json_encode($message);
        }

        $this->log('error', "API Error {$status}: {$url}", [
            'response' => $body
        ]);

        throw new SocialMediaException("API Error ({$status}): {$message}");
    }

    /**
     * Validate and download a remote file to a temporary local path.
     * Useful for Video Uploads that require a file stream.
     *
     * @param string $url
     * @return string Path to the temporary file
     * @throws SocialMediaException
     */
    protected function getTemporaryFilePath(string $url): string {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            // If it's not a URL, assume it's already a local path and validate existence
            if (file_exists($url)) {
                return $url;
            }
            throw new SocialMediaException("Invalid file URL or path: {$url}");
        }

        try {
            $content = Http::timeout(60)->get($url)->throw()->body();

            // Create a temp file
            $tempPath = tempnam(sys_get_temp_dir(), 'social_media_upload_');
            file_put_contents($tempPath, $content);

            return $tempPath;
        } catch (\Exception $e) {
            throw new SocialMediaException("Failed to download file from {$url}: " . $e->getMessage());
        }
    }

    /**
     * Validate text length helper.
     *
     * @param string $text
     * @param int $max
     * @throws SocialMediaException
     */
    protected function validateText(string $text, int $max): void {
        if (mb_strlen($text) > $max) {
            throw new SocialMediaException("Caption exceeds maximum length of {$max} characters.");
        }
    }
}

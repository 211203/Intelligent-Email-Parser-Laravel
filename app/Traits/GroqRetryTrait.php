<?php

namespace App\Traits;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

trait GroqRetryTrait
{
    /**
     * Make a Groq API call with exponential backoff retry on 429 rate limits.
     *
     * @param string $endpoint The API endpoint (e.g. '/openai/v1/chat/completions')
     * @param array<string, mixed> $payload The request body
     * @param int $maxRetries Maximum number of retry attempts
     * @param int $baseDelay Base delay in seconds (doubles each retry)
     * @return array<string, mixed>
     */
    private function groqWithRetry(string $endpoint, array $payload, int $maxRetries = 3, int $baseDelay = 2): array
    {
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            $response = $this->groqHttp()->post($endpoint, $payload);

            if ($response->status() === 429 && $attempt < $maxRetries) {
                $delay = $baseDelay * pow(2, $attempt - 1); // 2s, 4s, 8s
                Log::warning('Groq API rate limited, retrying', [
                    'class' => static::class,
                    'attempt' => $attempt,
                    'delay_seconds' => $delay,
                ]);
                sleep($delay);
                continue;
            }

            $response->throw();
            $json = $response->json();

            if (!is_array($json)) {
                throw new RuntimeException('Groq API did not return an array response');
            }

            return $json;
        }

        throw new RuntimeException('Groq API rate limit exceeded after ' . $maxRetries . ' retries');
    }

    private function groqHttp(): PendingRequest
    {
        $baseUrl = rtrim((string) env('GROQ_BASE_URL', 'https://api.groq.com'), '/');
        $apiKey = (string) env('GROQ_API_KEY');

        if ($apiKey === '') {
            throw new RuntimeException('Missing GROQ_API_KEY');
        }

        return Http::baseUrl($baseUrl)
            ->withToken($apiKey)
            ->acceptJson()
            ->asJson()
            ->timeout(60);
    }
}

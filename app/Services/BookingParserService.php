<?php

namespace App\Services;

use App\Helpers\EmailPreprocessor;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class BookingParserService
{
    public function __construct(private readonly EmailPreprocessor $emailPreprocessor)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function parse(string $rawText, ?string $clientName = null, ?string $clientAttributes = null): array
    {
        $text = $this->emailPreprocessor->preprocess($rawText);

        $schema = [
            'guest_name' => 'string|null',
            'booking_id' => 'string|null',
            'check_in_date' => 'string|null',
            'check_out_date' => 'string|null',
            'guest_email' => 'string|null',
            'guest_phone' => 'string|null',
            'total_amount' => 'number|null',
        ];

        // Load any client-specific keyword fields and add them to the schema.
        $clientKeywords = $this->loadClientKeywords($clientName);
        foreach ($clientKeywords as $keyword) {
            $key = $this->keywordToSchemaKey($keyword);
            if (! array_key_exists($key, $schema)) {
                $schema[$key] = 'string|null';
            }
        }

        // If no explicit clientAttributes were provided, resolve them from the keyword configuration.
        if ($clientAttributes === null) {
            $clientAttributes = $this->resolveClientAttributes($clientName, $clientKeywords);
        }

        $expectedKeys = array_keys($schema);

        $prompt = $this->buildPrompt($text, $clientName, $clientAttributes, $schema, $expectedKeys);

        $resp = $this->groqHttp()
            ->post('/openai/v1/chat/completions', [
                'model' => env('GROQ_MODEL', 'llama-3.3-70b-versatile'),
                'messages' => [
                    ['role' => 'system', 'content' => 'Return only valid JSON. Do not wrap in markdown.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0,
            ])
            ->throw()
            ->json();

        $content = Arr::get($resp, 'choices.0.message.content', '');
        if (!is_string($content) || trim($content) === '') {
            throw new RuntimeException('Groq parser returned empty content');
        }

        $data = $this->decodePossiblyMalformedJson($content);
        $data = $this->normalize($data);

        $this->validateRequiredShape($data, $expectedKeys);

        return $data;
    }

    /**
     * @param array<string, string> $schema
     * @param array<int, string> $expectedKeys
     */
    private function buildPrompt(string $text, ?string $clientName, ?string $clientAttributes, array $schema, array $expectedKeys): string
    {
        $clientLine = $clientName ? "Client name hint: {$clientName}\n" : '';
        $attributesLine = $clientAttributes ? "Client specific rules/attributes: {$clientAttributes}\n" : '';

        return $clientLine . $attributesLine
            . "Extract booking information from the following text and output STRICT JSON only.\n"
            . "Schema keys and types: " . json_encode($schema) . "\n"
            . "Rules:\n"
            . "- Output a JSON object with exactly these keys: " . implode(', ', $expectedKeys) . "\n"
            . "- Use null if a field is missing\n"
            . "- Dates must be ISO format YYYY-MM-DD if possible, else keep original string\n"
            . "- total_amount must be numeric if possible, else null\n"
            . "- guest_phone should include country code if present, else keep digits\n"
            . "Text:\n"
            . $text;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodePossiblyMalformedJson(string $content): array
    {
        $trimmed = trim($content);

        $trimmed = preg_replace('/^```(?:json)?\s*/i', '', $trimmed) ?? $trimmed;
        $trimmed = preg_replace('/\s*```$/', '', $trimmed) ?? $trimmed;

        $first = strpos($trimmed, '{');
        $last = strrpos($trimmed, '}');
        if ($first !== false && $last !== false && $last > $first) {
            $trimmed = substr($trimmed, $first, $last - $first + 1);
        }

        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $trimmed = preg_replace("/\x{00A0}/u", ' ', $trimmed) ?? $trimmed;
        $trimmed = preg_replace('/,\s*}/', '}', $trimmed) ?? $trimmed;

        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        throw new RuntimeException('Unable to decode JSON from Groq response');
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalize(array $data): array
    {
        $phone = Arr::get($data, 'guest_phone');
        if (is_string($phone)) {
            $digits = preg_replace('/[^0-9+]/', '', $phone) ?? $phone;
            $data['guest_phone'] = $digits === '' ? null : $digits;
        }

        $amount = Arr::get($data, 'total_amount');
        if (is_string($amount)) {
            $normalized = preg_replace('/[^0-9.]/', '', $amount) ?? '';
            $data['total_amount'] = $normalized === '' ? null : (float) $normalized;
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, string> $keys
     */
    private function validateRequiredShape(array $data, array $keys): void
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $data)) {
                throw new RuntimeException("Missing key in parsed JSON: {$key}");
            }
        }
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

    /**
     * @return array<int, string>
     */
    private function loadClientKeywords(?string $clientName): array
    {
        if ($clientName === null || trim($clientName) === '') {
            return [];
        }

        $path = base_path('config/booking_keywords.json');
        if (! is_file($path)) {
            return [];
        }

        $json = file_get_contents($path);
        if ($json === false) {
            return [];
        }

        $data = json_decode($json, true);
        if (! is_array($data)) {
            return [];
        }

        foreach ($data as $name => $config) {
            if (is_string($name) && strcasecmp($name, $clientName) === 0) {
                $keywords = $config['keywords'] ?? null;
                if (! is_array($keywords)) {
                    return [];
                }

                $keywords = array_values(array_filter(array_map('strval', $keywords)));

                return $keywords;
            }
        }

        return [];
    }

    /**
     * @param array<int, string> $clientKeywords
     */
    private function resolveClientAttributes(?string $clientName, array $clientKeywords): ?string
    {
        if ($clientName === null || trim($clientName) === '') {
            return null;
        }

        if ($clientKeywords === []) {
            return null;
        }

        return 'Preferred extraction keywords: ' . implode(', ', $clientKeywords);
    }

    private function keywordToSchemaKey(string $keyword): string
    {
        $key = strtolower($keyword);
        $key = preg_replace('/[^a-z0-9]+/', '_', $key) ?? $key;
        $key = trim($key, '_');

        if ($key === '') {
            $key = 'field_' . substr(md5($keyword), 0, 8);
        }

        return $key;
    }
}
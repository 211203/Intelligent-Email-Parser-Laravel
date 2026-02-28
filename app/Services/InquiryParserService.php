<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class InquiryParserService
{
    /**
     * @return array<string, mixed>
     */
    public function parse(string $rawText, ?string $clientName = null): array
    {
        $schema = [
            'check_in_date' => 'string|null',
            'check_out_date' => 'string|null',
            'number_of_guests' => 'integer|null',
            'number_of_rooms' => 'integer|null',
            'room_type_requested' => 'string|null',
            'intent_type' => 'string|required', // availability | pricing | reservation | general
        ];

        $expectedKeys = array_keys($schema);
        $prompt = $this->buildPrompt($rawText, $clientName, $schema, $expectedKeys);

        $response = $this->groqHttp()
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

        $content = $response['choices'][0]['message']['content'] ?? '';
        if (!is_string($content) || trim($content) === '') {
            throw new RuntimeException('InquiryParserService returned empty content');
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
    private function buildPrompt(string $text, ?string $clientName, array $schema, array $expectedKeys): string
    {
        $clientLine = $clientName ? "Client name hint: {$clientName}\n" : '';

        return $clientLine
            . "Extract inquiry information from the following text and output STRICT JSON only.\n"
            . "Schema keys and types: " . json_encode($schema) . "\n"
            . "Intent types: availability, pricing, reservation, general\n"
            . "Rules:\n"
            . "- Output a JSON object with exactly these keys: " . implode(', ', $expectedKeys) . "\n"
            . "- Use null if a field is missing\n"
            . "- Dates must be ISO format YYYY-MM-DD if possible, else keep original string\n"
            . "- number_of_guests and number_of_rooms must be integers if possible, else null\n"
            . "- intent_type is required and must be one of: availability, pricing, reservation, general\n"
            . "- room_type_requested: standard, deluxe, suite, etc.\n"
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

        throw new RuntimeException('Unable to decode JSON from InquiryParserService response');
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalize(array $data): array
    {
        // Normalize number_of_guests
        $guests = $data['number_of_guests'] ?? null;
        if (is_string($guests)) {
            $normalized = preg_replace('/[^0-9]/', '', $guests) ?? '';
            $data['number_of_guests'] = $normalized === '' ? null : (int) $normalized;
        }

        // Normalize number_of_rooms
        $rooms = $data['number_of_rooms'] ?? null;
        if (is_string($rooms)) {
            $normalized = preg_replace('/[^0-9]/', '', $rooms) ?? '';
            $data['number_of_rooms'] = $normalized === '' ? null : (int) $normalized;
        }

        // Normalize intent_type to lowercase
        if (isset($data['intent_type']) && is_string($data['intent_type'])) {
            $data['intent_type'] = strtolower($data['intent_type']);
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
                throw new RuntimeException("Missing key in parsed inquiry JSON: {$key}");
            }
        }

        // Validate intent_type specifically
        $validIntents = ['availability', 'pricing', 'reservation', 'general'];
        if (!in_array($data['intent_type'], $validIntents)) {
            throw new RuntimeException("Invalid intent_type: {$data['intent_type']}. Must be one of: " . implode(', ', $validIntents));
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
}

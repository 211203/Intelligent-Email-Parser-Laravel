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
    public function parse(string $rawText, ?string $clientName = null): array
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

        $prompt = $this->buildPrompt($text, $clientName, $schema);

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

        $this->validateRequiredShape($data);

        return $data;
    }

    /**
     * @param array<string, string> $schema
     */
    private function buildPrompt(string $text, ?string $clientName, array $schema): string
    {
        $clientLine = $clientName ? "Client name hint: {$clientName}\n" : '';

        return $clientLine
            . "Extract booking information from the following text and output STRICT JSON only.\n"
            . "Schema keys and types: " . json_encode($schema) . "\n"
            . "Rules:\n"
            . "- Output JSON object with exactly these keys: guest_name, booking_id, check_in_date, check_out_date, guest_email, guest_phone, total_amount\n"
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
     */
    private function validateRequiredShape(array $data): void
    {
        $keys = [
            'guest_name',
            'booking_id',
            'check_in_date',
            'check_out_date',
            'guest_email',
            'guest_phone',
            'total_amount',
        ];

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
}

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
        $clientKey = strtolower($clientName ?? 'default');
        
        $schemaPath = storage_path('app/schemas/booking_schema.json');
        $keywords = ['guest_name', 'booking_id', 'total_amount']; // minimum fallback
        
        if (file_exists($schemaPath)) {
            $schemaData = json_decode(file_get_contents($schemaPath), true);
            if (isset($schemaData[$clientKey]['keywords'])) {
                $keywords = $schemaData[$clientKey]['keywords'];
            } elseif (isset($schemaData['default']['keywords'])) {
                $keywords = $schemaData['default']['keywords'];
            }
        }

        // Build a dynamic LLM schema description based on the keywords
        $schema = [];
        $requiredKeys = [];
        foreach ($keywords as $kw) {
            $schemaKey = str_replace([' ', '-'], '_', strtolower($kw));
            $requiredKeys[] = $schemaKey;
            
            if (str_contains($schemaKey, 'date')) {
                $schema[$schemaKey] = 'string|null (YYYY-MM-DD format)';
            } elseif (str_contains($schemaKey, 'amount') || str_contains($schemaKey, 'total') || str_contains($schemaKey, 'number')) {
                $schema[$schemaKey] = 'number|null';
            } else {
                $schema[$schemaKey] = 'string|null';
            }
        }

        $prompt = $this->buildPrompt($text, $clientName, $schema, $requiredKeys);

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
        
        // Ensure all requested keys exist
        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $data)) {
                $data[$key] = null; // Fill missing fields gracefully
            }
        }

        return $data;
    }

    /**
     * @param array<string, string> $schema
     * @param array<int, string> $requiredKeys
     */
    private function buildPrompt(string $text, ?string $clientName, array $schema, array $requiredKeys): string
    {
        $clientLine = $clientName ? "Client name hint: {$clientName}\n" : '';
        $keysList = implode(', ', $requiredKeys);

        return $clientLine
            . "Extract booking information from the following text and output STRICT JSON only.\n"
            . "We need to extract specific fields defined by a custom schema.\n"
            . "Schema keys and expected types: \n" . json_encode($schema, JSON_PRETTY_PRINT) . "\n"
            . "Rules:\n"
            . "- Output JSON object with exactly these keys: {$keysList}\n"
            . "- Use null if a field is missing in the text.\n"
            . "- Dates must be ISO format YYYY-MM-DD if possible, else keep original string.\n"
            . "- Amounts/Totals must be numeric if possible, else null.\n"
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

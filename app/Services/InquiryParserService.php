<?php

namespace App\Services;

use App\Helpers\EmailPreprocessor;
use App\Traits\GroqRetryTrait;
use Illuminate\Support\Arr;
use RuntimeException;

class InquiryParserService
{
    use GroqRetryTrait;

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
        
        $schemaPath = storage_path('app/schemas/inquiry_schema.json');
        $keywords = ['intent type', 'check-in date', 'number of guests']; // minimum fallback
        
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
            } elseif (str_contains($schemaKey, 'number')) {
                $schema[$schemaKey] = 'number|null';
            } elseif ($schemaKey === 'intent_type') {
                $schema[$schemaKey] = '"availability"|"pricing"|"reservation"|"general"';
            } else {
                $schema[$schemaKey] = 'string|null';
            }
        }

        $prompt = $this->buildPrompt($text, $clientName, $schema, $requiredKeys);

        $resp = $this->groqWithRetry('/openai/v1/chat/completions', [
            'model' => env('GROQ_MODEL', 'llama-3.3-70b-versatile'),
            'messages' => [
                ['role' => 'system', 'content' => 'Return only valid JSON. Do not wrap in markdown.'],
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => 0,
        ]);

        $content = Arr::get($resp, 'choices.0.message.content', '');
        if (!is_string($content) || trim($content) === '') {
            throw new RuntimeException('Groq parser returned empty content');
        }

        $data = $this->decodePossiblyMalformedJson($content);
        
        // Final normalization for specific intents if present
        if (isset($data['intent_type']) && is_string($data['intent_type'])) {
            $normalizedIntent = strtolower(trim($data['intent_type']));
            $allowed = ['availability', 'pricing', 'reservation', 'general'];
            $data['intent_type'] = in_array($normalizedIntent, $allowed, true) ? $normalizedIntent : 'general';
        }

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
        $keysList = implode(', ', $requiredKeys);
        $currentDate = now()->toDateString();
        $currentYear = now()->year;
        $schemaJson = json_encode($schema);

        $prompt = "Extract hotel inquiry details as JSON.\n"
            . "Keys: {$keysList}\n"
            . "Types: {$schemaJson}\n"
            . "Today: {$currentDate}. Default year: {$currentYear}. Dates MUST BE YYYY-MM-DD format. intent_type MUST be: availability, pricing, reservation, or general.\n"
            . "CRITICAL RULE 1: If text contains partial dates like '15th July to 17th July' or 'tomorrow', YOU MUST infer the year/month/day using Today's date and return exact 'YYYY-MM-DD'. Do NOT return null if any date hint is mentioned.\n"
            . "CRITICAL RULE 2: If the guest asks what rooms are available (e.g., 'share the room categories available'), intent_type MUST be 'availability'.\n"
            . ($clientName ? "Client: {$clientName}\n" : '')
            . "Text:\n{$text}";

        return $prompt;
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

}

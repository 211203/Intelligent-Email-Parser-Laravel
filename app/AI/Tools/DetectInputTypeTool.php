<?php

namespace App\AI\Tools;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DetectInputTypeTool
{
    /**
     * @return array{ok: bool, type: string}
     */
    public function handle(string $emailContent): array
    {
        $prompt = $this->buildPrompt($emailContent);
        
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
            throw new RuntimeException('DetectInputTypeTool returned empty content');
        }

        $data = $this->decodeJson($content);
        
        if (!isset($data['type']) || !in_array($data['type'], ['booking', 'inquiry'])) {
            throw new RuntimeException('Invalid type returned from DetectInputTypeTool');
        }

        return [
            'ok' => true,
            'type' => $data['type'],
        ];
    }

    private function buildPrompt(string $emailContent): string
    {
        return "Analyze the following email content and determine if it's a booking confirmation/invoice or an inquiry.

Return JSON with exactly:
{
  \"type\": \"booking\" | \"inquiry\"
}

Rules:
- \"booking\": Contains booking confirmation, invoice, reservation details, payment info, booking ID
- \"inquiry\": Contains questions about availability, pricing, room information, requests for information

Email content:
" . $emailContent;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(string $content): array
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

        throw new RuntimeException('Unable to decode JSON from DetectInputTypeTool response');
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

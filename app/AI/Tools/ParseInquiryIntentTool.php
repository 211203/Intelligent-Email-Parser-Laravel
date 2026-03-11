<?php

namespace App\AI\Tools;

use App\Services\InquiryParserService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Illuminate\Support\Facades\Log;
use Stringable;

class ParseInquiryIntentTool implements Tool
{
    public function __construct(private readonly InquiryParserService $inquiryParserService)
    {
    }

    public function name(): string
    {
        return 'parse_inquiry_intent';
    }

    public function description(): Stringable|string
    {
        return 'Parse an inquiry email to extract guest intent, requested dates, number of guests, room preferences, and contact details. Use after detecting the email type as "inquiry". Input: text (string, required), client_name (string, optional).';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): Stringable|string
    {
        $text = (string) ($request['text'] ?? '');
        $clientName = $request['client_name'] ?? null;

        try {
            $inquiry = $this->inquiryParserService->parse($text, $clientName);

            Log::info('ParseInquiryIntentTool.success', ['inquiry_keys' => array_keys($inquiry)]);

            return json_encode(['ok' => true, 'inquiry' => $inquiry]);
        } catch (\Exception $e) {
            Log::error('ParseInquiryIntentTool.error', ['error' => $e->getMessage()]);
            return json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
    }
}

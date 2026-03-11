<?php

namespace App\AI\Tools;

use App\Services\InquiryService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Illuminate\Support\Facades\Log;
use Stringable;

class SaveInquiryTool implements Tool
{
    public function __construct(private readonly InquiryService $inquiryService)
    {
    }

    public function name(): string
    {
        return 'save_inquiry';
    }

    public function description(): Stringable|string
    {
        return 'Save a parsed inquiry to the database. Returns the saved inquiry ID. Input: inquiry_data (string, required — JSON string of parsed inquiry), source_type (string, required — e.g. "email"), client_name (string, required), raw_content (string, optional — original email text).';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): Stringable|string
    {
        $inquiryDataJson = (string) ($request['inquiry_data'] ?? '{}');
        $sourceType = (string) ($request['source_type'] ?? 'email');
        $clientName = (string) ($request['client_name'] ?? 'default');
        $rawContent = $request['raw_content'] ?? null;

        $inquiryData = json_decode($inquiryDataJson, true);
        if (!is_array($inquiryData)) {
            $inquiryData = [];
        }

        try {
            $inquiry = $this->inquiryService->createInquiryFromParsedData(
                $inquiryData,
                $sourceType,
                $clientName,
                $rawContent
            );

            Log::info('SaveInquiryTool.success', ['inquiry_id' => $inquiry->id]);

            return json_encode([
                'ok' => true,
                'inquiry_id' => $inquiry->id,
                'message' => "Inquiry saved successfully with ID: {$inquiry->id}",
            ]);
        } catch (\Exception $e) {
            Log::error('SaveInquiryTool.error', ['error' => $e->getMessage()]);
            return json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
    }
}

<?php

namespace App\AI\Tools;

use App\Models\Inquiry;
use App\Services\ResponseGenerationService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Illuminate\Support\Facades\Log;
use Stringable;

class GenerateResponseTool implements Tool
{
    public function __construct(private readonly ResponseGenerationService $responseService)
    {
    }

    public function name(): string
    {
        return 'generate_response';
    }

    public function description(): Stringable|string
    {
        return 'Generate a professional, natural-language email response for a guest inquiry using availability data and pricing quotes. Use after check_availability. Input: inquiry_id (integer, required), quote_data (string, required — JSON string of availability/quote data from check_availability).';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): Stringable|string
    {
        $inquiryId = (int) ($request['inquiry_id'] ?? 0);
        $quoteDataJson = (string) ($request['quote_data'] ?? '{}');

        $quoteData = json_decode($quoteDataJson, true);
        if (!is_array($quoteData)) {
            $quoteData = [];
        }

        try {
            $inquiry = Inquiry::findOrFail($inquiryId);
            $responseText = $this->responseService->generateResponse($inquiry, $quoteData);

            Log::info('GenerateResponseTool.success', [
                'inquiry_id' => $inquiryId,
                'response_length' => strlen($responseText),
            ]);

            return json_encode(['ok' => true, 'response_text' => $responseText]);
        } catch (\Exception $e) {
            Log::error('GenerateResponseTool.error', ['inquiry_id' => $inquiryId, 'error' => $e->getMessage()]);
            return json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
    }
}

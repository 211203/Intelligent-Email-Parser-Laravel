<?php

namespace App\AI\Tools;

use App\Models\Inquiry;
use App\Services\QuoteService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Illuminate\Support\Facades\Log;
use Stringable;

class CheckAvailabilityTool implements Tool
{
    public function __construct(private readonly QuoteService $quoteService)
    {
    }

    public function name(): string
    {
        return 'check_availability';
    }

    public function description(): Stringable|string
    {
        return 'Check room availability and generate price quotes for a saved inquiry. Returns available rooms and pricing. Input: inquiry_id (integer, required — the ID returned by save_inquiry).';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): Stringable|string
    {
        $inquiryId = (int) ($request['inquiry_id'] ?? 0);

        try {
            $inquiry = Inquiry::findOrFail($inquiryId);
            $quoteData = $this->quoteService->generateQuotesForInquiry($inquiry);

            Log::info('CheckAvailabilityTool.success', [
                'inquiry_id' => $inquiryId,
                'has_availability' => $quoteData['has_availability'],
                'room_count' => count($quoteData['available_rooms']),
            ]);

            return json_encode(['ok' => true, 'data' => $quoteData]);
        } catch (\Exception $e) {
            Log::error('CheckAvailabilityTool.error', ['inquiry_id' => $inquiryId, 'error' => $e->getMessage()]);
            return json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
    }
}

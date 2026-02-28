<?php

namespace App\AI\Tools;

use App\Services\InquiryService;

class SaveInquiryTool
{
    public function __construct(private readonly InquiryService $inquiryService)
    {
    }

    /**
     * @return array{ok: bool, inquiry_id?: int, message?: string}
     */
    public function handle(array $inquiryData, string $sourceType, string $clientName, ?string $rawContent = null): array
    {
        try {
            $inquiry = $this->inquiryService->createInquiryFromParsedData(
                $inquiryData,
                $sourceType,
                $clientName,
                $rawContent
            );

            return [
                'ok' => true,
                'inquiry_id' => $inquiry->id,
                'message' => "Inquiry saved successfully with ID: {$inquiry->id}",
            ];
        } catch (\Exception $e) {
            return [
                'ok' => false,
                'message' => 'Failed to save inquiry: ' . $e->getMessage(),
            ];
        }
    }
}

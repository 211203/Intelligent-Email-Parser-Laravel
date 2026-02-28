<?php

namespace App\AI\Tools;

use App\Services\InquiryParserService;

class ParseInquiryIntentTool
{
    public function __construct(private readonly InquiryParserService $inquiryParserService)
    {
    }

    /**
     * @return array{ok: bool, inquiry?: array<string, mixed>}
     */
    public function handle(string $text, ?string $clientName = null): array
    {
        $inquiry = $this->inquiryParserService->parse($text, $clientName);

        return [
            'ok' => true,
            'inquiry' => $inquiry,
        ];
    }
}

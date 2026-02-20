<?php

namespace App\AI\Tools;

use App\Services\GmailService;

class FetchGmailTool
{
    public function __construct(private readonly GmailService $gmailService)
    {
    }

    /** @return array{ok: bool, text?: string} */
    public function handle(): array
    {
        $text = $this->gmailService->fetchLatestEmailPlainText();

        return [
            'ok' => true,
            'text' => $text,
        ];
    }
}

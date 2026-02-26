<?php

namespace App\AI\Tools;

use App\Services\GmailService;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class FetchGmailTool
{
    public function __construct(private readonly GmailService $gmailService)
    {
    }

    /**
     * @return array{ok: bool, pdf_path?: string}
     */
    public function handle(?string $clientName = null): array
    {
        Log::info('FetchGmailTool.start', [
            'client_name' => $clientName,
        ]);

        $clientName = $clientName !== null ? trim($clientName) : null;
        $safeSubject = $clientName !== null ? str_replace('"', ' ', $clientName) : null;
        $query = $safeSubject !== null && $safeSubject !== ''
            ? 'is:unread subject:"' . $safeSubject . '"'
            : 'is:unread';

        $email = $this->gmailService->fetchLatestEmail([
            'query' => $query,
            'pdf_only' => true,
        ]);

        $attachments = $email['attachments'] ?? [];
        if (empty($attachments)) {
            Log::warning('FetchGmailTool.no_pdf_attachment', [
                'client_name' => $clientName,
                'query' => $query,
            ]);
            throw new RuntimeException('No PDF attachment found in latest Gmail message');
        }

        $pdfPath = (string) ($attachments[0]['full_path'] ?? '');
        if ($pdfPath === '') {
            throw new RuntimeException('PDF attachment full_path is missing');
        }

        $result = [
            'ok' => true,
            'pdf_path' => $pdfPath,
        ];

        Log::info('FetchGmailTool.success', [
            'client_name' => $clientName,
            'pdf_path' => $pdfPath,
        ]);

        return $result;
    }
}

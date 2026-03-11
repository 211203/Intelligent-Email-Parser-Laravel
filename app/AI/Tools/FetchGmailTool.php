<?php

namespace App\AI\Tools;

use App\Services\RealGmailService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Illuminate\Support\Facades\Log;
use Stringable;

class FetchGmailTool implements Tool
{
    public function __construct(private readonly RealGmailService $gmailService)
    {
    }

    public function name(): string
    {
        return 'fetch_gmail';
    }

    public function description(): Stringable|string
    {
        return 'Fetch the latest unread email from Gmail for a given client. Returns the email body text and an optional PDF attachment path if present. Input: client_name (string, required).';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): Stringable|string
    {
        $clientName = $request['client_name'] ?? null;

        Log::info('FetchGmailTool.start', ['client_name' => $clientName]);

        try {
            $email = $this->gmailService->fetchLatestEmail([
                'client_name' => $clientName,
            ]);

            $rawBody = $email['body_text'] ?? $email['body_html'] ?? '';
            $subject = $email['subject'] ?? 'No subject';
            $emailContent = "Subject: " . $subject . "\n\n" . substr($rawBody, 0, 4000);

            $pdfPath = null;
            $attachments = $email['attachments'] ?? [];

            if (!empty($attachments)) {
                foreach ($attachments as $attachment) {
                    if (isset($attachment['type']) && $attachment['type'] === 'pdf') {
                        $pdfContent = $this->gmailService->downloadAttachment($email['message_id'], $attachment['attachment_id']);

                        $filename = 'gmail_' . $email['message_id'] . '_' . $attachment['filename'];
                        $pdfPath = storage_path('app/public/uploads/' . $filename);

                        $directory = dirname($pdfPath);
                        if (!is_dir($directory)) {
                            mkdir($directory, 0755, true);
                        }

                        file_put_contents($pdfPath, $pdfContent);
                        $this->gmailService->markAsRead($email['message_id']);
                        break;
                    }
                }
            }

            if (!$pdfPath) {
                $this->gmailService->markAsRead($email['message_id']);
            }

            Log::info('FetchGmailTool.success', [
                'client_name' => $clientName,
                'has_pdf' => $pdfPath !== null,
                'content_length' => strlen($emailContent),
            ]);

            return json_encode([
                'ok' => true,
                'email_content' => $emailContent,
                'pdf_path' => $pdfPath,
            ]);

        } catch (\Exception $e) {
            Log::error('FetchGmailTool.error', [
                'client_name' => $clientName,
                'error' => $e->getMessage(),
            ]);

            return json_encode([
                'ok' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

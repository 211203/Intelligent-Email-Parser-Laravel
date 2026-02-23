<?php

namespace App\Http\Controllers;

use App\Services\GmailService;
use App\Services\PdfExtractionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Throwable;

class BookingController extends Controller
{
    public function __construct(
        private readonly PdfExtractionService $pdfExtractionService,
        private readonly GmailService $gmailService,
    ) {
    }

    public function process(Request $request): JsonResponse
    {
        $request->validate([
            'client_name' => 'required|string',
            'file' => 'nullable|file|mimes:pdf',
        ]);
        
        $clientName = $request->input('client_name');
        
        try {
            $pdfPath = $this->resolvePdfPath($request);
        } catch (Throwable $e) {
            return response()->json([
                'error' => 'pdf_resolution_failed',
                'message' => $e->getMessage(),
            ], 400);
        }

        try {
            $text = $this->pdfExtractionService->extractText($pdfPath);
        } catch (Throwable $e) {
            return response()->json([
                'error' => 'PDF parsing failed',
                'message' => $e->getMessage(),
            ], 500);
        }

        $preview = mb_substr($text, 0, 500);

        return response()->json([
            'status' => 'pdf_processed',
            'client_name' => $clientName,
            'file_path' => $pdfPath,
            'text_length' => mb_strlen($text),
            'preview' => $preview,
        ]);
    }

    private function resolvePdfPath(Request $request): string
    {
        if ($request->hasFile('file')) {
            $stored = $request->file('file')->store('uploads');
            $absolutePath = Storage::disk('local')->path($stored);

            $this->waitForFile($absolutePath);

            return $absolutePath;
        }

        $clientName = trim((string) $request->input('client_name'));
        $safeSubject = str_replace('"', ' ', $clientName);
        $query = $safeSubject !== ''
            ? 'is:unread subject:"' . $safeSubject . '"'
            : 'is:unread';
        $email = $this->gmailService->fetchLatestEmail([
            'query' => $query,
            'pdf_only' => true,
        ]);
        
        $attachments = $email['attachments'] ?? [];
        if (empty($attachments)) {
            throw new \RuntimeException("No PDF attachment found in latest email for client: {$clientName}");
        }

        return $attachments[0]['full_path'];
    }

    private function waitForFile(string $path, int $maxWaitSeconds = 5): void
    {
        $waited = 0;
        while ($waited < $maxWaitSeconds) {
            clearstatcache(true, $path);
            if (is_file($path) && is_readable($path) && filesize($path) > 0) {
                return;
            }
            sleep(1);
            $waited++;
        }
    }

    public function testPdf(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf',
        ]);

        $file = $request->file('file');
        $path = $file->getRealPath();

        try {
            $text = $this->pdfExtractionService->extractText($path);
        } catch (Throwable $exception) {
            return response()->json([
                'error' => 'PDF parsing failed',
                'message' => $exception->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'text' => $text,
        ]);
    }
}

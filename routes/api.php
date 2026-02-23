<?php

use App\Http\Controllers\BookingController;
use App\Services\GmailService;
use Illuminate\Support\Facades\Route;

Route::post('/process', [BookingController::class, 'process']);
Route::post('/test-pdf', [BookingController::class, 'testPdf']);

Route::get('/test-gmail', function (GmailService $gmail) {
    try {
        $email = $gmail->fetchLatestEmail();

        $metadata = [
            'id' => $email['id'] ?? null,
            'threadId' => $email['threadId'] ?? null,
            'subject' => $email['subject'] ?? null,
            'from' => $email['from'] ?? null,
            'date' => $email['date'] ?? null,
            'snippet' => $email['snippet'] ?? null,
        ];

        return response()->json([
            'success' => true,
            'metadata' => $metadata,
            'html' => $email['html'] ?? null,
            'plain' => $email['plain'] ?? null,
            'attachments' => $email['attachments'] ?? [],
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], 500);
    }
});

<?php

namespace App\Http\Controllers;

use App\AI\Agents\BookingAgent;
use App\Models\Booking;
use App\Services\PdfExtractionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Throwable;

class BookingController extends Controller
{
    public function __construct(
        private readonly PdfExtractionService $pdfExtractionService,
        private readonly BookingAgent $bookingAgent,
    ) {
    }

    public function process(Request $request): JsonResponse
    {
        $request->validate([
            'client_name' => 'required|string',
            'file' => 'nullable|file|mimes:pdf',
        ]);

        $clientName = (string) $request->input('client_name');
        $pdfPath = null;

        if ($request->hasFile('file')) {
            $stored = $request->file('file')->store('uploads');
            $absolutePath = Storage::disk('local')->path($stored);

            $this->waitForFile($absolutePath);

            $pdfPath = $absolutePath;
        }

        $source = $request->hasFile('file') ? 'api' : 'gmail';

        try {
            $result = $this->bookingAgent->run($pdfPath, $clientName);
        } catch (Throwable $e) {
            return response()->json([
                'error' => 'booking_agent_failed',
                'message' => $e->getMessage(),
            ], 500);
        }

        // Persist parsed booking data to the bookings table
        $bookingPayload = $result['booking'] ?? null;

        if (! is_array($bookingPayload)) {
            return response()->json([
                'error' => 'booking_persist_failed',
                'message' => 'BookingAgent did not return booking data in expected format.',
                'result' => $result,
            ], 500);
        }

        $booking = Booking::create([
            'guest_name' => $bookingPayload['guest_name'] ?? null,
            'booking_id' => $bookingPayload['booking_id'] ?? null,
            'check_in_date' => $bookingPayload['check_in_date'] ?? null,
            'check_out_date' => $bookingPayload['check_out_date'] ?? null,
            'total_amount' => $bookingPayload['total_amount'] ?? null,
            'guest_email' => $bookingPayload['guest_email'] ?? null,
            'guest_phone' => $bookingPayload['guest_phone'] ?? null,
            'source' => $source,
        ]);

        $merged = array_merge(
            $bookingPayload,
            $booking->only(['id', 'source', 'created_at', 'updated_at'])
        );

        return response()->json([
            'booking' => $merged,
        ]);
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

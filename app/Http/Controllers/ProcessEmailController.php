<?php

namespace App\Http\Controllers;

use App\AI\Agents\BookingAgent;
use App\AI\Tools\ExtractPdfTextTool;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ProcessEmailController extends Controller
{
    /**
     * Process email from multiple sources:
     * 1. Uploaded file (PDF/text)
     * 2. Direct content (via POST body)
     * 3. Gmail fetching (delegated to agent if no file/content)
     */
    public function process(Request $request): JsonResponse
    {
        try {
            $clientName = $request->input('client_name', 'default');

            Log::info('ProcessEmailController.process', [
                'client_name' => $clientName,
                'has_file' => $request->hasFile('file'),
                'has_content' => !empty($request->input('content')),
            ]);

            // Determine the content to process
            $emailContent = $this->resolveContent($request);

            if ($emailContent === null) {
                // No file or content provided — let the agent fetch from Gmail
                return $this->delegateToAgent(
                    "Process the latest email for client: {$clientName}. "
                    . "Fetch the email, determine its type, parse the content, and handle it accordingly."
                );
            }

            // Content is available — let the agent process it directly
            return $this->delegateToAgent(
                "Process this email content for client \"{$clientName}\":\n\n{$emailContent}\n\n"
                . "Detect whether this is a booking or inquiry, parse it, and handle it accordingly. "
                . "Do NOT call fetch_gmail — the content is already provided above."
            );

        } catch (\Exception $e) {
            Log::error('ProcessEmailController.process.failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Email processing failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Process email content directly (without Gmail fetching).
     */
    public function processContent(Request $request): JsonResponse
    {
        $emailContent = $request->input('content');
        $clientName = $request->input('client_name', 'default');

        if (empty($emailContent)) {
            return response()->json([
                'success' => false,
                'message' => 'Email content is required',
            ], 400);
        }

        Log::info('ProcessEmailController.processContent', [
            'client_name' => $clientName,
            'content_length' => strlen($emailContent),
        ]);

        return $this->delegateToAgent(
            "Process this email content for client \"{$clientName}\":\n\n{$emailContent}\n\n"
            . "Detect whether this is a booking or inquiry, parse it, and handle it accordingly. "
            . "Do NOT call fetch_gmail — the content is already provided above."
        );
    }

    /**
     * Get processing status and statistics.
     */
    public function status(): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => [
                    'total_inquiries' => \App\Models\Inquiry::count(),
                    'total_bookings' => \App\Models\Booking::count(),
                    'new_inquiries' => \App\Models\Inquiry::where('inquiry_status', 'new')->count(),
                    'last_processed' => \App\Models\Inquiry::latest('created_at')->first()?->created_at,
                    'system_status' => 'online',
                    'processor' => 'laravel_ai_sdk',
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get status: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test endpoint with sample data.
     */
    public function test(Request $request): JsonResponse
    {
        $testType = $request->input('type', 'inquiry');

        $sampleContent = $testType === 'booking'
            ? "BOOKING CONFIRMATION\nBooking ID: BK123456\nGuest: John Doe\nCheck-in: 2025-03-15\nCheck-out: 2025-03-18\nTotal Amount: \$450.00"
            : "Hi, I'm looking for a room from March 20-25 for 2 guests. Do you have availability and what are your rates?";

        Log::info('ProcessEmailController.test', ['type' => $testType]);

        return $this->delegateToAgent(
            "Process this sample email content for testing:\n\n{$sampleContent}\n\n"
            . "Detect whether this is a booking or inquiry, parse it, and handle it accordingly. "
            . "Do NOT call fetch_gmail — the content is already provided above."
        );
    }

    /**
     * Delegate processing to the BookingAgent via Laravel AI SDK.
     */
    private function delegateToAgent(string $prompt): JsonResponse
    {
        try {
            $agent = new BookingAgent();
            $result = $agent->processEmail($prompt);

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('ProcessEmailController.delegateToAgent.failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Agent processing failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Extract content from an uploaded file or direct input.
     * Returns null if no content is available (agent should fetch from Gmail).
     */
    private function resolveContent(Request $request): ?string
    {
        // Priority 1: Uploaded file
        if ($request->hasFile('file')) {
            $file = $request->file('file');

            if (!$file->isValid()) {
                return null;
            }

            $storedPath = $file->store('uploads', 'public');
            $fullPath = storage_path('app/public/' . $storedPath);

            if ($file->getMimeType() === 'application/pdf' || str_ends_with(strtolower($file->getClientOriginalName()), '.pdf')) {
                $parser = new \Smalot\PdfParser\Parser();
                $pdf = $parser->parseFile($fullPath);
                return $pdf->getText();
            }

            return file_get_contents($fullPath);
        }

        // Priority 2: Direct content
        $content = $request->input('content');
        return !empty($content) ? $content : null;
    }
}

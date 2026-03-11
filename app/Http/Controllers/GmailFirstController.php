<?php

namespace App\Http\Controllers;

use App\AI\Agents\BookingAgent;
use App\Services\RealGmailService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class GmailFirstController extends Controller
{
    public function __construct(
        private readonly RealGmailService $gmailService,
    ) {
    }

    /**
     * Gmail-first processing using Laravel AI SDK agent.
     * The AI model decides which tools to call and in what order.
     */
    public function process(Request $request): JsonResponse
    {
        try {
            $clientName = $request->input('client_name', 'default');

            Log::info('GmailFirstController.process', ['client_name' => $clientName]);

            $agent = new BookingAgent();

            $result = $agent->processEmail(
                "Process the latest email for client: {$clientName}. "
                . "Fetch the email, determine its type, parse the content, and handle it accordingly."
            );

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('GmailFirstController.process.failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Processing failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get Gmail authentication URL.
     */
    public function authUrl(): JsonResponse
    {
        try {
            $authUrl = $this->gmailService->getAuthUrl();

            return response()->json([
                'success' => true,
                'auth_url' => $authUrl,
                'message' => 'Use this URL to authorize Gmail access',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate auth URL: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle Gmail OAuth callback.
     */
    public function callback(Request $request): JsonResponse
    {
        try {
            $code = $request->input('code');

            if (empty($code)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authorization code is required',
                ], 400);
            }

            $token = $this->gmailService->authenticate($code);

            Log::info('Gmail authentication successful');

            return response()->json([
                'success' => true,
                'message' => 'Gmail authentication successful',
                'token_info' => [
                    'access_token' => substr($token['access_token'], 0, 20) . '...',
                    'expires_in' => $token['expires_in'] ?? null,
                    'scope' => $token['scope'] ?? null,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gmail authentication failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get processing status and statistics.
     */
    public function status(): JsonResponse
    {
        try {
            $stats = [
                'total_inquiries' => \App\Models\Inquiry::count(),
                'total_bookings' => \App\Models\Booking::count(),
                'new_inquiries' => \App\Models\Inquiry::where('inquiry_status', 'new')->count(),
                'last_processed' => \App\Models\Inquiry::latest('created_at')->first()?->created_at,
                'system_status' => 'online',
                'processor' => 'laravel_ai_sdk',
                'provider' => 'groq',
                'model' => 'llama-3.3-70b-versatile',
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get status: ' . $e->getMessage(),
            ], 500);
        }
    }
}

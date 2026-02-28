<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class SimpleProcessController extends Controller
{
    /**
     * Simple process endpoint that works without complex dependencies
     */
    public function process(Request $request): JsonResponse
    {
        try {
            $clientName = $request->input('client_name', 'default');
            $content = $request->input('content', '');
            $hasFile = $request->hasFile('file');
            
            Log::info('Simple process started', [
                'client_name' => $clientName,
                'has_content' => !empty($content),
                'has_file' => $hasFile
            ]);

            // Get content from various sources
            $emailContent = '';
            $source = 'unknown';

            if ($hasFile) {
                $file = $request->file('file');
                if ($file->isValid()) {
                    $emailContent = file_get_contents($file->getPathname());
                    $source = 'uploaded_file';
                    Log::info('Content from uploaded file', ['filename' => $file->getClientOriginalName()]);
                }
            } elseif (!empty($content)) {
                $emailContent = $content;
                $source = 'direct_content';
                Log::info('Content from direct input');
            }

            if (empty($emailContent)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No content provided. Please provide content or upload a file.',
                    'source' => $source
                ], 400);
            }

            // Simple type detection based on keywords
            $isBooking = $this->detectBookingType($emailContent);
            $type = $isBooking ? 'booking' : 'inquiry';
            
            Log::info('Type detected', ['type' => $type, 'source' => $source]);

            // Parse content based on type
            if ($isBooking) {
                $parsedData = $this->parseBookingContent($emailContent);
            } else {
                $parsedData = $this->parseInquiryContent($emailContent);
            }

            return response()->json([
                'success' => true,
                'type' => $type,
                'source' => $source,
                'message' => "Email processed as {$type}",
                'data' => [
                    $type => $parsedData,
                    'processed_at' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Simple process failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Processing failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Simple booking detection based on keywords
     */
    private function detectBookingType(string $content): bool
    {
        $bookingKeywords = [
            'booking confirmation', 'booking id', 'reservation', 'confirmed',
            'total amount', 'payment', 'invoice', 'receipt'
        ];

        $contentLower = strtolower($content);
        
        foreach ($bookingKeywords as $keyword) {
            if (str_contains($contentLower, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Simple booking content parsing
     */
    private function parseBookingContent(string $content): array
    {
        $data = [
            'guest_name' => $this->extractField($content, ['guest:', 'name:', 'customer:']),
            'booking_id' => $this->extractField($content, ['booking id:', 'reservation id:', 'confirmation:']),
            'check_in_date' => $this->extractField($content, ['check-in:', 'arrival:', 'from:']),
            'check_out_date' => $this->extractField($content, ['check-out:', 'departure:', 'to:']),
            'total_amount' => $this->extractAmount($content),
            'guest_email' => $this->extractEmail($content),
            'guest_phone' => $this->extractPhone($content)
        ];

        return array_filter($data, function($value) {
            return !empty($value);
        });
    }

    /**
     * Simple inquiry content parsing
     */
    private function parseInquiryContent(string $content): array
    {
        $data = [
            'number_of_guests' => $this->extractGuestCount($content),
            'check_in_date' => $this->extractField($content, ['from:', 'check-in:', 'arrival:']),
            'check_out_date' => $this->extractField($content, ['to:', 'check-out:', 'departure:']),
            'room_type_requested' => $this->extractRoomType($content),
            'intent_type' => $this->detectIntentType($content)
        ];

        return array_filter($data, function($value) {
            return !empty($value);
        });
    }

    /**
     * Extract field using multiple possible labels
     */
    private function extractField(string $content, array $labels): ?string
    {
        foreach ($labels as $label) {
            if (str_contains(strtolower($content), strtolower($label))) {
                $pattern = '/' . preg_quote($label, '/') . '\s*([^\n\r]+)/i';
                if (preg_match($pattern, $content, $matches)) {
                    return trim($matches[1]);
                }
            }
        }
        return null;
    }

    /**
     * Extract amount from content
     */
    private function extractAmount(string $content): ?string
    {
        if (preg_match('/\$?(\d+(?:,\d{3})*(?:\.\d{2})?)/', $content, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Extract email from content
     */
    private function extractEmail(string $content): ?string
    {
        if (preg_match('/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/', $content, $matches)) {
            return $matches[0];
        }
        return null;
    }

    /**
     * Extract phone from content
     */
    private function extractPhone(string $content): ?string
    {
        if (preg_match('/(\+?\d{1,3}[-.\s]?)?\(?\d{3}\)?[-.\s]?\d{3}[-.\s]?\d{4}/', $content, $matches)) {
            return $matches[0];
        }
        return null;
    }

    /**
     * Extract guest count from content
     */
    private function extractGuestCount(string $content): ?int
    {
        if (preg_match('/(\d+)\s*(?:guest|people|person)/i', $content, $matches)) {
            return (int) $matches[1];
        }
        return null;
    }

    /**
     * Extract room type from content
     */
    private function extractRoomType(string $content): ?string
    {
        $roomTypes = ['deluxe', 'standard', 'suite', 'executive', 'family', 'presidential'];
        
        foreach ($roomTypes as $type) {
            if (str_contains(strtolower($content), $type)) {
                return ucfirst($type) . ' Room';
            }
        }
        
        return null;
    }

    /**
     * Detect intent type from content
     */
    private function detectIntentType(string $content): string
    {
        $contentLower = strtolower($content);
        
        if (str_contains($contentLower, 'rate') || str_contains($contentLower, 'price') || str_contains($contentLower, 'cost')) {
            return 'pricing';
        }
        
        if (str_contains($contentLower, 'available') || str_contains($contentLower, 'availability')) {
            return 'availability';
        }
        
        if (str_contains($contentLower, 'book') || str_contains($contentLower, 'reserve')) {
            return 'reservation';
        }
        
        return 'general';
    }

    /**
     * Get status
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
                'processor' => 'simple'
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get status: ' . $e->getMessage()
            ], 500);
        }
    }
}

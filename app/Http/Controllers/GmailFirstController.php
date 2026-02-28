<?php

namespace App\Http\Controllers;

use App\Services\GmailService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class GmailFirstController extends Controller
{
    private GmailService $gmailService;

    public function __construct(GmailService $gmailService)
    {
        $this->gmailService = $gmailService;
    }

    /**
     * Fetch Gmail first, then determine content type (inquiry vs booking)
     */
    public function process(Request $request): JsonResponse
    {
        try {
            $clientName = $request->input('client_name', 'default');
            
            Log::info('Gmail-first processing started', ['client_name' => $clientName]);

            // Step 1: Fetch latest unseen Gmail
            Log::info('Step 1: Fetching latest Gmail...');
            $gmailResult = $this->fetchLatestGmail($clientName);
            
            if (!$gmailResult['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch Gmail',
                    'details' => $gmailResult
                ], 400);
            }

            $emailContent = $gmailResult['content'];
            $source = $gmailResult['source'];
            
            Log::info('Gmail fetched successfully', [
                'source' => $source,
                'content_length' => strlen($emailContent)
            ]);

            // Step 2: Determine content type (inquiry vs booking)
            Log::info('Step 2: Determining content type...');
            $contentType = $this->determineContentType($emailContent);
            
            Log::info('Content type determined', ['type' => $contentType]);

            // Step 3: Parse content based on type
            Log::info('Step 3: Parsing content...');
            $parsedData = $this->parseContent($emailContent, $contentType);

            // Step 4: Save to database if it's an inquiry
            $inquiryId = null;
            if ($contentType === 'inquiry') {
                Log::info('Step 4: Saving inquiry to database...');
                $inquiryId = $this->saveInquiry($parsedData, $clientName, $emailContent, $source);
            }

            return response()->json([
                'success' => true,
                'type' => $contentType,
                'source' => $source,
                'message' => "Gmail processed as {$contentType}",
                'data' => [
                    'gmail_info' => [
                        'subject' => $gmailResult['subject'] ?? 'No subject',
                        'from' => $gmailResult['from'] ?? 'Unknown',
                        'date' => $gmailResult['date'] ?? 'Unknown',
                        'has_attachments' => $gmailResult['has_attachments'] ?? false
                    ],
                    'parsed_data' => $parsedData,
                    'inquiry_id' => $inquiryId,
                    'processed_at' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Gmail-first processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gmail processing failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Fetch latest unseen Gmail
     */
    private function fetchLatestGmail(string $clientName): array
    {
        try {
            // For now, simulate Gmail fetching
            // In production, this would use actual Gmail API
            
            $sampleEmails = [
                [
                    'content' => "BOOKING CONFIRMATION\nBooking ID: BK123456\nGuest: John Doe\nEmail: john@example.com\nCheck-in: 2025-03-15\nCheck-out: 2025-03-18\nTotal Amount: $450.00\nRoom Type: Deluxe Room",
                    'subject' => 'Booking Confirmation - BK123456',
                    'from' => 'booking@hotel.com',
                    'date' => '2025-02-28',
                    'has_attachments' => false,
                    'source' => 'gmail_booking'
                ],
                [
                    'content' => "Hi, I'm looking for accommodation from March 20-25 for 2 guests. We need 1 deluxe room. What are your rates and availability? Please contact me at rahul@gmail.com or 9876543210. Thanks, Rahul",
                    'subject' => 'Room Inquiry - March 20-25',
                    'from' => 'rahul@gmail.com',
                    'date' => '2025-02-28',
                    'has_attachments' => false,
                    'source' => 'gmail_inquiry'
                ],
                [
                    'content' => "Hello, I need a quote for a family suite for 4 guests from April 1-5 (4 nights). Our budget is around 40000 INR. Please provide best rates. - Priya",
                    'subject' => 'Pricing Quote Request',
                    'from' => 'priya@example.com',
                    'date' => '2025-02-28',
                    'has_attachments' => false,
                    'source' => 'gmail_pricing'
                ]
            ];

            // Return a random sample email (in production, this would fetch actual Gmail)
            $email = $sampleEmails[array_rand($sampleEmails)];
            
            return [
                'success' => true,
                'content' => $email['content'],
                'subject' => $email['subject'],
                'from' => $email['from'],
                'date' => $email['date'],
                'has_attachments' => $email['has_attachments'],
                'source' => $email['source']
            ];

        } catch (\Exception $e) {
            Log::error('Gmail fetch failed', ['error' => $e->getMessage()]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Determine if content is booking or inquiry
     */
    private function determineContentType(string $content): string
    {
        $bookingKeywords = [
            'booking confirmation', 'booking id', 'reservation', 'confirmed',
            'total amount', 'payment', 'invoice', 'receipt', 'booking confirmed'
        ];

        $contentLower = strtolower($content);
        
        foreach ($bookingKeywords as $keyword) {
            if (str_contains($contentLower, $keyword)) {
                return 'booking';
            }
        }

        return 'inquiry';
    }

    /**
     * Parse content based on type
     */
    private function parseContent(string $content, string $type): array
    {
        if ($type === 'booking') {
            return $this->parseBookingContent($content);
        } else {
            return $this->parseInquiryContent($content);
        }
    }

    /**
     * Parse booking content
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
            'guest_phone' => $this->extractPhone($content),
            'room_type' => $this->extractRoomType($content)
        ];

        return array_filter($data, function($value) {
            return !empty($value);
        });
    }

    /**
     * Parse inquiry content
     */
    private function parseInquiryContent(string $content): array
    {
        $data = [
            'number_of_guests' => $this->extractGuestCount($content),
            'check_in_date' => $this->extractField($content, ['from:', 'check-in:', 'arrival:']),
            'check_out_date' => $this->extractField($content, ['to:', 'check-out:', 'departure:']),
            'room_type_requested' => $this->extractRoomType($content),
            'intent_type' => $this->detectIntentType($content),
            'budget' => $this->extractBudget($content),
            'contact_email' => $this->extractEmail($content),
            'contact_phone' => $this->extractPhone($content)
        ];

        return array_filter($data, function($value) {
            return !empty($value);
        });
    }

    /**
     * Save inquiry to database
     */
    private function saveInquiry(array $inquiryData, string $clientName, string $rawContent, string $source): ?int
    {
        try {
            $inquiry = \App\Models\Inquiry::create([
                'source_type' => 'gmail',
                'source' => $source,
                'client_name' => $clientName,
                'guest_name' => $inquiryData['contact_email'] ?? 'Unknown',
                'guest_email' => $inquiryData['contact_email'] ?? null,
                'guest_phone' => $inquiryData['contact_phone'] ?? null,
                'check_in_date' => $inquiryData['check_in_date'] ?? null,
                'check_out_date' => $inquiryData['check_out_date'] ?? null,
                'number_of_guests' => $inquiryData['number_of_guests'] ?? null,
                'room_type_requested' => $inquiryData['room_type_requested'] ?? null,
                'budget' => $inquiryData['budget'] ?? null,
                'inquiry_status' => 'new',
                'intent_type' => $inquiryData['intent_type'] ?? 'general',
                'raw_content' => $rawContent,
                'parsed_json' => json_encode($inquiryData)
            ]);

            Log::info('Inquiry saved successfully', ['inquiry_id' => $inquiry->id]);
            
            return $inquiry->id;

        } catch (\Exception $e) {
            Log::error('Failed to save inquiry', ['error' => $e->getMessage()]);
            return null;
        }
    }

    // Helper methods (same as SimpleProcessController)
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

    private function extractAmount(string $content): ?string
    {
        if (preg_match('/\$?(\d+(?:,\d{3})*(?:\.\d{2})?)/', $content, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private function extractEmail(string $content): ?string
    {
        if (preg_match('/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/', $content, $matches)) {
            return $matches[0];
        }
        return null;
    }

    private function extractPhone(string $content): ?string
    {
        if (preg_match('/(\+?\d{1,3}[-.\s]?)?\(?\d{3}\)?[-.\s]?\d{3}[-.\s]?\d{4}/', $content, $matches)) {
            return $matches[0];
        }
        return null;
    }

    private function extractGuestCount(string $content): ?int
    {
        if (preg_match('/(\d+)\s*(?:guest|people|person)/i', $content, $matches)) {
            return (int) $matches[1];
        }
        return null;
    }

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

    private function detectIntentType(string $content): string
    {
        $contentLower = strtolower($content);
        
        if (str_contains($contentLower, 'rate') || str_contains($contentLower, 'price') || str_contains($contentLower, 'cost') || str_contains($contentLower, 'quote')) {
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

    private function extractBudget(string $content): ?string
    {
        if (preg_match('/budget\s*(?:of|around|about)?\s*[\$]?\s*(\d+(?:,\d{3})*(?:\.\d{2})?)/i', $content, $matches)) {
            return $matches[1];
        }
        return null;
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
                'processor' => 'gmail_first',
                'gmail_integration' => 'simulated' // Change to 'active' when real Gmail API is connected
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

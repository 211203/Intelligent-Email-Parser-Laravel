<?php

namespace App\Http\Controllers;

use App\AI\Agents\BookingAgent;
use App\AI\Tools\DetectInputTypeTool;
use App\AI\Tools\ExtractPdfTextTool;
use App\AI\Tools\FetchGmailTool;
use App\AI\Tools\ParseBookingDataTool;
use App\AI\Tools\ParseInquiryIntentTool;
use App\AI\Tools\SaveInquiryTool;
use App\Services\GmailService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ProcessEmailController extends Controller
{
    public function __construct(
        private readonly FetchGmailTool $fetchGmailTool,
        private readonly ExtractPdfTextTool $extractPdfTextTool,
        private readonly DetectInputTypeTool $detectInputTypeTool,
        private readonly ParseBookingDataTool $parseBookingDataTool,
        private readonly ParseInquiryIntentTool $parseInquiryIntentTool,
        private readonly SaveInquiryTool $saveInquiryTool,
    ) {}

    /**
     * Process email from multiple sources:
     * 1. Uploaded file (PDF/text)
     * 2. Gmail fetching (if no file)
     * 3. Direct content (via POST data)
     */
    public function process(Request $request): JsonResponse
    {
        try {
            $clientName = $request->input('client_name', 'default');
            
            Log::info('Starting email processing', [
                'client_name' => $clientName,
                'has_file' => $request->hasFile('file'),
                'has_content' => !empty($request->input('content'))
            ]);

            $emailContent = '';
            $pdfPath = null;
            $source = 'unknown';

            // Priority 1: Check for uploaded file
            if ($request->hasFile('file')) {
                $file = $request->file('file');
                
                if ($file->isValid()) {
                    Log::info('Processing uploaded file', [
                        'filename' => $file->getClientOriginalName(),
                        'size' => $file->getSize(),
                        'mime_type' => $file->getMimeType()
                    ]);

                    // Store the file
                    $storedPath = $file->store('uploads', 'public');
                    $fullPath = storage_path('app/public/' . $storedPath);
                    
                    if ($file->getMimeType() === 'application/pdf' || str_ends_with(strtolower($file->getClientOriginalName()), '.pdf')) {
                        // Extract text from PDF
                        Log::info('Extracting text from uploaded PDF...');
                        $textResult = $this->extractPdfTextTool->handle($fullPath);
                        
                        if ($textResult['ok'] && !empty($textResult['text'])) {
                            $emailContent = $textResult['text'];
                            $pdfPath = $fullPath;
                            $source = 'uploaded_pdf';
                            Log::info('PDF text extracted', ['content_length' => strlen($emailContent)]);
                        }
                    } else {
                        // Read text file directly
                        $emailContent = file_get_contents($fullPath);
                        $pdfPath = $fullPath;
                        $source = 'uploaded_text';
                        Log::info('Text file read', ['content_length' => strlen($emailContent)]);
                    }
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid file upload',
                        'details' => $file->getErrorMessage()
                    ], 400);
                }
            }
            // Priority 2: Check for direct content
            else if (!empty($request->input('content'))) {
                $emailContent = $request->input('content');
                $source = 'direct_content';
                Log::info('Using direct content', ['content_length' => strlen($emailContent)]);
            }
            // Priority 3: Fetch from Gmail
            else {
                Log::info('Fetching from Gmail...');
                $gmailResult = $this->fetchGmailTool->handle($clientName);
                
                if (!$gmailResult['ok']) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No file provided and failed to fetch Gmail',
                        'details' => $gmailResult
                    ], 400);
                }

                if (!empty($gmailResult['pdf_path'])) {
                    // Extract text from PDF attachment
                    Log::info('Extracting PDF text from Gmail...');
                    $textResult = $this->extractPdfTextTool->handle($gmailResult['pdf_path']);
                    
                    if ($textResult['ok'] && !empty($textResult['text'])) {
                        $emailContent = $textResult['text'];
                        $pdfPath = $gmailResult['pdf_path'];
                        $source = 'gmail_pdf';
                        Log::info('Gmail PDF text extracted', ['content_length' => strlen($emailContent)]);
                    }
                } else if (!empty($gmailResult['text_content'])) {
                    // Use direct email text content
                    $emailContent = $gmailResult['text_content'];
                    $source = 'gmail_text';
                    Log::info('Using Gmail direct content', ['content_length' => strlen($emailContent)]);
                }
            }

            if (empty($emailContent)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No content found. Please provide a file, content, or ensure Gmail is configured.',
                    'source' => $source
                ], 400);
            }

            // Step 3: Detect email type (booking vs inquiry)
            Log::info('Detecting email type...', ['source' => $source]);
            $typeResult = $this->detectInputTypeTool->handle($emailContent);
            
            if (!$typeResult['ok']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to detect email type',
                    'details' => $typeResult
                ], 500);
            }

            $emailType = $typeResult['type'];
            Log::info('Email type detected', ['type' => $emailType, 'source' => $source]);

            // Step 4: Process based on type
            if ($emailType === 'booking') {
                return $this->processBooking($emailContent, $clientName, $pdfPath, $source);
            } else {
                return $this->processInquiry($emailContent, $clientName, $pdfPath, $source);
            }

        } catch (\Exception $e) {
            Log::error('Email processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Email processing failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process booking email
     */
    private function processBooking(string $emailContent, string $clientName, ?string $pdfPath, string $source = 'unknown'): JsonResponse
    {
        Log::info('Processing as booking...', ['source' => $source]);
        
        $bookingResult = $this->parseBookingDataTool->handle($emailContent, $clientName);
        
        if (!$bookingResult['ok']) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to parse booking data',
                'details' => $bookingResult
            ], 500);
        }

        Log::info('Booking parsed successfully', ['booking_data' => $bookingResult['booking']]);

        return response()->json([
            'success' => true,
            'type' => 'booking',
            'source' => $source,
            'message' => 'Email processed as booking',
            'data' => [
                'booking' => $bookingResult['booking'],
                'pdf_path' => $pdfPath,
                'processed_at' => now()->toISOString()
            ]
        ]);
    }

    /**
     * Process inquiry email
     */
    private function processInquiry(string $emailContent, string $clientName, ?string $pdfPath, string $source = 'unknown'): JsonResponse
    {
        Log::info('Processing as inquiry...', ['source' => $source]);
        
        // Parse inquiry details
        $inquiryResult = $this->parseInquiryIntentTool->handle($emailContent, $clientName);
        
        if (!$inquiryResult['ok']) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to parse inquiry data',
                'details' => $inquiryResult
            ], 500);
        }

        $inquiryData = $inquiryResult['inquiry'];
        Log::info('Inquiry parsed successfully', ['inquiry_data' => $inquiryData]);

        // Save inquiry to database
        $saveResult = $this->saveInquiryTool->handle(
            $inquiryData,
            $source === 'uploaded_pdf' || $source === 'gmail_pdf' ? 'pdf' : 'email',
            $clientName,
            $emailContent
        );

        if (!$saveResult['ok']) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save inquiry',
                'details' => $saveResult
            ], 500);
        }

        Log::info('Inquiry saved successfully', ['inquiry_id' => $saveResult['inquiry_id']]);

        return response()->json([
            'success' => true,
            'type' => 'inquiry',
            'source' => $source,
            'message' => 'Email processed as inquiry and saved to database',
            'data' => [
                'inquiry' => $inquiryData,
                'inquiry_id' => $saveResult['inquiry_id'],
                'pdf_path' => $pdfPath,
                'processed_at' => now()->toISOString()
            ]
        ]);
    }

    /**
     * Process email content directly (without Gmail fetching)
     */
    public function processContent(Request $request): JsonResponse
    {
        try {
            $emailContent = $request->input('content');
            $clientName = $request->input('client_name', 'default');
            
            if (empty($emailContent)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email content is required'
                ], 400);
            }
            
            Log::info('Processing email content directly', [
                'client_name' => $clientName,
                'content_length' => strlen($emailContent)
            ]);

            // Step 1: Detect email type (booking vs inquiry)
            Log::info('Step 1: Detecting email type...');
            $typeResult = $this->detectInputTypeTool->handle($emailContent);
            
            if (!$typeResult['ok']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to detect email type',
                    'details' => $typeResult
                ], 500);
            }

            $emailType = $typeResult['type'];
            Log::info('Email type detected', ['type' => $emailType]);

            // Step 2: Process based on type
            if ($emailType === 'booking') {
                return $this->processBooking($emailContent, $clientName, null);
            } else {
                return $this->processInquiry($emailContent, $clientName, null);
            }

        } catch (\Exception $e) {
            Log::error('Email content processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Email content processing failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get processing status and statistics
     */
    public function status(): JsonResponse
    {
        try {
            $stats = [
                'total_inquiries' => \App\Models\Inquiry::count(),
                'total_bookings' => \App\Models\Booking::count(),
                'new_inquiries' => \App\Models\Inquiry::where('inquiry_status', 'new')->count(),
                'last_processed' => \App\Models\Inquiry::latest('created_at')->first()?->created_at,
                'system_status' => 'online'
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

    /**
     * Test endpoint with sample data
     */
    public function test(Request $request): JsonResponse
    {
        try {
            $testType = $request->input('type', 'inquiry'); // 'booking' or 'inquiry'
            
            $sampleContent = $testType === 'booking' 
                ? "BOOKING CONFIRMATION\nBooking ID: BK123456\nGuest: John Doe\nCheck-in: 2025-03-15\nCheck-out: 2025-03-18\nTotal Amount: $450.00"
                : "Hi, I'm looking for a room from March 20-25 for 2 guests. Do you have availability and what are your rates?";

            // Test type detection
            $typeResult = $this->detectInputTypeTool->handle($sampleContent);
            
            // Test parsing
            if ($typeResult['type'] === 'booking') {
                $parseResult = $this->parseBookingDataTool->handle($sampleContent, 'test_client');
            } else {
                $parseResult = $this->parseInquiryIntentTool->handle($sampleContent, 'test_client');
            }

            return response()->json([
                'success' => true,
                'test_type' => $testType,
                'detected_type' => $typeResult['type'],
                'parsed_data' => $parseResult,
                'sample_content' => $sampleContent
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Test failed: ' . $e->getMessage()
            ], 500);
        }
    }
}

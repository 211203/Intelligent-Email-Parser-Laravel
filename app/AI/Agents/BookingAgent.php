<?php

namespace App\AI\Agents;

use App\AI\Tools\CheckAvailabilityTool;
use App\AI\Tools\ExtractPdfTextTool;
use App\AI\Tools\FetchGmailTool;
use App\AI\Tools\GenerateResponseTool;
use App\AI\Tools\ParseBookingDataTool;
use App\AI\Tools\ParseInquiryIntentTool;
use App\AI\Tools\SaveBookingTool;
use App\AI\Tools\SaveInquiryTool;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Tools\Request as ToolRequest;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Tool as PrismTool;

class BookingAgent
{
    private const MAX_RETRIES = 2;

    /**
     * Collected results from each tool execution.
     * These are captured during the Prism tool-calling loop.
     */
    private array $toolResults = [];

    /**
     * Process an email using Prism with Groq-compatible flat tool parameters.
     * Returns a rich associative array with all extracted data, availability,
     * pricing, and AI response — not just the LLM's summary text.
     */
    public function processEmail(string $prompt): array
    {
        Log::info('BookingAgent.processEmail', ['prompt_length' => strlen($prompt)]);

        $this->toolResults = [];
        $lastError = null;

        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            try {
                $response = Prism::text()
                    ->using('groq', 'llama-3.3-70b-versatile')
                    ->withSystemPrompt($this->instructions())
                    ->withTools($this->buildTools())
                    ->withMaxSteps(8)
                    ->withPrompt($prompt)
                    ->asText();

                Log::info('BookingAgent.processEmail.complete', [
                    'attempt' => $attempt,
                    'steps' => count($response->steps),
                    'tools_executed' => array_keys($this->toolResults),
                ]);

                return $this->buildResponse();

            } catch (\Throwable $e) {
                $lastError = $e;
                Log::warning('BookingAgent.processEmail.retry', [
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                ]);

                // Only retry on model-side generation failures
                if (!str_contains($e->getMessage(), 'Failed to call a function')
                    && !str_contains($e->getMessage(), 'failed_generation')) {
                    throw $e;
                }
            }
        }

        throw $lastError;
    }

    /**
     * Build a rich structured response from the captured tool results.
     */
    private function buildResponse(): array
    {
        $emailData   = $this->toolResults['fetch_email'] ?? null;
        $parseData   = $this->toolResults['classify_and_parse'] ?? null;
        $bookingData = $this->toolResults['save_booking'] ?? null;
        $inquiryData = $this->toolResults['handle_inquiry'] ?? null;

        $type = $parseData['type'] ?? 'unknown';

        $response = [
            'success' => true,
            'email_type' => $type,
            'email_fetched' => ($emailData['ok'] ?? false),
        ];

        // Include extracted/parsed fields
        if ($parseData) {
            $response['extracted_fields'] = $parseData['parsed_data'] ?? [];
        }

        // Inquiry-specific: availability, pricing, AI response
        if ($type === 'inquiry' && $inquiryData) {
            $response['inquiry'] = [
                'inquiry_id' => $inquiryData['inquiry_id'] ?? null,
                'has_availability' => $inquiryData['has_availability'] ?? false,
                'available_rooms' => $inquiryData['available_rooms'] ?? [],
                'pricing' => $inquiryData['pricing'] ?? [],
                'ai_response' => $inquiryData['response_text'] ?? null,
            ];
        }

        // Booking-specific
        if ($type === 'booking' && $bookingData) {
            $response['booking'] = [
                'saved' => $bookingData['ok'] ?? false,
                'booking_db_id' => $bookingData['id'] ?? null,
            ];
        }

        return $response;
    }

    /* ------------------------------------------------------------------
     *  System instructions
     * ----------------------------------------------------------------*/

    private function instructions(): string
    {
        return <<<'PROMPT'
You are a hotel booking assistant that processes emails step by step using tools.

WORKFLOW:
1. Call `fetch_email` to get the email content for the client.
2. Call `classify_and_parse` with the email text to determine type and extract data.
3. Based on the type returned:
   - If "booking": Call `save_booking` with the parsed booking data JSON string.
   - If "inquiry": Call `handle_inquiry` with the parsed inquiry data, client name, and email text.
4. Return a short confirmation message.

RULES:
- Always start with `fetch_email` unless email content is already in the prompt.
- If content is already provided, skip to `classify_and_parse`.
- Use exact tool outputs — never fabricate data.
- When a tool returns JSON, pass it forward as-is.
PROMPT;
    }

    /* ------------------------------------------------------------------
     *  Tool definitions (4 consolidated)
     * ----------------------------------------------------------------*/

    private function buildTools(): array
    {
        return [
            $this->buildFetchEmailTool(),
            $this->buildClassifyAndParseTool(),
            $this->buildSaveBookingTool(),
            $this->buildHandleInquiryTool(),
        ];
    }

    /**
     * Tool 1 — Fetch email (+ auto-extract PDF).
     */
    private function buildFetchEmailTool(): PrismTool
    {
        return (new PrismTool)
            ->as('fetch_email')
            ->for('Fetch the latest unread email from Gmail for a client. Returns email text and PDF text if an attachment was found.')
            ->withStringParameter('client_name', 'The client name to fetch emails for')
            ->using(function (string $client_name): string {
                Log::info('Tool:fetch_email', ['client_name' => $client_name]);

                $emailResult = json_decode(
                    (string) app(FetchGmailTool::class)->handle(new ToolRequest(['client_name' => $client_name])),
                    true
                );

                if (!($emailResult['ok'] ?? false)) {
                    $out = ['ok' => false, 'error' => $emailResult['error'] ?? 'Failed to fetch email'];
                    $this->toolResults['fetch_email'] = $out;
                    return json_encode($out);
                }

                $content = $emailResult['email_content'] ?? '';
                $pdfPath = $emailResult['pdf_path'] ?? null;

                if ($pdfPath) {
                    $pdfResult = json_decode(
                        (string) app(ExtractPdfTextTool::class)->handle(new ToolRequest(['pdf_path' => $pdfPath])),
                        true
                    );
                    if ($pdfResult['ok'] ?? false) {
                        $content .= "\n\n--- PDF ATTACHMENT ---\n" . ($pdfResult['text'] ?? '');
                    }
                }

                $out = ['ok' => true, 'email_content' => $content, 'has_pdf' => $pdfPath !== null];
                $this->toolResults['fetch_email'] = $out;
                return json_encode($out);
            });
    }
                            
    /**
     * Tool 2 — Classify email type & parse content.
     */
    private function buildClassifyAndParseTool(): PrismTool
    {
        return (new PrismTool)
            ->as('classify_and_parse')
            ->for('Classify the email as "booking" or "inquiry" and extract structured data. Returns the type and parsed data.')
            ->withStringParameter('email_content', 'The full email text to classify and parse')
            ->withStringParameter('client_name', 'The client name', required: false)
            ->using(function (string $email_content, string $client_name = ''): string {
                Log::info('Tool:classify_and_parse', ['content_length' => strlen($email_content)]);

                $type = $this->detectType($email_content);

                if ($type === 'booking') {
                    $parsed = json_decode(
                        (string) app(ParseBookingDataTool::class)->handle(
                            new ToolRequest(['text' => $email_content, 'client_name' => $client_name ?: null])
                        ),
                        true
                    );
                    $out = ['ok' => true, 'type' => 'booking', 'parsed_data' => $parsed['booking'] ?? $parsed];
                } else {
                    $parsed = json_decode(
                        (string) app(ParseInquiryIntentTool::class)->handle(
                            new ToolRequest(['text' => $email_content, 'client_name' => $client_name ?: null])
                        ),
                        true
                    );
                    $out = ['ok' => true, 'type' => 'inquiry', 'parsed_data' => $parsed['inquiry'] ?? $parsed];
                }

                $this->toolResults['classify_and_parse'] = $out;
                return json_encode($out);
            });
    }

    /**
     * Tool 3 — Save a booking.
     */
    private function buildSaveBookingTool(): PrismTool
    {
        return (new PrismTool)
            ->as('save_booking')
            ->for('Save a parsed booking confirmation to the database. Pass the parsed booking data as a JSON string.')
            ->withStringParameter('booking_data', 'JSON string containing guest_name, booking_id, dates, amounts')
            ->using(function (string $booking_data): string {
                Log::info('Tool:save_booking');
                $result = (string) app(SaveBookingTool::class)->handle(
                    new ToolRequest(['booking_data' => $booking_data])
                );
                $this->toolResults['save_booking'] = json_decode($result, true) ?? [];
                return $result;
            });
    }

    /**
     * Tool 4 — Handle inquiry end-to-end (save → availability → response).
     */
    private function buildHandleInquiryTool(): PrismTool
    {
        return (new PrismTool)
            ->as('handle_inquiry')
            ->for('Handle an inquiry: saves it, checks room availability, and generates a response email. Returns the full result.')
            ->withStringParameter('inquiry_data', 'JSON string of parsed inquiry data')
            ->withStringParameter('client_name', 'The client name')
            ->withStringParameter('raw_email', 'The original raw email text', required: false)
            ->using(function (string $inquiry_data, string $client_name, string $raw_email = ''): string {
                Log::info('Tool:handle_inquiry', ['client_name' => $client_name]);

                // Step 1: Save inquiry
                $saveResult = json_decode(
                    (string) app(SaveInquiryTool::class)->handle(new ToolRequest([
                        'inquiry_data' => $inquiry_data,
                        'source_type' => 'email',
                        'client_name' => $client_name,
                        'raw_content' => $raw_email ?: null,
                    ])),
                    true
                );

                if (!($saveResult['ok'] ?? false)) {
                    $out = ['ok' => false, 'error' => $saveResult['error'] ?? 'Failed to save inquiry'];
                    $this->toolResults['handle_inquiry'] = $out;
                    return json_encode($out);
                }

                $inquiryId = $saveResult['inquiry_id'];

                // Step 2: Check availability
                $availResult = json_decode(
                    (string) app(CheckAvailabilityTool::class)->handle(
                        new ToolRequest(['inquiry_id' => $inquiryId])
                    ),
                    true
                );

                $availData = $availResult['data'] ?? [];

                // Step 3: Generate response
                $responseResult = json_decode(
                    (string) app(GenerateResponseTool::class)->handle(new ToolRequest([
                        'inquiry_id' => $inquiryId,
                        'quote_data' => json_encode($availData),
                    ])),
                    true
                );

                $out = [
                    'ok' => true,
                    'inquiry_id' => $inquiryId,
                    'has_availability' => $availData['has_availability'] ?? false,
                    'available_rooms' => $availData['available_rooms'] ?? [],
                    'pricing' => $availData['quotes'] ?? $availData['pricing'] ?? [],
                    'response_text' => $responseResult['response_text'] ?? null,
                ];

                $this->toolResults['handle_inquiry'] = $out;
                return json_encode($out);
            });
    }

    /* ------------------------------------------------------------------
     *  Keyword-based email type detection
     * ----------------------------------------------------------------*/

    private function detectType(string $emailContent): string
    {
        $text = strtolower(preg_replace('/\s+/', ' ', $emailContent) ?? $emailContent);

        // Definitive phrases that immediately identify a booking
        $strictBookingPhrases = [
            'booking confirmation', 'reservation confirmation', 'reservation confirmed', 
            'booking confirmed', 'status: confirmed', 'new reservation',
        ];

        foreach ($strictBookingPhrases as $p) {
            if (str_contains($text, $p)) {
                return 'booking';
            }
        }

        $bookingPhrases = [
            'confirmation number', 'booking id', 'reservation id', 'invoice', 
            'receipt', 'paid', 'payment received', 'booking reference', 'booking voucher',
        ];
        $inquiryPhrases = [
            'availability', 'available', 'do you have', 'please let me know',
            'pricing', 'price', 'rate', 'tariff', 'quotation', 'quote',
            'how much', 'cost', 'looking for', 'interested in',
            'would like to book', 'wish to book', 'planning',
        ];

        $b = 0;
        foreach ($bookingPhrases as $p) {
            if (str_contains($text, $p)) {
                $b++;
            }
        }

        $i = 0;
        foreach ($inquiryPhrases as $p) {
            if (str_contains($text, $p)) {
                $i++;
            }
        }

        return $b > $i ? 'booking' : 'inquiry';
    }
}

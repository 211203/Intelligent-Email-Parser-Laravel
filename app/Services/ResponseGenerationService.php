<?php

namespace App\Services;

use App\Models\Inquiry;
use App\Traits\GroqRetryTrait;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ResponseGenerationService
{
    use GroqRetryTrait;
    /**
     * Generate a natural language response for an inquiry based on structured quote data.
     *
     * @param array $quoteData The structured output from QuoteService::generateQuotesForInquiry()
     */
    public function generateResponse(Inquiry $inquiry, array $quoteData): string
    {
        $prompt = $this->buildPrompt($inquiry, $quoteData);

        $response = $this->groqWithRetry('/openai/v1/chat/completions', [
            'model' => env('GROQ_MODEL', 'llama-3.3-70b-versatile'),
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Hotel front desk assistant. Write concise, professional email replies. Answer any specific questions asked in the Original Guest Query using the provided facilities/room data. Use ONLY provided data. Format prices with commas. If no rooms available for requested dates, explicitly answer their facilities questions anyway, apologize for the dates, and suggest alternate dates. End with call-to-action. Plain text only, no markdown, no subject line.',
                ],
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => 0.3,
        ]);

        $content = Arr::get($response, 'choices.0.message.content', '');
        if (!is_string($content) || trim($content) === '') {
            throw new RuntimeException('ResponseGenerationService returned empty content');
        }

        $responseText = trim($content);

        // Save the response to the inquiry record
        $inquiry->ai_response = $responseText;
        $inquiry->save();

        Log::info('ResponseGenerationService.generated', [
            'inquiry_id' => $inquiry->id,
            'response_length' => strlen($responseText),
        ]);

        return $responseText;
    }

    private function buildPrompt(Inquiry $inquiry, array $quoteData): string
    {
        $guestName = $inquiry->guest_name ?? 'Guest';
        $intentType = $inquiry->intent_type ?? 'general';
        $rawQuery = $inquiry->raw_content ?? 'Not provided';
        $checkIn = $quoteData['check_in'] ?? 'N/A';
        $checkOut = $quoteData['check_out'] ?? 'N/A';
        $numberOfNights = $quoteData['number_of_nights'] ?? 0;
        $numberOfRooms = $quoteData['number_of_rooms'] ?? 1;
        $numberOfGuests = $inquiry->number_of_guests ?? 'not specified';
        $hasAvailability = $quoteData['has_availability'] ?? false;

        $lines = [
            "Generate a hotel response email for the following inquiry:",
            "",
            "Guest name: {$guestName}",
            "Intent: {$intentType}",
            "Original Guest Query: \n\"{$rawQuery}\"",
            "",
            "Check-in: {$checkIn}",
            "Check-out: {$checkOut}",
            "Number of nights: {$numberOfNights}",
            "Number of rooms requested: {$numberOfRooms}",
            "Number of guests: {$numberOfGuests}",
            "",
        ];

        if ($hasAvailability && !empty($quoteData['available_rooms'])) {
            $lines[] = "Available rooms:";
            foreach ($quoteData['available_rooms'] as $room) {
                $facilities = empty($room['facilities']) ? 'None specified' : implode(', ', (array) $room['facilities']);
                $lines[] = "- {$room['room_type']}: {$room['price_per_night']} {$room['currency']}/night, {$room['available_rooms']} rooms available (max {$room['max_occupancy']} guests). Facilities: {$facilities}";
            }
            $lines[] = "";

            if (!empty($quoteData['quotes'])) {
                $lines[] = "Pricing quotes (for {$numberOfRooms} room(s), {$numberOfNights} night(s)):";
                foreach ($quoteData['quotes'] as $quote) {
                    $lines[] = "- {$quote['room_type']}: Subtotal {$quote['subtotal']} {$quote['currency']} + Tax {$quote['tax_amount']} {$quote['currency']} = Total {$quote['total_amount']} {$quote['currency']}";
                }
            }
        } else {
            $lines[] = "No rooms are available for the requested dates and criteria.";
            
            // Fetch all hotel facilities to answer general questions even if rooms aren't available
            $allRooms = \App\Models\Room::active()->get();
            if ($allRooms->isNotEmpty()) {
                $lines[] = "";
                $lines[] = "General Hotel Room Types and Facilities (for reference when answering guest questions):";
                foreach ($allRooms as $room) {
                    $facilities = empty($room->facilities) ? 'None specified' : implode(', ', (array) $room->facilities);
                    $lines[] = "- {$room->room_type} (Max {$room->max_occupancy} guests) Facilities: {$facilities}";
                }
            }
        }

        return implode("\n", $lines);
    }

}

<?php

namespace App\AI\Tools;

use App\Services\BookingParserService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Illuminate\Support\Facades\Log;
use Stringable;

class ParseBookingDataTool implements Tool
{
    public function __construct(private readonly BookingParserService $bookingParserService)
    {
    }

    public function name(): string
    {
        return 'parse_booking_data';
    }

    public function description(): Stringable|string
    {
        return 'Parse structured booking data (guest name, dates, room type, amounts) from raw text extracted from an email or PDF. Use after detecting the email type as "booking". Input: text (string, required), client_name (string, optional).';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): Stringable|string
    {
        $text = (string) ($request['text'] ?? '');
        $clientName = $request['client_name'] ?? null;

        try {
            $booking = $this->bookingParserService->parse($text, $clientName);

            Log::info('ParseBookingDataTool.success', ['booking_keys' => array_keys($booking)]);

            return json_encode(['ok' => true, 'booking' => $booking]);
        } catch (\Exception $e) {
            Log::error('ParseBookingDataTool.error', ['error' => $e->getMessage()]);
            return json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
    }
}

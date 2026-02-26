<?php

namespace App\AI\Tools;

use App\Services\BookingParserService;
use Illuminate\Support\Facades\Log;

class ParseBookingDataTool
{
    public function __construct(private readonly BookingParserService $bookingParserService)
    {
    }

    /**
     * @return array{ok: bool, booking?: array<string, mixed>}
     */
    public function handle(string $text, ?string $clientName = null): array
    {
        Log::info('ParseBookingDataTool.start', [
            'client_name' => $clientName,
            'text_length' => mb_strlen($text),
        ]);

        $booking = $this->bookingParserService->parse($text, $clientName);

        $result = [
            'ok' => true,
            'booking' => $booking,
        ];

        Log::info('ParseBookingDataTool.success', [
            'client_name' => $clientName,
            'keys' => array_keys($booking),
        ]);

        return $result;
    }
}

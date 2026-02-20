<?php

namespace App\AI\Tools;

use App\Services\BookingParserService;

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
        $booking = $this->bookingParserService->parse($text, $clientName);

        return [
            'ok' => true,
            'booking' => $booking,
        ];
    }
}

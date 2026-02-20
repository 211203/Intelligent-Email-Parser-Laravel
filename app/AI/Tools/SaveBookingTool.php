<?php

namespace App\AI\Tools;

use App\Models\Booking;
use Illuminate\Support\Arr;

class SaveBookingTool
{
    /**
     * @param array<string, mixed> $booking
     * @return array{ok: bool, saved: bool, id?: int}
     */
    public function handle(array $booking): array
    {
        $model = Booking::query()->create([
            'guest_name' => Arr::get($booking, 'guest_name'),
            'booking_id' => Arr::get($booking, 'booking_id'),
            'check_in_date' => Arr::get($booking, 'check_in_date'),
            'check_out_date' => Arr::get($booking, 'check_out_date'),
            'guest_email' => Arr::get($booking, 'guest_email'),
            'guest_phone' => Arr::get($booking, 'guest_phone'),
            'total_amount' => Arr::get($booking, 'total_amount'),
            'raw_data_json' => $booking,
        ]);

        return [
            'ok' => true,
            'saved' => true,
            'id' => $model->id,
        ];
    }
}

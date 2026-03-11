<?php

namespace App\AI\Tools;

use App\Models\Booking;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Illuminate\Support\Facades\Log;
use Stringable;

class SaveBookingTool implements Tool
{
    public function name(): string
    {
        return 'save_booking';
    }

    public function description(): Stringable|string
    {
        return 'Save a parsed booking confirmation to the database. Input: booking_data (string, required — JSON string containing guest_name, booking_id, check_in_date, check_out_date, total_amount, etc).';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): Stringable|string
    {
        $bookingJson = (string) ($request['booking_data'] ?? '{}');
        $booking = json_decode($bookingJson, true);

        if (!is_array($booking)) {
            return json_encode(['ok' => false, 'error' => 'Invalid booking data JSON']);
        }

        try {
            $model = Booking::query()->create([
                'guest_name' => Arr::get($booking, 'guest_name'),
                'booking_id' => Arr::get($booking, 'booking_id'),
                'check_in_date' => Arr::get($booking, 'check_in_date'),
                'check_out_date' => Arr::get($booking, 'check_out_date'),
                'guest_email' => Arr::get($booking, 'guest_email_address') ?? Arr::get($booking, 'guest_email'),
                'guest_phone' => Arr::get($booking, 'guest_phone_number') ?? Arr::get($booking, 'guest_phone'),
                'source' => Arr::get($booking, 'source') ?? 'api',
                'total_amount' => Arr::get($booking, 'total_amount'),
                'raw_data' => null,
            ]);

            Log::info('SaveBookingTool.success', ['booking_id' => $model->id]);

            return json_encode(['ok' => true, 'saved' => true, 'id' => $model->id]);
        } catch (\Exception $e) {
            Log::error('SaveBookingTool.error', ['error' => $e->getMessage()]);
            return json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
    }
}

<?php

namespace App\Services;

use App\Models\Inquiry;
use App\Models\Room;
use App\Models\RoomInventory;
use App\Models\InquiryQuote;
use App\Models\Hold;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class InquiryService
{
    public function createInquiryFromParsedData(array $parsedData, string $sourceType, string $clientName, string $rawContent = null): Inquiry
    {
        return Inquiry::create([
            'source_type' => $sourceType,
            'client_name' => $clientName,
            'guest_name' => $parsedData['guest_name'] ?? null,
            'guest_email' => $parsedData['guest_email'] ?? null,
            'guest_phone' => $parsedData['guest_phone'] ?? null,
            'check_in_date' => $this->parseDate($parsedData['check_in_date'] ?? null),
            'check_out_date' => $this->parseDate($parsedData['check_out_date'] ?? null),
            'number_of_guests' => $parsedData['number_of_guests'] ?? null,
            'number_of_rooms' => $parsedData['number_of_rooms'] ?? 1,
            'room_type_requested' => $parsedData['room_type_requested'] ?? null,
            'budget_amount' => $parsedData['budget_amount'] ?? null,
            'intent_type' => $parsedData['intent_type'] ?? 'general',
            'raw_content' => $rawContent,
            'parsed_json' => $parsedData,
        ]);
    }

    public function checkAvailability(Inquiry $inquiry): Collection
    {
        if (!$inquiry->check_in_date || !$inquiry->check_out_date) {
            return collect();
        }

        $rooms = Room::active()
            ->when($inquiry->room_type_requested, function ($query, $roomType) {
                return $query->where('room_type', 'like', "%{$roomType}%");
            })
            ->get();

        $availableRooms = collect();

        foreach ($rooms as $room) {
            $availableCount = $room->getAvailableRoomsForDateRange(
                $inquiry->check_in_date,
                $inquiry->check_out_date
            );

            if ($availableCount >= ($inquiry->number_of_rooms ?? 1)) {
                $availableRooms->push([
                    'room' => $room,
                    'available_rooms' => $availableCount,
                    'total_price' => $room->base_price * $inquiry->number_of_nights * ($inquiry->number_of_rooms ?? 1),
                ]);
            }
        }

        return $availableRooms;
    }

    public function generateQuote(Inquiry $inquiry, Room $room): InquiryQuote
    {
        $number_of_nights = $inquiry->number_of_nights;
        $number_of_rooms = $inquiry->number_of_rooms ?? 1;
        $price_per_night = $room->base_price;

        $quote = InquiryQuote::create([
            'inquiry_id' => $inquiry->id,
            'room_id' => $room->id,
            'price_per_night' => $price_per_night,
            'number_of_nights' => $number_of_nights,
            'currency' => $inquiry->currency,
        ]);

        $quote->calculateTotalAmount();
        $quote->save();

        return $quote;
    }

    public function createHold(Inquiry $inquiry, Room $room, Carbon $expiresAt): Hold
    {
        return Hold::create([
            'inquiry_id' => $inquiry->id,
            'room_id' => $room->id,
            'check_in_date' => $inquiry->check_in_date,
            'check_out_date' => $inquiry->check_out_date,
            'number_of_rooms' => $inquiry->number_of_rooms ?? 1,
            'hold_status' => 'active',
            'expires_at' => $expiresAt,
        ]);
    }

    public function updateInquiryStatus(Inquiry $inquiry, string $status): void
    {
        $inquiry->inquiry_status = $status;
        $inquiry->save();
    }

    public function getInquiriesByStatus(string $status): Collection
    {
        return Inquiry::byStatus($status)
            ->with(['quotes.room', 'holds.room'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getInquiriesByClient(string $clientName): Collection
    {
        return Inquiry::byClient($clientName)
            ->with(['quotes.room', 'holds.room'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getExpiredHolds(): Collection
    {
        return Hold::expired()->get();
    }

    public function expireHolds(): int
    {
        $expiredHolds = $this->getExpiredHolds();
        
        foreach ($expiredHolds as $hold) {
            $hold->expire();
        }

        return $expiredHolds->count();
    }

    private function parseDate($date): ?Carbon
    {
        if (!$date) {
            return null;
        }

        if ($date instanceof Carbon) {
            return $date;
        }

        try {
            return Carbon::parse($date);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getInquiryStatistics(): array
    {
        $totalInquiries = Inquiry::count();
        $newInquiries = Inquiry::byStatus('new')->count();
        $quotedInquiries = Inquiry::byStatus('quoted')->count();
        $convertedInquiries = Inquiry::byStatus('converted')->count();

        $inquiriesByIntent = Inquiry::selectRaw('intent_type, COUNT(*) as count')
            ->groupBy('intent_type')
            ->pluck('count', 'intent_type')
            ->toArray();

        return [
            'total_inquiries' => $totalInquiries,
            'new_inquiries' => $newInquiries,
            'quoted_inquiries' => $quotedInquiries,
            'converted_inquiries' => $convertedInquiries,
            'conversion_rate' => $totalInquiries > 0 ? ($convertedInquiries / $totalInquiries) * 100 : 0,
            'inquiries_by_intent' => $inquiriesByIntent,
        ];
    }
}

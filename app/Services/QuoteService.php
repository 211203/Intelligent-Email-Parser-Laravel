<?php

namespace App\Services;

use App\Models\Inquiry;
use App\Models\InquiryQuote;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class QuoteService
{
    public function __construct(
        private readonly AvailabilityService $availabilityService,
        private readonly PricingService $pricingService,
    ) {}

    /**
     * Generate quotes for an inquiry by checking availability and calculating pricing.
     *
     * Returns structured data with available rooms, pricing, and saved quotes.
     *
     * @return array{inquiry_id: int, check_in: string, check_out: string, number_of_nights: int, number_of_rooms: int, available_rooms: array, quotes: array, has_availability: bool}
     */
    public function generateQuotesForInquiry(Inquiry $inquiry): array
    {
        $checkIn = $inquiry->check_in_date;
        $checkOut = $inquiry->check_out_date;
        $numberOfRooms = $inquiry->number_of_rooms ?? 1;
        $numberOfNights = $inquiry->number_of_nights;
        $currency = $inquiry->currency ?? 'INR';

        if (!$checkIn || !$checkOut || $numberOfNights <= 0) {
            return [
                'inquiry_id' => $inquiry->id,
                'check_in' => $checkIn?->toDateString(),
                'check_out' => $checkOut?->toDateString(),
                'number_of_nights' => 0,
                'number_of_rooms' => $numberOfRooms,
                'available_rooms' => [],
                'quotes' => [],
                'has_availability' => false,
            ];
        }

        // Check availability
        $availableRooms = $this->availabilityService->checkAvailability(
            checkIn: $checkIn,
            checkOut: $checkOut,
            roomType: $inquiry->room_type_requested,
            numberOfGuests: $inquiry->number_of_guests,
            numberOfRooms: $numberOfRooms,
        );

        $roomData = [];
        $quoteData = [];

        foreach ($availableRooms as $entry) {
            /** @var Room $room */
            $room = $entry['room'];
            $minAvailable = $entry['min_available'];

            // Calculate pricing
            $pricing = $this->pricingService->calculatePricing(
                room: $room,
                numberOfNights: $numberOfNights,
                numberOfRooms: $numberOfRooms,
                currency: $currency,
            );

            // Save quote to database
            $quote = InquiryQuote::create([
                'inquiry_id' => $inquiry->id,
                'room_id' => $room->id,
                'price_per_night' => $pricing['price_per_night'],
                'number_of_nights' => $numberOfNights,
                'subtotal_amount' => $pricing['subtotal'],
                'tax_amount' => $pricing['tax_amount'],
                'discount_amount' => $pricing['discount_amount'],
                'total_amount' => $pricing['total_amount'],
                'currency' => $currency,
                'quote_status' => 'generated',
            ]);

            $roomData[] = [
                'room_id' => $room->id,
                'room_type' => $room->room_type,
                'description' => $room->description,
                'facilities' => $room->facilities,
                'max_occupancy' => $room->max_occupancy,
                'available_rooms' => $minAvailable,
                'price_per_night' => $pricing['price_per_night'],
                'currency' => $currency,
            ];

            $quoteData[] = [
                'quote_id' => $quote->id,
                'room_type' => $room->room_type,
                'price_per_night' => $pricing['price_per_night'],
                'number_of_nights' => $pricing['number_of_nights'],
                'number_of_rooms' => $pricing['number_of_rooms'],
                'subtotal' => $pricing['subtotal'],
                'tax_amount' => $pricing['tax_amount'],
                'total_amount' => $pricing['total_amount'],
                'currency' => $currency,
            ];
        }

        // Update inquiry status
        $inquiry->inquiry_status = $availableRooms->isNotEmpty() ? 'quoted' : 'availability_checked';
        $inquiry->save();

        return [
            'inquiry_id' => $inquiry->id,
            'check_in' => $checkIn->toDateString(),
            'check_out' => $checkOut->toDateString(),
            'number_of_nights' => $numberOfNights,
            'number_of_rooms' => $numberOfRooms,
            'available_rooms' => $roomData,
            'quotes' => $quoteData,
            'has_availability' => $availableRooms->isNotEmpty(),
        ];
    }
}

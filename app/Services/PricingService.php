<?php

namespace App\Services;

use App\Models\Room;

class PricingService
{
    private const TAX_RATE = 0.18; // 18% GST

    /**
     * Calculate pricing for a room stay.
     *
     * @return array{price_per_night: float, number_of_nights: int, number_of_rooms: int, subtotal: float, tax_amount: float, discount_amount: float, total_amount: float, currency: string}
     */
    public function calculatePricing(
        Room $room,
        int $numberOfNights,
        int $numberOfRooms = 1,
        float $discountAmount = 0.00,
        string $currency = 'INR'
    ): array {
        $pricePerNight = (float) $room->base_price;
        $subtotal = $pricePerNight * $numberOfNights * $numberOfRooms;
        $taxAmount = round($subtotal * self::TAX_RATE, 2);
        $totalAmount = round($subtotal + $taxAmount - $discountAmount, 2);

        return [
            'price_per_night' => $pricePerNight,
            'number_of_nights' => $numberOfNights,
            'number_of_rooms' => $numberOfRooms,
            'subtotal' => round($subtotal, 2),
            'tax_amount' => $taxAmount,
            'discount_amount' => round($discountAmount, 2),
            'total_amount' => max(0, $totalAmount),
            'currency' => $currency,
        ];
    }
}

<?php

namespace Database\Seeders;

use App\Models\Room;
use App\Models\RoomInventory;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class RoomSeeder extends Seeder
{
    public function run(): void
    {
        $rooms = [
            [
                'room_type' => 'Deluxe Room',
                'description' => 'Spacious deluxe room with city view, king-size bed, mini bar, and modern amenities.',
                'base_price' => 5000.00,
                'max_occupancy' => 2,
                'total_rooms' => 10,
                'is_active' => true,
            ],
            [
                'room_type' => 'Executive Room',
                'description' => 'Premium executive room with panoramic view, work desk, lounge access, and complimentary breakfast.',
                'base_price' => 7000.00,
                'max_occupancy' => 3,
                'total_rooms' => 8,
                'is_active' => true,
            ],
            [
                'room_type' => 'Suite',
                'description' => 'Luxury suite with separate living area, jacuzzi, premium amenities, and dedicated concierge.',
                'base_price' => 12000.00,
                'max_occupancy' => 4,
                'total_rooms' => 4,
                'is_active' => true,
            ],
            [
                'room_type' => 'Standard Room',
                'description' => 'Comfortable standard room with essential amenities, queen-size bed, and garden view.',
                'base_price' => 3000.00,
                'max_occupancy' => 2,
                'total_rooms' => 15,
                'is_active' => true,
            ],
            [
                'room_type' => 'Family Room',
                'description' => 'Spacious family room with two double beds, kids-friendly amenities, and extra space.',
                'base_price' => 8500.00,
                'max_occupancy' => 5,
                'total_rooms' => 6,
                'is_active' => true,
            ],
        ];

        foreach ($rooms as $roomData) {
            $room = Room::updateOrCreate(
                ['room_type' => $roomData['room_type']],
                $roomData
            );

            // Seed 90 days of inventory from today
            $startDate = Carbon::today();
            for ($i = 0; $i < 90; $i++) {
                $date = $startDate->copy()->addDays($i);

                // Simulate some realistic bookings
                $bookedRooms = rand(0, (int) floor($room->total_rooms * 0.4));
                $blockedRooms = rand(0, 1); // Occasionally block 1 room for maintenance

                RoomInventory::updateOrCreate(
                    [
                        'room_id' => $room->id,
                        'date' => $date->toDateString(),
                    ],
                    [
                        'total_rooms' => $room->total_rooms,
                        'booked_rooms' => $bookedRooms,
                        'blocked_rooms' => $blockedRooms,
                        'held_rooms' => 0,
                    ]
                );
            }
        }
    }
}

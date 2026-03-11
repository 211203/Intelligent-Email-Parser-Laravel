<?php

namespace App\Services;

use App\Models\Room;
use App\Models\RoomInventory;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AvailabilityService
{
    /**
     * Check room availability for a date range, optionally filtered by room type and guest capacity.
     *
     * Returns a collection of arrays, each containing:
     *   - room: the Room model
     *   - min_available: minimum rooms available across the entire date range
     *
     * @return Collection<int, array{room: Room, min_available: int}>
     */
    public function checkAvailability(
        Carbon $checkIn,
        Carbon $checkOut,
        ?string $roomType = null,
        ?int $numberOfGuests = null,
        int $numberOfRooms = 1
    ): Collection {
        $query = Room::query()->where('is_active', true);

        if ($roomType) {
            $query->where('room_type', 'like', "%{$roomType}%");
        }

        if ($numberOfGuests && $numberOfRooms > 0) {
            $guestsPerRoom = (int) ceil($numberOfGuests / $numberOfRooms);
            $query->where('max_occupancy', '>=', $guestsPerRoom);
        }

        $rooms = $query->get();
        $availableRooms = collect();

        // Get all inventory records for the date range in one query (avoid N+1)
        $dateRange = [$checkIn->toDateString(), $checkOut->copy()->subDay()->toDateString()];

        $inventoryMap = RoomInventory::whereBetween('date', $dateRange)
            ->whereIn('room_id', $rooms->pluck('id'))
            ->get()
            ->groupBy('room_id');

        // Calculate the number of stay nights
        $stayNights = $checkIn->diffInDays($checkOut);
        if ($stayNights <= 0) {
            return collect();
        }

        foreach ($rooms as $room) {
            $roomInventories = $inventoryMap->get($room->id, collect());
            $minimumAvailable = $room->total_rooms;

            // Check each night of the stay
            $currentDate = $checkIn->copy();
            for ($i = 0; $i < $stayNights; $i++) {
                $dateStr = $currentDate->toDateString();
                $inventory = $roomInventories->firstWhere('date', $currentDate);

                if ($inventory) {
                    $available = $inventory->total_rooms
                        - $inventory->booked_rooms
                        - $inventory->blocked_rooms
                        - ($inventory->held_rooms ?? 0);
                    $minimumAvailable = min($minimumAvailable, max(0, $available));
                }

                $currentDate->addDay();
            }

            if ($minimumAvailable >= $numberOfRooms) {
                $availableRooms->push([
                    'room' => $room,
                    'min_available' => $minimumAvailable,
                ]);
            }
        }

        return $availableRooms;
    }

    /**
     * Check if a specific room has enough availability for a date range.
     */
    public function isRoomAvailable(Room $room, Carbon $checkIn, Carbon $checkOut, int $numberOfRooms = 1): bool
    {
        $stayNights = $checkIn->diffInDays($checkOut);
        if ($stayNights <= 0) {
            return false;
        }

        $dateRange = [$checkIn->toDateString(), $checkOut->copy()->subDay()->toDateString()];

        $inventories = RoomInventory::where('room_id', $room->id)
            ->whereBetween('date', $dateRange)
            ->get();

        $currentDate = $checkIn->copy();
        for ($i = 0; $i < $stayNights; $i++) {
            $inventory = $inventories->firstWhere('date', $currentDate);

            if ($inventory) {
                $available = $inventory->total_rooms
                    - $inventory->booked_rooms
                    - $inventory->blocked_rooms
                    - ($inventory->held_rooms ?? 0);

                if ($available < $numberOfRooms) {
                    return false;
                }
            } else {
                // No inventory row = default to total_rooms
                if ($room->total_rooms < $numberOfRooms) {
                    return false;
                }
            }

            $currentDate->addDay();
        }

        return true;
    }
}

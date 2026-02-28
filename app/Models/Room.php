<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    protected $fillable = [
        'room_type',
        'description',
        'base_price',
        'max_occupancy',
        'total_rooms',
        'is_active',
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function inventories(): HasMany
    {
        return $this->hasMany(RoomInventory::class);
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(InquiryQuote::class);
    }

    public function holds(): HasMany
    {
        return $this->hasMany(Hold::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, $roomType)
    {
        return $query->where('room_type', $roomType);
    }

    public function getAvailableRoomsForDate($date): int
    {
        $inventory = $this->inventories()
            ->where('date', $date)
            ->first();

        if (!$inventory) {
            return $this->total_rooms;
        }

        return $inventory->total_rooms - $inventory->booked_rooms - $inventory->blocked_rooms;
    }

    public function getAvailableRoomsForDateRange($startDate, $endDate): int
    {
        $inventories = $this->inventories()
            ->whereBetween('date', [$startDate, $endDate])
            ->get();

        if ($inventories->isEmpty()) {
            return $this->total_rooms;
        }

        $minimumAvailable = $this->total_rooms;

        foreach ($inventories as $inventory) {
            $available = $inventory->total_rooms - $inventory->booked_rooms - $inventory->blocked_rooms;
            $minimumAvailable = min($minimumAvailable, $available);
        }

        return max(0, $minimumAvailable);
    }
}

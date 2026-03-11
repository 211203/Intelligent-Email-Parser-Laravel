<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomInventory extends Model
{
    protected $fillable = [
        'room_id',
        'date',
        'total_rooms',
        'booked_rooms',
        'blocked_rooms',
        'held_rooms',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function getAvailableRoomsAttribute(): int
    {
        return $this->total_rooms - $this->booked_rooms - $this->blocked_rooms - ($this->held_rooms ?? 0);
    }

    public function scopeAvailable($query)
    {
        return $query->whereRaw('(total_rooms - booked_rooms - blocked_rooms) > 0');
    }

    public function scopeForDate($query, $date)
    {
        return $query->where('date', $date);
    }

    public function scopeForDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }
}

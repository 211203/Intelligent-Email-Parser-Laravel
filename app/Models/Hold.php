<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Hold extends Model
{
    protected $fillable = [
        'inquiry_id',
        'room_id',
        'check_in_date',
        'check_out_date',
        'number_of_rooms',
        'hold_status',
        'expires_at',
    ];

    protected $casts = [
        'check_in_date' => 'date',
        'check_out_date' => 'date',
        'expires_at' => 'datetime',
    ];

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(Inquiry::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function scopeActive($query)
    {
        return $query->where('hold_status', 'active')
                    ->where('expires_at', '>', now());
    }

    public function scopeExpired($query)
    {
        return $query->where(function ($q) {
            $q->where('hold_status', 'expired')
              ->orWhere('expires_at', '<=', now());
        });
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('hold_status', $status);
    }

    public function getNumberOfNightsAttribute(): int
    {
        if (!$this->check_in_date || !$this->check_out_date) {
            return 0;
        }
        
        return $this->check_in_date->diffInDays($this->check_out_date);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function expire(): void
    {
        $this->hold_status = 'expired';
        $this->save();
    }

    public function convert(): void
    {
        $this->hold_status = 'converted';
        $this->save();
    }

    public function cancel(): void
    {
        $this->hold_status = 'cancelled';
        $this->save();
    }
}

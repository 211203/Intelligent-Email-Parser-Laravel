<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inquiry extends Model
{
    protected $fillable = [
        'source_type',
        'client_name',
        'guest_name',
        'guest_email',
        'guest_phone',
        'check_in_date',
        'check_out_date',
        'number_of_guests',
        'number_of_rooms',
        'room_type_requested',
        'budget_amount',
        'currency',
        'inquiry_status',
        'intent_type',
        'raw_content',
        'parsed_json',
    ];

    protected $casts = [
        'check_in_date' => 'date',
        'check_out_date' => 'date',
        'budget_amount' => 'decimal:2',
        'parsed_json' => 'array',
    ];

    public function quotes(): HasMany
    {
        return $this->hasMany(InquiryQuote::class);
    }

    public function holds(): HasMany
    {
        return $this->hasMany(Hold::class);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('inquiry_status', $status);
    }

    public function scopeByIntent($query, $intent)
    {
        return $query->where('intent_type', $intent);
    }

    public function scopeByClient($query, $clientName)
    {
        return $query->where('client_name', $clientName);
    }

    public function getNumberOfNightsAttribute(): int
    {
        if (!$this->check_in_date || !$this->check_out_date) {
            return 0;
        }
        
        return $this->check_in_date->diffInDays($this->check_out_date);
    }
}

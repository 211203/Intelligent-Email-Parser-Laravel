<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InquiryQuote extends Model
{
    protected $fillable = [
        'inquiry_id',
        'room_id',
        'price_per_night',
        'number_of_nights',
        'subtotal_amount',
        'tax_amount',
        'discount_amount',
        'total_amount',
        'currency',
        'quote_status',
    ];

    protected $casts = [
        'price_per_night' => 'decimal:2',
        'subtotal_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(Inquiry::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('quote_status', $status);
    }

    public function scopeByInquiry($query, $inquiryId)
    {
        return $query->where('inquiry_id', $inquiryId);
    }

    public function calculateTotalAmount(): void
    {
        $this->subtotal_amount = $this->price_per_night * $this->number_of_nights;
        $this->tax_amount = $this->subtotal_amount * 0.18; // 18% tax
        $this->total_amount = $this->subtotal_amount + $this->tax_amount - ($this->discount_amount ?? 0);
    }
}

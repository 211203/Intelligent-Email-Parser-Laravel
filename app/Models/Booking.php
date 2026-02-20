<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $fillable = [
        'guest_name',
        'booking_id',
        'check_in_date',
        'check_out_date',
        'total_amount',
        'guest_email',
        'guest_phone',
        'source',
        'raw_data',
    ];

    protected $casts = [
        'raw_data' => 'array',
    ];
}

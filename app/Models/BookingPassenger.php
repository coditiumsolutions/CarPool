<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingPassenger extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'booking_passengers';

    protected $fillable = ['booking_id', 'passenger_name', 'passenger_phone'];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}

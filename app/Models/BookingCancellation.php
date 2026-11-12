<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingCancellation extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'booking_cancellations';

    protected $fillable = ['booking_id', 'cancelled_by', 'reason', 'refund_amount'];

    protected function casts(): array
    {
        return ['refund_amount' => 'decimal:2'];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}

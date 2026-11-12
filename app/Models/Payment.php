<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'payments';

    protected $fillable = [
        'booking_id', 'user_id', 'payment_method', 'transaction_reference', 'amount',
        'commission_amount', 'refund_amount', 'payment_status', 'payment_date',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'refund_amount' => 'decimal:2',
            'payment_date' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booking extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_COMPLETED = 'completed';

    protected $table = 'bookings';

    protected $fillable = [
        'ride_id', 'ride_offer_id', 'passenger_id', 'booking_date', 'total_seats',
        'price_per_seat', 'total_amount', 'payment_status', 'booking_status',
    ];

    protected function casts(): array
    {
        return [
            'booking_date' => 'datetime',
            'price_per_seat' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    /** The driver, whether the booking came from a published ride or an accepted offer. */
    public function driverId(): ?int
    {
        return $this->ride?->driver_id ?? $this->rideOffer?->driver_id;
    }

    public function passenger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'passenger_id');
    }

    /** A booking is made against either a ride or an accepted ride offer. */
    public function ride(): BelongsTo
    {
        return $this->belongsTo(Ride::class);
    }

    public function rideOffer(): BelongsTo
    {
        return $this->belongsTo(RideOffer::class, 'ride_offer_id');
    }

    public function passengers(): HasMany
    {
        return $this->hasMany(BookingPassenger::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class);
    }

    public function cancellation(): HasOne
    {
        return $this->hasOne(BookingCancellation::class);
    }
}

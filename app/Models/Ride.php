<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ride extends Model
{
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_STARTED = 'started';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    /** Statuses counted as "active" on the dashboard. */
    public const ACTIVE_STATUSES = [self::STATUS_SCHEDULED, self::STATUS_STARTED];

    protected $table = 'rides';

    protected $fillable = [
        'driver_id', 'vehicle_id', 'ride_type', 'from_city_id', 'to_city_id', 'from_location', 'to_location',
        'from_latitude', 'from_longitude', 'to_latitude', 'to_longitude', 'departure_datetime', 'arrival_datetime',
        'total_seats', 'available_seats', 'price_per_seat', 'smoking_allowed', 'pets_allowed', 'luggage_allowed',
        'gender_preference', 'notes', 'ride_status',
    ];

    protected function casts(): array
    {
        return [
            'departure_datetime' => 'datetime',
            'arrival_datetime' => 'datetime',
            'price_per_seat' => 'decimal:2',
            'smoking_allowed' => 'boolean',
            'pets_allowed' => 'boolean',
            'luggage_allowed' => 'boolean',
        ];
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function fromCity(): BelongsTo
    {
        return $this->belongsTo(City::class, 'from_city_id');
    }

    public function toCity(): BelongsTo
    {
        return $this->belongsTo(City::class, 'to_city_id');
    }

    public function stops(): HasMany
    {
        return $this->hasMany(RideStop::class)->orderBy('stop_order');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function tracking(): HasMany
    {
        return $this->hasMany(RideTracking::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RideRequest extends Model
{
    protected $table = 'ride_requests';

    protected $fillable = [
        'passenger_id', 'ride_type', 'from_city_id', 'to_city_id', 'from_location', 'to_location',
        'from_latitude', 'from_longitude', 'to_latitude', 'to_longitude', 'departure_datetime',
        'required_seats', 'max_budget', 'notes', 'request_status',
    ];

    protected function casts(): array
    {
        return ['departure_datetime' => 'datetime', 'max_budget' => 'decimal:2'];
    }

    public function passenger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'passenger_id');
    }

    public function fromCity(): BelongsTo
    {
        return $this->belongsTo(City::class, 'from_city_id');
    }

    public function toCity(): BelongsTo
    {
        return $this->belongsTo(City::class, 'to_city_id');
    }

    public function offers(): HasMany
    {
        return $this->hasMany(RideOffer::class, 'request_id');
    }
}

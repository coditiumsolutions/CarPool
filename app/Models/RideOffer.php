<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RideOffer extends Model
{
    protected $table = 'ride_offers';

    protected $fillable = ['request_id', 'driver_id', 'vehicle_id', 'offered_price', 'offered_seats', 'message', 'offer_status'];

    protected function casts(): array
    {
        return ['offered_price' => 'decimal:2'];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(RideRequest::class, 'request_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'ride_offer_id');
    }
}

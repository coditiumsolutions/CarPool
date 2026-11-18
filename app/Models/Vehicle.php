<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    protected $table = 'vehicles';

    protected $fillable = [
        'user_id', 'vehicle_type', 'make', 'model', 'registration_no', 'color',
        'model_year', 'total_seats', 'is_verified', 'status',
    ];

    protected function casts(): array
    {
        return ['is_verified' => 'boolean', 'total_seats' => 'integer'];
    }

    public function documents(): HasMany
    {
        return $this->hasMany(VehicleDocument::class);
    }

    /**
     * approved  = admin verified the vehicle
     * rejected  = not verified and a document was rejected
     * pending   = otherwise (awaiting documents or review)
     * Requires the `documents` relation to be loaded.
     */
    public function getVerificationStatusAttribute(): string
    {
        if ($this->is_verified) {
            return 'approved';
        }

        return $this->documents->contains('verification_status', 'rejected') ? 'rejected' : 'pending';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rides(): HasMany
    {
        return $this->hasMany(Ride::class);
    }

    public function rideOffers(): HasMany
    {
        return $this->hasMany(RideOffer::class);
    }
}

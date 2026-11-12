<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public const TYPE_ADMIN = 'admin';
    public const TYPE_DRIVER = 'driver';
    public const TYPE_PASSENGER = 'passenger';
    public const TYPE_BOTH = 'both';

    protected $table = 'users';

    protected $fillable = [
        'name', 'email', 'phone', 'password', 'user_type', 'gender', 'date_of_birth',
        'profile_image', 'city_id', 'address', 'cnic', 'is_verified', 'status',
    ];

    /** CNIC is sensitive: never serialised to JSON/arrays. */
    protected $hidden = ['password', 'cnic'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'cnic' => 'encrypted',
            'date_of_birth' => 'date',
            'is_verified' => 'boolean',
        ];
    }

    /** The users table has no remember_token column, so "remember me" is disabled. */
    public function getRememberTokenName()
    {
        return '';
    }

    public function isAdmin(): bool
    {
        return $this->user_type === self::TYPE_ADMIN;
    }

    public function isDriver(): bool
    {
        return in_array($this->user_type, [self::TYPE_DRIVER, self::TYPE_BOTH], true);
    }

    public function isPassenger(): bool
    {
        return in_array($this->user_type, [self::TYPE_PASSENGER, self::TYPE_BOTH], true);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /** e.g. 12345-*******-1 : only last digit visible. */
    public function getMaskedCnicAttribute(): ?string
    {
        $cnic = $this->cnic;
        if (! $cnic) {
            return null;
        }

        return str_repeat('*', max(strlen($cnic) - 1, 0)).substr($cnic, -1);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(UserDocument::class);
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function savedLocations(): HasMany
    {
        return $this->hasMany(SavedLocation::class);
    }

    /** Rides this user drives. */
    public function rides(): HasMany
    {
        return $this->hasMany(Ride::class, 'driver_id');
    }

    public function rideRequests(): HasMany
    {
        return $this->hasMany(RideRequest::class, 'passenger_id');
    }

    public function rideOffers(): HasMany
    {
        return $this->hasMany(RideOffer::class, 'driver_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'passenger_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class);
    }

    public function reviewsGiven(): HasMany
    {
        return $this->hasMany(Review::class, 'reviewer_id');
    }

    public function reviewsReceived(): HasMany
    {
        return $this->hasMany(Review::class, 'reviewee_id');
    }

    public function trackingPoints(): HasMany
    {
        return $this->hasMany(RideTracking::class, 'driver_id');
    }
}

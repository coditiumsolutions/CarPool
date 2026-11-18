<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
        'emergency_contact_name', 'emergency_contact_phone',
    ];

    /** CNIC is sensitive: never serialised to JSON/arrays. */
    protected $hidden = ['password', 'cnic'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'cnic' => 'encrypted',
            'date_of_birth' => 'date:Y-m-d',
            'is_verified' => 'boolean',
            // Aggregates added by the publicProfile()/passengerProfile() scopes.
            'rating_avg' => 'float',
            'reviews_count' => 'integer',
            'completed_rides_count' => 'integer',
            'completed_bookings_count' => 'integer',
            'member_since' => 'integer',
        ];
    }

    /** Stored value may be a relative path (uploaded photo) or a full URL; always return a full URL. */
    protected function profileImage(): Attribute
    {
        return Attribute::get(function (?string $value) {
            if (! $value) {
                return null;
            }

            if (Str::startsWith($value, ['http://', 'https://'])) {
                return $value;
            }

            $url = Storage::disk('public')->url($value);

            return Str::startsWith($url, ['http://', 'https://']) ? $url : url($url); // always absolute
        });
    }

    private static function memberSinceSql(): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "CAST(strftime('%Y', users.created_at) AS INTEGER)"
            : 'YEAR(users.created_at)';
    }

    private static function ratingSql(): string
    {
        return '(SELECT ROUND(COALESCE(AVG(rv.rating), 0), 2) FROM reviews rv WHERE rv.reviewee_id = users.id) AS rating_avg, '
            .'(SELECT COUNT(*) FROM reviews rv WHERE rv.reviewee_id = users.id) AS reviews_count, '
            .self::memberSinceSql().' AS member_since';
    }

    /**
     * Safe public fields for a driver plus rating_avg, reviews_count,
     * completed_rides_count and member_since. Use inside with() closures.
     */
    public function scopePublicProfile(Builder $query, bool $withPhone = false): Builder
    {
        $columns = ['users.id', 'users.name', 'users.gender', 'users.profile_image', 'users.is_verified'];
        if ($withPhone) {
            $columns[] = 'users.phone';
        }

        return $query->select($columns)->selectRaw(
            self::ratingSql().', '
            ."(SELECT COUNT(*) FROM rides rd WHERE rd.driver_id = users.id AND rd.ride_status = 'completed') "
            .'+ (SELECT COUNT(*) FROM bookings bk JOIN ride_offers ro ON ro.id = bk.ride_offer_id '
            ."WHERE ro.driver_id = users.id AND bk.booking_status = 'completed') AS completed_rides_count"
        );
    }

    /** Public fields for a passenger shown to drivers (phone is masked by the caller until confirmed). */
    public function scopePassengerProfile(Builder $query): Builder
    {
        return $query->select(['users.id', 'users.name', 'users.gender', 'users.profile_image', 'users.is_verified', 'users.phone'])
            ->selectRaw(
                self::ratingSql().', '
                ."(SELECT COUNT(*) FROM bookings pb WHERE pb.passenger_id = users.id AND pb.booking_status = 'completed') AS completed_bookings_count"
            );
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

    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    public function trackingPoints(): HasMany
    {
        return $this->hasMany(RideTracking::class, 'driver_id');
    }
}

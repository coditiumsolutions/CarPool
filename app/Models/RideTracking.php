<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RideTracking extends Model
{
    /** Table only has recorded_at, no created_at/updated_at. */
    public $timestamps = false;

    protected $table = 'ride_tracking';

    protected $fillable = ['ride_id', 'driver_id', 'latitude', 'longitude', 'recorded_at'];

    protected function casts(): array
    {
        return ['recorded_at' => 'datetime'];
    }

    public function ride(): BelongsTo
    {
        return $this->belongsTo(Ride::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }
}

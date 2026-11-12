<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RideStop extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'ride_stops';

    protected $fillable = ['ride_id', 'stop_name', 'latitude', 'longitude', 'stop_order'];

    public function ride(): BelongsTo
    {
        return $this->belongsTo(Ride::class);
    }
}

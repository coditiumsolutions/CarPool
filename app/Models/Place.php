<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Place extends Model
{
    protected $table = 'places';

    protected $fillable = ['city_id', 'name', 'latitude', 'longitude'];

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}

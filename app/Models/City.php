<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class City extends Model
{
    protected $table = 'cities';

    protected $fillable = ['city_name', 'country', 'status'];

    protected function casts(): array
    {
        return ['status' => 'boolean'];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function ridesFrom(): HasMany
    {
        return $this->hasMany(Ride::class, 'from_city_id');
    }

    public function ridesTo(): HasMany
    {
        return $this->hasMany(Ride::class, 'to_city_id');
    }
}

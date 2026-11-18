<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleDocument extends Model
{
    protected $table = 'vehicle_documents';

    protected $fillable = ['vehicle_id', 'document_type', 'document_image', 'expiry_date', 'verification_status', 'rejection_reason'];

    /** Private storage path; files are served through the authenticated file endpoint. */
    protected $hidden = ['document_image'];

    protected function casts(): array
    {
        return ['expiry_date' => 'date:Y-m-d'];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}

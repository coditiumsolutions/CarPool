<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Complaint extends Model
{
    protected $table = 'complaints';

    protected $fillable = ['booking_id', 'user_id', 'subject', 'description', 'complaint_status', 'category'];

    /** complaints.complaint_status values. */
    public const STATUSES = ['open', 'in_progress', 'resolved', 'rejected'];

    public const CATEGORIES = ['payment', 'driver_behavior', 'passenger_behavior', 'safety', 'vehicle', 'app_issue', 'other'];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

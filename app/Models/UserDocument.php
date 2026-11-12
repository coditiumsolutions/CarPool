<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDocument extends Model
{
    protected $table = 'user_documents';

    protected $fillable = [
        'user_id', 'document_type', 'document_number', 'document_image', 'expiry_date', 'verification_status',
    ];

    /** Document numbers (CNIC, licence) are sensitive. */
    protected $hidden = ['document_number'];

    protected function casts(): array
    {
        return ['expiry_date' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

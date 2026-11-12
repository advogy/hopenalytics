<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A church's recorded membership as of a date. Uploaded by a Division admin
 * (MembershipImportController), then confirmed or rejected by the Union admin
 * (MembershipConfirmationController). Only confirmed figures count toward engagement.
 */
class ChurchMembership extends Model
{
    public const PENDING = 'pending';

    public const CONFIRMED = 'confirmed';

    public const REJECTED = 'rejected';

    protected $fillable = ['church_id', 'as_of', 'members_count', 'status', 'uploaded_by', 'reviewed_by', 'reviewed_at'];

    protected $casts = [
        'as_of' => 'date',
        'members_count' => 'integer',
        'reviewed_at' => 'datetime',
    ];

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}

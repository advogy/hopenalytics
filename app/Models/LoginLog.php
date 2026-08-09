<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class LoginLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['user_id', 'ip_address', 'user_agent', 'logged_out_at'];

    protected $casts = [
        'logged_out_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Human-readable session length ("2 jam 15 menit") — null when still active (no
     * logged_out_at yet) or when the session expired/was abandoned without an explicit logout
     * (see the migration's own comment on logged_out_at), since there's nothing honest to show
     * in either case.
     */
    public function getDurationLabelAttribute(): ?string
    {
        if (! $this->logged_out_at) {
            return null;
        }

        return $this->created_at->diffForHumans($this->logged_out_at, [
            'syntax' => Carbon::DIFF_ABSOLUTE,
            'parts' => 2,
        ]);
    }
}

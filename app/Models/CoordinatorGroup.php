<?php

namespace App\Models;

use App\Enums\GroupPlatform;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One chat-group link (WhatsApp, Messenger, …) for either a specific Union
 * (union_id set) or the app-wide default (union_id null) — see the Settings page's
 * Koordinator Global tab and Kelola Akun → Uni.
 */
class CoordinatorGroup extends Model
{
    protected $fillable = ['union_id', 'platform', 'url'];

    protected $casts = [
        'platform' => GroupPlatform::class,
    ];

    public function union(): BelongsTo
    {
        return $this->belongsTo(Union::class);
    }
}

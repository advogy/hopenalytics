<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use Prunable;

    /** Entries older than this are deleted daily by `model:prune` (see routes/console.php). */
    public const RETENTION_MONTHS = 12;

    const UPDATED_AT = null;

    protected $fillable = ['actor_id', 'actor_name', 'action', 'subject_type', 'subject_id', 'subject_label', 'description'];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subMonths(self::RETENTION_MONTHS));
    }
}

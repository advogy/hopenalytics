<?php

namespace App\Models;

use Illuminate\Bus\Batch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Bus;

/**
 * One "Kirim Email" broadcast — see the migration's own doc comment for why this doesn't track
 * per-recipient status itself (Laravel's own job_batches table already does, keyed by batch_id).
 */
class EmailBroadcast extends Model
{
    protected $fillable = ['sender_id', 'division_id', 'subject', 'body', 'groups', 'total_recipients', 'batch_id'];

    protected $casts = ['groups' => 'array'];

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    /**
     * Null once the batch itself has aged out of job_batches (Laravel prunes finished batches —
     * see config/queue.php's prune schedule, if any) — callers should treat that the same as "no
     * longer trackable, but total_recipients/created_at still tell the history story."
     */
    public function batch(): ?Batch
    {
        return $this->batch_id ? Bus::findBatch($this->batch_id) : null;
    }
}

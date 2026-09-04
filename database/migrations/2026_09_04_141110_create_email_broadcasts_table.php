<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per "Kirim Email" broadcast (Admin Global/Divisi nudging every social-media-owning
     * account to update their data — see Admin\EmailBroadcastController). Deliberately doesn't
     * track per-recipient status itself — the actual send is a Bus::batch() of
     * SendBulkAnnouncementEmail jobs (same throttled-via-delay() pattern already proven safe on
     * this exact Hostinger setup by ChurchRefreshController::all()), and Laravel's own
     * `job_batches` table (already used by that feature) already tracks
     * total/processed/failed counts per batch id — no need to duplicate that here.
     */
    public function up(): void
    {
        Schema::create('email_broadcasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            // Null for an Admin Global send (nationwide) — set for an Admin Divisi send, purely
            // as a record of what was targeted at the time (a Division could theoretically be
            // renamed/removed later; this is display-only, never re-queried to resend).
            $table->foreignId('division_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject');
            $table->text('body');
            // Which recipient groups this send targeted (see
            // EmailBroadcastController::GROUPS) — display-only history from here on, never
            // re-queried to resend; a role's meaning doesn't change after the fact the way a
            // Division's could, so no fuzziness here the way division_id above has to allow for.
            $table->json('groups');
            $table->unsignedInteger('total_recipients');
            // Laravel's own batch id (uuid) — null only in the moment between creating this row
            // and actually dispatching the batch, never persisted null.
            $table->string('batch_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_broadcasts');
    }
};

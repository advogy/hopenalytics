<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('admin_suggestions', function (Blueprint $table) {
            $table->id();
            // The member who typed a not-yet-existing Gereja name during Lengkapi Profil (or
            // later, Profil Saya's Wilayah section) — see CompleteProfileController::store()/
            // PersonController's own equivalent. Cascades: if the requester's account is ever
            // deleted outright, their pending suggestion goes with it rather than dangling.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained()->cascadeOnDelete();
            // Always a real, already-existing Conference — only the church NAME is unverified.
            $table->foreignId('conference_id')->constrained()->cascadeOnDelete();
            $table->string('church_name');
            $table->string('status')->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            // Set on approval — the Church that was actually created (or matched, if someone
            // else's suggestion for the same name/conference was approved first) for this
            // request, so the requester's own history stays traceable even though their Person's
            // own church_id could later be changed again independently.
            $table->foreignId('resulting_church_id')->nullable()->constrained('churches')->nullOnDelete();
            $table->timestamps();

            // No DB-level uniqueness on (user_id, status): a rejected request should still be
            // resubmittable later with a different name, which would mean a second 'rejected'
            // row over time — that's a real history, not a duplicate. "One PENDING suggestion
            // per requester at a time" is instead enforced in application code (see
            // FindsOrCreatesChurch::findExistingChurchOrSuggestAdmin(), which updates an
            // existing pending row in place rather than creating a second one).
            $table->index(['conference_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_suggestions');
    }
};

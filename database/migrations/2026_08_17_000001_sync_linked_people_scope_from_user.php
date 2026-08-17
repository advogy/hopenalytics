<?php

use App\Models\Person;
use Illuminate\Database\Migrations\Migration;

/**
 * One-time backfill: until this point, a linked Person's own union_id/conference_id and its
 * linked User's self-reported union_id/conference_id (Profil Saya's "Wilayah" tab, as it existed
 * at the time) were two entirely disconnected fields answering the same real-world question, so
 * existing data had already drifted apart in production (confirmed: a linked Person reporting no
 * Daerah while their own user account reported one). This aligns every already-linked Person to
 * its user's report at the time, one time.
 *
 * Superseded moments later by 2026_08_17_000003, once Person also gained its own church_id
 * column and became the sole place this data lives going forward (see
 * CompleteProfileController::store()) — kept here, inlined rather than calling the
 * Person::syncScopeFromUser() helper that briefly existed for this, since that helper no longer
 * exists and a migration must stay runnable standalone on a fresh install.
 */
return new class extends Migration
{
    public function up(): void
    {
        Person::query()->whereNotNull('user_id')->with('user')->each(function (Person $person) {
            if ($person->user) {
                $person->update(['union_id' => null, 'conference_id' => $person->user->conference_id]);
            }
        });
    }

    public function down(): void
    {
        // Not reversible — the prior (drifted) values weren't recorded anywhere.
    }
};

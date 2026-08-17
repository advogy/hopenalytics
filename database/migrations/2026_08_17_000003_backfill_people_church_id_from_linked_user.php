<?php

use App\Models\Person;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * One-time move of a self-registered member's reported region from users.union_id/conference_id/
 * church_id onto their linked Person, now that Person is the sole place this lives going forward
 * (see CompleteProfileController::store()) — church_id specifically, since union_id/conference_id
 * were already backfilled by the 2026_08_17_000001 migration, but that one ran before this
 * table had a church_id column to copy into.
 *
 * Deliberately scoped to role === null users only — a role-holder's union_id/conference_id/
 * church_id means their own assigned admin scope (see UserAssignmentController::promote()), not
 * a personal region report, and must never be copied onto a Person.
 */
return new class extends Migration
{
    public function up(): void
    {
        Person::query()
            ->whereNotNull('user_id')
            ->whereHas('user', fn ($query) => $query->whereNull('role'))
            ->with('user')
            ->each(function (Person $person) {
                $person->update([
                    'union_id' => null,
                    'conference_id' => $person->user->conference_id,
                    'church_id' => $person->user->church_id,
                ]);
            });
    }

    public function down(): void
    {
        // Not reversible — the prior values weren't recorded anywhere.
    }
};

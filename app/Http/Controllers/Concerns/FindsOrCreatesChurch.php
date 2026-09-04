<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\AdminSuggestionStatus;
use App\Models\AdminSuggestion;
use App\Models\Church;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Shared by CompleteProfileController (the one-time Lengkapi Profil interstitial) and
 * PersonController (Profil Saya's "Info Personal" tab, where the same Wilayah fields live
 * folded in alongside the rest of a member's own Person edit) — both let a member type their
 * Gereja's name freehand rather than picking from a list, since not every church is guaranteed
 * to already exist in the system yet.
 */
trait FindsOrCreatesChurch
{
    /**
     * The member-facing entry point (used by both callers above) — an EXISTING church name
     * (case-insensitive, so "GMAHK Kelapa Gading" and "gmahk kelapa gading" both resolve to the
     * same row) links normally, same as always. A genuinely NEW name never creates the Church
     * outright anymore: per the user's explicit call, typing a name nobody's registered yet
     * reads as "I'm likely this church's own admin" — held as a pending AdminSuggestion for
     * Daerah-and-up review (see Admin\AdminSuggestionController) instead of an unreviewed
     * Church + implicit admin claim appearing instantly. Returns the matched Church, or null
     * when a suggestion was created/updated instead — the caller must leave the Person's own
     * church_id unset in that case (same as if they'd left Gereja blank entirely) and tell the
     * member their request is pending.
     *
     * A second submission overwrites the requester's own still-pending suggestion in place
     * (editing Lengkapi Profil/Wilayah again before it's been reviewed) rather than piling up a
     * duplicate row — a previously rejected or approved one is untouched history, never reused.
     */
    private function findExistingChurchOrSuggestAdmin(User $user, int $conferenceId, string $name): ?Church
    {
        $existing = Church::where('conference_id', $conferenceId)
            ->whereRaw('LOWER(name) = ?', [Str::lower(trim($name))])
            ->first();

        if ($existing) {
            return $existing;
        }

        $pending = AdminSuggestion::where('user_id', $user->id)->where('status', AdminSuggestionStatus::Pending)->first();

        if ($pending) {
            $pending->update(['conference_id' => $conferenceId, 'church_name' => trim($name)]);
        } else {
            AdminSuggestion::create([
                'user_id' => $user->id,
                'person_id' => $user->person->id,
                'conference_id' => $conferenceId,
                'church_name' => trim($name),
                'status' => AdminSuggestionStatus::Pending,
            ]);
        }

        return null;
    }

    /**
     * The actual create-or-reuse — now only ever called from
     * Admin\AdminSuggestionController::approve(), at the moment a suggestion is accepted (never
     * directly from a member-facing form anymore, see findExistingChurchOrSuggestAdmin() above).
     * Re-checks for an existing match at approval time too, in case a second member separately
     * registered the same real church (by an exact-matching name, in the same Daerah) in the
     * meantime — the second suggestion then just resolves to the first one's Church instead of
     * creating a sibling duplicate.
     */
    private function findOrCreateChurch(int $conferenceId, string $name): Church
    {
        $existing = Church::where('conference_id', $conferenceId)
            ->whereRaw('LOWER(name) = ?', [Str::lower(trim($name))])
            ->first();

        if ($existing) {
            return $existing;
        }

        $original = Str::slug($name);
        $slug = $original;
        $i = 1;

        for ($attempt = 0; $attempt < 5; $attempt++) {
            while (Church::where('slug', $slug)->exists()) {
                $slug = "{$original}-{$i}";
                $i++;
            }

            try {
                return Church::create([
                    'conference_id' => $conferenceId,
                    'name' => trim($name),
                    'slug' => $slug,
                    'is_active' => true,
                ]);
            } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                // Another request created a church with this exact slug between our check
                // above and this insert (e.g. two people completing profiles for the same
                // new church at once) — recompute against the now-current state and retry.
                $slug = "{$original}-{$i}";
                $i++;
            }
        }

        throw new \RuntimeException('Could not generate a unique church slug after 5 attempts.');
    }
}

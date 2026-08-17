<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Church;
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
     * Reuses an existing church by name within the chosen conference (case-insensitive, so
     * "GMAHK Kelapa Gading" and "gmahk kelapa gading" don't create duplicates) or creates a
     * new one — same slug-uniqueness pattern as ChurchController::store().
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

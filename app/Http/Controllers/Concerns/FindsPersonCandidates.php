<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Person;
use Illuminate\Support\Collection;

trait FindsPersonCandidates
{
    /**
     * Independent (never-linked) Person records whose name plausibly matches a newly
     * registered member — lets them claim a Person an admin already created and started
     * tracking, instead of ending up with two disconnected records for the same individual.
     * Matched loosely (substring either direction) since real names vary in spelling/titles
     * ("Pdt. John Doe" vs "John Doe") — a human confirms the match on the link-person
     * screen, so a loose match here just means an extra candidate shown, not a silent wrong
     * link. Capped at 5 and only ever computed from the caller's OWN name (never client
     * input) — LinkPersonController re-derives this same list server-side before honoring a
     * claim, so a submitted person_id can't be used to grab an unrelated person's record.
     */
    private function findPersonCandidates(string $name): Collection
    {
        $normalized = mb_strtolower(trim($name));

        if ($normalized === '') {
            return collect();
        }

        return Person::whereNull('user_id')
            ->withCount('socials')
            ->get()
            ->filter(function (Person $person) use ($normalized) {
                $personName = mb_strtolower(trim($person->name));

                return $personName !== '' && (
                    str_contains($personName, $normalized) || str_contains($normalized, $personName)
                );
            })
            ->take(5)
            ->values();
    }
}

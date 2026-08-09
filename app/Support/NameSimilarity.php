<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Fuzzy "did you mean an existing entity" matching for the Uni/Daerah/Gereja/Institusi/Personal
 * create-and-edit forms — deliberately loose (per the user's explicit call) so it catches
 * same-place-different-wording near-duplicates like "GMAHK Bekasi" vs "Gereja Bekasi" vs
 * "Jemaat Bekasi Pusat", not just typos. This is advisory only: callers never block submission
 * on a match, they just surface it for the admin to judge.
 */
class NameSimilarity
{
    /**
     * Common Indonesian Adventist naming cruft that would otherwise dominate the comparison and
     * mask the part of the name that actually distinguishes one entity from another (e.g. two
     * unrelated churches both starting "GMAHK Jemaat ..." would look artificially close without
     * this stripped first).
     */
    private const STOP_WORDS = ['gmahk', 'gereja', 'jemaat', 'advent', 'masehi', 'hari', 'ketujuh', 'sda'];

    /** Loose threshold (percent, via similar_text()) — more suggestions, admin filters by eye. */
    private const THRESHOLD = 40;

    private const MAX_RESULTS = 5;

    public static function normalize(string $name): string
    {
        $name = Str::lower(trim($name));
        $name = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $name) ?? $name;
        $words = array_filter(preg_split('/\s+/', trim($name)) ?: [], fn ($w) => $w !== '' && ! in_array($w, self::STOP_WORDS, true));

        return $words ? implode(' ', $words) : Str::lower(trim($name));
    }

    /**
     * @param  Collection<int, object>  $candidates  each must expose $nameKey as a string property
     * @return Collection<int, array{model: object, percent: float}> sorted highest-first, capped at MAX_RESULTS
     */
    public static function findSimilar(string $needle, Collection $candidates, string $nameKey = 'name'): Collection
    {
        $normalizedNeedle = self::normalize($needle);

        if ($normalizedNeedle === '') {
            return collect();
        }

        return $candidates
            ->map(function ($candidate) use ($normalizedNeedle, $nameKey) {
                $normalizedCandidate = self::normalize((string) $candidate->{$nameKey});
                similar_text($normalizedNeedle, $normalizedCandidate, $percent);

                // A short name fully contained in a longer one (or vice versa) reads as an
                // obvious near-duplicate to a human even when similar_text()'s character-run
                // matching scores it lower — e.g. "bekasi" vs "bekasi pusat".
                if ($normalizedCandidate !== '' && (str_contains($normalizedCandidate, $normalizedNeedle) || str_contains($normalizedNeedle, $normalizedCandidate))) {
                    $percent = max($percent, 70.0);
                }

                return ['model' => $candidate, 'percent' => $percent];
            })
            ->filter(fn ($row) => $row['percent'] >= self::THRESHOLD)
            ->sortByDesc('percent')
            ->take(self::MAX_RESULTS)
            ->values();
    }
}

<?php

namespace App\Http\Controllers\Concerns;

use App\Models\AppSetting;
use App\Models\Church;
use App\Models\ChurchSocial;
use App\Models\Conference;
use App\Models\Union;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * "Pertumbuhan gabungan": a region's growth score over its organisation accounts AND the
 * personal accounts of the people in it, per the user's explicit call.
 *
 *  - Gereja: the church's own accounts + personal accounts of people who chose that church.
 *  - Daerah: the Daerah's own accounts + every church in it + every person in it.
 *  - Uni / Divisi: the same, one level up each.
 *
 * Every account weighs the same, like the regular growth score; the organisation and personal
 * parts are also scored separately, with their account counts, so it stays visible where the
 * combined number comes from. Personal accounts only count with their owner's consent.
 * Institutions aren't part of any of this.
 *
 * Each account's sample is its latest weekly comparison per metric — the same pair and the same
 * capped percent as the regular score (BuildsLeaderboards::latestTwoStats()/growthPercent()),
 * so the two always agree for an account. Needs BuildsLeaderboards alongside it.
 */
trait BuildsCombinedGrowth
{
    public const COMBINED_LEVELS = ['church', 'conference', 'union', 'division'];

    /** Minutes a computed set of scores is reused; new snapshots or account changes invalidate it sooner. */
    private const COMBINED_CACHE_MINUTES = 60;

    /** @var array<string, Collection<int, ChurchSocial>> */
    private array $combinedSocialsCache = [];

    /**
     * Combined growth for one region, with its sparkline history and per-account breakdown —
     * for that region's detail page. Only that region's accounts are loaded.
     *
     * @return array{history: array, metrics: array, breakdown: array, sampleCount: int, sampleSum: float, own: ?float, personal: ?float, ownCount: int, personalCount: int}
     */
    protected function combinedGrowthDetail(string $level, int $id): array
    {
        $socials = $this->combinedSocials($level, $id)->filter(fn ($social) => $social->region[$level] == $id)->values();
        $parts = $this->aggregateSamples($socials, $this->latestSamples($socials));

        return $this->growthScoreHistory($socials) + [
            'own' => $parts['own'], 'personal' => $parts['personal'],
            'ownCount' => $parts['ownCount'], 'personalCount' => $parts['personalCount'],
        ];
    }

    /**
     * Latest combined score for many regions of one level at once — for rankings and tables.
     * One pass over the accounts, and reused across requests until new data arrives.
     *
     * @return Collection<int, array{score: ?float, own: ?float, personal: ?float, ownCount: int, personalCount: int, metrics: array}> keyed by region id
     */
    protected function combinedGrowthScores(string $level, ?iterable $ids = null): Collection
    {
        $all = Cache::remember(
            'combined-growth:'.$level.':'.$this->combinedDataFingerprint(),
            now()->addMinutes(self::COMBINED_CACHE_MINUTES),
            function () use ($level) {
                $socials = $this->combinedSocials();
                $samples = $this->latestSamples($socials);

                return $socials->filter(fn ($social) => $social->region[$level] !== null)
                    ->groupBy(fn ($social) => $social->region[$level])
                    ->map(fn (Collection $group) => $this->aggregateSamples($group, $samples))
                    ->all();
            },
        );

        $scores = collect($all);

        return $ids === null ? $scores : $scores->only(collect($ids)->all());
    }

    /**
     * Each account's latest weekly growth per metric, as capped percents.
     *
     * @return array<int, array<string, float>> keyed by account id, then metric
     */
    private function latestSamples(Collection $socials): array
    {
        $this->primeLatestTwoStats($socials);
        $samples = [];

        foreach (AppSetting::current()->enabledMetricValues() as $metric) {
            [$applicable, $fieldResolver] = $this->metricDefinition($metric, $socials);

            foreach ($applicable as $social) {
                $stats = $this->latestTwoStats($social);

                if ($stats->count() < 2) {
                    continue;
                }

                $field = $fieldResolver($social);
                $percent = self::growthPercent($stats[1]->{$field}, $stats[0]->{$field});

                if ($percent !== null) {
                    $samples[$social->id][$metric] = $percent;
                }
            }
        }

        return $samples;
    }

    /** Combined, organisation and personal scores for one group of accounts, from their samples. */
    private function aggregateSamples(Collection $group, array $samples): array
    {
        $average = fn (array $values) => $values === [] ? null : round(array_sum($values) / count($values), 2);
        $all = $own = $personal = [];
        $byMetric = [];

        foreach ($group as $social) {
            foreach ($samples[$social->id] ?? [] as $metric => $percent) {
                $all[] = $percent;
                $byMetric[$metric][] = $percent;
                if ($social->kind === 'personal') {
                    $personal[] = $percent;
                } else {
                    $own[] = $percent;
                }
            }
        }

        return [
            'score' => $average($all),
            'own' => $average($own),
            'personal' => $average($personal),
            'ownCount' => $group->where('kind', 'own')->count(),
            'personalCount' => $group->where('kind', 'personal')->count(),
            'metrics' => array_map($average, $byMetric),
        ];
    }

    /**
     * Changes whenever anything the scores depend on changes — a new snapshot, an account or
     * person edited (consent, church, active) — so cached scores are never stale.
     */
    private function combinedDataFingerprint(): string
    {
        return implode('|', [
            DB::table('church_stats')->max('id'),
            DB::table('church_socials')->max('updated_at'),
            DB::table('church_socials')->count(),
            DB::table('people')->max('updated_at'),
            DB::table('churches')->max('updated_at'),
            implode(',', AppSetting::current()->enabledMetricValues()),
        ]);
    }

    /**
     * Active accounts of active owners except institutions', each tagged with the church, Daerah,
     * Uni and Divisi it belongs to (->region) and whether it's an organisation or personal
     * account (->kind). Given a level and id, only accounts that could belong to that region are
     * loaded (the PHP-side ->region check is still the final word).
     */
    private function combinedSocials(?string $level = null, ?int $id = null): Collection
    {
        $cacheKey = $level ? "{$level}:{$id}" : 'all';

        if (isset($this->combinedSocialsCache[$cacheKey])) {
            return $this->combinedSocialsCache[$cacheKey];
        }

        $churchConference = Church::query()->where('is_active', true)->pluck('conference_id', 'id');
        $conferenceUnion = Conference::query()->pluck('union_id', 'id');
        $unionDivision = Union::query()->pluck('division_id', 'id');

        $up = function (?int $church, ?int $conference, ?int $union, ?int $division) use ($churchConference, $conferenceUnion, $unionDivision) {
            $conference ??= $church ? ($churchConference[$church] ?? null) : null;
            $union ??= $conference ? ($conferenceUnion[$conference] ?? null) : null;
            $division ??= $union ? ($unionDivision[$union] ?? null) : null;

            return ['church' => $church, 'conference' => $conference, 'union' => $union, 'division' => $division];
        };

        return $this->combinedSocialsCache[$cacheKey] = ChurchSocial::query()
            ->where('is_active', true)
            ->whereNull('institution_id')
            ->ownerActive()
            ->consentGranted()
            ->when($level, fn (Builder $q) => $this->restrictToRegion($q, $level, $id, $churchConference, $conferenceUnion, $unionDivision))
            ->with('person:id,church_id,conference_id,union_id,name')
            ->get()
            ->map(function (ChurchSocial $social) use ($up, $churchConference) {
                if ($social->person_id !== null) {
                    $person = $social->person;
                    // A person whose church is inactive still belongs to its Daerah, not the church.
                    $church = $person->church_id && $churchConference->has($person->church_id) ? $person->church_id : null;
                    $social->kind = 'personal';
                    $social->region = $up($church, $person->conference_id, $person->union_id, null);
                } else {
                    $social->kind = 'own';
                    $social->region = $up(
                        $social->church_id && $churchConference->has($social->church_id) ? $social->church_id : null,
                        $social->conference_id,
                        $social->union_id,
                        $social->division_id,
                    );
                }

                return $social;
            })
            ->filter(fn ($social) => array_filter($social->region) !== [])
            // A plain collection: grouping and only() work on region ids, which an Eloquent
            // collection would read as model keys instead.
            ->toBase();
    }

    /** Narrows the query to accounts owned in (or by people in) one region, by id lists. */
    private function restrictToRegion(Builder $query, string $level, int $id, Collection $churchConference, Collection $conferenceUnion, Collection $unionDivision): Builder
    {
        $unions = match ($level) {
            // Loose comparisons: ids plucked from the database may come back as strings.
            'division' => $unionDivision->filter(fn ($division) => $division == $id)->keys()->all(),
            'union' => [$id],
            default => [],
        };
        $conferences = match ($level) {
            'division', 'union' => $conferenceUnion->filter(fn ($union) => in_array($union, $unions))->keys()->all(),
            'conference' => [$id],
            default => [],
        };
        $churches = $level === 'church' ? [$id] : $churchConference->filter(fn ($conference) => in_array($conference, $conferences))->keys()->all();

        return $query->where(fn (Builder $q) => $q
            ->whereIn('church_id', $churches)
            ->orWhereIn('conference_id', $conferences)
            ->orWhereIn('union_id', $unions)
            ->when($level === 'division', fn ($q2) => $q2->orWhere('division_id', $id))
            ->orWhereHas('person', fn (Builder $p) => $p
                ->whereIn('church_id', $churches)
                ->orWhereIn('conference_id', $conferences)
                ->orWhereIn('union_id', $unions)));
    }
}

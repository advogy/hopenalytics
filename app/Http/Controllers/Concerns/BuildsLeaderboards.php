<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\SocialPlatform;
use App\Models\ChurchSocial;
use Illuminate\Support\Collection;

trait BuildsLeaderboards
{
    protected function leaderboardTitles(): array
    {
        return [
            'reach' => ['title' => 'Followers / Subscribers', 'subtitle' => __('dashboard.reach_subtitle')],
            'views' => ['title' => 'Views', 'subtitle' => __('dashboard.views_subtitle')],
            'likes' => ['title' => 'Likes', 'subtitle' => __('dashboard.likes_subtitle')],
            'posts' => ['title' => 'Post / Video', 'subtitle' => __('dashboard.posts_subtitle')],
        ];
    }

    /**
     * Which platforms each growth metric applies to — views only exist for YouTube,
     * likes only for TikTok, posts don't apply to Facebook, reach applies everywhere.
     * "semua" (every applicable platform combined) is always offered first.
     */
    protected function metricPlatforms(): array
    {
        return [
            'reach' => ['semua', 'youtube', 'instagram', 'tiktok', 'facebook'],
            'views' => ['semua', 'youtube'],
            'likes' => ['semua', 'tiktok'],
            'posts' => ['semua', 'youtube', 'instagram', 'tiktok'],
        ];
    }

    /**
     * $scoped = false is for the public presentation pages, which must show the same
     * general data no matter who (if anyone) happens to be logged in while viewing them —
     * never the viewer's own role/scope.
     */
    protected function activeSocials(bool $scoped = true): Collection
    {
        return ChurchSocial::query()
            ->with('church')
            ->where('is_active', true)
            ->whereHas('church', fn ($q) => $q->where('is_active', true))
            ->when($scoped, fn ($q) => $q->visibleTo(auth()->user()))
            ->get();
    }

    protected function activeSocialsPersonal(bool $scoped = true): Collection
    {
        return ChurchSocial::query()
            ->with('person')
            ->where('is_active', true)
            ->whereHas('person', fn ($q) => $q->where('is_active', true))
            ->when($scoped, fn ($q) => $q->visibleTo(auth()->user()))
            ->get();
    }

    /**
     * Resolve which socials qualify for a growth metric, and the stat field to compare.
     *
     * @return array{0: Collection, 1: callable}
     */
    protected function metricDefinition(string $metric, Collection $activeSocials): array
    {
        return match ($metric) {
            'reach' => [
                $activeSocials,
                fn ($social) => $social->platform === SocialPlatform::YouTube ? 'subscribers_count' : 'followers_count',
            ],
            'views' => [
                $activeSocials->where('platform', SocialPlatform::YouTube),
                fn () => 'views_count',
            ],
            'likes' => [
                $activeSocials->where('platform', SocialPlatform::TikTok),
                fn () => 'likes_count',
            ],
            'posts' => [
                $activeSocials->reject(fn ($social) => $social->platform === SocialPlatform::Facebook),
                fn ($social) => $social->platform === SocialPlatform::YouTube ? 'videos_count' : 'posts_count',
            ],
        };
    }

    /**
     * Rank socials by the given field — 'delta' (week-over-week growth, default) or
     * 'value' (current value), highest first.
     */
    protected function buildLeaderboard(Collection $socials, callable $fieldResolver, ?int $limit, string $sortBy = 'delta'): Collection
    {
        $ranked = $socials
            ->map(function (ChurchSocial $social) use ($fieldResolver) {
                $field = $fieldResolver($social);
                $stats = $social->stats()->limit(2)->get();

                if ($stats->count() < 2) {
                    return null;
                }

                return [
                    'social' => $social,
                    'delta' => ($stats[0]->{$field} ?? 0) - ($stats[1]->{$field} ?? 0),
                    'latest' => $stats[0]->{$field} ?? 0,
                ];
            })
            ->filter();

        $ranked = $sortBy === 'value' ? $ranked->sortByDesc('latest') : $ranked->sortByDesc('delta');

        return ($limit ? $ranked->take($limit) : $ranked)->values();
    }

    /**
     * Aggregate every church's current value + week-over-week growth for one metric
     * (reach, views, likes, or posts), optionally scoped to one platform, summed across
     * a church's "gereja" and "umum" accounts.
     *
     * $sortBy 'value' ranks by the metric's current total (highest first); 'delta' ranks
     * by weekly growth instead (highest first, churches with no growth data last).
     */
    protected function metricComparisonRows(string $metric, ?string $platform, string $sortBy = 'delta'): Collection
    {
        [$socials, $fieldResolver] = $this->metricDefinition($metric, $this->activeSocials());

        // "semua" means every applicable platform combined — no filtering, same as passing null.
        if ($platform && $platform !== 'semua') {
            $socials = $socials->where('platform', SocialPlatform::from($platform));
        }

        $rows = $socials
            ->groupBy('church_id')
            ->map(function (Collection $churchSocials) use ($fieldResolver) {
                $church = $churchSocials->first()->church;
                $currentTotal = 0;
                $deltaTotal = 0;
                $hasDelta = false;

                foreach ($churchSocials as $social) {
                    $field = $fieldResolver($social);
                    $stats = $social->stats()->limit(2)->get();
                    $currentTotal += $stats->get(0)?->{$field} ?? 0;

                    if ($stats->count() >= 2) {
                        $deltaTotal += ($stats[0]->{$field} ?? 0) - ($stats[1]->{$field} ?? 0);
                        $hasDelta = true;
                    }
                }

                return [
                    'church' => $church,
                    'label' => $church->name,
                    'value' => $currentTotal,
                    'delta' => $hasDelta ? $deltaTotal : null,
                ];
            });

        return ($sortBy === 'delta'
            ? $rows->sortByDesc(fn ($row) => $row['delta'] ?? -INF)
            : $rows->sortByDesc('value')
        )->values();
    }

    /**
     * Same as metricComparisonRows(), but aggregated per person instead of per church —
     * for the personal-accounts side of the analytics/comparison pages.
     */
    protected function metricComparisonRowsPersonal(string $metric, ?string $platform, string $sortBy = 'delta'): Collection
    {
        [$socials, $fieldResolver] = $this->metricDefinition($metric, $this->activeSocialsPersonal());

        if ($platform && $platform !== 'semua') {
            $socials = $socials->where('platform', SocialPlatform::from($platform));
        }

        $rows = $socials
            ->groupBy('person_id')
            ->map(function (Collection $personSocials) use ($fieldResolver) {
                $person = $personSocials->first()->person;
                $currentTotal = 0;
                $deltaTotal = 0;
                $hasDelta = false;

                foreach ($personSocials as $social) {
                    $field = $fieldResolver($social);
                    $stats = $social->stats()->limit(2)->get();
                    $currentTotal += $stats->get(0)?->{$field} ?? 0;

                    if ($stats->count() >= 2) {
                        $deltaTotal += ($stats[0]->{$field} ?? 0) - ($stats[1]->{$field} ?? 0);
                        $hasDelta = true;
                    }
                }

                return [
                    'person' => $person,
                    'label' => $person->name,
                    'value' => $currentTotal,
                    'delta' => $hasDelta ? $deltaTotal : null,
                ];
            });

        return ($sortBy === 'delta'
            ? $rows->sortByDesc(fn ($row) => $row['delta'] ?? -INF)
            : $rows->sortByDesc('value')
        )->values();
    }

    /**
     * Rank every church by a fair composite weekly-growth score.
     *
     * Each account's growth is measured as a percentage change (not a raw delta), so a
     * small church isn't penalised for having a smaller audience than a large one. The
     * composite score is the average percentage growth across every metric (reach,
     * views, likes, posts) that actually applies to that church's own accounts — a
     * church without a TikTok account, for example, simply isn't scored on likes.
     */
    protected function growthScoreRows(bool $scoped = true): Collection
    {
        $activeSocials = $this->activeSocials($scoped);
        $metrics = ['reach', 'views', 'likes', 'posts'];

        $percentBySocial = [];

        foreach ($metrics as $metric) {
            [$socials, $fieldResolver] = $this->metricDefinition($metric, $activeSocials);

            foreach ($socials as $social) {
                $field = $fieldResolver($social);
                $stats = $social->stats()->limit(2)->get();

                if ($stats->count() < 2) {
                    continue;
                }

                $previous = $stats[1]->{$field} ?? 0;
                $current = $stats[0]->{$field} ?? 0;

                if ($previous <= 0) {
                    continue;
                }

                $percentBySocial[$social->id]['church'] = $social->church;
                $percentBySocial[$social->id]['metrics'][$metric] = round((($current - $previous) / $previous) * 100, 2);
            }
        }

        return collect($percentBySocial)
            ->groupBy(fn ($entry) => $entry['church']->id)
            ->map(function (Collection $entries) {
                $church = $entries->first()['church'];

                $allPercents = $entries->flatMap(fn ($entry) => $entry['metrics'])->values();

                $byMetric = collect(['reach', 'views', 'likes', 'posts'])->mapWithKeys(function ($metric) use ($entries) {
                    $values = $entries->pluck("metrics.{$metric}")->filter(fn ($v) => $v !== null);

                    return [$metric => $values->isEmpty() ? null : round($values->avg(), 2)];
                });

                return [
                    'church' => $church,
                    'score' => $allPercents->isEmpty() ? null : round($allPercents->avg(), 2),
                    'metrics' => $byMetric,
                    'sampleCount' => $allPercents->count(),
                    'accountCount' => $entries->count(),
                ];
            })
            ->sortByDesc(fn ($row) => $row['score'] ?? -INF)
            ->values();
    }

    /**
     * Rank each platform (Instagram, TikTok, YouTube, Facebook) by the same composite
     * weekly-growth score as growthScoreRows(), grouped by platform instead of by
     * church/person — for comparing platform performance against each other.
     */
    protected function growthScoreRowsByPlatform(Collection $activeSocials): Collection
    {
        $metrics = ['reach', 'views', 'likes', 'posts'];

        $percentsByPlatform = [];

        foreach ($metrics as $metric) {
            [$socials, $fieldResolver] = $this->metricDefinition($metric, $activeSocials);

            foreach ($socials as $social) {
                $field = $fieldResolver($social);
                $stats = $social->stats()->limit(2)->get();

                if ($stats->count() < 2) {
                    continue;
                }

                $previous = $stats[1]->{$field} ?? 0;
                $current = $stats[0]->{$field} ?? 0;

                if ($previous <= 0) {
                    continue;
                }

                $percentsByPlatform[$social->platform->value]['metrics'][$metric][] = round((($current - $previous) / $previous) * 100, 2);
                $percentsByPlatform[$social->platform->value]['accountIds'][$social->id] = true;
            }
        }

        return collect($percentsByPlatform)
            ->map(function ($entry, $platform) {
                $byMetric = collect(['reach', 'views', 'likes', 'posts'])->mapWithKeys(function ($metric) use ($entry) {
                    $values = collect($entry['metrics'][$metric] ?? []);

                    return [$metric => $values->isEmpty() ? null : round($values->avg(), 2)];
                });

                $allPercents = collect($entry['metrics'])->flatten();

                return [
                    'platform' => $platform,
                    'score' => $allPercents->isEmpty() ? null : round($allPercents->avg(), 2),
                    'metrics' => $byMetric,
                    'accountCount' => count($entry['accountIds'] ?? []),
                ];
            })
            ->filter(fn ($row) => $row['score'] !== null)
            ->sortByDesc('score')
            ->values();
    }

    /**
     * Same composite weekly-growth score as growthScoreRows(), but per person instead
     * of per church — for the personal-accounts presentation board.
     */
    protected function growthScoreRowsPersonal(bool $scoped = true): Collection
    {
        $activeSocials = $this->activeSocialsPersonal($scoped);
        $metrics = ['reach', 'views', 'likes', 'posts'];

        $percentBySocial = [];

        foreach ($metrics as $metric) {
            [$socials, $fieldResolver] = $this->metricDefinition($metric, $activeSocials);

            foreach ($socials as $social) {
                $field = $fieldResolver($social);
                $stats = $social->stats()->limit(2)->get();

                if ($stats->count() < 2) {
                    continue;
                }

                $previous = $stats[1]->{$field} ?? 0;
                $current = $stats[0]->{$field} ?? 0;

                if ($previous <= 0) {
                    continue;
                }

                $percentBySocial[$social->id]['person'] = $social->person;
                $percentBySocial[$social->id]['metrics'][$metric] = round((($current - $previous) / $previous) * 100, 2);
            }
        }

        return collect($percentBySocial)
            ->groupBy(fn ($entry) => $entry['person']->id)
            ->map(function (Collection $entries) {
                $person = $entries->first()['person'];

                $allPercents = $entries->flatMap(fn ($entry) => $entry['metrics'])->values();

                $byMetric = collect(['reach', 'views', 'likes', 'posts'])->mapWithKeys(function ($metric) use ($entries) {
                    $values = $entries->pluck("metrics.{$metric}")->filter(fn ($v) => $v !== null);

                    return [$metric => $values->isEmpty() ? null : round($values->avg(), 2)];
                });

                return [
                    'person' => $person,
                    'score' => $allPercents->isEmpty() ? null : round($allPercents->avg(), 2),
                    'metrics' => $byMetric,
                    'sampleCount' => $allPercents->count(),
                    'accountCount' => $entries->count(),
                ];
            })
            ->sortByDesc(fn ($row) => $row['score'] ?? -INF)
            ->values();
    }

    /**
     * Composite growth score for one entity's own accounts, one per historical
     * week-transition (oldest first) — for a sparkline showing the score trend,
     * unlike growthScoreRows()/growthScoreRowsPersonal() which only look at the
     * latest transition across every entity at once.
     */
    protected function growthScoreHistory(Collection $socials, int $limit = 8): array
    {
        if ($socials->isEmpty()) {
            return [];
        }

        $metrics = ['reach', 'views', 'likes', 'posts'];

        $statsBySocial = $socials->mapWithKeys(fn ($social) => [
            $social->id => $social->stats()->orderBy('recorded_at')->get()->keyBy(fn ($s) => $s->recorded_at->toDateString()),
        ]);

        $allDates = $statsBySocial->flatMap(fn ($stats) => $stats->keys())->unique()->sort()->values();

        if ($allDates->count() < 2) {
            return [];
        }

        $scores = [];

        for ($i = 1; $i < $allDates->count(); $i++) {
            $previousDate = $allDates[$i - 1];
            $currentDate = $allDates[$i];
            $percents = [];

            foreach ($metrics as $metric) {
                [$applicableSocials, $fieldResolver] = $this->metricDefinition($metric, $socials);

                foreach ($applicableSocials as $social) {
                    $stats = $statsBySocial[$social->id];

                    if (! $stats->has($previousDate) || ! $stats->has($currentDate)) {
                        continue;
                    }

                    $field = $fieldResolver($social);
                    $previous = $stats[$previousDate]->{$field} ?? 0;
                    $current = $stats[$currentDate]->{$field} ?? 0;

                    if ($previous <= 0) {
                        continue;
                    }

                    $percents[] = (($current - $previous) / $previous) * 100;
                }
            }

            if (! empty($percents)) {
                $scores[] = round(array_sum($percents) / count($percents), 2);
            }
        }

        return array_slice($scores, -$limit);
    }
}

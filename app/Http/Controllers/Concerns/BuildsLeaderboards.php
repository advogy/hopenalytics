<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\SocialPlatform;
use App\Models\AppSetting;
use App\Models\ChurchSocial;
use App\Models\Conference;
use App\Models\Division;
use App\Models\Union;
use Illuminate\Support\Collection;

trait BuildsLeaderboards
{
    /**
     * True for a viewer who sees data spanning MULTIPLE Unions — Admin/Pimpinan Global (every
     * Union), a plain member (unscoped), Admin/Pimpinan Nasional (scoped to their own assigned
     * set of Unions, but still potentially more than one — see User::assignedUnions()), AND
     * Admin/Pimpinan Divisi (every Union under their Division, also potentially more than one).
     * All get the same "grouped by Union > Conference, with a Union filter" UI treatment; the
     * only thing that actually differs between them is WHICH Unions populate that filter — see
     * regionFilterOptions() below, the one place that distinction matters. Shared by every page
     * that offers the Uni/Daerah region filter + collapsible grouping (Analitik & Grafik,
     * Perbandingan Metrik, and the per-metric leaderboard pages).
     */
    protected function isNasionalView(): bool
    {
        $role = auth()->user()->role;

        return $role === null || ($role->hasGlobalAccess() ?? false) || in_array($role->level(), ['global', 'nasional', 'divisi'], true);
    }

    /** A uni-level viewer gets Daerah > entity grouping (no Uni tier — it'd always be their own single Uni). */
    protected function isUniView(): bool
    {
        return ! $this->isNasionalView() && auth()->user()->role->level() === 'uni';
    }

    /**
     * True for a viewer whose grouped tables should show a Divisi tier above Uni — everyone
     * isNasionalView() covers EXCEPT a Divisi-level viewer themselves, who's already scoped to
     * exactly one Divisi (same reasoning as isUniView() never showing its own Uni tier).
     */
    protected function showsDivisionTier(): bool
    {
        return $this->isNasionalView() && auth()->user()->role?->level() !== 'divisi';
    }

    /**
     * Union/Conference option lists for the region filter's cascading comboboxes, scoped to what
     * the viewer may pick from: Admin/Pimpinan Global (and unscoped members) see every Union;
     * Admin/Pimpinan Nasional see only their own assigned set (per the user's explicit call — one
     * country can have several Unions and one Union can span several countries, so this is a
     * direct assignment, not derived from a country); uni-level only ever sees their own Union's
     * Conferences (no Union combobox is rendered for them at all — see the region-filter partial).
     */
    protected function regionFilterOptions(?string $selectedUnionId): array
    {
        $isNasionalView = $this->isNasionalView();
        $isUniView = $this->isUniView();
        $user = auth()->user();
        $isTrulyGlobal = $user->role === null || ($user->role->hasGlobalAccess() ?? false) || $user->role->level() === 'global';
        // Derived from the actual level, not from isNasionalView() (which now also returns true
        // for 'divisi') — otherwise a Divisi viewer would incorrectly run the assignedUnionIds()
        // branch below (Admin Nasional's own Union set, meaningless to a Divisi user).
        $isScopedNasional = $user->role?->level() === 'nasional';
        $isDivisiView = $user->role?->level() === 'divisi';

        $unionOptions = match (true) {
            $isTrulyGlobal => Union::where('is_active', true)->orderBy('name')->get(),
            $isScopedNasional => Union::where('is_active', true)->whereIn('id', $user->assignedUnionIds())->orderBy('name')->get(),
            $isDivisiView => Union::where('is_active', true)->where('division_id', $user->division_id)->orderBy('name')->get(),
            default => collect(),
        };

        $conferenceOptions = Conference::where('is_active', true)
            ->when($isUniView, fn ($query) => $query->where('union_id', $user->union_id))
            ->when($isScopedNasional && ! $selectedUnionId, fn ($query) => $query->whereIn('union_id', $user->assignedUnionIds()))
            ->when($isDivisiView && ! $selectedUnionId, fn ($query) => $query->whereHas('union', fn ($q) => $q->where('division_id', $user->division_id)))
            ->when($isNasionalView && $selectedUnionId, fn ($query) => $query->where('union_id', $selectedUnionId))
            ->with('union')
            ->orderBy('name')
            ->get();

        return [$unionOptions, $conferenceOptions];
    }

    /**
     * Matches an entity (Church/Person/Institution/Union/Conference) against the selected
     * Uni/Daerah. Church has no union_id column of its own (only conference_id), hence the ??
     * fallback chain; it just always resolves to null there and falls through correctly to the
     * conference_id check. A raw Union/Conference (the organisasi scope's own entities — see
     * regionEntityUnion()/regionEntityConference() below) needs its own branch since it has no
     * conference_id/union_id *column of its own to compare directly* — it just *is* the Uni or
     * Daerah being filtered for.
     */
    protected function matchesRegionFilter(?string $selectedUnionId, ?string $selectedConferenceId): \Closure
    {
        return function ($entity) use ($selectedUnionId, $selectedConferenceId) {
            if ($entity instanceof Union || $entity instanceof Conference) {
                if ($selectedConferenceId) {
                    return $this->regionEntityConference($entity)?->id !== null
                        && (string) $this->regionEntityConference($entity)->id === (string) $selectedConferenceId;
                }

                if ($selectedUnionId) {
                    $entityUnionId = $this->regionEntityUnion($entity)?->id;

                    return $entityUnionId !== null && (string) $entityUnionId === (string) $selectedUnionId;
                }

                return true;
            }

            if ($selectedConferenceId) {
                return (string) $entity->conference_id === (string) $selectedConferenceId;
            }

            if ($selectedUnionId) {
                $entityUnionId = $entity->conference?->union_id ?? $entity->union_id ?? null;

                return $entityUnionId !== null && (string) $entityUnionId === (string) $selectedUnionId;
            }

            return true;
        };
    }

    /**
     * The Union a region-groupable entity belongs under — itself, if the entity IS a Union
     * (the organisasi scope's Uni-level rows); its own union relation, if it's a Conference
     * (organisasi's Daerah-level rows); otherwise the usual Church/Person/Institution chain.
     */
    private function regionEntityUnion($entity): ?Union
    {
        return match (true) {
            $entity instanceof Union => $entity,
            $entity instanceof Conference => $entity->union,
            default => $entity->conference?->union ?? $entity->union ?? null,
        };
    }

    /**
     * The Conference a region-groupable entity belongs under — itself, if the entity IS a
     * Conference; null for a Union (it has no single Daerah — it sits at the Uni-level tier,
     * same bucket as an entity with no conference_id at all); otherwise the usual chain.
     */
    private function regionEntityConference($entity): ?Conference
    {
        return match (true) {
            $entity instanceof Conference => $entity,
            $entity instanceof Union => null,
            default => $entity->conference ?? null,
        };
    }

    /** The Divisi a region-groupable entity's Union belongs to (or null — a Union not yet placed under any Divisi). */
    private function regionEntityDivision($entity): ?Division
    {
        return $this->regionEntityUnion($entity)?->division;
    }

    /**
     * Groups any rows collection into the same Uni > Daerah > entity nesting used across the
     * region-filterable pages — $entityAccessor plucks the Church/Person/Institution/Union/
     * Conference out of one row, since each caller's row shape differs (analytics'
     * ['church'=>...], metricComparison's ['entity'=>...], leaderboard's ['social'=>...] where
     * the entity is $social->church, organisasi's ['organization'=>...] which is a Union OR a
     * Conference — see regionEntityUnion()/regionEntityConference() above).
     */
    protected function groupByRegion(Collection $rows, callable $entityAccessor): Collection
    {
        return $rows
            ->groupBy(function ($row) use ($entityAccessor) {
                $union = $this->regionEntityUnion($entityAccessor($row));

                return $union?->id ?? 'nasional';
            })
            ->map(function ($unionRows) use ($entityAccessor) {
                $sample = $entityAccessor($unionRows->first());
                $union = $this->regionEntityUnion($sample);
                $division = $this->regionEntityDivision($sample);

                // A row whose entity IS the Union itself sits directly under the Union's own
                // header — Union sits above Daerah in the hierarchy, so it can never itself
                // "not have a Daerah yet" the way a church/institution/conference-owned row can.
                $ownRows = $unionRows->filter(fn ($row) => $entityAccessor($row) instanceof Union);
                $nestedRows = $unionRows->reject(fn ($row) => $entityAccessor($row) instanceof Union);

                return [
                    'label' => $union?->name ?? __('analytics.group_national'),
                    // Not part of the grouping key/nesting itself (the tree below stays Union >
                    // Conference > rows, unchanged) — just tagged onto each Union-group so a
                    // caller that wants a Divisi tier (see components/grouped-rows.blade.php's
                    // showDivisionHeader) can re-bucket these top-level entries by division for
                    // rendering, without this method needing to know or care whether that's
                    // actually happening on any given page.
                    'divisionId' => $division?->id ?? 'no-division',
                    'divisionName' => $division?->name ?? __('analytics.group_no_division'),
                    'rows' => $ownRows,
                    'conferences' => $nestedRows
                        ->groupBy(fn ($row) => $this->regionEntityConference($entityAccessor($row))?->id ?? 'uni-level')
                        ->map(function ($conferenceRows) use ($entityAccessor) {
                            $sample = $entityAccessor($conferenceRows->first());
                            $conference = $this->regionEntityConference($sample);

                            return [
                                'label' => $conference?->name ?? __('analytics.group_union_level'),
                                'rows' => $conferenceRows,
                            ];
                        })
                        ->sortBy('label'),
                ];
            })
            ->sortBy('label');
    }

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
     * likes only for TikTok, reach applies everywhere. Posts now includes Facebook too, via
     * recent_posts_count (see FacebookStatsFetcher) — a recent-~10-post sample rather than a
     * lifetime total the way YouTube's videos_count/Instagram-TikTok's posts_count are, since
     * Facebook exposes no lifetime post count via scraping; per the user's explicit call to
     * fold it into this metric anyway rather than leave Facebook without a posts comparison at
     * all. "semua" (every applicable platform combined) is always offered first.
     */
    protected function metricPlatforms(): array
    {
        // X has no views or likes metric tracked (only followers/following/posts_count —
        // see XStatsFetcher), so it's left out of those two, same as Facebook is left out
        // of 'likes'. Each list is further intersected with whichever platforms are
        // currently enabled (see AppSetting::enabledPlatformValues(), Settings' platform
        // toggle card) — a disabled platform must not show as an empty comparison tab.
        $enabled = AppSetting::current()->enabledPlatformValues();
        $onlyEnabled = fn (array $platforms) => array_values(array_intersect($platforms, ['semua', ...$enabled]));

        return [
            'reach' => $onlyEnabled(['semua', 'youtube', 'instagram', 'tiktok', 'facebook', 'x']),
            'views' => $onlyEnabled(['semua', 'youtube', 'instagram', 'tiktok']),
            'likes' => $onlyEnabled(['semua', 'tiktok']),
            'posts' => $onlyEnabled(['semua', 'youtube', 'instagram', 'tiktok', 'facebook', 'x']),
        ];
    }

    /**
     * $scoped = false is for the public presentation pages, which must show the same
     * general data no matter who (if anyone) happens to be logged in while viewing them —
     * never the viewer's own role/scope.
     */
    // $category ('gereja'/'umum', see SocialCategory) is church-scope-only — the other
    // owner-type variants below don't take it since their category is always a single fixed
    // value per ChurchSocialController's own validation (see analytics.blade.php's Gereja tab
    // for where this filter actually surfaces in the UI).
    protected function activeSocials(bool $scoped = true, ?string $category = null): Collection
    {
        return ChurchSocial::query()
            ->with('church.conference.union')
            ->where('is_active', true)
            ->whereHas('church', fn ($q) => $q->where('is_active', true))
            ->when($scoped, fn ($q) => $q->whereHas('church', fn ($q2) => $this->analyticsChurchScope($q2)))
            ->when($category, fn ($q) => $q->where('category', $category))
            ->get();
    }

    protected function activeSocialsPersonal(bool $scoped = true): Collection
    {
        return ChurchSocial::query()
            ->with(['person.conference.union', 'person.union'])
            ->where('is_active', true)
            ->whereHas('person', fn ($q) => $q->where('is_active', true))
            ->when($scoped, fn ($q) => $q->whereHas('person', fn ($q2) => $this->analyticsPersonScope($q2)))
            ->get();
    }

    /** Same shape as activeSocials()/activeSocialsPersonal(), for an institution's own organization-level accounts. */
    protected function activeSocialsInstitution(bool $scoped = true): Collection
    {
        return ChurchSocial::query()
            ->with(['institution.conference.union', 'institution.union'])
            ->where('is_active', true)
            ->whereHas('institution', fn ($q) => $q->where('is_active', true))
            ->when($scoped, fn ($q) => $q->whereHas('institution', fn ($q2) => $this->analyticsInstitutionScope($q2)))
            ->get();
    }

    /**
     * Union- and Conference-owned accounts together (the "Organisasi" scope — a ChurchSocial row
     * is owned by exactly one of union_id/conference_id here, never both, per
     * OrganizationSocialController). Unlike the other activeSocials*() variants, there's no
     * single owner relation to whereHas() an is_active check through, since a row's owner is one
     * of two different columns — both branches are checked directly instead.
     */
    protected function activeSocialsOrganization(bool $scoped = true): Collection
    {
        return ChurchSocial::query()
            ->with(['union', 'conference.union'])
            ->where('is_active', true)
            ->where(fn ($q) => $q
                ->where(fn ($q2) => $q2->whereNotNull('union_id')->whereHas('union', fn ($q3) => $q3->where('is_active', true)))
                ->orWhere(fn ($q2) => $q2->whereNotNull('conference_id')->whereHas('conference', fn ($q3) => $q3->where('is_active', true)))
            )
            ->when($scoped, fn ($q) => $this->analyticsOrganizationScope($q))
            ->get();
    }

    /**
     * Same church-visibility rule as Church::scopeVisibleTo(), except a gereja-level viewer
     * sees their whole Daerah/Konferens instead of just their single church (the same breadth
     * as an admin_daerah), and a plain member (role === null) sees everything unscoped —
     * analytics/statistics pages are meant to inform everyone, per the user's explicit call,
     * not just people with an assigned admin region. Church::scopeVisibleTo() itself stays
     * untouched: it also drives edit permissions via ChurchPolicy, so widening it there would
     * accidentally grant admin_gereja edit rights over their whole conference instead of just
     * their own church — this is a read-only, analytics-only exception.
     */
    private function analyticsChurchScope($query)
    {
        $user = auth()->user();

        return match (true) {
            $user->role === null => $query,
            $user->role->level() === 'gereja' => $query->where('conference_id', $user->church?->conference_id),
            default => $query->visibleTo($user),
        };
    }

    /** Same reasoning as analyticsChurchScope(), for Person instead of Church. */
    private function analyticsPersonScope($query)
    {
        $user = auth()->user();

        return match (true) {
            $user->role === null => $query,
            $user->role->level() === 'gereja' => $query->where('conference_id', $user->church?->conference_id),
            default => $query->visibleTo($user),
        };
    }

    /**
     * Same reasoning as analyticsChurchScope()/analyticsPersonScope(), for Institution instead
     * of Church/Person — except Institution::scopeVisibleTo() itself already returns everything
     * for role === null (nasional institutions apply to every union, per the user's explicit
     * call), so only the gereja-level branch needs overriding here: gereja-level isn't handled
     * by scopeVisibleTo() at all (falls to its empty default), so this widens it to the same
     * breadth as analyticsChurchScope() — nasional institutions plus their own Uni's and own
     * Daerah's institutions, derived from their church's conference the same way
     * Institution::scopeVisibleTo()'s daerah-level branch derives its own union.
     */
    private function analyticsInstitutionScope($query)
    {
        $user = auth()->user();

        if ($user->role === null) {
            return $query;
        }

        if ($user->role->level() !== 'gereja') {
            return $query->visibleTo($user);
        }

        $conferenceId = $user->church?->conference_id;
        $unionId = $user->church?->conference?->union_id;

        return $query->where(function ($q) use ($conferenceId, $unionId) {
            $q->where(fn ($q2) => $q2->whereNull('union_id')->whereNull('conference_id')) // nasional
                ->when($unionId, fn ($q2) => $q2->orWhere(
                    fn ($q3) => $q3->where('union_id', $unionId)->whereNull('conference_id')
                )) // this union's own
                ->when($conferenceId, fn ($q2) => $q2->orWhere('conference_id', $conferenceId)); // this daerah's own
        });
    }

    /**
     * Same reasoning as analyticsChurchScope()/analyticsPersonScope()/analyticsInstitutionScope(),
     * for Union/Conference-owned accounts — but operating directly on the ChurchSocial query
     * itself (union_id/conference_id are columns right there), not via a whereHas() on an owner
     * relation, since a row's owner here is one of two different columns rather than one single
     * FK. Unlike Institution, there's no "nasional-level organization account" case to handle —
     * every organisasi-category row is owned by exactly one real Union or Conference (see
     * OrganizationSocialController), never neither.
     */
    private function analyticsOrganizationScope($query)
    {
        $user = auth()->user();

        if ($user->role === null || ($user->role->hasGlobalAccess() ?? false) || $user->role->level() === 'global') {
            return $query;
        }

        if ($user->role->level() === 'nasional') {
            $unionIds = $user->assignedUnionIds();

            return $query->where(fn ($q) => $q
                ->whereIn('union_id', $unionIds)
                ->orWhereHas('conference', fn ($q2) => $q2->whereIn('union_id', $unionIds)));
        }

        if ($user->role->level() === 'divisi') {
            return $query->where(fn ($q) => $q
                ->whereHas('union', fn ($q2) => $q2->where('division_id', $user->division_id))
                ->orWhereHas('conference.union', fn ($q2) => $q2->where('division_id', $user->division_id)));
        }

        if ($user->role->level() === 'uni') {
            return $query->where(fn ($q) => $q
                ->where('union_id', $user->union_id)
                ->orWhereHas('conference', fn ($q2) => $q2->where('union_id', $user->union_id)));
        }

        $conferenceId = $user->role->level() === 'gereja' ? $user->church?->conference_id : $user->conference_id;
        $ownUnionId = $user->role->level() === 'gereja' ? $user->church?->conference?->union_id : $user->conference?->union_id;

        return $query->where(fn ($q) => $q
            ->when($ownUnionId, fn ($q2) => $q2->orWhere('union_id', $ownUnionId))
            ->orWhere('conference_id', $conferenceId));
    }

    /**
     * Which Union rows the Organisasi tab's "Data Per Organisasi" table shows — unlike
     * analyticsOrganizationScope() above (which scopes ChurchSocial rows for the leaderboard/
     * metric-comparison pages), this scopes the Union entities themselves directly, since the
     * analytics tab lists Unions/Conferences as rows (with their own eager-loaded ->socials),
     * not individual accounts. A uni-level viewer sees only their own Union (one row, for
     * context); daerah/gereja-level sees their own Union too (their own Daerah's parent, same
     * "this union's own" context Institution::scopeVisibleTo() gives daerah-level viewers).
     */
    private function analyticsUnionScope($query)
    {
        $user = auth()->user();

        return match (true) {
            $user->role === null || ($user->role->hasGlobalAccess() ?? false) || $user->role->level() === 'global' => $query,
            $user->role->level() === 'nasional' => $query->whereIn('id', $user->assignedUnionIds()),
            $user->role->level() === 'divisi' => $query->where('division_id', $user->division_id),
            $user->role->level() === 'uni' => $query->where('id', $user->union_id),
            $user->role->level() === 'gereja' => $query->where('id', $user->church?->conference?->union_id),
            default => $query->where('id', $user->conference?->union_id), // daerah-level
        };
    }

    /** Same reasoning as analyticsUnionScope(), for Conference rows instead. */
    private function analyticsConferenceScope($query)
    {
        $user = auth()->user();

        return match (true) {
            $user->role === null || ($user->role->hasGlobalAccess() ?? false) || $user->role->level() === 'global' => $query,
            $user->role->level() === 'nasional' => $query->whereIn('union_id', $user->assignedUnionIds()),
            $user->role->level() === 'divisi' => $query->whereHas('union', fn ($q) => $q->where('division_id', $user->division_id)),
            $user->role->level() === 'uni' => $query->where('union_id', $user->union_id),
            $user->role->level() === 'gereja' => $query->where('id', $user->church?->conference_id),
            default => $query->where('id', $user->conference_id), // daerah-level
        };
    }

    /** Parses the Organisasi tab's composite entity-picker value ("union-3"/"conference-5") into a [type, id] pair, or [null, null] if empty/malformed. */
    protected function parseOrganizationKey(?string $key): array
    {
        if (! $key) {
            return [null, null];
        }

        if (str_starts_with($key, 'union-')) {
            return ['union', substr($key, 6)];
        }

        if (str_starts_with($key, 'conference-')) {
            return ['conference', substr($key, 11)];
        }

        return [null, null];
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
            // Instagram (recent_reels_views) and TikTok (recent_video_plays) are recent-sample
            // aggregates (last ~10-12 posts/videos), not a lifetime total like YouTube's
            // views_count — same asymmetry already accepted for the "posts" metric's Facebook
            // inclusion below. Facebook has no view-count field scraped at all, so it stays out.
            'views' => [
                $activeSocials->whereIn('platform', [SocialPlatform::YouTube, SocialPlatform::Instagram, SocialPlatform::TikTok]),
                fn ($social) => match ($social->platform) {
                    SocialPlatform::Instagram => 'recent_reels_views',
                    SocialPlatform::TikTok => 'recent_video_plays',
                    default => 'views_count',
                },
            ],
            'likes' => [
                $activeSocials->where('platform', SocialPlatform::TikTok),
                fn () => 'likes_count',
            ],
            'posts' => [
                $activeSocials,
                fn ($social) => match ($social->platform) {
                    SocialPlatform::YouTube => 'videos_count',
                    SocialPlatform::Facebook => 'recent_posts_count',
                    default => 'posts_count',
                },
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
    protected function metricComparisonRows(string $metric, ?string $platform, string $sortBy = 'delta', ?string $category = null): Collection
    {
        [$socials, $fieldResolver] = $this->metricDefinition($metric, $this->activeSocials(category: $category));

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
     * Same as metricComparisonRows()/metricComparisonRowsPersonal(), but aggregated per
     * institution instead — for the institution-accounts side of the analytics/comparison
     * pages.
     */
    protected function metricComparisonRowsInstitution(string $metric, ?string $platform, string $sortBy = 'delta'): Collection
    {
        [$socials, $fieldResolver] = $this->metricDefinition($metric, $this->activeSocialsInstitution());

        if ($platform && $platform !== 'semua') {
            $socials = $socials->where('platform', SocialPlatform::from($platform));
        }

        $rows = $socials
            ->groupBy('institution_id')
            ->map(function (Collection $institutionSocials) use ($fieldResolver) {
                $institution = $institutionSocials->first()->institution;
                $currentTotal = 0;
                $deltaTotal = 0;
                $hasDelta = false;

                foreach ($institutionSocials as $social) {
                    $field = $fieldResolver($social);
                    $stats = $social->stats()->limit(2)->get();
                    $currentTotal += $stats->get(0)?->{$field} ?? 0;

                    if ($stats->count() >= 2) {
                        $deltaTotal += ($stats[0]->{$field} ?? 0) - ($stats[1]->{$field} ?? 0);
                        $hasDelta = true;
                    }
                }

                return [
                    'institution' => $institution,
                    'label' => $institution->name,
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
     * Same as metricComparisonRows()/metricComparisonRowsPersonal()/
     * metricComparisonRowsInstitution(), but aggregated per Union-or-Conference instead — a
     * row's owner is one of two different columns here (never both), so the group-by key is
     * computed rather than a single column name.
     */
    protected function metricComparisonRowsOrganization(string $metric, ?string $platform, string $sortBy = 'delta'): Collection
    {
        [$socials, $fieldResolver] = $this->metricDefinition($metric, $this->activeSocialsOrganization());

        if ($platform && $platform !== 'semua') {
            $socials = $socials->where('platform', SocialPlatform::from($platform));
        }

        $rows = $socials
            ->groupBy(fn (ChurchSocial $social) => $social->union_id ? "union-{$social->union_id}" : "conference-{$social->conference_id}")
            ->map(function (Collection $orgSocials) use ($fieldResolver) {
                $sample = $orgSocials->first();
                $organization = $sample->union ?? $sample->conference;
                $currentTotal = 0;
                $deltaTotal = 0;
                $hasDelta = false;

                foreach ($orgSocials as $social) {
                    $field = $fieldResolver($social);
                    $stats = $social->stats()->limit(2)->get();
                    $currentTotal += $stats->get(0)?->{$field} ?? 0;

                    if ($stats->count() >= 2) {
                        $deltaTotal += ($stats[0]->{$field} ?? 0) - ($stats[1]->{$field} ?? 0);
                        $hasDelta = true;
                    }
                }

                return [
                    'organization' => $organization,
                    'label' => $organization->name,
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
     * Restricts a ChurchSocial query to whatever the current viewer's analytics scope may see,
     * across all five owner types at once — combines analyticsChurchScope()/analyticsPersonScope()/
     * analyticsInstitutionScope()/analyticsOrganizationScope(), which the activeSocials*()
     * methods above apply one owner type at a time, into a single OR for queries (needs-
     * attention, the auto-fetch accounts list) that span every owner type together.
     */
    private function analyticsAnyOwnerScope($query)
    {
        return $query->where(fn ($q) => $q
            ->whereHas('church', fn ($q2) => $this->analyticsChurchScope($q2))
            ->orWhereHas('person', fn ($q2) => $this->analyticsPersonScope($q2))
            ->orWhereHas('institution', fn ($q2) => $this->analyticsInstitutionScope($q2))
            ->orWhere(fn ($q2) => $this->analyticsOrganizationScope(
                $q2->where(fn ($q3) => $q3->whereNotNull('union_id')->orWhereNotNull('conference_id'))
            )));
    }

    /**
     * Active, auto-fetchable accounts whose last fetch attempt failed — the same eligibility
     * check "Dapatkan Data Terbaru" uses, so this always reflects accounts that button would
     * refresh but are currently broken. Shared by ChurchDashboardController (the "Akun perlu
     * perhatian" stat card + its detail page) and Admin\AccountController (the same stat card
     * on Kelola Akun), so both always agree on what counts as "needs attention". Covers every
     * owner type (church/person/institution/union/conference) — see ChurchSocial::scopeOwnerActive().
     */
    protected function accountsNeedingAttentionQuery()
    {
        return $this->analyticsAnyOwnerScope(
            ChurchSocial::query()
                ->where('is_active', true)
                ->where('is_auto_fetch', true)
                ->where('last_fetch_status', 'failed')
                ->ownerActive()
        );
    }

    /**
     * Every active, auto-fetchable account across all five owner types, scoped to what the
     * viewer may see — same shape as accountsNeedingAttentionQuery() minus the failed-only
     * filter, so an admin can audit every automatic account's last update (not just the ones
     * currently broken) on the "Akun Otomatis" list.
     */
    protected function autoFetchAccountsQuery()
    {
        return $this->analyticsAnyOwnerScope(
            ChurchSocial::query()
                ->where('is_active', true)
                ->where('is_auto_fetch', true)
                ->ownerActive()
        );
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
    protected function growthScoreRows(bool $scoped = true, ?string $category = null): Collection
    {
        $activeSocials = $this->activeSocials($scoped, $category);
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

                // array_values() strips each entry's metric-name keys ('reach', 'views', ...)
                // before flattening — flatMap()'s underlying collapse() merges same-keyed
                // arrays with array_merge semantics, which would otherwise silently keep only
                // the LAST account's value for each metric name and discard the rest instead of
                // treating every (metric, account) percent as its own independent sample.
                $allPercents = $entries->flatMap(fn ($entry) => array_values($entry['metrics']))->values();

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

                // array_values() strips each entry's metric-name keys ('reach', 'views', ...)
                // before flattening — flatMap()'s underlying collapse() merges same-keyed
                // arrays with array_merge semantics, which would otherwise silently keep only
                // the LAST account's value for each metric name and discard the rest instead of
                // treating every (metric, account) percent as its own independent sample.
                $allPercents = $entries->flatMap(fn ($entry) => array_values($entry['metrics']))->values();

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
     * Same composite weekly-growth score as growthScoreRows()/growthScoreRowsPersonal(), but
     * per institution instead — for the Institusi tab's Top 5/Bottom 5.
     */
    protected function growthScoreRowsInstitution(bool $scoped = true): Collection
    {
        $activeSocials = $this->activeSocialsInstitution($scoped);
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

                $percentBySocial[$social->id]['institution'] = $social->institution;
                $percentBySocial[$social->id]['metrics'][$metric] = round((($current - $previous) / $previous) * 100, 2);
            }
        }

        return collect($percentBySocial)
            ->groupBy(fn ($entry) => $entry['institution']->id)
            ->map(function (Collection $entries) {
                $institution = $entries->first()['institution'];

                // array_values() strips each entry's metric-name keys ('reach', 'views', ...)
                // before flattening — flatMap()'s underlying collapse() merges same-keyed
                // arrays with array_merge semantics, which would otherwise silently keep only
                // the LAST account's value for each metric name and discard the rest instead of
                // treating every (metric, account) percent as its own independent sample.
                $allPercents = $entries->flatMap(fn ($entry) => array_values($entry['metrics']))->values();

                $byMetric = collect(['reach', 'views', 'likes', 'posts'])->mapWithKeys(function ($metric) use ($entries) {
                    $values = $entries->pluck("metrics.{$metric}")->filter(fn ($v) => $v !== null);

                    return [$metric => $values->isEmpty() ? null : round($values->avg(), 2)];
                });

                return [
                    'institution' => $institution,
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
     * Same composite weekly-growth score as growthScoreRows()/growthScoreRowsPersonal()/
     * growthScoreRowsInstitution(), but per Union-or-Conference instead — for Perbandingan
     * Metrik's Organisasi scope. Grouped by a computed "union-ID"/"conference-ID" key rather
     * than the entity's own ->id, since a Union and a Conference could otherwise collide on the
     * same numeric id.
     */
    protected function growthScoreRowsOrganization(bool $scoped = true): Collection
    {
        $activeSocials = $this->activeSocialsOrganization($scoped);
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

                $percentBySocial[$social->id]['organization'] = $social->union ?? $social->conference;
                $percentBySocial[$social->id]['groupKey'] = $social->union_id ? "union-{$social->union_id}" : "conference-{$social->conference_id}";
                $percentBySocial[$social->id]['metrics'][$metric] = round((($current - $previous) / $previous) * 100, 2);
            }
        }

        return collect($percentBySocial)
            ->groupBy(fn ($entry) => $entry['groupKey'])
            ->map(function (Collection $entries) {
                $organization = $entries->first()['organization'];

                // array_values() strips each entry's metric-name keys ('reach', 'views', ...)
                // before flattening — flatMap()'s underlying collapse() merges same-keyed
                // arrays with array_merge semantics, which would otherwise silently keep only
                // the LAST account's value for each metric name and discard the rest instead of
                // treating every (metric, account) percent as its own independent sample.
                $allPercents = $entries->flatMap(fn ($entry) => array_values($entry['metrics']))->values();

                $byMetric = collect(['reach', 'views', 'likes', 'posts'])->mapWithKeys(function ($metric) use ($entries) {
                    $values = $entries->pluck("metrics.{$metric}")->filter(fn ($v) => $v !== null);

                    return [$metric => $values->isEmpty() ? null : round($values->avg(), 2)];
                });

                return [
                    'organization' => $organization,
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
     *
     * Each account is compared against its OWN record sequence (step 0 = its own latest vs.
     * second-latest stat row, step 1 = the pair before that, etc.) — the same "own latest two"
     * comparison growthScoreRows() uses for the current score, just generalized across more
     * steps back in time. This used to instead align every account to a shared set of calendar
     * dates (the union of every account's recorded_at dates), which silently dropped or
     * misaligned any account whose fetch cadence didn't land on the same day as the others
     * (YouTube's own API vs. Apify-scraped platforms, retries, backfills, etc.) — that's why
     * this history's most recent point could disagree with growthScoreRows()'s dashboard
     * number for the exact same entity. Comparing each account against itself removes that
     * dependency on shared dates entirely, so the two are always consistent.
     *
     * @return array{history: array<int, float>, metrics: array<string, float|null>, breakdown: array, sampleCount: int, sampleSum: float}
     *   'history' is the sparkline series (oldest first). 'metrics' is the same reach/views/
     *   likes/posts breakdown growthScoreRows()' own 'metrics' key exposes, but for just this
     *   entity's latest transition — same per-metric averaging, so the number shown here next
     *   to "Skor Pertumbuhan Mingguan" always agrees with what the dashboard leaderboard shows
     *   for this same entity. 'breakdown'/'sampleCount'/'sampleSum' are the underlying per-
     *   account samples behind the latest transition — for the "how was this calculated"
     *   drill-down (see components/growth-score-summary.blade.php's modal), not used by the
     *   card itself.
     */
    protected function growthScoreHistory(Collection $socials, int $limit = 8): array
    {
        $empty = ['history' => [], 'metrics' => [], 'breakdown' => [], 'sampleCount' => 0, 'sampleSum' => 0.0];

        if ($socials->isEmpty()) {
            return $empty;
        }

        $metricNames = ['reach', 'views', 'likes', 'posts'];
        $platformLabels = ['youtube' => 'YouTube', 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'facebook' => 'Facebook', 'x' => 'X'];

        $statsBySocial = $socials->mapWithKeys(fn ($social) => [
            $social->id => $social->stats()->limit($limit + 1)->get(),
        ]);

        $steps = min($statsBySocial->max(fn ($stats) => max($stats->count() - 1, 0)), $limit);

        if ($steps < 1) {
            return $empty;
        }

        $scoresByStep = [];
        $percentsByStepAndMetric = [];
        $breakdown = [];
        $sampleCount = 0;
        $sampleSum = 0.0;

        for ($step = 0; $step < $steps; $step++) {
            $percents = [];

            foreach ($metricNames as $metric) {
                [$applicableSocials, $fieldResolver] = $this->metricDefinition($metric, $socials);

                foreach ($applicableSocials as $social) {
                    $stats = $statsBySocial[$social->id];

                    if ($stats->count() < $step + 2) {
                        continue;
                    }

                    $field = $fieldResolver($social);
                    $current = $stats[$step]->{$field} ?? 0;
                    $previous = $stats[$step + 1]->{$field} ?? 0;

                    if ($previous <= 0) {
                        continue;
                    }

                    // Rounded immediately (matching growthScoreRows()'s own per-sample
                    // rounding) and that rounded value is what's summed/averaged from here on
                    // — so the modal's displayed samples always sum to exactly the displayed
                    // final score, with no "why doesn't the math add up" floating-point gap.
                    $pct = round((($current - $previous) / $previous) * 100, 2);
                    $percents[] = $pct;
                    $percentsByStepAndMetric[$step][$metric][] = $pct;

                    // Only the latest transition (step 0) is what the card/modal actually
                    // shows — the deeper history steps only ever feed the sparkline.
                    if ($step === 0) {
                        $breakdown[$metric][] = [
                            'label' => ($platformLabels[$social->platform->value] ?? $social->platform->value).' · '.$social->display_handle,
                            'previous' => $previous,
                            'current' => $current,
                            'percent' => $pct,
                        ];
                        $sampleCount++;
                        $sampleSum += $pct;
                    }
                }
            }

            if (! empty($percents)) {
                $scoresByStep[$step] = round(array_sum($percents) / count($percents), 2);
            }
        }

        // Built newest-first above (step 0 = latest transition) to mirror growthScoreRows();
        // reversed here since the sparkline reads oldest-first. Steps with no data are simply
        // absent rather than null, same compacting behavior as before.
        $history = collect(range($steps - 1, 0))
            ->filter(fn ($step) => array_key_exists($step, $scoresByStep))
            ->map(fn ($step) => $scoresByStep[$step])
            ->values()
            ->all();

        $currentMetrics = collect($metricNames)->mapWithKeys(function ($metric) use ($percentsByStepAndMetric) {
            $values = $percentsByStepAndMetric[0][$metric] ?? [];

            return [$metric => empty($values) ? null : round(array_sum($values) / count($values), 2)];
        })->all();

        return [
            'history' => $history,
            'metrics' => $currentMetrics,
            'breakdown' => $breakdown,
            'sampleCount' => $sampleCount,
            'sampleSum' => round($sampleSum, 2),
        ];
    }
}

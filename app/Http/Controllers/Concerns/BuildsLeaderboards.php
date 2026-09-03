<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\SocialPlatform;
use App\Models\AppSetting;
use App\Models\ChurchSocial;
use App\Models\Conference;
use App\Models\Division;
use App\Models\Goal;
use App\Models\Union;
use Illuminate\Support\Collection;

trait BuildsLeaderboards
{
    /**
     * True for a viewer who may see data spanning MULTIPLE Unions — which, per the user's
     * explicit call, is now every authenticated admin/pimpinan role, Uni/Daerah/Gereja
     * included: the Uni/Daerah region filter defaults to fully unfiltered ("data global yg
     * ada") for everyone, narrowed only by an explicit, voluntary pick — Uni/Daerah/Gereja's
     * own pick is just restricted to their own natural reach (see regionFilterOptions()
     * below), never someone else's, but leaving it blank shows the same nationwide view
     * Global/Nasional/Divisi already default to. All get the same "grouped by Union >
     * Conference, with a Union filter" UI treatment; the only thing that actually differs
     * between them is WHICH Unions/Conferences populate that filter — see
     * regionFilterOptions() below, the one place that distinction still matters. Shared by
     * every page that offers the Uni/Daerah region filter + collapsible grouping (Analitik &
     * Grafik, Perbandingan Metrik, the per-metric leaderboard pages, and the Hastag tab).
     *
     * A plain member (role === null) is NOT one of these — per the user's own, separate,
     * explicit call, a Personal account is scoped no wider than its own Daerah (see
     * analyticsChurchScope() and the other analytics*Scope() methods below) and gets no
     * region filter/grouping at all; that policy is untouched by this one.
     */
    protected function isNasionalView(): bool
    {
        return auth()->user()->role !== null;
    }

    /**
     * Retired now that isNasionalView() above covers Uni too (an admin_uni gets the exact
     * same Uni+Daerah filter and nationwide-by-default view everyone else does — see that
     * method's own doc comment) — always false, so every one of the many `$isUniView ? ... :
     * ...` call sites elsewhere (built back when Uni-level was hard-pinned to its own Union
     * with no filter of its own) now falls straight through to reading the viewer's own
     * explicit filter selection instead, exactly like Global/Nasional/Divisi/Daerah/Gereja
     * already do. Kept as a method (never deleted) rather than mechanically renaming every
     * one of those call sites.
     */
    protected function isUniView(): bool
    {
        return false;
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
     * the viewer may pick from — NOT to what they may ultimately see (see isNasionalView()'s own
     * doc comment: every role can reach a nationwide view by leaving the filter blank; these
     * lists only govern which non-blank shortcuts are offered): Admin/Pimpinan Global see every
     * Union; Admin/Pimpinan Nasional see only their own assigned set (per the user's explicit
     * call — one country can have several Unions and one Union can span several countries, so
     * this is a direct assignment, not derived from a country); Admin/Pimpinan Divisi see their
     * own Division's Unions; Uni gets its own single Union as the only non-blank option (a real,
     * pickable field now — every other tab used to pin this silently instead) plus every
     * Conference under it; Daerah and Gereja (same breadth as Daerah, per the user's explicit
     * call) each get their own single Union AND their own single Conference as the only
     * non-blank options at each tier — picking neither is "Global", picking just the Union is
     * "Global dalam uni" (their whole Union), picking both narrows to exactly their own Daerah.
     */
    protected function regionFilterOptions(?string $selectedUnionId): array
    {
        $user = auth()->user();
        $level = $user->role?->level();
        $isTrulyGlobal = $user->role !== null && ($user->role->hasGlobalAccess() || $level === 'global');
        $isScopedNasional = $level === 'nasional';
        $isDivisiView = $level === 'divisi';
        $isUniLevel = $level === 'uni';
        $isDaerahOrGereja = in_array($level, ['daerah', 'gereja'], true);

        $ownUnionId = match (true) {
            $isUniLevel, $level === 'daerah' => $user->union_id,
            $level === 'gereja' => $user->church?->conference?->union_id,
            default => null,
        };
        $ownConferenceId = match (true) {
            $level === 'daerah' => $user->conference_id,
            $level === 'gereja' => $user->church?->conference_id,
            default => null,
        };

        $unionOptions = match (true) {
            $isTrulyGlobal => Union::where('is_active', true)->orderBy('name')->get(),
            $isScopedNasional => Union::where('is_active', true)->whereIn('id', $user->assignedUnionIds())->orderBy('name')->get(),
            $isDivisiView => Union::where('is_active', true)->where('division_id', $user->division_id)->orderBy('name')->get(),
            $isUniLevel || $isDaerahOrGereja => Union::where('id', $ownUnionId)->get(),
            default => collect(),
        };

        $conferenceOptions = Conference::where('is_active', true)
            ->when($isUniLevel, fn ($query) => $query->where('union_id', $ownUnionId))
            ->when($isDaerahOrGereja, fn ($query) => $query->where('id', $ownConferenceId))
            ->when($isScopedNasional && ! $selectedUnionId, fn ($query) => $query->whereIn('union_id', $user->assignedUnionIds()))
            ->when($isDivisiView && ! $selectedUnionId, fn ($query) => $query->whereHas('union', fn ($q) => $q->where('division_id', $user->division_id)))
            ->when(($isTrulyGlobal || $isScopedNasional || $isDivisiView) && $selectedUnionId, fn ($query) => $query->where('union_id', $selectedUnionId))
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
     * Narrows a HashtagPost query to posts whose matched account (its churchSocial — null for an
     * unmatched author, always excluded once a region filter is active) belongs to the selected
     * Uni/Daerah. A ChurchSocial's owner is always exactly one of church/person/institution/
     * union/conference/division (see that model's own mutually-exclusive owner columns), so each
     * gets its own whereHas branch here — same per-owner-type region logic as directory()'s own
     * $applyChurchRegionFilter/$applyPersonOrInstitutionRegionFilter/$applyUnionRegionFilter/
     * $applyConferenceRegionFilter, just reapplied one hop further out through churchSocial
     * instead of directly on the owner's own table. A Divisi-owned account's posts are (like
     * directory()'s own Division rows) never narrowed by this filter at all — a Division has no
     * union_id/conference_id of its own to match against. Shared by ChurchDashboardController's
     * hashtagComparisonData() and ExportController's hashtagDataset(), so the export always
     * narrows exactly the same way the live page it's exporting does.
     */
    protected function applyHashtagRegionFilter($query, ?string $selectedUnionId, ?string $selectedConferenceId)
    {
        if (! $selectedUnionId && ! $selectedConferenceId) {
            return $query;
        }

        return $query->whereHas('churchSocial', function ($social) use ($selectedUnionId, $selectedConferenceId) {
            $social->where(function ($owner) use ($selectedUnionId, $selectedConferenceId) {
                $owner->whereHas('church', fn ($q) => $selectedConferenceId
                    ? $q->where('conference_id', $selectedConferenceId)
                    : $q->whereHas('conference', fn ($q2) => $q2->where('union_id', $selectedUnionId)))
                    ->orWhereHas('person', fn ($q) => $selectedConferenceId
                        ? $q->where('conference_id', $selectedConferenceId)
                        : $q->where(fn ($q2) => $q2->where('union_id', $selectedUnionId)->orWhereHas('conference', fn ($q3) => $q3->where('union_id', $selectedUnionId))))
                    ->orWhereHas('institution', fn ($q) => $selectedConferenceId
                        ? $q->where('conference_id', $selectedConferenceId)
                        : $q->where(fn ($q2) => $q2->where('union_id', $selectedUnionId)->orWhereHas('conference', fn ($q3) => $q3->where('union_id', $selectedUnionId))))
                    ->orWhereHas('union', fn ($q) => $selectedConferenceId ? $q->whereRaw('1 = 0') : $q->whereKey($selectedUnionId))
                    ->orWhereHas('conference', fn ($q) => $selectedConferenceId ? $q->whereKey($selectedConferenceId) : $q->where('union_id', $selectedUnionId));
            });
        });
    }

    /**
     * The Uni/Daerah applyHashtagRegionFilter() should fall back to when the viewer picked
     * nothing themselves — which only ever matters for a plain member (role === null): every
     * admin/pimpinan role now defaults to fully unfiltered/global when nothing's selected (see
     * isNasionalView()'s own doc comment), same as applyHashtagRegionFilter() already does on
     * its own with no defaulting needed, so there's nothing to fall back to for them. A member
     * is scoped no wider than their own reported Daerah/Uni, or zero with neither set — the
     * one policy this method still exists to apply, unchanged from the rest of Analitik &
     * Grafik's own member-scoping (analyticsChurchScope() and friends).
     */
    protected function defaultHashtagRegionScope(): array
    {
        $user = auth()->user();

        if ($user->role === null) {
            return [
                $this->personalUnionId() ? (string) $this->personalUnionId() : null,
                $this->personalConferenceId() ? (string) $this->personalConferenceId() : null,
            ];
        }

        return [null, null];
    }

    /**
     * The Union a region-groupable entity belongs under — itself, if the entity IS a Union
     * (the organisasi scope's Uni-level rows); its own union relation, if it's a Conference
     * (organisasi's Daerah-level rows); null for a Division (it sits ABOVE Uni — see
     * regionEntityDivision() below, and groupByRegion()'s own 'rows' bucket at the Divisi tier);
     * otherwise the usual Church/Person/Institution chain.
     */
    private function regionEntityUnion($entity): ?Union
    {
        return match (true) {
            $entity instanceof Union => $entity,
            $entity instanceof Conference => $entity->union,
            $entity instanceof Division => null,
            default => $entity->conference?->union ?? $entity->union ?? null,
        };
    }

    /**
     * The Conference a region-groupable entity belongs under — itself, if the entity IS a
     * Conference; null for a Union or a Division (neither has a single Daerah of its own — a
     * Union sits at the Uni-level tier, same bucket as an entity with no conference_id at all;
     * a Division sits a tier above that again); otherwise the usual chain.
     */
    private function regionEntityConference($entity): ?Conference
    {
        return match (true) {
            $entity instanceof Conference => $entity,
            $entity instanceof Union, $entity instanceof Division => null,
            default => $entity->conference ?? null,
        };
    }

    /**
     * The Divisi a region-groupable entity belongs under — itself, if the entity IS a Division
     * (the organisasi scope's Divisi-level rows); otherwise whichever Divisi its resolved Union
     * belongs to (or null — a Union not yet placed under any Divisi).
     */
    private function regionEntityDivision($entity): ?Division
    {
        if ($entity instanceof Division) {
            return $entity;
        }

        return $this->regionEntityUnion($entity)?->division;
    }

    /**
     * Groups any rows collection into the same Divisi > Uni > Daerah > entity nesting used
     * across the region-filterable pages — $entityAccessor plucks the Church/Person/Institution/
     * Union/Conference/Division out of one row, since each caller's row shape differs (analytics'
     * ['church'=>...], metricComparison's ['entity'=>...], leaderboard's ['social'=>...] where
     * the entity is $social->church, organisasi's ['organization'=>...] which is a Union,
     * Conference, OR Division — see regionEntity*() above). Every tier (Divisi/Uni/Daerah) has
     * its own 'rows' bucket for entities owned directly at that level (e.g. a Divisi-owned social
     * account sits in the Divisi group's own 'rows', not nested under any Uni) — 'rows' is always
     * present, just possibly empty, at all three tiers.
     */
    protected function groupByRegion(Collection $rows, callable $entityAccessor): Collection
    {
        return $rows
            ->groupBy(function ($row) use ($entityAccessor) {
                $division = $this->regionEntityDivision($entityAccessor($row));

                return $division?->id ?? 'no-division';
            })
            ->map(function ($divisionRows) use ($entityAccessor) {
                $sample = $entityAccessor($divisionRows->first());
                $division = $this->regionEntityDivision($sample);

                $ownRows = $divisionRows->filter(fn ($row) => $entityAccessor($row) instanceof Division);
                $nestedRows = $divisionRows->reject(fn ($row) => $entityAccessor($row) instanceof Division);

                return [
                    'label' => $division?->name ?? __('analytics.group_no_division'),
                    'rows' => $ownRows,
                    'unions' => $nestedRows
                        ->groupBy(fn ($row) => $this->regionEntityUnion($entityAccessor($row))?->id ?? 'nasional')
                        ->map(function ($unionRows) use ($entityAccessor) {
                            $sample = $entityAccessor($unionRows->first());
                            $union = $this->regionEntityUnion($sample);

                            // A row whose entity IS the Union itself sits directly under the
                            // Union's own header — Union sits above Daerah in the hierarchy, so
                            // it can never itself "not have a Daerah yet" the way a church/
                            // institution/conference-owned row can.
                            $ownRows = $unionRows->filter(fn ($row) => $entityAccessor($row) instanceof Union);
                            $nestedRows = $unionRows->reject(fn ($row) => $entityAccessor($row) instanceof Union);

                            return [
                                'label' => $union?->name ?? __('analytics.group_national'),
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
                        ->sortBy('label'),
                ];
            })
            ->sortBy('label');
    }

    protected function leaderboardTitles(): array
    {
        return [
            'reach' => ['title' => __('common.metric_reach'), 'subtitle' => __('dashboard.reach_subtitle')],
            'views' => ['title' => __('common.metric_views'), 'subtitle' => __('dashboard.views_subtitle')],
            'likes' => ['title' => __('common.metric_likes'), 'subtitle' => __('dashboard.likes_subtitle')],
            'posts' => ['title' => __('common.metric_posts'), 'subtitle' => __('dashboard.posts_subtitle')],
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
        // X and Threads have no views or likes metric tracked (only followers/posts_count —
        // see XStatsFetcher/ThreadsStatsFetcher), so both are left out of those two, same as
        // Facebook is left out of 'likes'. Each list is further intersected with whichever
        // platforms are currently enabled (see AppSetting::enabledPlatformValues(), Settings'
        // platform toggle card) — a disabled platform must not show as an empty comparison tab.
        $enabled = AppSetting::current()->enabledPlatformValues();
        $onlyEnabled = fn (array $platforms) => array_values(array_intersect($platforms, ['semua', ...$enabled]));

        return [
            'reach' => $onlyEnabled(['semua', 'youtube', 'instagram', 'tiktok', 'facebook', 'x', 'threads']),
            'views' => $onlyEnabled(['semua', 'youtube', 'instagram', 'tiktok']),
            'likes' => $onlyEnabled(['semua', 'tiktok']),
            'posts' => $onlyEnabled(['semua', 'youtube', 'instagram', 'tiktok', 'facebook', 'x', 'threads']),
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
    protected function activeSocials(bool $scoped = true, ?string $category = null, bool $applyCeiling = false): Collection
    {
        return ChurchSocial::query()
            ->with('church.conference.union')
            ->where('is_active', true)
            ->whereHas('church', fn ($q) => $q->where('is_active', true))
            ->when($scoped, fn ($q) => $q->whereHas('church', fn ($q2) => $this->analyticsChurchScope($q2, $applyCeiling)))
            ->when($category, fn ($q) => $q->where('category', $category))
            ->get();
    }

    protected function activeSocialsPersonal(bool $scoped = true, bool $applyCeiling = false): Collection
    {
        return ChurchSocial::query()
            ->with(['person.conference.union', 'person.union'])
            ->where('is_active', true)
            ->whereHas('person', fn ($q) => $q->where('is_active', true))
            ->when($scoped, fn ($q) => $q->whereHas('person', fn ($q2) => $this->analyticsPersonScope($q2, $applyCeiling)))
            ->get();
    }

    /** Same shape as activeSocials()/activeSocialsPersonal(), for an institution's own organization-level accounts. */
    protected function activeSocialsInstitution(bool $scoped = true, bool $applyCeiling = false): Collection
    {
        return ChurchSocial::query()
            ->with(['institution.conference.union', 'institution.union'])
            ->where('is_active', true)
            ->whereHas('institution', fn ($q) => $q->where('is_active', true))
            ->when($scoped, fn ($q) => $q->whereHas('institution', fn ($q2) => $this->analyticsInstitutionScope($q2, $applyCeiling)))
            ->get();
    }

    /**
     * Divisi-, Union-, and Conference-owned accounts together (the "Organisasi" scope — a
     * ChurchSocial row is owned by exactly one of division_id/union_id/conference_id here, never
     * more than one, per OrganizationSocialController). Unlike the other activeSocials*()
     * variants, there's no single owner relation to whereHas() an is_active check through, since
     * a row's owner is one of three different columns — each branch is checked directly instead.
     */
    protected function activeSocialsOrganization(bool $scoped = true, bool $applyCeiling = false): Collection
    {
        return ChurchSocial::query()
            ->with(['division', 'union.division', 'conference.union.division'])
            ->where('is_active', true)
            ->where(fn ($q) => $q
                ->where(fn ($q2) => $q2->whereNotNull('division_id')->whereHas('division', fn ($q3) => $q3->where('is_active', true)))
                ->orWhere(fn ($q2) => $q2->whereNotNull('union_id')->whereHas('union', fn ($q3) => $q3->where('is_active', true)))
                ->orWhere(fn ($q2) => $q2->whereNotNull('conference_id')->whereHas('conference', fn ($q3) => $q3->where('is_active', true)))
            )
            ->when($scoped, fn ($q) => $this->analyticsOrganizationScope($q, $applyCeiling))
            ->get();
    }

    /**
     * The dashboard's Goal widget rows — one per metric (reach/views/likes/posts), each showing
     * the viewer's own fair-share target against their own scope's actual current total.
     *
     * The national target (Kelola Tujuan) is divided evenly across every active Union, and each
     * Union's share is divided evenly again across its own active Conferences — a uni-level
     * viewer sees their Union's third; a daerah/gereja-level viewer (gereja gets the same
     * Daerah/Konferens breadth as admin_daerah elsewhere on this dashboard) sees their
     * Conference's share of that. Institusi-level viewers get nothing back (empty collection) —
     * institutions sit outside the nasional→uni→daerah chain (see UserRole::level()), so there's
     * no natural fair-share scope for them here.
     *
     * Protected here (not private on one controller) since both ChurchDashboardController's
     * admin "Ringkasan" dashboard AND PersonController's personal dashboard (people/show —
     * "Tujuan Bersama") call this with the SAME viewer-scoping shape, just different callers; a
     * role === null viewer always lands on the isGlobal branch below regardless of which page
     * called it, so this needs no change to work for a plain member's own dashboard too.
     *
     * $churchSocials/$institutionSocials/$personalSocials/$organizationSocials are the SAME
     * already-viewer-scoped collections the caller computed elsewhere (via
     * activeSocials()/activeSocialsInstitution()/activeSocialsPersonal()/
     * activeSocialsOrganization() above), reused here so the "current" total matches the
     * viewer's own scope without re-querying.
     */
    protected function goalProgressRows(Collection $churchSocials, Collection $institutionSocials, Collection $personalSocials, Collection $organizationSocials): Collection
    {
        $user = auth()->user();
        $level = $user->role?->level();
        $isGlobal = $user->role === null || ($user->role?->hasGlobalAccess() ?? false) || $level === 'global';
        // Admin/Pimpinan Nasional: sums the fair share of every Union they're assigned to,
        // rather than the full national total (Global) or a single Union's third (Uni-level) —
        // see User::assignedUnions().
        $isScopedNasional = ! $isGlobal && $level === 'nasional';
        // Admin/Pimpinan Divisi: same fair-share shape as Nasional above, just counting only
        // the active Unions that belong to their own Division instead of an arbitrary set.
        $isDivisi = ! $isGlobal && ! $isScopedNasional && $level === 'divisi';
        $isUni = ! $isGlobal && ! $isScopedNasional && ! $isDivisi && $level === 'uni';
        $isDaerahOrGereja = ! $isGlobal && ! $isScopedNasional && ! $isDivisi && ! $isUni && in_array($level, ['daerah', 'gereja'], true);

        if (! $isGlobal && ! $isScopedNasional && ! $isDivisi && ! $isUni && ! $isDaerahOrGereja) {
            return collect();
        }

        $unionCount = Union::where('is_active', true)->count();
        $assignedUnionCount = $isScopedNasional ? count($user->assignedUnionIds()) : 0;
        $divisionUnionCount = $isDivisi ? Union::where('is_active', true)->where('division_id', $user->division_id)->count() : 0;

        // admin_gereja has no $user->conference of its own (only $user->church) — same
        // conference_id derivation as analyticsChurchScope()'s gereja-level branch.
        $conference = $user->conference ?? $user->church?->conference;

        $scopeLabel = match (true) {
            $isGlobal => __('goals.scope_global'),
            $isScopedNasional => __('goals.scope_nasional_scoped', ['names' => $user->assignedUnions()->pluck('name')->implode(', ') ?: '—']),
            $isDivisi => __('goals.scope_divisi', ['name' => $user->division?->name ?? '—']),
            $isUni => __('goals.scope_uni', ['name' => $user->union?->name ?? '—']),
            default => __('goals.scope_daerah', ['name' => $conference?->name ?? '—']),
        };

        // Personal and Organisasi (union/conference-owned) accounts count toward "current" too,
        // so this total matches the Distribution Channels widget's total reach — union/daerah
        // target splitting above is unaffected, it only divides $goal->target_value, never
        // re-derives it from this total.
        $combinedSocials = $churchSocials->merge($institutionSocials)->merge($personalSocials)->merge($organizationSocials);

        return collect(Goal::METRICS)->map(function ($metric) use ($combinedSocials, $isGlobal, $isScopedNasional, $isDivisi, $isUni, $unionCount, $assignedUnionCount, $divisionUnionCount, $conference, $scopeLabel) {
            $goal = Goal::forMetric($metric);

            $target = match (true) {
                $isGlobal => $goal->target_value,
                $isScopedNasional => $unionCount > 0 ? (int) round($goal->target_value / $unionCount * $assignedUnionCount) : 0,
                $isDivisi => $unionCount > 0 ? (int) round($goal->target_value / $unionCount * $divisionUnionCount) : 0,
                $isUni => $unionCount > 0 ? (int) round($goal->target_value / $unionCount) : 0,
                default => (function () use ($goal, $unionCount, $conference) {
                    if ($unionCount === 0) {
                        return 0;
                    }

                    $unionShare = $goal->target_value / $unionCount;
                    $conferenceCount = $conference?->union?->conferences()->where('is_active', true)->count() ?? 0;

                    return $conferenceCount > 0 ? (int) round($unionShare / $conferenceCount) : 0;
                })(),
            };

            [$filteredSocials, $fieldResolver] = $this->metricDefinition($metric, $combinedSocials);
            $current = $filteredSocials->sum(fn ($social) => $social->latestStat?->{$fieldResolver($social)} ?? 0);

            return [
                'metric' => $metric,
                'label' => __('goals.metric_'.$metric),
                'year' => $goal->target_year,
                'target' => $target,
                'current' => $current,
                'percent' => $target > 0 ? round(min($current / $target, 1) * 100, 1) : 0,
                'scopeLabel' => $scopeLabel,
            ];
        })->values();
    }

    /**
     * A Personal/member account's (role === null) own Daerah — the Conference their linked
     * Person record is assigned to via Profil Saya's own Wilayah section (see PersonController::
     * resolveSelfReportedScope()). Null if they've only picked a Union with no specific Daerah,
     * or haven't completed Wilayah at all.
     */
    private function personalConferenceId(): ?int
    {
        return auth()->user()->person?->conference_id;
    }

    /** Same as personalConferenceId(), for the Union case — null once a specific Daerah is set (see resolveSelfReportedScope()'s "never both at once"), so this only ever fires for a Union-only or fully-independent member. */
    private function personalUnionId(): ?int
    {
        return auth()->user()->person?->union_id;
    }

    /**
     * A plain member (role === null) is scoped no wider than their own Daerah — or their own
     * Union if they've only set that much — per the user's explicit call restricting Analitik &
     * Grafik for these accounts; one who hasn't completed Wilayah at all sees nothing until
     * they do. Any real admin/pimpinan role sees everything by default now (per
     * isNasionalView()'s own doc comment: the region filter narrows further only when they
     * explicitly pick something — see the ->filter($matchesRegionFilter) call every filterable
     * tab already applies afterward) — UNLESS $applyCeiling is true, which restores this
     * method's original per-role ceiling (their own Church::scopeVisibleTo() reach; a
     * gereja-level viewer's whole Daerah/Konferens instead of just their single church). Only
     * ChurchDashboardController::index() (the Ringkasan dashboard's map/Goal-progress-widget/
     * its own Top 5/Bottom 5) and the account-audit lists (accountsNeedingAttentionQuery() and
     * friends) pass true — none of those has a region filter to leave blank for "Global" the
     * way every OTHER Analitik & Grafik tab now does, so removing their ceiling entirely would
     * silently make a Daerah/Uni admin's own dashboard numbers nationwide with no way to narrow
     * back — never asked for, and it would break the Goal-progress widget's fair-share-vs-
     * actual comparison outright (that target is deliberately divided down to exactly this same
     * ceiling). Church::scopeVisibleTo() itself stays untouched either way: it also drives edit
     * permissions via ChurchPolicy, a completely separate, unaffected policy from this
     * read-only analytics scope.
     */
    private function analyticsChurchScope($query, bool $applyCeiling = false)
    {
        $user = auth()->user();

        if ($user->role === null) {
            if ($conferenceId = $this->personalConferenceId()) {
                return $query->where('conference_id', $conferenceId);
            }

            $unionId = $this->personalUnionId();

            return $unionId
                ? $query->whereHas('conference', fn ($q) => $q->where('union_id', $unionId))
                : $query->whereRaw('1 = 0');
        }

        if (! $applyCeiling) {
            return $query;
        }

        return match (true) {
            $user->role->level() === 'gereja' => $query->where('conference_id', $user->church?->conference_id),
            default => $query->visibleTo($user),
        };
    }

    /** Same reasoning as analyticsChurchScope() (including $applyCeiling), for Person instead of Church — Person also has its own union_id column (a church has none), so the Union-only fallback matches directly on that too, not just via conference. */
    private function analyticsPersonScope($query, bool $applyCeiling = false)
    {
        $user = auth()->user();

        if ($user->role === null) {
            if ($conferenceId = $this->personalConferenceId()) {
                return $query->where('conference_id', $conferenceId);
            }

            $unionId = $this->personalUnionId();

            return $unionId
                ? $query->where(fn ($q) => $q->where('union_id', $unionId)->orWhereHas('conference', fn ($q2) => $q2->where('union_id', $unionId)))
                : $query->whereRaw('1 = 0');
        }

        if (! $applyCeiling) {
            return $query;
        }

        return match (true) {
            $user->role->level() === 'gereja' => $query->where('conference_id', $user->church?->conference_id),
            default => $query->visibleTo($user),
        };
    }

    /** Same reasoning as analyticsChurchScope()/analyticsPersonScope() (including $applyCeiling), for Institution instead of Church/Person. */
    private function analyticsInstitutionScope($query, bool $applyCeiling = false)
    {
        $user = auth()->user();

        if ($user->role === null) {
            $conferenceId = $this->personalConferenceId();
            $unionId = $this->personalUnionId();

            return $query->where(function ($q) use ($conferenceId, $unionId) {
                $q->where(fn ($q2) => $q2->whereNull('union_id')->whereNull('conference_id')) // nasional
                    ->when($unionId, fn ($q2) => $q2->orWhere(
                        fn ($q3) => $q3->where('union_id', $unionId)->whereNull('conference_id')
                    )) // this union's own
                    ->when($conferenceId, fn ($q2) => $q2->orWhere('conference_id', $conferenceId)); // this daerah's own
            });
        }

        if (! $applyCeiling) {
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
     * Same reasoning as analyticsChurchScope()/analyticsPersonScope()/analyticsInstitutionScope()
     * (including $applyCeiling), for Divisi/Union/Conference-owned accounts. A plain member
     * (role === null) is scoped no wider than their own Daerah's own accounts if they've set
     * one, or their own Union's-and-below accounts if they've only set that much, or nothing at
     * all if neither.
     */
    private function analyticsOrganizationScope($query, bool $applyCeiling = false)
    {
        $user = auth()->user();

        if ($user->role === null) {
            if ($conferenceId = $this->personalConferenceId()) {
                return $query->where('conference_id', $conferenceId);
            }

            $unionId = $this->personalUnionId();

            return $unionId
                ? $query->where(fn ($q) => $q->where('union_id', $unionId)->orWhereHas('conference', fn ($q2) => $q2->where('union_id', $unionId)))
                : $query->whereRaw('1 = 0');
        }

        if (! $applyCeiling) {
            return $query;
        }

        if ($user->role->hasGlobalAccess() || $user->role->level() === 'global') {
            return $query;
        }

        if ($user->role->level() === 'nasional') {
            $unionIds = $user->assignedUnionIds();

            return $query->where(fn ($q) => $q
                ->whereHas('division', fn ($q2) => $q2->whereHas('unions', fn ($q3) => $q3->whereIn('id', $unionIds)))
                ->orWhereIn('union_id', $unionIds)
                ->orWhereHas('conference', fn ($q2) => $q2->whereIn('union_id', $unionIds)));
        }

        if ($user->role->level() === 'divisi') {
            return $query->where(fn ($q) => $q
                ->where('division_id', $user->division_id)
                ->orWhereHas('union', fn ($q2) => $q2->where('division_id', $user->division_id))
                ->orWhereHas('conference.union', fn ($q2) => $q2->where('division_id', $user->division_id)));
        }

        if ($user->role->level() === 'uni') {
            return $query->where(fn ($q) => $q
                ->where('union_id', $user->union_id)
                ->orWhereHas('conference', fn ($q2) => $q2->where('union_id', $user->union_id)));
        }

        $conferenceId = $user->role->level() === 'gereja' ? $user->church?->conference_id : $user->conference_id;

        return $query->where('conference_id', $conferenceId);
    }

    /**
     * Which Division rows the Organisasi tab's "Data Per Organisasi" table shows. A plain
     * member (role === null) is at most Daerah/Uni-level breadth (see analyticsChurchScope()),
     * always narrower than a Division, so never sees a Division row.
     */
    private function analyticsDivisionScope($query, bool $applyCeiling = false)
    {
        $user = auth()->user();

        if ($user->role === null) {
            return $query->whereRaw('1 = 0');
        }

        if (! $applyCeiling) {
            return $query;
        }

        return match (true) {
            $user->role->hasGlobalAccess() || $user->role->level() === 'global' => $query,
            $user->role->level() === 'nasional' => $query->whereHas('unions', fn ($q) => $q->whereIn('id', $user->assignedUnionIds())),
            $user->role->level() === 'divisi' => $query->where('id', $user->division_id),
            $user->role->level() === 'gereja' => $query->whereHas('unions', fn ($q) => $q->where('id', $user->church?->conference?->union_id)),
            default => $query->whereRaw('1 = 0'), // uni/daerah-level: Divisi is above their own level
        };
    }

    /**
     * Which Union rows the Organisasi tab's "Data Per Organisasi" table shows. A plain member
     * (role === null) sees their own Union row only if they've set a Union with no specific
     * Daerah (a Daerah-scoped member's own Union sits one tier above their own breadth, so it's
     * excluded).
     */
    private function analyticsUnionScope($query, bool $applyCeiling = false)
    {
        $user = auth()->user();

        if ($user->role === null) {
            $unionId = $this->personalConferenceId() ? null : $this->personalUnionId();

            return $unionId ? $query->where('id', $unionId) : $query->whereRaw('1 = 0');
        }

        if (! $applyCeiling) {
            return $query;
        }

        return match (true) {
            $user->role->hasGlobalAccess() || $user->role->level() === 'global' => $query,
            $user->role->level() === 'nasional' => $query->whereIn('id', $user->assignedUnionIds()),
            $user->role->level() === 'divisi' => $query->where('division_id', $user->division_id),
            $user->role->level() === 'uni' => $query->where('id', $user->union_id),
            $user->role->level() === 'gereja' => $query->where('id', $user->church?->conference?->union_id),
            default => $query->whereRaw('1 = 0'), // daerah-level: Union is above their own level
        };
    }

    /**
     * Same reasoning as analyticsUnionScope(), for Conference rows instead. A plain member
     * (role === null) sees their own Daerah row if they've set one, or every Conference under
     * their own Union if they've only set that much, or none at all if neither is set.
     */
    private function analyticsConferenceScope($query, bool $applyCeiling = false)
    {
        $user = auth()->user();

        if ($user->role === null) {
            if ($conferenceId = $this->personalConferenceId()) {
                return $query->where('id', $conferenceId);
            }

            $unionId = $this->personalUnionId();

            return $unionId ? $query->where('union_id', $unionId) : $query->whereRaw('1 = 0');
        }

        if (! $applyCeiling) {
            return $query;
        }

        return match (true) {
            $user->role->hasGlobalAccess() || $user->role->level() === 'global' => $query,
            $user->role->level() === 'nasional' => $query->whereIn('union_id', $user->assignedUnionIds()),
            $user->role->level() === 'divisi' => $query->whereHas('union', fn ($q) => $q->where('division_id', $user->division_id)),
            $user->role->level() === 'uni' => $query->where('union_id', $user->union_id),
            $user->role->level() === 'gereja' => $query->where('id', $user->church?->conference_id),
            default => $query->where('id', $user->conference_id), // daerah-level
        };
    }

    /** Parses the Organisasi tab's composite entity-picker value ("division-1"/"union-3"/"conference-5") into a [type, id] pair, or [null, null] if empty/malformed. */
    protected function parseOrganizationKey(?string $key): array
    {
        if (! $key) {
            return [null, null];
        }

        if (str_starts_with($key, 'division-')) {
            return ['division', substr($key, 9)];
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
                    SocialPlatform::Facebook, SocialPlatform::Threads => 'recent_posts_count',
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
            ->groupBy(fn (ChurchSocial $social) => match (true) {
                $social->division_id !== null => "division-{$social->division_id}",
                $social->union_id !== null => "union-{$social->union_id}",
                default => "conference-{$social->conference_id}",
            })
            ->map(function (Collection $orgSocials) use ($fieldResolver) {
                $sample = $orgSocials->first();
                $organization = $sample->division ?? $sample->union ?? $sample->conference;
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
    private function analyticsAnyOwnerScope($query, bool $applyCeiling = false)
    {
        return $query->where(fn ($q) => $q
            ->whereHas('church', fn ($q2) => $this->analyticsChurchScope($q2, $applyCeiling))
            ->orWhereHas('person', fn ($q2) => $this->analyticsPersonScope($q2, $applyCeiling))
            ->orWhereHas('institution', fn ($q2) => $this->analyticsInstitutionScope($q2, $applyCeiling))
            ->orWhere(fn ($q2) => $this->analyticsOrganizationScope(
                $q2->where(fn ($q3) => $q3->whereNotNull('division_id')->orWhereNotNull('union_id')->orWhereNotNull('conference_id')),
                $applyCeiling
            )));
    }

    /**
     * Active, auto-fetchable accounts whose last fetch attempt failed — the same eligibility
     * check "Dapatkan Data Terbaru" uses, so this always reflects accounts that button would
     * refresh but are currently broken. Shared by ChurchDashboardController (the "Akun perlu
     * perhatian" stat card + its detail page) and Admin\AccountController (the same stat card
     * on Kelola Akun), so both always agree on what counts as "needs attention". Covers every
     * owner type (church/person/institution/union/conference) — see ChurchSocial::scopeOwnerActive().
     *
     * applyCeiling: true, same as ChurchDashboardController::index() — this is an operational
     * audit list with no region filter of its own to leave blank for "Global" the way every
     * Analitik & Grafik comparison tab now does, so a Daerah/Uni admin's own account-maintenance
     * queue stays their own region's accounts, not a nationwide one with no way to narrow back.
     */
    protected function accountsNeedingAttentionQuery()
    {
        return $this->analyticsAnyOwnerScope(
            ChurchSocial::query()
                ->where('is_active', true)
                ->where('is_auto_fetch', true)
                ->where('last_fetch_status', 'failed')
                ->ownerActive(),
            applyCeiling: true
        );
    }

    /**
     * Every active, auto-fetchable account across all five owner types, scoped to what the
     * viewer may see — same shape as accountsNeedingAttentionQuery() minus the failed-only
     * filter, so an admin can audit every automatic account's last update (not just the ones
     * currently broken) on the "Akun Otomatis" list. Same applyCeiling reasoning as
     * accountsNeedingAttentionQuery().
     */
    protected function autoFetchAccountsQuery()
    {
        return $this->analyticsAnyOwnerScope(
            ChurchSocial::query()
                ->where('is_active', true)
                ->where('is_auto_fetch', true)
                ->ownerActive(),
            applyCeiling: true
        );
    }

    /**
     * Every active, manually-entered account across all five owner types, scoped to what the
     * viewer may see — the mirror image of autoFetchAccountsQuery() (is_auto_fetch = false
     * instead of true), so an admin can audit every manual account's last entry the same way,
     * on the "Akun Manual" list. Same applyCeiling reasoning as accountsNeedingAttentionQuery().
     */
    protected function manualAccountsQuery()
    {
        return $this->analyticsAnyOwnerScope(
            ChurchSocial::query()
                ->where('is_active', true)
                ->where('is_auto_fetch', false)
                ->ownerActive(),
            applyCeiling: true
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
    protected function growthScoreRows(bool $scoped = true, ?string $category = null, bool $applyCeiling = false): Collection
    {
        $activeSocials = $this->activeSocials($scoped, $category, $applyCeiling);
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
    protected function growthScoreRowsPersonal(bool $scoped = true, bool $applyCeiling = false): Collection
    {
        $activeSocials = $this->activeSocialsPersonal($scoped, $applyCeiling);
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
    protected function growthScoreRowsInstitution(bool $scoped = true, bool $applyCeiling = false): Collection
    {
        $activeSocials = $this->activeSocialsInstitution($scoped, $applyCeiling);
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
    protected function growthScoreRowsOrganization(bool $scoped = true, bool $applyCeiling = false): Collection
    {
        $activeSocials = $this->activeSocialsOrganization($scoped, $applyCeiling);
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

                $percentBySocial[$social->id]['organization'] = $social->division ?? $social->union ?? $social->conference;
                $percentBySocial[$social->id]['groupKey'] = match (true) {
                    $social->division_id !== null => "division-{$social->division_id}",
                    $social->union_id !== null => "union-{$social->union_id}",
                    default => "conference-{$social->conference_id}",
                };
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
        $platformLabels = ['youtube' => 'YouTube', 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'facebook' => 'Facebook', 'x' => 'X', 'threads' => 'Threads'];

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

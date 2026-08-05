<?php

namespace App\Http\Controllers;

use App\Enums\SocialPlatform;
use App\Http\Controllers\Concerns\BuildsLeaderboards;
use App\Models\Church;
use App\Models\ChurchStat;
use App\Models\Conference;
use App\Models\Goal;
use App\Models\Institution;
use App\Models\Person;
use App\Models\Union;
use App\Support\ComparisonScope;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ChurchDashboardController extends Controller
{
    use BuildsLeaderboards;

    public function index()
    {
        // A gereja-level admin manages exactly one church, so most of this dashboard is scoped
        // to their whole Daerah/Konferens instead — same breadth as an admin_daerah, and
        // consistent with how Analytics already treats them (see
        // BuildsLeaderboards::analyticsChurchScope()/analyticsPersonScope()). Only the map and
        // the platform-score widget stay fully unscoped/national, per the user's explicit
        // call — a single pin or a platform ranking is more useful seen against the whole
        // national picture than one Daerah's worth of accounts.
        $isGerejaLevel = auth()->user()->role?->level() === 'gereja';

        $churches = $this->analyticsChurchScope(Church::query()->where('is_active', true))
            ->with(['socials' => fn ($query) => $query->where('is_active', true)->with('latestStat'), 'conference.union'])
            ->orderBy('name')
            ->get();

        // Map markers don't carry their own Uni/Daerah color — churches only reach a Union
        // through their Conference, while Person/Institution can also attach directly to a
        // Union — so this mirrors BuildsLeaderboards::regionEntityUnion()'s fallback chain
        // rather than reusing it (that one takes ChurchSocial-shaped rows, not these entities).
        $resolveUnion = fn ($entity) => $entity->conference?->union ?? $entity->union ?? null;

        // Church/Person/Institution each have their own direct conference() relation (unlike
        // resolveUnion() above, this needs no fallback chain — an entity is either attached to a
        // Conference or it isn't, there's nothing else to fall back to).
        $resolveConference = fn ($entity) => $entity->conference;

        $allSocials = $churches->flatMap->socials;

        $reachCountField = fn ($social) => $social->platform === SocialPlatform::YouTube ? 'subscribers_count' : 'followers_count';

        $totalReachChurch = $allSocials->sum(fn ($social) => $social->latestStat?->{$reachCountField($social)} ?? 0);

        $mapChurchSource = $isGerejaLevel
            ? Church::query()->where('is_active', true)->with(['socials' => fn ($query) => $query->where('is_active', true)->with('latestStat'), 'conference.union'])->get()
            : $churches;

        // One batched query instead of ChurchPolicy::update()'s own per-church visibleTo() query
        // run once per map pin — its ability check is otherwise identical (a read-only role can
        // never edit, full stop), so replicating that half in-memory keeps this to O(1) queries.
        $editableChurchIds = (auth()->user()->role === null || auth()->user()->role->isReadOnly())
            ? []
            : Church::whereIn('id', $mapChurchSource->pluck('id'))->visibleTo(auth()->user())->pluck('id')->all();

        $mapChurches = $mapChurchSource->filter(fn ($church) => $church->latitude !== null && $church->longitude !== null)
            ->map(fn ($church) => [
                'name' => $church->name,
                'city' => $church->city,
                'url' => route('churches.show', $church),
                // Only offered to whoever can actually fix it — the Uni/Daerah map tab's own
                // church-point markers link here so a wrongly-placed pin can be corrected right
                // from the map instead of hunting the church down in Kelola Akun first.
                'editUrl' => in_array($church->id, $editableChurchIds, true) ? route('churches.edit', $church) : null,
                'lat' => $church->latitude,
                'lng' => $church->longitude,
                'reach' => $church->socials->sum(fn ($social) => $social->latestStat?->{$reachCountField($social)} ?? 0),
                'unionId' => $resolveUnion($church)?->id,
                'unionName' => $resolveUnion($church)?->name,
                'conferenceId' => $resolveConference($church)?->id,
                'conferenceName' => $resolveConference($church)?->name,
            ])
            ->values();

        $unmappedCount = $mapChurchSource->count() - $mapChurches->count();

        $people = $this->analyticsPersonScope(Person::query()->where('is_active', true))
            ->with(['socials' => fn ($query) => $query->where('is_active', true)->with('latestStat'), 'conference.union', 'union'])
            ->get();

        $personalSocials = $people->flatMap->socials;

        $totalReachPersonal = $personalSocials->sum(fn ($social) => $social->latestStat?->{$reachCountField($social)} ?? 0);

        $mapPeopleSource = $isGerejaLevel
            ? Person::query()->where('is_active', true)->with(['socials' => fn ($query) => $query->where('is_active', true)->with('latestStat'), 'conference.union', 'union'])->get()
            : $people;

        $mapPeople = $mapPeopleSource->filter(fn ($person) => $person->latitude !== null && $person->longitude !== null)
            ->map(fn ($person) => [
                'name' => $person->name,
                'city' => $person->city,
                'url' => route('people.show', $person),
                'lat' => $person->latitude,
                'lng' => $person->longitude,
                'reach' => $person->socials->sum(fn ($social) => $social->latestStat?->{$reachCountField($social)} ?? 0),
                'unionId' => $resolveUnion($person)?->id,
                'unionName' => $resolveUnion($person)?->name,
                'conferenceId' => $resolveConference($person)?->id,
                'conferenceName' => $resolveConference($person)?->name,
            ])
            ->values();

        $unmappedPeopleCount = $mapPeopleSource->count() - $mapPeople->count();

        $institutions = $this->analyticsInstitutionScope(Institution::query()->where('is_active', true))
            ->with(['socials' => fn ($query) => $query->where('is_active', true)->with('latestStat'), 'conference.union', 'union'])
            ->get();

        $institutionSocials = $institutions->flatMap->socials;

        $totalReachInstitution = $institutionSocials->sum(fn ($social) => $social->latestStat?->{$reachCountField($social)} ?? 0);

        $mapInstitutionSource = $isGerejaLevel
            ? Institution::query()->where('is_active', true)->with(['socials' => fn ($query) => $query->where('is_active', true)->with('latestStat'), 'conference.union', 'union'])->get()
            : $institutions;

        $mapInstitutions = $mapInstitutionSource->filter(fn ($institution) => $institution->latitude !== null && $institution->longitude !== null)
            ->map(fn ($institution) => [
                'name' => $institution->name,
                'city' => $institution->city,
                'url' => route('institutions.show', $institution),
                'lat' => $institution->latitude,
                'lng' => $institution->longitude,
                'reach' => $institution->socials->sum(fn ($social) => $social->latestStat?->{$reachCountField($social)} ?? 0),
                'unionId' => $resolveUnion($institution)?->id,
                'unionName' => $resolveUnion($institution)?->name,
                'conferenceId' => $resolveConference($institution)?->id,
                'conferenceName' => $resolveConference($institution)?->name,
            ])
            ->values();

        $unmappedInstitutionsCount = $mapInstitutionSource->count() - $mapInstitutions->count();

        // The map's "Uni/Daerah" tab recolors the same church/personal/institution pins by
        // region instead of adding new markers (Union/Conference have no lat/lng of their own)
        // — pins whose Uni/Daerah can't be resolved are left out of that tab entirely (rather
        // than lumped into a catch-all bucket) to avoid visually overlapping real territory they
        // don't belong to, so both the legend and this tab's own summary count only cover pins
        // that actually have a Union.
        $mapOrganizationItems = $mapChurches->concat($mapPeople)->concat($mapInstitutions)
            ->filter(fn ($item) => $item['unionId'] !== null);

        $unionIds = $mapOrganizationItems->pluck('unionId')->unique();
        $unionOffices = Union::whereIn('id', $unionIds)->get(['id', 'latitude', 'longitude'])->keyBy('id');

        $mapUnions = $mapOrganizationItems
            ->unique('unionId')
            ->sortBy('unionName')
            ->values()
            ->map(fn ($item) => [
                'id' => $item['unionId'],
                'name' => $item['unionName'],
                'officeLat' => $unionOffices[$item['unionId']]->latitude ?? null,
                'officeLng' => $unionOffices[$item['unionId']]->longitude ?? null,
            ]);

        $conferenceIds = $mapOrganizationItems->pluck('conferenceId')->filter()->unique();
        $conferenceOffices = Conference::whereIn('id', $conferenceIds)->get(['id', 'latitude', 'longitude'])->keyBy('id');

        // The Daerah-level layer shown once the viewer zooms into a Union — inherits its color
        // from the parent Union (assigned in JS) so the two zoom tiers read as the same region.
        $mapConferences = $mapOrganizationItems
            ->filter(fn ($item) => $item['conferenceId'] !== null)
            ->unique('conferenceId')
            ->sortBy('conferenceName')
            ->values()
            ->map(fn ($item) => [
                'id' => $item['conferenceId'],
                'name' => $item['conferenceName'],
                'unionId' => $item['unionId'],
                'officeLat' => $conferenceOffices[$item['conferenceId']]->latitude ?? null,
                'officeLng' => $conferenceOffices[$item['conferenceId']]->longitude ?? null,
            ]);

        // Unlike the map/platform-score below, Top 5/Bottom 5 use the normal scoped call —
        // analyticsChurchScope() already resolves a gereja-level viewer to their whole
        // Daerah/Konferens (same as admin_daerah), not their single church, so this ranks them
        // against real peers instead of either "1 of 1" or the full nasional field, per the
        // user's explicit call.
        $allGrowthScores = $this->growthScoreRows();

        $mapScoreRow = fn ($row) => [
            'entity' => $row['church'],
            'score' => $row['score'],
            'metrics' => $row['metrics'],
            'accountCount' => $row['accountCount'],
        ];

        $topGrowthScores = $allGrowthScores->take(5)->map($mapScoreRow);
        $bottomGrowthScores = $allGrowthScores->sortBy('score')->take(5)->values()->map($mapScoreRow);

        // growthScoreRowsByPlatform() only returns a row for a platform that has at least one
        // account with 2+ weekly stat snapshots to diff — Facebook accounts frequently don't
        // have that yet, so it silently drops out instead of showing up with "no data". Backfill
        // any missing platform as a null-score placeholder row so every platform this app tracks
        // always shows up here, per the user's explicit call.
        $platformScoreSocials = $this->activeSocials(scoped: ! $isGerejaLevel);
        $platformScoreRows = $this->growthScoreRowsByPlatform($platformScoreSocials);
        $missingPlatformScoreRows = collect(SocialPlatform::cases())
            ->reject(fn ($platform) => $platformScoreRows->contains('platform', $platform->value))
            ->map(fn ($platform) => [
                'platform' => $platform->value,
                'score' => null,
                'metrics' => ['reach' => null, 'views' => null, 'likes' => null, 'posts' => null],
                'accountCount' => $platformScoreSocials->where('platform', $platform)->count(),
            ]);
        $platformScoreRows = $platformScoreRows->concat($missingPlatformScoreRows);

        // Union/Conference-owned ("Organisasi") accounts, scoped to the viewer's own region —
        // shared by the Goal card and Distribution Channels below so their totals stay in sync
        // with each other, per the user's explicit call.
        $organizationSocials = $this->activeSocialsOrganization();

        $goalRows = $this->goalProgressRows($allSocials, $institutionSocials, $personalSocials, $organizationSocials);

        // Distribution Channels widget — church+personal+institution+organisasi, matching the
        // Goal card's "current" total above. Always one row per platform, in
        // SocialPlatform::cases() order, even a platform with zero accounts — the icon row
        // needs to show every platform's count, not just the ones in use.
        $platformLabels = ['youtube' => 'YouTube', 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'facebook' => 'Facebook'];
        $allPlatformSocials = $allSocials->merge($personalSocials)->merge($institutionSocials)->merge($organizationSocials);
        $totalSocialAccounts = $allPlatformSocials->count();
        // Owner-type breakdown for the Total Akun card — replaces the old separate "Akun sosial
        // gereja/institusi/personal" stat cards above, which showed the same counts split across
        // three cards instead of one, per the user's explicit call.
        $accountsByOwnerType = [
            ['icon' => 'building-office', 'label' => __('dashboard.owner_type_organisasi'), 'count' => $organizationSocials->count()],
            ['icon' => 'building-office', 'label' => __('dashboard.owner_type_gereja'), 'count' => $allSocials->count()],
            ['icon' => 'building-office', 'label' => __('dashboard.owner_type_institusi'), 'count' => $institutionSocials->count()],
            ['icon' => 'user', 'label' => __('dashboard.owner_type_personal'), 'count' => $personalSocials->count()],
        ];

        // Short scope label ("Nasional"/"Uni :name"/etc., same style as the Goals section header)
        // for every section on this dashboard that shares $allSocials/$institutionSocials/
        // $personalSocials's own scoping — Ringkasan, Pertumbuhan, Total Akun, Jangkauan. A
        // gereja-level viewer gets the Daerah label since that's the actual breadth of those
        // collections (see analyticsChurchScope()), not their single church.
        $scopeUser = auth()->user();
        $scopeConference = $scopeUser->conference ?? $scopeUser->church?->conference;
        $regionScopeLabel = match (true) {
            $scopeUser->role === null || ($scopeUser->role?->hasNasionalAccess() ?? false) || $scopeUser->role?->level() === 'nasional'
                => __('goals.scope_nasional'),
            $scopeUser->role?->level() === 'uni'
                => __('goals.scope_uni', ['name' => $scopeUser->union?->name ?? '—']),
            $scopeUser->role?->level() === 'daerah' || $isGerejaLevel
                => __('goals.scope_daerah', ['name' => $scopeConference?->name ?? '—']),
            $scopeUser->role?->level() === 'institusi'
                => __('dashboard.scope_institusi', ['name' => $scopeUser->institution?->name ?? '—']),
            default => __('goals.scope_nasional'),
        };

        // Peta (and, elsewhere in this method, Skor Performa Platform) stay fully unscoped for a
        // gereja-level viewer — see the block comment at the top of this method — so its header
        // needs "Nasional" instead of $regionScopeLabel's Daerah label for that one role; every
        // other role sees the same breadth on both, so the label is identical.
        $mapScopeLabel = $isGerejaLevel ? __('goals.scope_nasional') : $regionScopeLabel;

        // Reach-by-category pie chart — replaces the three separate reach stat-cards (their raw
        // numbers now ride along as each row's "detail" line) with a single visualization of the
        // same church/institution/personal totals as a share of combined reach, per the user's
        // explicit call. Colors match the map's own per-category marker colors above
        // (buildClusterGroup() in the inline map script below) for visual consistency across the
        // dashboard — validated via the dataviz skill's validate_palette.js, passes clean in both
        // light and dark with no per-mode adjustment needed.
        $totalReachAll = $totalReachChurch + $totalReachInstitution + $totalReachPersonal;
        $reachPercent = fn ($value) => $totalReachAll > 0 ? round($value / $totalReachAll * 100, 1) : 0;
        $reachByOwnerType = [
            ['label' => __('common.church'), 'value' => $reachPercent($totalReachChurch), 'detail' => number_format($totalReachChurch), 'color' => '#2563eb'],
            ['label' => __('common.institution'), 'value' => $reachPercent($totalReachInstitution), 'detail' => number_format($totalReachInstitution), 'color' => '#d97706'],
            ['label' => __('common.personal'), 'value' => $reachPercent($totalReachPersonal), 'detail' => number_format($totalReachPersonal), 'color' => '#7c3aed'],
        ];
        $distributionChannels = collect(SocialPlatform::cases())->map(function ($platform) use ($allPlatformSocials, $reachCountField, $platformLabels) {
            $platformSocials = $allPlatformSocials->where('platform', $platform);

            return [
                'platform' => $platform,
                'label' => $platformLabels[$platform->value],
                'count' => $platformSocials->count(),
                'reach' => $platformSocials->sum(fn ($social) => $social->latestStat?->{$reachCountField($social)} ?? 0),
            ];
        });

        // "Pertumbuhan minggu ini" combines church and personal accounts (unlike the leaderboard above,
        // which stays church-only since it ranks churches against each other).
        [$combinedReachSocials, $combinedReachField] = $this->metricDefinition('reach', $allSocials->merge($personalSocials));
        $weeklyGrowth = $this->buildLeaderboard($combinedReachSocials, $combinedReachField, null)->sum('delta');

        $user = auth()->user();

        // Shown under the dashboard title so it's always clear whose data this is — especially
        // for a gereja-level admin, whose stat cards and "big picture" widgets deliberately use
        // two different scopes (see the block comment at the top of this method).
        $scopeLabel = match (true) {
            $user->role?->hasNasionalAccess() || $user->role?->level() === 'nasional'
                => 'Ringkasan nasional — seluruh gereja dan akun personal.',
            $user->role?->level() === 'uni'
                => 'Wilayah Uni ' . ($user->union?->name ?? '—') . '.',
            $user->role?->level() === 'daerah'
                => 'Wilayah Daerah ' . ($user->conference?->name ?? '—') . '.',
            $isGerejaLevel
                => ($user->church?->name ?? 'gereja Anda') . ' — kartu statistik dan Top 5/Terendah untuk wilayah Daerah/Konferens Anda; Peta dan Skor Platform ditampilkan secara nasional untuk perbandingan.',
            $user->role?->level() === 'institusi'
                => 'Institusi ' . ($user->institution?->name ?? '—') . '.',
            default => __('dashboard.subtitle'),
        };

        return view('churches.index', [
            'scopeLabel' => $scopeLabel,
            'totalChurches' => $churches->count(),
            'totalSocials' => $allSocials->count(),
            'totalPeople' => $people->count(),
            'totalInstitutions' => $institutions->count(),
            'weeklyGrowth' => $weeklyGrowth,
            'mapUnions' => $mapUnions,
            'mapConferences' => $mapConferences,
            'mapOrganizationCount' => $mapOrganizationItems->count(),
            'mapChurches' => $mapChurches,
            'unmappedCount' => $unmappedCount,
            'mapPeople' => $mapPeople,
            'unmappedPeopleCount' => $unmappedPeopleCount,
            'mapInstitutions' => $mapInstitutions,
            'unmappedInstitutionsCount' => $unmappedInstitutionsCount,
            'topGrowthScores' => $topGrowthScores,
            'bottomGrowthScores' => $bottomGrowthScores,
            'platformScoreRows' => $platformScoreRows,
            'platformLabels' => ['youtube' => 'YouTube', 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'facebook' => 'Facebook'],
            'goalRows' => $goalRows,
            'distributionChannels' => $distributionChannels,
            'regionScopeLabel' => $regionScopeLabel,
            'totalSocialAccounts' => $totalSocialAccounts,
            'accountsByOwnerType' => $accountsByOwnerType,
            'mapScopeLabel' => $mapScopeLabel,
            'reachByOwnerType' => $reachByOwnerType,
        ]);
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
     * $churchSocials/$institutionSocials are the SAME already-viewer-scoped collections index()
     * computed for the KPI cards above (via analyticsChurchScope()/analyticsInstitutionScope()),
     * reused here so the "current" total matches the viewer's own scope without re-querying.
     */
    private function goalProgressRows(Collection $churchSocials, Collection $institutionSocials, Collection $personalSocials, Collection $organizationSocials): Collection
    {
        $user = auth()->user();
        $level = $user->role?->level();
        $isNasional = $user->role === null || ($user->role?->hasNasionalAccess() ?? false) || $level === 'nasional';
        $isUni = ! $isNasional && $level === 'uni';
        $isDaerahOrGereja = ! $isNasional && ! $isUni && in_array($level, ['daerah', 'gereja'], true);

        if (! $isNasional && ! $isUni && ! $isDaerahOrGereja) {
            return collect();
        }

        $unionCount = Union::where('is_active', true)->count();

        // admin_gereja has no $user->conference of its own (only $user->church) — same
        // conference_id derivation as analyticsChurchScope()'s gereja-level branch.
        $conference = $user->conference ?? $user->church?->conference;

        $scopeLabel = match (true) {
            $isNasional => __('goals.scope_nasional'),
            $isUni => __('goals.scope_uni', ['name' => $user->union?->name ?? '—']),
            default => __('goals.scope_daerah', ['name' => $conference?->name ?? '—']),
        };

        // Personal and Organisasi (union/conference-owned) accounts count toward "current" too,
        // so this total matches the Distribution Channels widget's total reach — union/daerah
        // target splitting above is unaffected, it only divides $goal->target_value, never
        // re-derives it from this total.
        $combinedSocials = $churchSocials->merge($institutionSocials)->merge($personalSocials)->merge($organizationSocials);

        return collect(Goal::METRICS)->map(function ($metric) use ($combinedSocials, $isNasional, $isUni, $unionCount, $conference, $scopeLabel) {
            $goal = Goal::forMetric($metric);

            $target = match (true) {
                $isNasional => $goal->target_value,
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

    public function needsAttention()
    {
        $socials = $this->accountsNeedingAttentionQuery()
            ->with(['church', 'person', 'institution', 'union', 'conference'])
            ->orderByDesc('last_fetched_at')
            ->get();

        return view('churches.needs-attention', ['socials' => $socials]);
    }

    /**
     * "Akun Otomatis" — every active, auto-fetchable account across all five owner types, so an
     * admin can audit when each one last actually pulled data instead of only finding out once
     * it's already broken (see needsAttention() above, which only shows the failed ones).
     * Ordered with never-fetched accounts first (nulls sort first ascending), then oldest
     * last_fetched_at — the two situations most worth an admin's attention first.
     */
    public function autoFetchAccounts()
    {
        $socials = $this->autoFetchAccountsQuery()
            ->with(['church', 'person', 'institution', 'union', 'conference'])
            ->orderByRaw('last_fetched_at IS NULL DESC')
            ->orderBy('last_fetched_at')
            ->get();

        return view('churches.auto-fetch-accounts', ['socials' => $socials]);
    }

    public function personalMetricComparison(Request $request)
    {
        $metricLabels = ['reach' => 'Followers/Subscribers', 'views' => 'Views', 'likes' => 'Likes', 'posts' => 'Post / Video'];

        $isUniView = $this->isUniView();
        $selectedUnionId = $isUniView ? (string) $request->user()->union_id : $request->query('union_id');
        $selectedConferenceId = $request->query('conference_id');
        $matchesRegionFilter = $this->matchesRegionFilter($selectedUnionId, $selectedConferenceId);
        [$unionOptions, $conferenceOptions] = $this->regionFilterOptions($selectedUnionId);

        $scoreRows = $this->growthScoreRowsPersonal()
            ->map(fn ($row) => [
                'entity' => $row['person'],
                'score' => $row['score'],
                'metrics' => $row['metrics'],
                'accountCount' => $row['accountCount'],
            ])
            ->filter(fn ($row) => $matchesRegionFilter($row['entity']))
            ->values();

        $groupedScoreRows = ($this->isNasionalView() || $isUniView)
            ? $this->groupByRegion($scoreRows, fn ($row) => $row['entity'])
            : null;

        return view('churches.metric-comparison', [
            'scope' => ComparisonScope::personal(),
            'metricLabels' => $metricLabels,
            'scoreRows' => $scoreRows,
            'groupedScoreRows' => $groupedScoreRows,
            'unionOptions' => $unionOptions,
            'conferenceOptions' => $conferenceOptions,
            'selectedUnionId' => $selectedUnionId,
            'selectedConferenceId' => $selectedConferenceId,
            'isNasionalView' => $this->isNasionalView(),
            'isUniView' => $isUniView,
        ]);
    }

    public function personalLeaderboard(Request $request, string $metric)
    {
        $titles = $this->leaderboardTitles();
        $metricLabels = ['reach' => 'Followers/Subscribers', 'views' => 'Views', 'likes' => 'Likes', 'posts' => 'Post / Video'];

        abort_unless(isset($titles[$metric]), 404);

        $sort = $request->query('sort') === 'value' ? 'value' : 'delta';

        $isUniView = $this->isUniView();
        $selectedUnionId = $isUniView ? (string) $request->user()->union_id : $request->query('union_id');
        $selectedConferenceId = $request->query('conference_id');
        $matchesRegionFilter = $this->matchesRegionFilter($selectedUnionId, $selectedConferenceId);
        [$unionOptions, $conferenceOptions] = $this->regionFilterOptions($selectedUnionId);

        [$socials, $field] = $this->metricDefinition($metric, $this->activeSocialsPersonal());

        $rows = $this->buildLeaderboard($socials, $field, null, $sort)
            ->filter(fn ($row) => $matchesRegionFilter($row['social']->person))
            ->values();

        $groupedRows = ($this->isNasionalView() || $isUniView)
            ? $this->groupByRegion($rows, fn ($row) => $row['social']->person)
            : null;

        return view('churches.leaderboard', [
            'scope' => ComparisonScope::personal(),
            'metric' => $metric,
            'metricLabels' => $metricLabels,
            'title' => $titles[$metric]['title'],
            'subtitle' => $titles[$metric]['subtitle'],
            'sort' => $sort,
            'rows' => $rows,
            'groupedRows' => $groupedRows,
            'unionOptions' => $unionOptions,
            'conferenceOptions' => $conferenceOptions,
            'selectedUnionId' => $selectedUnionId,
            'selectedConferenceId' => $selectedConferenceId,
            'isNasionalView' => $this->isNasionalView(),
            'isUniView' => $isUniView,
        ]);
    }

    public function personalPlatformComparison(Request $request, string $platform = 'semua')
    {
        $platformLabels = ['semua' => 'Semua', 'youtube' => 'YouTube', 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'facebook' => 'Facebook'];
        $metricLabels = ['reach' => 'Followers/Subscribers', 'views' => 'Views', 'likes' => 'Likes', 'posts' => 'Post / Video'];
        $metricPlatforms = $this->metricPlatforms();

        abort_unless(isset($platformLabels[$platform]), 404);

        $sort = $request->query('sort') === 'value' ? 'value' : 'delta';
        $metric = $request->query('metric');

        $isNasionalView = $this->isNasionalView();
        $isUniView = $this->isUniView();
        $selectedUnionId = $isUniView ? (string) $request->user()->union_id : $request->query('union_id');
        $selectedConferenceId = $request->query('conference_id');
        $matchesRegionFilter = $this->matchesRegionFilter($selectedUnionId, $selectedConferenceId);
        [$unionOptions, $conferenceOptions] = $this->regionFilterOptions($selectedUnionId);
        $regionFilterData = [
            'unionOptions' => $unionOptions,
            'conferenceOptions' => $conferenceOptions,
            'selectedUnionId' => $selectedUnionId,
            'selectedConferenceId' => $selectedConferenceId,
            'isNasionalView' => $isNasionalView,
            'isUniView' => $isUniView,
        ];

        if (! $metric) {
            $applicableMetrics = collect($metricPlatforms)
                ->filter(fn ($platforms) => in_array($platform, $platforms, true))
                ->keys();

            $sections = $applicableMetrics->mapWithKeys(fn ($m) => [
                $m => $this->metricComparisonRowsPersonal($m, $platform, $sort)
                    ->filter(fn ($row) => $matchesRegionFilter($row['person']))
                    ->values(),
            ]);

            $platformScoreRows = $platform === 'semua' ? $this->growthScoreRowsByPlatform($this->activeSocialsPersonal()) : null;

            return view('churches.platform-comparison-overview', array_merge([
                'scope' => ComparisonScope::personal(),
                'platform' => $platform,
                'platformLabels' => $platformLabels,
                'metricLabels' => $metricLabels,
                'sort' => $sort,
                'sections' => $sections,
                'platformScoreRows' => $platformScoreRows,
            ], $regionFilterData));
        }

        abort_unless(isset($metricLabels[$metric]), 404);

        if (! in_array($platform, $metricPlatforms[$metric], true)) {
            $fallbackPlatform = collect($metricPlatforms[$metric])->first(fn ($p) => $p !== 'semua');

            return redirect()->route('people.platform-comparison', array_filter([
                'platform' => $fallbackPlatform,
                'metric' => $metric,
                'sort' => $sort === 'value' ? 'value' : null,
            ]));
        }

        $rows = $this->metricComparisonRowsPersonal($metric, $platform, $sort)
            ->filter(fn ($row) => $matchesRegionFilter($row['person']))
            ->values();

        $groupedRows = ($isNasionalView || $isUniView)
            ? $this->groupByRegion($rows, fn ($row) => $row['person'])
            : null;

        return view('churches.platform-comparison', array_merge([
            'scope' => ComparisonScope::personal(),
            'platform' => $platform,
            'platformLabels' => $platformLabels,
            'metric' => $metric,
            'metricLabels' => $metricLabels,
            'metricPlatforms' => $metricPlatforms[$metric],
            'sort' => $sort,
            'rows' => $rows,
            'groupedRows' => $groupedRows,
        ], $regionFilterData));
    }

    public function institutionMetricComparison(Request $request)
    {
        $metricLabels = ['reach' => 'Followers/Subscribers', 'views' => 'Views', 'likes' => 'Likes', 'posts' => 'Post / Video'];

        $isUniView = $this->isUniView();
        $selectedUnionId = $isUniView ? (string) $request->user()->union_id : $request->query('union_id');
        $selectedConferenceId = $request->query('conference_id');
        $matchesRegionFilter = $this->matchesRegionFilter($selectedUnionId, $selectedConferenceId);
        [$unionOptions, $conferenceOptions] = $this->regionFilterOptions($selectedUnionId);

        $scoreRows = $this->growthScoreRowsInstitution()
            ->map(fn ($row) => [
                'entity' => $row['institution'],
                'score' => $row['score'],
                'metrics' => $row['metrics'],
                'accountCount' => $row['accountCount'],
            ])
            ->filter(fn ($row) => $matchesRegionFilter($row['entity']))
            ->values();

        $groupedScoreRows = ($this->isNasionalView() || $isUniView)
            ? $this->groupByRegion($scoreRows, fn ($row) => $row['entity'])
            : null;

        return view('churches.metric-comparison', [
            'scope' => ComparisonScope::institution(),
            'metricLabels' => $metricLabels,
            'scoreRows' => $scoreRows,
            'groupedScoreRows' => $groupedScoreRows,
            'unionOptions' => $unionOptions,
            'conferenceOptions' => $conferenceOptions,
            'selectedUnionId' => $selectedUnionId,
            'selectedConferenceId' => $selectedConferenceId,
            'isNasionalView' => $this->isNasionalView(),
            'isUniView' => $isUniView,
        ]);
    }

    public function institutionLeaderboard(Request $request, string $metric)
    {
        $titles = $this->leaderboardTitles();
        $metricLabels = ['reach' => 'Followers/Subscribers', 'views' => 'Views', 'likes' => 'Likes', 'posts' => 'Post / Video'];

        abort_unless(isset($titles[$metric]), 404);

        $sort = $request->query('sort') === 'value' ? 'value' : 'delta';

        $isUniView = $this->isUniView();
        $selectedUnionId = $isUniView ? (string) $request->user()->union_id : $request->query('union_id');
        $selectedConferenceId = $request->query('conference_id');
        $matchesRegionFilter = $this->matchesRegionFilter($selectedUnionId, $selectedConferenceId);
        [$unionOptions, $conferenceOptions] = $this->regionFilterOptions($selectedUnionId);

        [$socials, $field] = $this->metricDefinition($metric, $this->activeSocialsInstitution());

        $rows = $this->buildLeaderboard($socials, $field, null, $sort)
            ->filter(fn ($row) => $matchesRegionFilter($row['social']->institution))
            ->values();

        $groupedRows = ($this->isNasionalView() || $isUniView)
            ? $this->groupByRegion($rows, fn ($row) => $row['social']->institution)
            : null;

        return view('churches.leaderboard', [
            'scope' => ComparisonScope::institution(),
            'metric' => $metric,
            'metricLabels' => $metricLabels,
            'title' => $titles[$metric]['title'],
            'subtitle' => $titles[$metric]['subtitle'],
            'sort' => $sort,
            'rows' => $rows,
            'groupedRows' => $groupedRows,
            'unionOptions' => $unionOptions,
            'conferenceOptions' => $conferenceOptions,
            'selectedUnionId' => $selectedUnionId,
            'selectedConferenceId' => $selectedConferenceId,
            'isNasionalView' => $this->isNasionalView(),
            'isUniView' => $isUniView,
        ]);
    }

    public function organizationMetricComparison(Request $request)
    {
        $metricLabels = ['reach' => 'Followers/Subscribers', 'views' => 'Views', 'likes' => 'Likes', 'posts' => 'Post / Video'];

        $isUniView = $this->isUniView();
        $selectedUnionId = $isUniView ? (string) $request->user()->union_id : $request->query('union_id');
        $selectedConferenceId = $request->query('conference_id');
        $matchesRegionFilter = $this->matchesRegionFilter($selectedUnionId, $selectedConferenceId);
        [$unionOptions, $conferenceOptions] = $this->regionFilterOptions($selectedUnionId);

        $scoreRows = $this->growthScoreRowsOrganization()
            ->map(fn ($row) => [
                'entity' => $row['organization'],
                'score' => $row['score'],
                'metrics' => $row['metrics'],
                'accountCount' => $row['accountCount'],
            ])
            ->filter(fn ($row) => $matchesRegionFilter($row['entity']))
            ->values();

        $groupedScoreRows = ($this->isNasionalView() || $isUniView)
            ? $this->groupByRegion($scoreRows, fn ($row) => $row['entity'])
            : null;

        return view('churches.metric-comparison', [
            'scope' => ComparisonScope::organization(),
            'metricLabels' => $metricLabels,
            'scoreRows' => $scoreRows,
            'groupedScoreRows' => $groupedScoreRows,
            'unionOptions' => $unionOptions,
            'conferenceOptions' => $conferenceOptions,
            'selectedUnionId' => $selectedUnionId,
            'selectedConferenceId' => $selectedConferenceId,
            'isNasionalView' => $this->isNasionalView(),
            'isUniView' => $isUniView,
        ]);
    }

    public function organizationLeaderboard(Request $request, string $metric)
    {
        $titles = $this->leaderboardTitles();
        $metricLabels = ['reach' => 'Followers/Subscribers', 'views' => 'Views', 'likes' => 'Likes', 'posts' => 'Post / Video'];

        abort_unless(isset($titles[$metric]), 404);

        $sort = $request->query('sort') === 'value' ? 'value' : 'delta';

        $isUniView = $this->isUniView();
        $selectedUnionId = $isUniView ? (string) $request->user()->union_id : $request->query('union_id');
        $selectedConferenceId = $request->query('conference_id');
        $matchesRegionFilter = $this->matchesRegionFilter($selectedUnionId, $selectedConferenceId);
        [$unionOptions, $conferenceOptions] = $this->regionFilterOptions($selectedUnionId);

        [$socials, $field] = $this->metricDefinition($metric, $this->activeSocialsOrganization());

        $rows = $this->buildLeaderboard($socials, $field, null, $sort)
            ->filter(fn ($row) => $matchesRegionFilter($row['social']->union ?? $row['social']->conference))
            ->values();

        $groupedRows = ($this->isNasionalView() || $isUniView)
            ? $this->groupByRegion($rows, fn ($row) => $row['social']->union ?? $row['social']->conference)
            : null;

        return view('churches.leaderboard', [
            'scope' => ComparisonScope::organization(),
            'metric' => $metric,
            'metricLabels' => $metricLabels,
            'title' => $titles[$metric]['title'],
            'subtitle' => $titles[$metric]['subtitle'],
            'sort' => $sort,
            'rows' => $rows,
            'groupedRows' => $groupedRows,
            'unionOptions' => $unionOptions,
            'conferenceOptions' => $conferenceOptions,
            'selectedUnionId' => $selectedUnionId,
            'selectedConferenceId' => $selectedConferenceId,
            'isNasionalView' => $this->isNasionalView(),
            'isUniView' => $isUniView,
        ]);
    }

    public function organizationPlatformComparison(Request $request, string $platform = 'semua')
    {
        $platformLabels = ['semua' => 'Semua', 'youtube' => 'YouTube', 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'facebook' => 'Facebook'];
        $metricLabels = ['reach' => 'Followers/Subscribers', 'views' => 'Views', 'likes' => 'Likes', 'posts' => 'Post / Video'];
        $metricPlatforms = $this->metricPlatforms();

        abort_unless(isset($platformLabels[$platform]), 404);

        $sort = $request->query('sort') === 'value' ? 'value' : 'delta';
        $metric = $request->query('metric');

        $isNasionalView = $this->isNasionalView();
        $isUniView = $this->isUniView();
        $selectedUnionId = $isUniView ? (string) $request->user()->union_id : $request->query('union_id');
        $selectedConferenceId = $request->query('conference_id');
        $matchesRegionFilter = $this->matchesRegionFilter($selectedUnionId, $selectedConferenceId);
        [$unionOptions, $conferenceOptions] = $this->regionFilterOptions($selectedUnionId);
        $regionFilterData = [
            'unionOptions' => $unionOptions,
            'conferenceOptions' => $conferenceOptions,
            'selectedUnionId' => $selectedUnionId,
            'selectedConferenceId' => $selectedConferenceId,
            'isNasionalView' => $isNasionalView,
            'isUniView' => $isUniView,
        ];

        if (! $metric) {
            $applicableMetrics = collect($metricPlatforms)
                ->filter(fn ($platforms) => in_array($platform, $platforms, true))
                ->keys();

            $sections = $applicableMetrics->mapWithKeys(fn ($m) => [
                $m => $this->metricComparisonRowsOrganization($m, $platform, $sort)
                    ->filter(fn ($row) => $matchesRegionFilter($row['organization']))
                    ->values(),
            ]);

            $platformScoreRows = $platform === 'semua' ? $this->growthScoreRowsByPlatform($this->activeSocialsOrganization()) : null;

            return view('churches.platform-comparison-overview', array_merge([
                'scope' => ComparisonScope::organization(),
                'platform' => $platform,
                'platformLabels' => $platformLabels,
                'metricLabels' => $metricLabels,
                'sort' => $sort,
                'sections' => $sections,
                'platformScoreRows' => $platformScoreRows,
            ], $regionFilterData));
        }

        abort_unless(isset($metricLabels[$metric]), 404);

        if (! in_array($platform, $metricPlatforms[$metric], true)) {
            $fallbackPlatform = collect($metricPlatforms[$metric])->first(fn ($p) => $p !== 'semua');

            return redirect()->route('organizations.platform-comparison', array_filter([
                'platform' => $fallbackPlatform,
                'metric' => $metric,
                'sort' => $sort === 'value' ? 'value' : null,
            ]));
        }

        $rows = $this->metricComparisonRowsOrganization($metric, $platform, $sort)
            ->filter(fn ($row) => $matchesRegionFilter($row['organization']))
            ->values();

        $groupedRows = ($isNasionalView || $isUniView)
            ? $this->groupByRegion($rows, fn ($row) => $row['organization'])
            : null;

        return view('churches.platform-comparison', array_merge([
            'scope' => ComparisonScope::organization(),
            'platform' => $platform,
            'platformLabels' => $platformLabels,
            'metric' => $metric,
            'metricLabels' => $metricLabels,
            'metricPlatforms' => $metricPlatforms[$metric],
            'sort' => $sort,
            'rows' => $rows,
            'groupedRows' => $groupedRows,
        ], $regionFilterData));
    }

    public function institutionPlatformComparison(Request $request, string $platform = 'semua')
    {
        $platformLabels = ['semua' => 'Semua', 'youtube' => 'YouTube', 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'facebook' => 'Facebook'];
        $metricLabels = ['reach' => 'Followers/Subscribers', 'views' => 'Views', 'likes' => 'Likes', 'posts' => 'Post / Video'];
        $metricPlatforms = $this->metricPlatforms();

        abort_unless(isset($platformLabels[$platform]), 404);

        $sort = $request->query('sort') === 'value' ? 'value' : 'delta';
        $metric = $request->query('metric');

        $isNasionalView = $this->isNasionalView();
        $isUniView = $this->isUniView();
        $selectedUnionId = $isUniView ? (string) $request->user()->union_id : $request->query('union_id');
        $selectedConferenceId = $request->query('conference_id');
        $matchesRegionFilter = $this->matchesRegionFilter($selectedUnionId, $selectedConferenceId);
        [$unionOptions, $conferenceOptions] = $this->regionFilterOptions($selectedUnionId);
        $regionFilterData = [
            'unionOptions' => $unionOptions,
            'conferenceOptions' => $conferenceOptions,
            'selectedUnionId' => $selectedUnionId,
            'selectedConferenceId' => $selectedConferenceId,
            'isNasionalView' => $isNasionalView,
            'isUniView' => $isUniView,
        ];

        if (! $metric) {
            $applicableMetrics = collect($metricPlatforms)
                ->filter(fn ($platforms) => in_array($platform, $platforms, true))
                ->keys();

            $sections = $applicableMetrics->mapWithKeys(fn ($m) => [
                $m => $this->metricComparisonRowsInstitution($m, $platform, $sort)
                    ->filter(fn ($row) => $matchesRegionFilter($row['institution']))
                    ->values(),
            ]);

            $platformScoreRows = $platform === 'semua' ? $this->growthScoreRowsByPlatform($this->activeSocialsInstitution()) : null;

            return view('churches.platform-comparison-overview', array_merge([
                'scope' => ComparisonScope::institution(),
                'platform' => $platform,
                'platformLabels' => $platformLabels,
                'metricLabels' => $metricLabels,
                'sort' => $sort,
                'sections' => $sections,
                'platformScoreRows' => $platformScoreRows,
            ], $regionFilterData));
        }

        abort_unless(isset($metricLabels[$metric]), 404);

        if (! in_array($platform, $metricPlatforms[$metric], true)) {
            $fallbackPlatform = collect($metricPlatforms[$metric])->first(fn ($p) => $p !== 'semua');

            return redirect()->route('institutions.platform-comparison', array_filter([
                'platform' => $fallbackPlatform,
                'metric' => $metric,
                'sort' => $sort === 'value' ? 'value' : null,
            ]));
        }

        $rows = $this->metricComparisonRowsInstitution($metric, $platform, $sort)
            ->filter(fn ($row) => $matchesRegionFilter($row['institution']))
            ->values();

        $groupedRows = ($isNasionalView || $isUniView)
            ? $this->groupByRegion($rows, fn ($row) => $row['institution'])
            : null;

        return view('churches.platform-comparison', array_merge([
            'scope' => ComparisonScope::institution(),
            'platform' => $platform,
            'platformLabels' => $platformLabels,
            'metric' => $metric,
            'metricLabels' => $metricLabels,
            'metricPlatforms' => $metricPlatforms[$metric],
            'sort' => $sort,
            'rows' => $rows,
            'groupedRows' => $groupedRows,
        ], $regionFilterData));
    }

    public function analytics(Request $request)
    {
        $selectedPlatform = $request->query('platform');

        // Only narrows the growth-over-time chart, not the KPI hero or the Data Per * table —
        // those stay a snapshot of the latest stat regardless of range, since "current total"
        // wouldn't mean much scoped to an arbitrary past range.
        $selectedStartDate = $this->validDateOrNull($request->query('start_date'));
        $selectedEndDate = $this->validDateOrNull($request->query('end_date'));

        $user = $request->user();
        $isUniView = $this->isUniView();

        $selectedUnionId = $isUniView ? (string) $user->union_id : $request->query('union_id');
        $selectedConferenceId = $request->query('conference_id');

        // Applied to each entity collection right after it's queried, so the Uni/Daerah filter
        // propagates everywhere downstream for free: the KPI totals, the growth-over-time chart
        // (via $visibleIds below), the Data Per * table, and even the church_id/person_id/
        // institution_id picker's own option list — all without needing to touch any of that
        // logic separately.
        $matchesRegionFilter = $this->matchesRegionFilter($selectedUnionId, $selectedConferenceId);

        [$unionOptions, $conferenceOptions] = $this->regionFilterOptions($selectedUnionId);

        // conference.union is only needed for the Data Per * tables' Uni/Daerah grouping (see
        // analytics.blade.php's $groupEntityRows) — cheap to always eager-load rather than
        // conditionally adding it just for nasional/uni-level viewers.
        $churches = $this->analyticsChurchScope(Church::query()->where('is_active', true))
            ->with(['socials' => fn ($query) => $query->where('is_active', true)->with('latestStat'), 'conference.union'])
            ->orderBy('name')
            ->get()
            ->filter($matchesRegionFilter)
            ->values();

        $selectedChurchId = $request->query('church_id');

        $growthOverTimeChurch = $this->growthOverTime('church_id', 'churches', $selectedChurchId, $selectedPlatform, $churches->pluck('id'), $selectedStartDate, $selectedEndDate);

        $filteredChurches = $churches
            ->when($selectedChurchId, fn ($collection) => $collection->where('id', (int) $selectedChurchId))
            ->when($selectedPlatform, fn ($collection) => $collection->filter(
                fn ($church) => $church->socials->contains(fn ($social) => $social->platform->value === $selectedPlatform)
            ));

        $people = $this->analyticsPersonScope(Person::query()->where('is_active', true))
            ->with(['socials' => fn ($query) => $query->where('is_active', true)->with('latestStat'), 'union', 'conference.union'])
            ->orderBy('name')
            ->get()
            ->filter($matchesRegionFilter)
            ->values();

        $selectedPersonId = $request->query('person_id');

        $growthOverTimePersonal = $this->growthOverTime('person_id', 'people', $selectedPersonId, $selectedPlatform, $people->pluck('id'), $selectedStartDate, $selectedEndDate);

        $filteredPeople = $people
            ->when($selectedPersonId, fn ($collection) => $collection->where('id', (int) $selectedPersonId))
            ->when($selectedPlatform, fn ($collection) => $collection->filter(
                fn ($person) => $person->socials->contains(fn ($social) => $social->platform->value === $selectedPlatform)
            ));

        $institutions = $this->analyticsInstitutionScope(Institution::query()->where('is_active', true))
            ->with(['socials' => fn ($query) => $query->where('is_active', true)->with('latestStat'), 'union', 'conference.union'])
            ->orderBy('name')
            ->get()
            ->filter($matchesRegionFilter)
            ->values();

        $selectedInstitutionId = $request->query('institution_id');

        $growthOverTimeInstitution = $this->growthOverTime('institution_id', 'institutions', $selectedInstitutionId, $selectedPlatform, $institutions->pluck('id'), $selectedStartDate, $selectedEndDate);

        $filteredInstitutions = $institutions
            ->when($selectedInstitutionId, fn ($collection) => $collection->where('id', (int) $selectedInstitutionId))
            ->when($selectedPlatform, fn ($collection) => $collection->filter(
                fn ($institution) => $institution->socials->contains(fn ($social) => $social->platform->value === $selectedPlatform)
            ));

        // Organisasi tab: Union- and Conference-owned accounts shown together as one flat list
        // (each row is either a Union or a Conference model), grouped Uni > Daerah the same way
        // as the other three tabs — a Union row sits at the "uni-level" tier under itself, a
        // Conference row at its own Daerah tier under its parent Union (see BuildsLeaderboards::
        // regionEntityUnion()/regionEntityConference()).
        $visibleUnions = $this->analyticsUnionScope(Union::query()->where('is_active', true))
            ->with(['socials' => fn ($query) => $query->where('is_active', true)->with('latestStat')])
            ->orderBy('name')
            ->get();

        $visibleConferences = $this->analyticsConferenceScope(Conference::query()->where('is_active', true))
            ->with(['socials' => fn ($query) => $query->where('is_active', true)->with('latestStat'), 'union'])
            ->orderBy('name')
            ->get();

        $organizations = $visibleUnions->concat($visibleConferences)
            ->filter($matchesRegionFilter)
            ->values();

        $selectedOrganizationKey = $request->query('organization_id');
        [$selectedOrgType, $selectedOrgId] = $this->parseOrganizationKey($selectedOrganizationKey);

        $growthOverTimeOrganization = $this->growthOverTimeOrganization(
            $selectedOrganizationKey, $selectedPlatform, $visibleUnions->pluck('id'), $visibleConferences->pluck('id'), $selectedStartDate, $selectedEndDate
        );

        $filteredOrganizations = $organizations
            ->when($selectedOrganizationKey, fn ($collection) => $collection->filter(fn ($org) => match ($selectedOrgType) {
                'union' => $org instanceof Union && (string) $org->id === (string) $selectedOrgId,
                'conference' => $org instanceof Conference && (string) $org->id === (string) $selectedOrgId,
                default => true,
            }))
            ->when($selectedPlatform, fn ($collection) => $collection->filter(
                fn ($org) => $org->socials->contains(fn ($social) => $social->platform->value === $selectedPlatform)
            ));

        return view('churches.analytics', [
            'churches' => $churches,
            'filteredChurches' => $filteredChurches,
            'growthOverTime' => $growthOverTimeChurch,
            'selectedChurchId' => $selectedChurchId,
            'people' => $people,
            'filteredPeople' => $filteredPeople,
            'growthOverTimePersonal' => $growthOverTimePersonal,
            'selectedPersonId' => $selectedPersonId,
            'institutions' => $institutions,
            'filteredInstitutions' => $filteredInstitutions,
            'growthOverTimeInstitution' => $growthOverTimeInstitution,
            'selectedInstitutionId' => $selectedInstitutionId,
            'organizations' => $organizations,
            'filteredOrganizations' => $filteredOrganizations,
            'growthOverTimeOrganization' => $growthOverTimeOrganization,
            'selectedOrganizationKey' => $selectedOrganizationKey,
            'selectedPlatform' => $selectedPlatform,
            'unionOptions' => $unionOptions,
            'conferenceOptions' => $conferenceOptions,
            'selectedUnionId' => $selectedUnionId,
            'selectedConferenceId' => $selectedConferenceId,
            'selectedStartDate' => $selectedStartDate,
            'selectedEndDate' => $selectedEndDate,
        ]);
    }

    /** Guards against garbage query-string input reaching a SQL date comparison. */
    private function validDateOrNull(?string $date): ?string
    {
        if (! $date || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $date));

        return checkdate($month, $day, $year) ? $date : null;
    }

    /**
     * Weekly aggregate stats (reach/views/likes/posts) for church-, person-, or institution-owned
     * accounts. $ownerColumn is 'church_id', 'person_id', or 'institution_id' on church_socials;
     * $ownerTable is the matching owner table ('churches', 'people', or 'institutions') — both
     * are internal constants, never user input. $visibleIds restricts to IDs the viewing user's
     * role/scope may see (pre-resolved via Church::visibleTo()/Person::visibleTo()/
     * Institution::visibleTo(), rather than duplicating that hierarchy SQL here).
     */
    private function growthOverTime(string $ownerColumn, string $ownerTable, ?string $ownerId, ?string $platform, Collection $visibleIds, ?string $startDate = null, ?string $endDate = null)
    {
        return ChurchStat::query()
            ->join('church_socials', 'church_socials.id', '=', 'church_stats.church_social_id')
            ->join($ownerTable, "{$ownerTable}.id", '=', "church_socials.{$ownerColumn}")
            ->where('church_socials.is_active', true)
            ->where("{$ownerTable}.is_active", true)
            ->whereIn("{$ownerTable}.id", $visibleIds)
            ->when($ownerId, fn ($query) => $query->where("{$ownerTable}.id", $ownerId))
            ->when($platform, fn ($query) => $query->where('church_socials.platform', $platform))
            ->when($startDate, fn ($query) => $query->whereDate('church_stats.recorded_at', '>=', $startDate))
            ->when($endDate, fn ($query) => $query->whereDate('church_stats.recorded_at', '<=', $endDate))
            ->selectRaw(
                "church_stats.recorded_at as recorded_at,
                SUM(CASE WHEN church_socials.platform = 'youtube' THEN church_stats.subscribers_count ELSE church_stats.followers_count END) as total_reach,
                SUM(church_stats.views_count) as total_views,
                SUM(church_stats.likes_count) as total_likes,
                SUM(CASE WHEN church_socials.platform = 'youtube' THEN church_stats.videos_count ELSE church_stats.posts_count END) as total_posts"
            )
            ->groupBy('church_stats.recorded_at')
            ->orderBy('church_stats.recorded_at')
            ->get();
    }

    /**
     * Same shape as growthOverTime(), for the Organisasi tab — Union- and Conference-owned
     * accounts don't share a single owner column, so this checks both directly instead of the
     * generic $ownerColumn/$ownerTable join growthOverTime() uses for the other three tabs.
     * $selectedOrganizationKey is the composite "union-ID"/"conference-ID" entity-picker value
     * (see BuildsLeaderboards::parseOrganizationKey()).
     */
    private function growthOverTimeOrganization(?string $selectedOrganizationKey, ?string $platform, Collection $visibleUnionIds, Collection $visibleConferenceIds, ?string $startDate = null, ?string $endDate = null)
    {
        [$selectedType, $selectedId] = $this->parseOrganizationKey($selectedOrganizationKey);

        return ChurchStat::query()
            ->join('church_socials', 'church_socials.id', '=', 'church_stats.church_social_id')
            ->where('church_socials.is_active', true)
            ->where(function ($query) use ($visibleUnionIds, $visibleConferenceIds) {
                $query->whereIn('church_socials.union_id', $visibleUnionIds)
                    ->orWhereIn('church_socials.conference_id', $visibleConferenceIds);
            })
            ->when($selectedType === 'union', fn ($query) => $query->where('church_socials.union_id', $selectedId))
            ->when($selectedType === 'conference', fn ($query) => $query->where('church_socials.conference_id', $selectedId))
            ->when($platform, fn ($query) => $query->where('church_socials.platform', $platform))
            ->when($startDate, fn ($query) => $query->whereDate('church_stats.recorded_at', '>=', $startDate))
            ->when($endDate, fn ($query) => $query->whereDate('church_stats.recorded_at', '<=', $endDate))
            ->selectRaw(
                "church_stats.recorded_at as recorded_at,
                SUM(CASE WHEN church_socials.platform = 'youtube' THEN church_stats.subscribers_count ELSE church_stats.followers_count END) as total_reach,
                SUM(church_stats.views_count) as total_views,
                SUM(church_stats.likes_count) as total_likes,
                SUM(CASE WHEN church_socials.platform = 'youtube' THEN church_stats.videos_count ELSE church_stats.posts_count END) as total_posts"
            )
            ->groupBy('church_stats.recorded_at')
            ->orderBy('church_stats.recorded_at')
            ->get();
    }

    public function directory(Request $request)
    {
        $selectedPlatform = $request->query('platform');
        $search = trim((string) $request->query('search'));
        $autoFetch = in_array($request->query('auto_fetch'), ['auto', 'manual'], true) ? $request->query('auto_fetch') : null;
        $hideEmptyChurches = $request->boolean('hide_empty_churches');
        $hideEmptyPeople = $request->boolean('hide_empty_people');
        $hideEmptyInstitutions = $request->boolean('hide_empty_institutions');
        $hideEmptyOrganizations = $request->boolean('hide_empty_organizations');
        $activeTab = in_array($request->query('tab'), ['institusi', 'personal', 'gereja'], true) ? $request->query('tab') : 'organisasi';

        // Sort — per tab, same options as Kelola Akun's equivalent tabs minus "status" (the
        // directory only ever shows active entities to begin with, so there's nothing to sort
        // by there). Changes the within-group order (see groupByRegion() below) since the
        // Uni/Daerah grouping itself always stays alphabetical.
        $sortGereja = $request->query('sort_gereja', 'name_asc');
        $sortInstitusi = $request->query('sort_institusi', 'name_asc');
        $sortPersonal = $request->query('sort_personal', 'name_asc');
        $sortOrganisasi = $request->query('sort_organisasi', 'name_asc');

        // The directory is public and unscoped (see below), so — unlike every other
        // region-filterable page — the Uni/Daerah picker isn't narrowed to the viewer's own
        // admin region: everyone gets the full nasional picker, regardless of role.
        $selectedUnionId = $request->query('union_id');
        $selectedConferenceId = $request->query('conference_id');
        $unionOptions = Union::where('is_active', true)->orderBy('name')->get();
        $conferenceOptions = Conference::where('is_active', true)
            ->when($selectedUnionId, fn ($q) => $q->where('union_id', $selectedUnionId))
            ->with('union')
            ->orderBy('name')
            ->get();

        // Church has no union_id column of its own — only conference_id — hence the
        // whereHas('conference') branch for a union-only (no conference chosen) filter.
        $applyChurchRegionFilter = fn ($query) => $query
            ->when($selectedConferenceId, fn ($q) => $q->where('conference_id', $selectedConferenceId))
            ->when($selectedUnionId && ! $selectedConferenceId, fn ($q) => $q->whereHas(
                'conference', fn ($q2) => $q2->where('union_id', $selectedUnionId)
            ));

        // Person/Institution both carry their own union_id (for a nasional/uni-level account
        // with no specific Daerah) alongside conference_id — see analyticsPersonScope()/
        // analyticsInstitutionScope() in BuildsLeaderboards for the same union_id/conference_id
        // shape used elsewhere.
        $applyPersonOrInstitutionRegionFilter = fn ($query) => $query
            ->when($selectedConferenceId, fn ($q) => $q->where('conference_id', $selectedConferenceId))
            ->when($selectedUnionId && ! $selectedConferenceId, fn ($q) => $q->where(fn ($q2) => $q2
                ->where('union_id', $selectedUnionId)
                ->orWhereHas('conference', fn ($q3) => $q3->where('union_id', $selectedUnionId))
            ));

        // A Union row never "is" a specific Daerah, so a conference_id filter excludes every
        // Union outright; a Conference row's own union_id column narrows it directly, same as
        // Person/Institution above.
        $applyUnionRegionFilter = fn ($query) => $query
            ->when($selectedConferenceId, fn ($q) => $q->whereRaw('1 = 0'))
            ->when($selectedUnionId && ! $selectedConferenceId, fn ($q) => $q->where('id', $selectedUnionId));

        $applyConferenceRegionFilter = fn ($query) => $query
            ->when($selectedConferenceId, fn ($q) => $q->where('id', $selectedConferenceId))
            ->when($selectedUnionId && ! $selectedConferenceId, fn ($q) => $q->where('union_id', $selectedUnionId));

        // Shared with whereHas() below so "no data" means the same thing as what the eager
        // load actually shows in the account columns — not just "no accounts at all" while
        // ignoring the platform/auto_fetch filters currently applied.
        $socialsFilter = fn ($query) => $query
            ->where('is_active', true)
            ->when($selectedPlatform, fn ($q) => $q->where('platform', $selectedPlatform))
            ->when($autoFetch, fn ($q) => $q->where('is_auto_fetch', $autoFetch === 'auto'));

        // The directory is a public, read-only listing — every account visible to anyone
        // browsing it, not scoped to the viewer's own admin region. visibleTo() scoping is
        // for "what can I manage", which is a different question, answered instead by the
        // can:update checks on the church/person detail pages (and by churches.index / Kelola
        // Akun's Personal tab, which still apply visibleTo() for that management context).
        // Grouped into a collapsible Uni > Daerah > entity nesting — same scheme as Analitik &
        // Grafik/Perbandingan Metrik/Perbandingan Platform (see BuildsLeaderboards::
        // groupByRegion()) — always on here rather than gated behind isNasionalView()/
        // isUniView(), since the directory is unscoped for every viewer (see above): even an
        // admin_gereja can browse every Uni/Daerah's entries on this page, so grouping is always
        // potentially multi-region here regardless of who's looking.
        $churches = Church::query()
            ->where('is_active', true)
            ->when($search, fn ($q) => $q->where(fn ($q2) => $q2
                ->where('name', 'like', "%{$search}%")
                ->orWhere('city', 'like', "%{$search}%"),
            ))
            ->tap($applyChurchRegionFilter)
            ->when($hideEmptyChurches, fn ($q) => $q->whereHas('socials', $socialsFilter))
            ->with(['socials' => $socialsFilter, 'conference.union'])
            ->tap(fn ($q) => match ($sortGereja) {
                'name_desc' => $q->orderByDesc('name'),
                'city_asc' => $q->orderBy('city')->orderBy('name'),
                'city_desc' => $q->orderByDesc('city')->orderBy('name'),
                'daerah_asc' => $q->orderBy(Conference::select('name')->whereColumn('conferences.id', 'churches.conference_id'))->orderBy('name'),
                'daerah_desc' => $q->orderByDesc(Conference::select('name')->whereColumn('conferences.id', 'churches.conference_id'))->orderBy('name'),
                default => $q->orderBy('name'),
            })
            ->get();
        $groupedChurches = $this->groupByRegion($churches, fn ($church) => $church);

        $people = Person::query()
            ->where('is_active', true)
            ->when($search, fn ($q) => $q->where(fn ($q2) => $q2
                ->where('name', 'like', "%{$search}%")
                ->orWhere('city', 'like', "%{$search}%"),
            ))
            ->tap($applyPersonOrInstitutionRegionFilter)
            ->when($hideEmptyPeople, fn ($q) => $q->whereHas('socials', $socialsFilter))
            ->with(['socials' => $socialsFilter, 'conference.union', 'union'])
            ->tap(fn ($q) => match ($sortPersonal) {
                'name_desc' => $q->orderByDesc('name'),
                'city_asc' => $q->orderBy('city')->orderBy('name'),
                'city_desc' => $q->orderByDesc('city')->orderBy('name'),
                'scope_asc' => $q->orderBy($this->directoryScopeOrderExpression('people'))->orderBy('name'),
                'scope_desc' => $q->orderByDesc($this->directoryScopeOrderExpression('people'))->orderBy('name'),
                default => $q->orderBy('name'),
            })
            ->get();
        $groupedPeople = $this->groupByRegion($people, fn ($person) => $person);

        $institutions = Institution::query()
            ->where('is_active', true)
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->tap($applyPersonOrInstitutionRegionFilter)
            ->when($hideEmptyInstitutions, fn ($q) => $q->whereHas('socials', $socialsFilter))
            ->with(['socials' => $socialsFilter, 'conference.union', 'union'])
            ->tap(fn ($q) => match ($sortInstitusi) {
                'name_desc' => $q->orderByDesc('name'),
                'region_asc' => $q->orderBy($this->directoryScopeOrderExpression('institutions'))->orderBy('name'),
                'region_desc' => $q->orderByDesc($this->directoryScopeOrderExpression('institutions'))->orderBy('name'),
                default => $q->orderBy('name'),
            })
            ->get();
        $groupedInstitutions = $this->groupByRegion($institutions, fn ($institution) => $institution);

        $unions = Union::query()
            ->where('is_active', true)
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->tap($applyUnionRegionFilter)
            ->when($hideEmptyOrganizations, fn ($q) => $q->whereHas('socials', $socialsFilter))
            ->with(['socials' => $socialsFilter])
            ->orderBy('name', $sortOrganisasi === 'name_desc' ? 'desc' : 'asc')
            ->get();

        $conferences = Conference::query()
            ->where('is_active', true)
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->tap($applyConferenceRegionFilter)
            ->when($hideEmptyOrganizations, fn ($q) => $q->whereHas('socials', $socialsFilter))
            ->with(['socials' => $socialsFilter, 'union'])
            ->orderBy('name', $sortOrganisasi === 'name_desc' ? 'desc' : 'asc')
            ->get();

        $organizations = $unions->concat($conferences)->values();
        $groupedOrganizations = $this->groupByRegion($organizations, fn ($organization) => $organization);

        return view('churches.directory', [
            'churches' => $churches,
            'groupedChurches' => $groupedChurches,
            'people' => $people,
            'groupedPeople' => $groupedPeople,
            'institutions' => $institutions,
            'groupedInstitutions' => $groupedInstitutions,
            'organizations' => $organizations,
            'groupedOrganizations' => $groupedOrganizations,
            'selectedPlatform' => $selectedPlatform,
            'search' => $search,
            'autoFetch' => $autoFetch,
            'hideEmptyChurches' => $hideEmptyChurches,
            'hideEmptyPeople' => $hideEmptyPeople,
            'hideEmptyInstitutions' => $hideEmptyInstitutions,
            'hideEmptyOrganizations' => $hideEmptyOrganizations,
            'activeTab' => $activeTab,
            'unionOptions' => $unionOptions,
            'conferenceOptions' => $conferenceOptions,
            'selectedUnionId' => $selectedUnionId,
            'selectedConferenceId' => $selectedConferenceId,
            'sortGereja' => $sortGereja,
            'sortInstitusi' => $sortInstitusi,
            'sortPersonal' => $sortPersonal,
            'sortOrganisasi' => $sortOrganisasi,
        ]);
    }

    /**
     * An institution/person's "region"/"scope" column (see churches.directory) shows its
     * Conference name if it has one, else its Union name, else "Nasional"/"Independent" — this
     * mirrors that same fallback chain as a single orderable expression, since Eloquent's
     * orderBy() can't sort by "whichever of these two relations happens to be set" any other
     * way. Same pattern as AccountController's institutionRegionOrderExpression()/
     * personScopeOrderExpression(), just parameterized over the table name since both entity
     * types share this exact shape here.
     */
    private function directoryScopeOrderExpression(string $table)
    {
        return DB::raw("COALESCE(
            (SELECT name FROM conferences WHERE conferences.id = {$table}.conference_id),
            (SELECT name FROM unions WHERE unions.id = {$table}.union_id)
        )");
    }

    public function metricComparison(Request $request)
    {
        $metricLabels = ['reach' => 'Followers/Subscribers', 'views' => 'Views', 'likes' => 'Likes', 'posts' => 'Post / Video'];

        $isUniView = $this->isUniView();
        $selectedUnionId = $isUniView ? (string) $request->user()->union_id : $request->query('union_id');
        $selectedConferenceId = $request->query('conference_id');
        $matchesRegionFilter = $this->matchesRegionFilter($selectedUnionId, $selectedConferenceId);
        [$unionOptions, $conferenceOptions] = $this->regionFilterOptions($selectedUnionId);

        $scoreRows = $this->growthScoreRows()
            ->map(fn ($row) => [
                'entity' => $row['church'],
                'score' => $row['score'],
                'metrics' => $row['metrics'],
                'accountCount' => $row['accountCount'],
            ])
            ->filter(fn ($row) => $matchesRegionFilter($row['entity']))
            ->values();

        $groupedScoreRows = ($this->isNasionalView() || $isUniView)
            ? $this->groupByRegion($scoreRows, fn ($row) => $row['entity'])
            : null;

        return view('churches.metric-comparison', [
            'scope' => ComparisonScope::church(),
            'metricLabels' => $metricLabels,
            'scoreRows' => $scoreRows,
            'groupedScoreRows' => $groupedScoreRows,
            'unionOptions' => $unionOptions,
            'conferenceOptions' => $conferenceOptions,
            'selectedUnionId' => $selectedUnionId,
            'selectedConferenceId' => $selectedConferenceId,
            'isNasionalView' => $this->isNasionalView(),
            'isUniView' => $isUniView,
        ]);
    }

    public function leaderboard(Request $request, string $metric)
    {
        $titles = $this->leaderboardTitles();
        $metricLabels = ['reach' => 'Followers/Subscribers', 'views' => 'Views', 'likes' => 'Likes', 'posts' => 'Post / Video'];

        abort_unless(isset($titles[$metric]), 404);

        $sort = $request->query('sort') === 'value' ? 'value' : 'delta';

        $isUniView = $this->isUniView();
        $selectedUnionId = $isUniView ? (string) $request->user()->union_id : $request->query('union_id');
        $selectedConferenceId = $request->query('conference_id');
        $matchesRegionFilter = $this->matchesRegionFilter($selectedUnionId, $selectedConferenceId);
        [$unionOptions, $conferenceOptions] = $this->regionFilterOptions($selectedUnionId);

        [$socials, $field] = $this->metricDefinition($metric, $this->activeSocials());

        $rows = $this->buildLeaderboard($socials, $field, null, $sort)
            ->filter(fn ($row) => $matchesRegionFilter($row['social']->church))
            ->values();

        $groupedRows = ($this->isNasionalView() || $isUniView)
            ? $this->groupByRegion($rows, fn ($row) => $row['social']->church)
            : null;

        return view('churches.leaderboard', [
            'scope' => ComparisonScope::church(),
            'metric' => $metric,
            'metricLabels' => $metricLabels,
            'title' => $titles[$metric]['title'],
            'subtitle' => $titles[$metric]['subtitle'],
            'sort' => $sort,
            'rows' => $rows,
            'groupedRows' => $groupedRows,
            'unionOptions' => $unionOptions,
            'conferenceOptions' => $conferenceOptions,
            'selectedUnionId' => $selectedUnionId,
            'selectedConferenceId' => $selectedConferenceId,
            'isNasionalView' => $this->isNasionalView(),
            'isUniView' => $isUniView,
        ]);
    }

    public function presentation()
    {
        $countField = ['youtube' => 'subscribers_count', 'instagram' => 'followers_count', 'tiktok' => 'followers_count', 'facebook' => 'followers_count'];

        // Public presentation page — always shows general data, never scoped to whoever
        // (if anyone) happens to be logged in while it's displayed.
        $churches = Church::query()
            ->where('is_active', true)
            ->with(['socials' => fn ($q) => $q->where('is_active', true)->with('latestStat')])
            ->get();

        $rows = $churches->map(function ($church) use ($countField) {
            $byPlatform = collect(['youtube', 'instagram', 'tiktok', 'facebook'])->mapWithKeys(
                fn ($platform) => [
                    $platform => $church->socials
                        ->filter(fn ($s) => $s->platform->value === $platform)
                        ->sum(fn ($s) => $s->latestStat?->{$countField[$platform]} ?? 0),
                ]
            );

            return [
                'entity' => $church,
                'byPlatform' => $byPlatform,
                'total' => $byPlatform->sum(),
            ];
        })->sortByDesc('total')->values();

        return view('churches.presentation', [
            'scope' => ComparisonScope::church(),
            'rows' => $rows,
            'totalEntities' => $churches->count(),
            'totalSocials' => $churches->flatMap->socials->count(),
            'totalReach' => $rows->sum('total'),
        ]);
    }

    public function presentationGrowth()
    {
        // Public presentation page — always unscoped, see presentation() above.
        $churches = Church::query()->where('is_active', true)->get();

        $scores = $this->growthScoreRows(scoped: false)->keyBy(fn ($row) => $row['church']->id);

        $emptyMetrics = collect(['reach' => null, 'views' => null, 'likes' => null, 'posts' => null]);

        $rows = $churches->map(function ($church) use ($scores, $emptyMetrics) {
            $scored = $scores->get($church->id);

            return [
                'entity' => $church,
                'score' => $scored['score'] ?? null,
                'metrics' => $scored['metrics'] ?? $emptyMetrics,
            ];
        })->sortByDesc(fn ($row) => $row['score'] ?? -INF)->values();

        $scoredRows = $rows->filter(fn ($row) => $row['score'] !== null);

        return view('churches.presentation-growth', [
            'scope' => ComparisonScope::church(),
            'rows' => $rows,
            'totalEntities' => $churches->count(),
            'totalSocials' => $this->activeSocials(scoped: false)->count(),
            'avgScore' => $scoredRows->isNotEmpty() ? round($scoredRows->avg('score'), 2) : null,
        ]);
    }

    public function personalPresentation()
    {
        $countField = ['youtube' => 'subscribers_count', 'instagram' => 'followers_count', 'tiktok' => 'followers_count', 'facebook' => 'followers_count'];

        // Public presentation page — always unscoped, see presentation() above.
        $people = Person::query()
            ->where('is_active', true)
            ->with(['socials' => fn ($q) => $q->where('is_active', true)->with('latestStat')])
            ->get();

        $rows = $people->map(function ($person) use ($countField) {
            $byPlatform = collect(['youtube', 'instagram', 'tiktok', 'facebook'])->mapWithKeys(
                fn ($platform) => [
                    $platform => $person->socials
                        ->filter(fn ($s) => $s->platform->value === $platform)
                        ->sum(fn ($s) => $s->latestStat?->{$countField[$platform]} ?? 0),
                ]
            );

            return [
                'entity' => $person,
                'byPlatform' => $byPlatform,
                'total' => $byPlatform->sum(),
            ];
        })->sortByDesc('total')->values();

        return view('churches.presentation', [
            'scope' => ComparisonScope::personal(),
            'rows' => $rows,
            'totalEntities' => $people->count(),
            'totalSocials' => $people->flatMap->socials->count(),
            'totalReach' => $rows->sum('total'),
        ]);
    }

    public function personalPresentationGrowth()
    {
        // Public presentation page — always unscoped, see presentation() above.
        $people = Person::query()->where('is_active', true)->get();

        $scores = $this->growthScoreRowsPersonal(scoped: false)->keyBy(fn ($row) => $row['person']->id);

        $emptyMetrics = collect(['reach' => null, 'views' => null, 'likes' => null, 'posts' => null]);

        $rows = $people->map(function ($person) use ($scores, $emptyMetrics) {
            $scored = $scores->get($person->id);

            return [
                'entity' => $person,
                'score' => $scored['score'] ?? null,
                'metrics' => $scored['metrics'] ?? $emptyMetrics,
            ];
        })->sortByDesc(fn ($row) => $row['score'] ?? -INF)->values();

        $scoredRows = $rows->filter(fn ($row) => $row['score'] !== null);

        return view('churches.presentation-growth', [
            'scope' => ComparisonScope::personal(),
            'rows' => $rows,
            'totalEntities' => $people->count(),
            'totalSocials' => $this->activeSocialsPersonal(scoped: false)->count(),
            'avgScore' => $scoredRows->isNotEmpty() ? round($scoredRows->avg('score'), 2) : null,
        ]);
    }

    public function platformComparison(Request $request, string $platform = 'semua')
    {
        $platformLabels = ['semua' => 'Semua', 'youtube' => 'YouTube', 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'facebook' => 'Facebook'];
        $metricLabels = ['reach' => 'Followers/Subscribers', 'views' => 'Views', 'likes' => 'Likes', 'posts' => 'Post / Video'];
        $metricPlatforms = $this->metricPlatforms();

        abort_unless(isset($platformLabels[$platform]), 404);

        $sort = $request->query('sort') === 'value' ? 'value' : 'delta';
        $metric = $request->query('metric');

        $isNasionalView = $this->isNasionalView();
        $isUniView = $this->isUniView();
        $selectedUnionId = $isUniView ? (string) $request->user()->union_id : $request->query('union_id');
        $selectedConferenceId = $request->query('conference_id');
        $matchesRegionFilter = $this->matchesRegionFilter($selectedUnionId, $selectedConferenceId);
        [$unionOptions, $conferenceOptions] = $this->regionFilterOptions($selectedUnionId);
        $regionFilterData = [
            'unionOptions' => $unionOptions,
            'conferenceOptions' => $conferenceOptions,
            'selectedUnionId' => $selectedUnionId,
            'selectedConferenceId' => $selectedConferenceId,
            'isNasionalView' => $isNasionalView,
            'isUniView' => $isUniView,
        ];

        // No metric picked: show every metric that applies to this platform together on one page.
        if (! $metric) {
            $applicableMetrics = collect($metricPlatforms)
                ->filter(fn ($platforms) => in_array($platform, $platforms, true))
                ->keys();

            $sections = $applicableMetrics->mapWithKeys(fn ($m) => [
                $m => $this->metricComparisonRows($m, $platform, $sort)
                    ->filter(fn ($row) => $matchesRegionFilter($row['church']))
                    ->values(),
            ]);

            // "Semua" compares platforms against each other, so it gets a composite score
            // card instead of per-metric church leaderboards (which don't apply platform-vs-platform).
            $platformScoreRows = $platform === 'semua' ? $this->growthScoreRowsByPlatform($this->activeSocials()) : null;

            return view('churches.platform-comparison-overview', array_merge([
                'scope' => ComparisonScope::church(),
                'platform' => $platform,
                'platformLabels' => $platformLabels,
                'metricLabels' => $metricLabels,
                'sort' => $sort,
                'sections' => $sections,
                'platformScoreRows' => $platformScoreRows,
            ], $regionFilterData));
        }

        abort_unless(isset($metricLabels[$metric]), 404);

        // views/likes only exist for one real platform each — snap to it instead of showing empty data.
        if (! in_array($platform, $metricPlatforms[$metric], true)) {
            $fallbackPlatform = collect($metricPlatforms[$metric])->first(fn ($p) => $p !== 'semua');

            return redirect()->route('churches.platform-comparison', array_filter([
                'platform' => $fallbackPlatform,
                'metric' => $metric,
                'sort' => $sort === 'value' ? 'value' : null,
            ]));
        }

        $rows = $this->metricComparisonRows($metric, $platform, $sort)
            ->filter(fn ($row) => $matchesRegionFilter($row['church']))
            ->values();

        $groupedRows = ($isNasionalView || $isUniView)
            ? $this->groupByRegion($rows, fn ($row) => $row['church'])
            : null;

        return view('churches.platform-comparison', array_merge([
            'scope' => ComparisonScope::church(),
            'platform' => $platform,
            'platformLabels' => $platformLabels,
            'metric' => $metric,
            'metricLabels' => $metricLabels,
            'metricPlatforms' => $metricPlatforms[$metric],
            'sort' => $sort,
            'rows' => $rows,
            'groupedRows' => $groupedRows,
        ], $regionFilterData));
    }

    public function show(Church $church)
    {
        $church->load(['socials' => fn ($query) => $query->where('is_active', true)->with('latestStat')]);

        $history = $church->socials->mapWithKeys(
            fn ($social) => [$social->id => $social->stats()->limit(30)->get()]
        );

        $scoreHistory = $this->growthScoreHistory($church->socials);

        // Only a gereja-level viewer gets these — their own dashboard stat cards now show
        // their whole Daerah/Konferens (per the user's explicit call), so this is the one
        // place left that summarizes just their own world at a glance: this one church, plus
        // their own personal accounts (they manage both — see Profil Saya's Media Sosial tab).
        $ownStats = null;

        if (auth()->user()->role?->level() === 'gereja') {
            $reachCountField = fn ($social) => $social->platform === SocialPlatform::YouTube ? 'subscribers_count' : 'followers_count';

            $personSocials = auth()->user()->person
                ? auth()->user()->person->socials()->where('is_active', true)->with('latestStat')->get()
                : collect();

            $combinedSocials = $church->socials->merge($personSocials);
            [$reachSocials, $reachField] = $this->metricDefinition('reach', $combinedSocials);

            $ownStats = [
                'churchReach' => $church->socials->sum(fn ($social) => $social->latestStat?->{$reachCountField($social)} ?? 0),
                'totalAccounts' => $church->socials->count(),
                'weeklyGrowth' => $this->buildLeaderboard($reachSocials, $reachField, null)->sum('delta'),
                'personalAccounts' => $personSocials->count(),
            ];
        }

        return view('churches.show', [
            'church' => $church,
            'history' => $history,
            'scoreHistory' => $scoreHistory,
            'ownStats' => $ownStats,
        ]);
    }

    public function showInstitution(Institution $institution)
    {
        $institution->load(['socials' => fn ($query) => $query->where('is_active', true)->with('latestStat')]);

        $history = $institution->socials->mapWithKeys(
            fn ($social) => [$social->id => $social->stats()->limit(30)->get()]
        );

        $scoreHistory = $this->growthScoreHistory($institution->socials);

        // Only the admin_institusi actually bound to THIS institution gets these — same
        // reasoning as churches.show's $ownStats (see show() above), minus the personal-accounts
        // card, which has no institution-level equivalent.
        $ownStats = null;

        if (auth()->user()->role?->level() === 'institusi' && auth()->user()->institution_id === $institution->id) {
            $reachCountField = fn ($social) => $social->platform === SocialPlatform::YouTube ? 'subscribers_count' : 'followers_count';

            [$reachSocials, $reachField] = $this->metricDefinition('reach', $institution->socials);

            $ownStats = [
                'reach' => $institution->socials->sum(fn ($social) => $social->latestStat?->{$reachCountField($social)} ?? 0),
                'totalAccounts' => $institution->socials->count(),
                'weeklyGrowth' => $this->buildLeaderboard($reachSocials, $reachField, null)->sum('delta'),
            ];
        }

        return view('institutions.show', [
            'institution' => $institution,
            'history' => $history,
            'scoreHistory' => $scoreHistory,
            'ownStats' => $ownStats,
        ]);
    }

    /**
     * Same shape as show()/showInstitution() — no $ownStats special case, since Union/Conference
     * have no "this is my own single entity" viewer concept the way a gereja-level or
     * admin_institusi viewer does (they manage their whole region via Kelola Akun instead).
     */
    public function showUnion(Union $union)
    {
        $union->load(['socials' => fn ($query) => $query->where('is_active', true)->with('latestStat')]);

        $history = $union->socials->mapWithKeys(
            fn ($social) => [$social->id => $social->stats()->limit(30)->get()]
        );

        $scoreHistory = $this->growthScoreHistory($union->socials);

        return view('organizations.show', [
            'organization' => $union,
            'history' => $history,
            'scoreHistory' => $scoreHistory,
        ]);
    }

    /** Same as showUnion(), for Conference instead of Union. */
    public function showConference(Conference $conference)
    {
        $conference->load(['socials' => fn ($query) => $query->where('is_active', true)->with('latestStat')]);

        $history = $conference->socials->mapWithKeys(
            fn ($social) => [$social->id => $social->stats()->limit(30)->get()]
        );

        $scoreHistory = $this->growthScoreHistory($conference->socials);

        return view('organizations.show', [
            'organization' => $conference,
            'history' => $history,
            'scoreHistory' => $scoreHistory,
        ]);
    }
}

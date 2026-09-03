<?php

namespace App\Http\Controllers;

use App\Enums\SocialPlatform;
use App\Http\Controllers\Concerns\BuildsLeaderboards;
use App\Models\AppSetting;
use App\Models\Church;
use App\Models\ChurchSocial;
use App\Models\ChurchStat;
use App\Models\Conference;
use App\Models\Division;
use App\Models\Hashtag;
use App\Models\HashtagPost;
use App\Models\Institution;
use App\Models\Person;
use App\Models\Union;
use App\Services\GeoBoundaryMatcher;
use App\Support\ComparisonScope;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
            ->with(['socials' => fn ($query) => $query->where('is_active', true)->with('latestStat'), 'conference.union.division'])
            ->orderBy('name')
            ->get();

        $allSocials = $churches->flatMap->socials;

        $reachCountField = fn ($social) => $social->platform === SocialPlatform::YouTube ? 'subscribers_count' : 'followers_count';

        $totalReachChurch = $allSocials->sum(fn ($social) => $social->latestStat?->{$reachCountField($social)} ?? 0);

        $mapChurchSource = $isGerejaLevel
            ? Church::query()->where('is_active', true)->with(['socials' => fn ($query) => $query->where('is_active', true)->with('latestStat'), 'conference.union.division'])->get()
            : $churches;

        // One batched query instead of ChurchPolicy::update()'s own per-church visibleTo() query
        // run once per map pin — its ability check is otherwise identical (a read-only role can
        // never edit, full stop), so replicating that half in-memory keeps this to O(1) queries.
        $editableChurchIds = (auth()->user()->role === null || auth()->user()->role->isReadOnly())
            ? []
            : Church::whereIn('id', $mapChurchSource->pluck('id'))->visibleTo(auth()->user())->pluck('id')->all();

        // Same composite % growth score the Top 5/Bottom 5 cards above use (see
        // growthScoreRows()'s own doc comment) — scoped the same way as $mapChurchSource itself,
        // so a gereja-level viewer's unscoped map isn't scored against their own narrower
        // Daerah-only figure. Missing entirely (rather than 0) for a church with under 2 weeks
        // of tracking — the heat map layer skips those instead of treating "no data yet" as
        // "no growth".
        $churchGrowthScores = $this->growthScoreRows(scoped: ! $isGerejaLevel)->keyBy(fn ($row) => $row['church']->id);

        $mapChurches = $mapChurchSource->filter(fn ($church) => $church->latitude !== null && $church->longitude !== null)
            ->map(fn ($church) => [
                'name' => $church->name,
                'city' => $church->city,
                'url' => route('churches.show', $church),
                // Only offered to whoever can actually fix it — the growth map's own dots link
                // here so a wrongly-placed pin can be corrected right from the map instead of
                // hunting the church down in Kelola Akun first.
                'editUrl' => in_array($church->id, $editableChurchIds, true) ? route('churches.edit', $church) : null,
                'lat' => $church->latitude,
                'lng' => $church->longitude,
                'reach' => $church->socials->sum(fn ($social) => $social->latestStat?->{$reachCountField($social)} ?? 0),
                'growthScore' => $churchGrowthScores->get($church->id)['score'] ?? null,
            ])
            ->values();

        $unmappedCount = $mapChurchSource->count() - $mapChurches->count();

        $people = $this->analyticsPersonScope(Person::query()->where('is_active', true))
            ->with(['socials' => fn ($query) => $query->where('is_active', true)->with('latestStat'), 'conference.union.division', 'union.division'])
            ->get();

        $personalSocials = $people->flatMap->socials;

        $totalReachPersonal = $personalSocials->sum(fn ($social) => $social->latestStat?->{$reachCountField($social)} ?? 0);

        $mapPeopleSource = $isGerejaLevel
            ? Person::query()->where('is_active', true)->with(['socials' => fn ($query) => $query->where('is_active', true)->with('latestStat'), 'conference.union.division', 'union.division'])->get()
            : $people;

        $personGrowthScores = $this->growthScoreRowsPersonal(scoped: ! $isGerejaLevel)->keyBy(fn ($row) => $row['person']->id);

        $mapPeople = $mapPeopleSource->filter(fn ($person) => $person->latitude !== null && $person->longitude !== null)
            ->map(fn ($person) => [
                'name' => $person->name,
                'city' => $person->city,
                'url' => route('people.show', $person),
                'lat' => $person->latitude,
                'lng' => $person->longitude,
                'reach' => $person->socials->sum(fn ($social) => $social->latestStat?->{$reachCountField($social)} ?? 0),
                'growthScore' => $personGrowthScores->get($person->id)['score'] ?? null,
            ])
            ->values();

        $unmappedPeopleCount = $mapPeopleSource->count() - $mapPeople->count();

        $institutions = $this->analyticsInstitutionScope(Institution::query()->where('is_active', true))
            ->with(['socials' => fn ($query) => $query->where('is_active', true)->with('latestStat'), 'conference.union.division', 'union.division'])
            ->get();

        $institutionSocials = $institutions->flatMap->socials;

        $totalReachInstitution = $institutionSocials->sum(fn ($social) => $social->latestStat?->{$reachCountField($social)} ?? 0);

        $mapInstitutionSource = $isGerejaLevel
            ? Institution::query()->where('is_active', true)->with(['socials' => fn ($query) => $query->where('is_active', true)->with('latestStat'), 'conference.union.division', 'union.division'])->get()
            : $institutions;

        $institutionGrowthScores = $this->growthScoreRowsInstitution(scoped: ! $isGerejaLevel)->keyBy(fn ($row) => $row['institution']->id);

        $mapInstitutions = $mapInstitutionSource->filter(fn ($institution) => $institution->latitude !== null && $institution->longitude !== null)
            ->map(fn ($institution) => [
                'name' => $institution->name,
                'city' => $institution->city,
                'url' => route('institutions.show', $institution),
                'lat' => $institution->latitude,
                'lng' => $institution->longitude,
                'reach' => $institution->socials->sum(fn ($social) => $social->latestStat?->{$reachCountField($social)} ?? 0),
                'growthScore' => $institutionGrowthScores->get($institution->id)['score'] ?? null,
            ])
            ->values();

        $unmappedInstitutionsCount = $mapInstitutionSource->count() - $mapInstitutions->count();

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
        $enabledPlatformValues = AppSetting::current()->enabledPlatformValues();
        $enabledPlatformCases = collect(SocialPlatform::cases())->filter(fn ($p) => in_array($p->value, $enabledPlatformValues, true));

        $platformScoreSocials = $this->activeSocials(scoped: ! $isGerejaLevel);
        $platformScoreRows = $this->growthScoreRowsByPlatform($platformScoreSocials);
        $missingPlatformScoreRows = $enabledPlatformCases
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
        $platformLabels = AppSetting::current()->enabledPlatformLabels();
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

        // Short scope label — an array of ['level' => "Level Nasional", 'value' => "Uni A, Uni
        // B"] rather than one pre-joined string, so the view can print it as two lines (level,
        // then the actual region names) per the user's explicit call — 'value' is null for
        // Global, which has no particular region name of its own to show on a second line.
        // Shown once, at the very top of the page, for every section on this dashboard that
        // shares $allSocials/$institutionSocials/$personalSocials's own scoping — Ringkasan,
        // Pertumbuhan, Total Akun, Jangkauan. A gereja-level viewer gets the Daerah label since
        // that's the actual breadth of those collections (see analyticsChurchScope()), not their
        // single church.
        $scopeUser = auth()->user();
        $scopeConference = $scopeUser->conference ?? $scopeUser->church?->conference;
        $regionScopeLabel = match (true) {
            // A plain member (role === null) is scoped no wider than their own Daerah/Uni here
            // too now (see analyticsChurchScope()) — labeling it "Global" while $allSocials etc.
            // actually only cover their own Wilayah would be misleading, so this mirrors that
            // same personalConferenceId()/personalUnionId() fallback instead of the old
            // unconditional Global label.
            $scopeUser->role === null && $this->personalConferenceId()
                => ['level' => __('goals.level_daerah'), 'value' => $scopeUser->person?->conference?->name ?? '—'],
            $scopeUser->role === null && $this->personalUnionId()
                => ['level' => __('goals.level_uni'), 'value' => $scopeUser->person?->union?->name ?? '—'],
            $scopeUser->role === null
                => ['level' => __('goals.level_daerah'), 'value' => '—'],
            ($scopeUser->role?->hasGlobalAccess() ?? false) || $scopeUser->role?->level() === 'global'
                => ['level' => __('goals.level_global'), 'value' => null],
            $scopeUser->role?->level() === 'nasional'
                => ['level' => __('goals.level_nasional'), 'value' => $scopeUser->assignedUnions()->pluck('name')->implode(', ') ?: '—'],
            $scopeUser->role?->level() === 'divisi'
                => ['level' => __('goals.level_divisi'), 'value' => $scopeUser->division?->name ?? '—'],
            $scopeUser->role?->level() === 'uni'
                => ['level' => __('goals.level_uni'), 'value' => $scopeUser->union?->name ?? '—'],
            $scopeUser->role?->level() === 'daerah' || $isGerejaLevel
                => ['level' => __('goals.level_daerah'), 'value' => $scopeConference?->name ?? '—'],
            $scopeUser->role?->level() === 'institusi'
                => ['level' => __('dashboard.level_institusi'), 'value' => $scopeUser->institution?->name ?? '—'],
            default => ['level' => __('goals.level_global'), 'value' => null],
        };

        // Peta (and, elsewhere in this method, Skor Performa Platform) stay fully unscoped for a
        // gereja-level viewer — see the block comment at the top of this method — so its header
        // needs "Global" instead of $regionScopeLabel's Daerah label for that one role; every
        // other role sees the same breadth on both, so the label is identical — the view only
        // ever prints $mapScopeLabel a second time when it actually differs from
        // $regionScopeLabel, per the user's explicit call not to repeat the same label twice.
        $mapScopeLabel = $isGerejaLevel ? ['level' => __('goals.level_global'), 'value' => null] : $regionScopeLabel;

        // Reach-by-category pie chart — replaces the three separate reach stat-cards (their raw
        // numbers now ride along as each row's "detail" line) with a single visualization of the
        // church/institution/personal/organisasi totals as a share of combined reach, per the
        // user's explicit call. Church/institution/personal colors match the map's own
        // per-category marker colors above (buildClusterGroup() in the inline map script below)
        // for visual consistency across the dashboard; organisasi's green has no map equivalent
        // to match (the map colors Union/Daerah per-region, not as one single category) so it's
        // a new 4th hue — validated together via the dataviz skill's validate_palette.js, passes
        // clean in both light and dark with no per-mode adjustment needed.
        $totalReachOrganisasi = $organizationSocials->sum(fn ($social) => $social->latestStat?->{$reachCountField($social)} ?? 0);
        $totalReachAll = $totalReachChurch + $totalReachInstitution + $totalReachPersonal + $totalReachOrganisasi;
        $reachPercent = fn ($value) => $totalReachAll > 0 ? round($value / $totalReachAll * 100, 1) : 0;
        $reachByOwnerType = [
            ['label' => __('common.church'), 'value' => $reachPercent($totalReachChurch), 'detail' => number_format($totalReachChurch), 'color' => '#2563eb'],
            ['label' => __('common.institution'), 'value' => $reachPercent($totalReachInstitution), 'detail' => number_format($totalReachInstitution), 'color' => '#d97706'],
            ['label' => __('common.personal'), 'value' => $reachPercent($totalReachPersonal), 'detail' => number_format($totalReachPersonal), 'color' => '#7c3aed'],
            ['label' => __('dashboard.owner_type_organisasi'), 'value' => $reachPercent($totalReachOrganisasi), 'detail' => number_format($totalReachOrganisasi), 'color' => '#059669'],
        ];
        $distributionChannels = $enabledPlatformCases->map(function ($platform) use ($allPlatformSocials, $reachCountField, $platformLabels) {
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

        // Global, not scoped to the viewer — same reasoning as hashtagComparisonData(): hashtag
        // posts have no owner/region in this system, so every viewer sees the same numbers.
        $topHashtagPost = HashtagPost::query()
            ->selectRaw('hashtag_id, COUNT(*) as total')
            ->groupBy('hashtag_id')
            ->orderByDesc('total')
            ->with('hashtag')
            ->first();

        $hashtagSummary = [
            'total' => HashtagPost::count(),
            'byPlatform' => HashtagPost::selectRaw('platform, COUNT(*) as total')->groupBy('platform')->pluck('total', 'platform'),
            'topHashtag' => $topHashtagPost?->hashtag,
            'topHashtagCount' => $topHashtagPost?->total ?? 0,
        ];

        $mapAllItems = $mapChurches->concat($mapPeople)->concat($mapInstitutions);

        // Experimental — an alternative to the dot-based growth map above, per the user's own
        // "untuk pembanding" framing: colors each Southeast Asian province/state (not each
        // individual point) by the AVERAGE growth score of whatever's inside it, matched via
        // GeoBoundaryMatcher (see that class's own doc comment for the boundary data source).
        // Kept as a fully separate tab rather than replacing the dot map — safe to remove
        // outright later if this turns out not to earn its keep.
        $boundaryMatcher = new GeoBoundaryMatcher;
        $regionScores = [];
        foreach ($mapAllItems->whereNotNull('growthScore') as $item) {
            $region = $boundaryMatcher->regionFor($item['lat'], $item['lng']);

            if ($region === null) {
                continue;
            }

            // trim(): geoBoundaries' own shapeName field carries a stray trailing tab on at
            // least one entry ("Hà Nội\t") — confirmed while testing this against real
            // coordinates — normalized here once rather than wherever this key gets read.
            $key = $region['country'].'|'.trim($region['name']);
            $regionScores[$key]['name'] ??= trim($region['name']);
            $regionScores[$key]['country'] ??= $region['country'];
            $regionScores[$key]['scores'][] = $item['growthScore'];
        }

        $mapRegionGrowth = collect($regionScores)->map(fn (array $r) => [
            'name' => $r['name'],
            'country' => $r['country'],
            'score' => round(array_sum($r['scores']) / count($r['scores']), 2),
            'count' => count($r['scores']),
        ])->values();

        return view('churches.index', [
            'mapGrowthDataCount' => $mapAllItems->whereNotNull('growthScore')->count(),
            'mapNoGrowthDataCount' => $mapAllItems->whereNull('growthScore')->count(),
            'mapRegionGrowth' => $mapRegionGrowth,
            'hashtagSummary' => $hashtagSummary,
            'totalChurches' => $churches->count(),
            'totalPeople' => $people->count(),
            'totalInstitutions' => $institutions->count(),
            'weeklyGrowth' => $weeklyGrowth,
            'mapChurches' => $mapChurches,
            'unmappedCount' => $unmappedCount,
            'mapPeople' => $mapPeople,
            'unmappedPeopleCount' => $unmappedPeopleCount,
            'mapInstitutions' => $mapInstitutions,
            'unmappedInstitutionsCount' => $unmappedInstitutionsCount,
            'topGrowthScores' => $topGrowthScores,
            'bottomGrowthScores' => $bottomGrowthScores,
            'platformScoreRows' => $platformScoreRows,
            'platformLabels' => $platformLabels,
            'goalRows' => $goalRows,
            'distributionChannels' => $distributionChannels,
            'regionScopeLabel' => $regionScopeLabel,
            'totalSocialAccounts' => $totalSocialAccounts,
            'accountsByOwnerType' => $accountsByOwnerType,
            'mapScopeLabel' => $mapScopeLabel,
            'reachByOwnerType' => $reachByOwnerType,
        ]);
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

    /**
     * "Akun Manual" — the mirror image of autoFetchAccounts() above (is_auto_fetch = false
     * instead of true), so an admin can audit every manually-entered account's last update the
     * same way they audit automatic ones.
     */
    public function manualAccounts()
    {
        $socials = $this->manualAccountsQuery()
            ->with(['church', 'person', 'institution', 'union', 'conference'])
            ->orderByRaw('last_fetched_at IS NULL DESC')
            ->orderBy('last_fetched_at')
            ->get();

        return view('churches.manual-accounts', ['socials' => $socials]);
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
            'showDivisionHeader' => $this->showsDivisionTier(),
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
            'showDivisionHeader' => $this->showsDivisionTier(),
            'isUniView' => $isUniView,
        ]);
    }

    public function personalPlatformComparison(Request $request, string $platform = 'semua')
    {
        $platformLabels = ['semua' => 'Semua'] + AppSetting::current()->enabledPlatformLabels();
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
            'showDivisionHeader' => $this->showsDivisionTier(),
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
            'showDivisionHeader' => $this->showsDivisionTier(),
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
            'showDivisionHeader' => $this->showsDivisionTier(),
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
            'showDivisionHeader' => $this->showsDivisionTier(),
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
            'showDivisionHeader' => $this->showsDivisionTier(),
            'isUniView' => $isUniView,
        ]);
    }

    public function organizationPlatformComparison(Request $request, string $platform = 'semua')
    {
        $platformLabels = ['semua' => 'Semua'] + AppSetting::current()->enabledPlatformLabels();
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
            'showDivisionHeader' => $this->showsDivisionTier(),
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
        $platformLabels = ['semua' => 'Semua'] + AppSetting::current()->enabledPlatformLabels();
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
            'showDivisionHeader' => $this->showsDivisionTier(),
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
            ->with(['socials' => fn ($query) => $query->where('is_active', true)->with('latestStat'), 'conference.union.division'])
            ->orderBy('name')
            ->get()
            ->filter($matchesRegionFilter)
            ->values();

        $selectedChurchId = $request->query('church_id');

        // Gereja-tab-only: a church-owned ChurchSocial's category is always either 'gereja' or
        // 'umum' (ChurchSocialController's own validation forces every other owner type to a
        // single fixed category), so this filter is meaningful only here — not threaded into
        // the Personal/Institusi/Organisasi tabs below.
        $selectedCategory = $request->query('category');

        $growthOverTimeChurch = $this->growthOverTime('church_id', 'churches', $selectedChurchId, $selectedPlatform, $churches->pluck('id'), $selectedStartDate, $selectedEndDate, $selectedCategory);

        $filteredChurches = $churches
            ->when($selectedChurchId, fn ($collection) => $collection->where('id', (int) $selectedChurchId))
            ->when($selectedPlatform, fn ($collection) => $collection->filter(
                fn ($church) => $church->socials->contains(fn ($social) => $social->platform->value === $selectedPlatform)
            ))
            ->when($selectedCategory, fn ($collection) => $collection->filter(
                fn ($church) => $church->socials->contains(fn ($social) => $social->category->value === $selectedCategory)
            ));

        $people = $this->analyticsPersonScope(Person::query()->where('is_active', true))
            ->with(['socials' => fn ($query) => $query->where('is_active', true)->with('latestStat'), 'union.division', 'conference.union.division'])
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
            ->with(['socials' => fn ($query) => $query->where('is_active', true)->with('latestStat'), 'union.division', 'conference.union.division'])
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

        // Organisasi tab: Divisi-, Union-, and Conference-owned accounts shown together as one
        // flat list (each row is a Division, Union, or Conference model), grouped Divisi > Uni >
        // Daerah the same way as the other three tabs — a Divisi row sits at its own top tier, a
        // Union row sits at the "uni-level" tier under its Divisi, a Conference row at its own
        // Daerah tier under its parent Union (see BuildsLeaderboards::regionEntityDivision()/
        // regionEntityUnion()/regionEntityConference()).
        $visibleDivisions = $this->analyticsDivisionScope(Division::query()->where('is_active', true))
            ->with(['socials' => fn ($query) => $query->where('is_active', true)->with('latestStat')])
            ->orderBy('name')
            ->get();

        $visibleUnions = $this->analyticsUnionScope(Union::query()->where('is_active', true))
            ->with(['socials' => fn ($query) => $query->where('is_active', true)->with('latestStat'), 'division'])
            ->orderBy('name')
            ->get();

        $visibleConferences = $this->analyticsConferenceScope(Conference::query()->where('is_active', true))
            ->with(['socials' => fn ($query) => $query->where('is_active', true)->with('latestStat'), 'union.division'])
            ->orderBy('name')
            ->get();

        $organizations = $visibleDivisions->concat($visibleUnions)->concat($visibleConferences)
            ->filter($matchesRegionFilter)
            ->values();

        $selectedOrganizationKey = $request->query('organization_id');
        [$selectedOrgType, $selectedOrgId] = $this->parseOrganizationKey($selectedOrganizationKey);

        $growthOverTimeOrganization = $this->growthOverTimeOrganization(
            $selectedOrganizationKey, $selectedPlatform, $visibleUnions->pluck('id'), $visibleConferences->pluck('id'), $visibleDivisions->pluck('id'), $selectedStartDate, $selectedEndDate
        );

        $filteredOrganizations = $organizations
            ->when($selectedOrganizationKey, fn ($collection) => $collection->filter(fn ($org) => match ($selectedOrgType) {
                'division' => $org instanceof Division && (string) $org->id === (string) $selectedOrgId,
                'union' => $org instanceof Union && (string) $org->id === (string) $selectedOrgId,
                'conference' => $org instanceof Conference && (string) $org->id === (string) $selectedOrgId,
                default => true,
            }))
            ->when($selectedPlatform, fn ($collection) => $collection->filter(
                fn ($org) => $org->socials->contains(fn ($social) => $social->platform->value === $selectedPlatform)
            ));

        // The "Hastag" tab's own filter uses hashtag_platform (not platform) so picking a
        // platform there doesn't also filter the other four tabs' shared platform query param —
        // see hashtagComparisonData()'s doc comment.
        $hashtagData = $this->hashtagComparisonData($request->query('hashtag'), $request->query('hashtag_platform'));

        // "Dapatkan Data Terbaru" — moved here from the dashboard, per the user's explicit call;
        // same can:trigger-refresh gate and ChurchRefreshController::all() target as before, just
        // relocated. max() is a raw aggregate query — it returns the column's raw DB value, not
        // run through ChurchSocial's own 'last_fetched_at' => 'datetime' cast, so it needs
        // parsing into a Carbon instance before the view can call translatedFormat() on it.
        $lastFetchedAtRaw = ChurchSocial::where('is_active', true)->visibleTo($user)->max('last_fetched_at');
        $lastFetchedAt = $lastFetchedAtRaw ? Carbon::parse($lastFetchedAtRaw) : null;

        // Matches ChurchRefreshController::all()'s own query exactly, so the confirm dialog's
        // count reflects the true number of accounts that button is about to refresh.
        $totalRefreshableSocials = ChurchSocial::where('is_active', true)
            ->where('is_auto_fetch', true)
            ->ownerActive()
            ->visibleTo($user)
            ->count();

        return view('churches.analytics', [
            'hashtagData' => $hashtagData,
            'lastFetchedAt' => $lastFetchedAt,
            'totalRefreshableSocials' => $totalRefreshableSocials,
            // A plain member (role === null) with no Daerah/Uni set at all sees zero rows on
            // every tab of this page (see analyticsChurchScope() and friends) — without this,
            // that just looks like "there's no data" rather than "you haven't completed
            // Wilayah yet", per the user's explicit call for a notice pointing them to fix it.
            'noPersonalRegion' => $user->role === null && ! $this->personalConferenceId() && ! $this->personalUnionId(),
            'churches' => $churches,
            'filteredChurches' => $filteredChurches,
            'growthOverTime' => $growthOverTimeChurch,
            'selectedChurchId' => $selectedChurchId,
            'selectedCategory' => $selectedCategory,
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

    /** Same guard as validDateOrNull(), for a <input type="datetime-local"> value ("Y-m-d\TH:i"). */
    private function validDatetimeOrNull(?string $datetime): ?Carbon
    {
        if (! $datetime || ! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $datetime)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d\TH:i', $datetime);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Weekly aggregate stats (reach/views/likes/posts) for church-, person-, or institution-owned
     * accounts. $ownerColumn is 'church_id', 'person_id', or 'institution_id' on church_socials;
     * $ownerTable is the matching owner table ('churches', 'people', or 'institutions') — both
     * are internal constants, never user input. $visibleIds restricts to IDs the viewing user's
     * role/scope may see (pre-resolved via Church::visibleTo()/Person::visibleTo()/
     * Institution::visibleTo(), rather than duplicating that hierarchy SQL here).
     */
    private function growthOverTime(string $ownerColumn, string $ownerTable, ?string $ownerId, ?string $platform, Collection $visibleIds, ?string $startDate = null, ?string $endDate = null, ?string $category = null)
    {
        return ChurchStat::query()
            ->join('church_socials', 'church_socials.id', '=', 'church_stats.church_social_id')
            ->join($ownerTable, "{$ownerTable}.id", '=', "church_socials.{$ownerColumn}")
            ->where('church_socials.is_active', true)
            // A raw join by table name bypasses ChurchSocial's enabledPlatform global
            // scope entirely (this query is rooted in ChurchStat) — needs its own filter.
            ->whereIn('church_socials.platform', AppSetting::current()->enabledPlatformValues())
            ->where("{$ownerTable}.is_active", true)
            ->whereIn("{$ownerTable}.id", $visibleIds)
            ->when($ownerId, fn ($query) => $query->where("{$ownerTable}.id", $ownerId))
            ->when($platform, fn ($query) => $query->where('church_socials.platform', $platform))
            // Only the Gereja tab ever passes this — category only varies (gereja/umum) on
            // church-owned socials; personal/institution/organisasi rows are always forced to a
            // single fixed category by ChurchSocialController's own validation, so filtering by
            // it there would be meaningless.
            ->when($category, fn ($query) => $query->where('church_socials.category', $category))
            ->when($startDate, fn ($query) => $query->whereDate('church_stats.recorded_at', '>=', $startDate))
            ->when($endDate, fn ($query) => $query->whereDate('church_stats.recorded_at', '<=', $endDate))
            ->selectRaw(
                "church_stats.recorded_at as recorded_at,
                SUM(CASE WHEN church_socials.platform = 'youtube' THEN church_stats.subscribers_count ELSE church_stats.followers_count END) as total_reach,
                SUM(CASE
                    WHEN church_socials.platform = 'youtube' THEN church_stats.views_count
                    WHEN church_socials.platform = 'instagram' THEN church_stats.recent_reels_views
                    WHEN church_socials.platform = 'tiktok' THEN church_stats.recent_video_plays
                    ELSE 0
                END) as total_views,
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
    private function growthOverTimeOrganization(?string $selectedOrganizationKey, ?string $platform, Collection $visibleUnionIds, Collection $visibleConferenceIds, Collection $visibleDivisionIds, ?string $startDate = null, ?string $endDate = null)
    {
        [$selectedType, $selectedId] = $this->parseOrganizationKey($selectedOrganizationKey);

        return ChurchStat::query()
            ->join('church_socials', 'church_socials.id', '=', 'church_stats.church_social_id')
            ->where('church_socials.is_active', true)
            // See growthOverTime()'s same comment — a raw join bypasses the model scope.
            ->whereIn('church_socials.platform', AppSetting::current()->enabledPlatformValues())
            ->where(function ($query) use ($visibleUnionIds, $visibleConferenceIds, $visibleDivisionIds) {
                $query->whereIn('church_socials.union_id', $visibleUnionIds)
                    ->orWhereIn('church_socials.conference_id', $visibleConferenceIds)
                    ->orWhereIn('church_socials.division_id', $visibleDivisionIds);
            })
            ->when($selectedType === 'union', fn ($query) => $query->where('church_socials.union_id', $selectedId))
            ->when($selectedType === 'conference', fn ($query) => $query->where('church_socials.conference_id', $selectedId))
            ->when($selectedType === 'division', fn ($query) => $query->where('church_socials.division_id', $selectedId))
            ->when($platform, fn ($query) => $query->where('church_socials.platform', $platform))
            ->when($startDate, fn ($query) => $query->whereDate('church_stats.recorded_at', '>=', $startDate))
            ->when($endDate, fn ($query) => $query->whereDate('church_stats.recorded_at', '<=', $endDate))
            ->selectRaw(
                "church_stats.recorded_at as recorded_at,
                SUM(CASE WHEN church_socials.platform = 'youtube' THEN church_stats.subscribers_count ELSE church_stats.followers_count END) as total_reach,
                SUM(CASE
                    WHEN church_socials.platform = 'youtube' THEN church_stats.views_count
                    WHEN church_socials.platform = 'instagram' THEN church_stats.recent_reels_views
                    WHEN church_socials.platform = 'tiktok' THEN church_stats.recent_video_plays
                    ELSE 0
                END) as total_views,
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
            ->with(['socials' => $socialsFilter, 'conference.union.division'])
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
            ->with(['socials' => $socialsFilter, 'conference.union.division', 'union.division'])
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
            ->with(['socials' => $socialsFilter, 'conference.union.division', 'union.division'])
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
            ->with(['socials' => $socialsFilter, 'division'])
            ->orderBy('name', $sortOrganisasi === 'name_desc' ? 'desc' : 'asc')
            ->get();

        $conferences = Conference::query()
            ->where('is_active', true)
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->tap($applyConferenceRegionFilter)
            ->when($hideEmptyOrganizations, fn ($q) => $q->whereHas('socials', $socialsFilter))
            ->with(['socials' => $socialsFilter, 'union.division'])
            ->orderBy('name', $sortOrganisasi === 'name_desc' ? 'desc' : 'asc')
            ->get();

        // Division has no union_id/conference_id column of its own, so — unlike Union/Conference
        // above — it isn't affected by the union_id/conference_id region-filter picker at all:
        // every Division is always shown here regardless of the selected filter (the filter only
        // narrows within a Divisi's own Uni/Daerah descendants).
        $divisions = Division::query()
            ->where('is_active', true)
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->when($hideEmptyOrganizations, fn ($q) => $q->whereHas('socials', $socialsFilter))
            ->with(['socials' => $socialsFilter])
            ->orderBy('name', $sortOrganisasi === 'name_desc' ? 'desc' : 'asc')
            ->get();

        $organizations = $divisions->concat($unions)->concat($conferences)->values();
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

        // Church-scope-only, same as the Analytics Gereja tab's filter — see activeSocials().
        $selectedCategory = $request->query('category');

        $scoreRows = $this->growthScoreRows(category: $selectedCategory)
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
            'showDivisionHeader' => $this->showsDivisionTier(),
            'isUniView' => $isUniView,
            'selectedCategory' => $selectedCategory,
        ]);
    }

    public function leaderboard(Request $request, string $metric)
    {
        $titles = $this->leaderboardTitles();
        $metricLabels = ['reach' => 'Followers/Subscribers', 'views' => 'Views', 'likes' => 'Likes', 'posts' => 'Post / Video'];

        abort_unless(isset($titles[$metric]), 404);

        $sort = $request->query('sort') === 'value' ? 'value' : 'delta';

        // Church-scope-only, same as the Analytics Gereja tab's filter — see activeSocials().
        $selectedCategory = $request->query('category');

        $isUniView = $this->isUniView();
        $selectedUnionId = $isUniView ? (string) $request->user()->union_id : $request->query('union_id');
        $selectedConferenceId = $request->query('conference_id');
        $matchesRegionFilter = $this->matchesRegionFilter($selectedUnionId, $selectedConferenceId);
        [$unionOptions, $conferenceOptions] = $this->regionFilterOptions($selectedUnionId);

        [$socials, $field] = $this->metricDefinition($metric, $this->activeSocials(category: $selectedCategory));

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
            'showDivisionHeader' => $this->showsDivisionTier(),
            'isUniView' => $isUniView,
            'selectedCategory' => $selectedCategory,
        ]);
    }

    public function presentation()
    {
        $countField = ['youtube' => 'subscribers_count', 'instagram' => 'followers_count', 'tiktok' => 'followers_count', 'facebook' => 'followers_count', 'x' => 'followers_count'];
        $enabledPlatforms = AppSetting::current()->enabledPlatformValues();

        // Public presentation page — always shows general data, never scoped to whoever
        // (if anyone) happens to be logged in while it's displayed.
        $churches = Church::query()
            ->where('is_active', true)
            ->with(['socials' => fn ($q) => $q->where('is_active', true)->with('latestStat'), 'conference.union'])
            ->get();

        $rows = $churches->map(function ($church) use ($countField, $enabledPlatforms) {
            $byPlatform = collect($enabledPlatforms)->mapWithKeys(
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
        $countField = ['youtube' => 'subscribers_count', 'instagram' => 'followers_count', 'tiktok' => 'followers_count', 'facebook' => 'followers_count', 'x' => 'followers_count'];
        $enabledPlatforms = AppSetting::current()->enabledPlatformValues();

        // Public presentation page — always unscoped, see presentation() above.
        $people = Person::query()
            ->where('is_active', true)
            ->with(['socials' => fn ($q) => $q->where('is_active', true)->with('latestStat'), 'conference.union', 'union'])
            ->get();

        $rows = $people->map(function ($person) use ($countField, $enabledPlatforms) {
            $byPlatform = collect($enabledPlatforms)->mapWithKeys(
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
        $platformLabels = ['semua' => 'Semua'] + AppSetting::current()->enabledPlatformLabels();
        $metricLabels = ['reach' => 'Followers/Subscribers', 'views' => 'Views', 'likes' => 'Likes', 'posts' => 'Post / Video'];
        $metricPlatforms = $this->metricPlatforms();

        abort_unless(isset($platformLabels[$platform]), 404);

        $sort = $request->query('sort') === 'value' ? 'value' : 'delta';
        $metric = $request->query('metric');

        // Church-scope-only, same as the Analytics Gereja tab's filter — see activeSocials().
        $selectedCategory = $request->query('category');

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
            'showDivisionHeader' => $this->showsDivisionTier(),
            'isUniView' => $isUniView,
            'selectedCategory' => $selectedCategory,
        ];

        // No metric picked: show every metric that applies to this platform together on one page.
        if (! $metric) {
            $applicableMetrics = collect($metricPlatforms)
                ->filter(fn ($platforms) => in_array($platform, $platforms, true))
                ->keys();

            $sections = $applicableMetrics->mapWithKeys(fn ($m) => [
                $m => $this->metricComparisonRows($m, $platform, $sort, $selectedCategory)
                    ->filter(fn ($row) => $matchesRegionFilter($row['church']))
                    ->values(),
            ]);

            // "Semua" compares platforms against each other, so it gets a composite score
            // card instead of per-metric church leaderboards (which don't apply platform-vs-platform).
            $platformScoreRows = $platform === 'semua' ? $this->growthScoreRowsByPlatform($this->activeSocials(category: $selectedCategory)) : null;

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
                'category' => $selectedCategory,
            ]));
        }

        $rows = $this->metricComparisonRows($metric, $platform, $sort, $selectedCategory)
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

    /**
     * Unlike metricComparison()/platformComparison() above, hashtag posts have no owner in this
     * system — they're external posts found by keyword search, not accounts registered under a
     * church/person/institution/union — so there's no region/category filter to apply and the
     * SAME global data renders on every scope's tab. These four thin methods exist only so the
     * page's "back to Analytics" link (via $scope->analyticsUrl()) returns to whichever tab the
     * admin came from, matching every other comparison page's convention.
     */
    public function hashtagComparison(Request $request)
    {
        return view('churches.hashtag-comparison', array_merge(
            $this->hashtagComparisonData($request->query('hashtag'), $request->query('platform')),
            ['scope' => ComparisonScope::church()],
        ));
    }

    public function personalHashtagComparison(Request $request)
    {
        return view('churches.hashtag-comparison', array_merge(
            $this->hashtagComparisonData($request->query('hashtag'), $request->query('platform')),
            ['scope' => ComparisonScope::personal()],
        ));
    }

    public function institutionHashtagComparison(Request $request)
    {
        return view('churches.hashtag-comparison', array_merge(
            $this->hashtagComparisonData($request->query('hashtag'), $request->query('platform')),
            ['scope' => ComparisonScope::institution()],
        ));
    }

    public function organizationHashtagComparison(Request $request)
    {
        return view('churches.hashtag-comparison', array_merge(
            $this->hashtagComparisonData($request->query('hashtag'), $request->query('platform')),
            ['scope' => ComparisonScope::organization()],
        ));
    }

    /**
     * Shared by all four hashtagComparison*() methods above, and by analytics()'s "Hastag" tab
     * (which reads its own filter values from hashtag_platform/hashtag instead of platform, so
     * its filter form doesn't collide with the other four tabs' shared "platform" query param on
     * that same page). $platforms follows the app-wide platform toggle (see
     * AppSetting::enabledPlatformValues()) the same way every other analytics view does — a
     * disabled platform's accounts are excluded from every ChurchSocial query app-wide (see its
     * own enabledPlatform global scope), so they stop being fetched — and therefore stop being
     * scanned for hashtag matches — too, keeping this summary table honest.
     *
     * union_id/conference_id read directly off the request (like the Uni/Daerah filter on every
     * other tab of this same page — same shared query params, deliberately, so picking a Daerah
     * once carries over whichever tab you're on) rather than taken as parameters, since none of
     * the 5 call sites otherwise had any reason to know about them — per the user's explicit call
     * for a Uni/Daerah filter on the Hastag tab "like the other tabs" have. This is a FILTER, not
     * a scope: hashtag posts stay global/unowned by default (see the class-wide reasoning above)
     * for every viewer regardless of role; picking a Uni/Daerah here just narrows the view, same
     * as picking a platform or a specific hashtag does.
     */
    private function hashtagComparisonData(?string $selectedHashtagId, ?string $selectedPlatform): array
    {
        $platforms = AppSetting::current()->enabledPlatformValues();

        $isUniView = $this->isUniView();
        $selectedUnionId = $isUniView ? (string) auth()->user()->union_id : request()->query('union_id');
        $selectedConferenceId = request()->query('conference_id');

        // Daerah/Gereja-level viewers (and a plain member) never get a region dropdown to pick
        // from at all (see analytics-region-filter.blade.php) — fall back to their own reach so
        // they're not left seeing every region's posts by default. See
        // defaultHashtagRegionScope()'s own doc comment for why this can't just reuse
        // analyticsChurchScope() directly.
        if (! $selectedUnionId && ! $selectedConferenceId) {
            [$selectedUnionId, $selectedConferenceId] = $this->defaultHashtagRegionScope();
        }

        // A plain member with no Daerah/Uni set at all has nowhere for defaultHashtagRegionScope()
        // to fall back to either (its own [null, null] there means the same "nothing selected" as
        // Global/Nasional/Divisi's deliberate unfiltered default) — without this, such a member
        // would see every region's hashtag posts instead of the zero rows analyticsChurchScope()
        // and friends already give them on every other Analitik & Grafik tab (see noPersonalRegion
        // on analytics() for the notice pointing them at Profil Saya to fix it).
        $noPersonalRegion = auth()->user()->role === null && ! $selectedUnionId && ! $selectedConferenceId;

        [$unionOptions, $conferenceOptions] = $this->regionFilterOptions($selectedUnionId);

        $hashtags = Hashtag::where('is_active', true)->orderBy('tag')->get();

        $countsByHashtag = HashtagPost::query()
            ->tap(fn ($q) => $noPersonalRegion ? $q->whereRaw('1 = 0') : $this->applyHashtagRegionFilter($q, $selectedUnionId, $selectedConferenceId))
            ->selectRaw('hashtag_id, platform, COUNT(*) as total')
            ->groupBy('hashtag_id', 'platform')
            ->get()
            ->groupBy('hashtag_id');

        $rows = $hashtags->map(function ($hashtag) use ($countsByHashtag, $platforms) {
            $countsForHashtag = $countsByHashtag->get($hashtag->id, collect())->keyBy('platform');
            $counts = collect($platforms)->mapWithKeys(fn ($platform) => [
                $platform => (int) ($countsForHashtag[$platform]->total ?? 0),
            ]);

            return [
                'hashtag' => $hashtag,
                'counts' => $counts,
                'total' => $counts->sum(),
            ];
        });

        $grandTotalByPlatform = collect($platforms)->mapWithKeys(fn ($platform) => [
            $platform => $rows->sum(fn ($row) => $row['counts'][$platform]),
        ]);

        // Same "last updated" convention as the dashboard's own refresh-status line (see
        // index()'s $lastFetchedAt) — last_seen_at is touched every time the weekly
        // hashtags:fetch-all job re-detects a post as still up, so its max is the most recent
        // sign the hashtag summary table actually reflects a real fetch run.
        $lastUpdatedAtRaw = HashtagPost::max('last_seen_at');
        $lastUpdatedAt = $lastUpdatedAtRaw ? Carbon::parse($lastUpdatedAtRaw) : null;

        // Monitoring window (e.g. watching a coordinated hashtag launch hour by hour) — per the
        // user's explicit call, an optional posted_from/posted_to range that, when both are set,
        // switches the chart below from a per-date count to a gap-filled per-hour count within
        // exactly that window (every hour shown even if 0, not just the ones with a post — a
        // silent gap would read as "no data yet" rather than "confirmed zero"), and narrows the
        // post list to the same window. Absent, everything behaves exactly as before (per-date,
        // unbounded).
        $postedFrom = $this->validDatetimeOrNull(request()->query('posted_from'));
        $postedTo = $this->validDatetimeOrNull(request()->query('posted_to'));
        $isMonitoringWindow = $postedFrom && $postedTo && $postedFrom->lessThanOrEqualTo($postedTo);

        if ($isMonitoringWindow) {
            $countsByHour = HashtagPost::query()
                ->whereBetween('posted_at', [$postedFrom, $postedTo])
                ->when($selectedHashtagId, fn ($q) => $q->where('hashtag_id', $selectedHashtagId))
                ->when($selectedPlatform, fn ($q) => $q->where('platform', $selectedPlatform))
                ->tap(fn ($q) => $noPersonalRegion ? $q->whereRaw('1 = 0') : $this->applyHashtagRegionFilter($q, $selectedUnionId, $selectedConferenceId))
                ->selectRaw("DATE_FORMAT(posted_at, '%Y-%m-%d %H:00:00') as post_hour, COUNT(*) as total")
                ->groupBy('post_hour')
                ->pluck('total', 'post_hour');

            // $growthLabels: the full "d M, H:00" for every point, always — used for the hover
            // tooltip, which should never be ambiguous about which day a hovered hour belongs
            // to. $growthShortLabels: the hour alone — what prints on the chart's x-axis for a
            // point that isn't the first one shown for its day. $growthDateKeys: which calendar
            // day each point falls on, compared between consecutive SHOWN points (not consecutive
            // raw hours) by the chart component itself, once it knows which points survive its
            // own label-thinning — deciding that here instead, against every raw hour, risked the
            // one hour that actually starts a new day landing on a thinned-out point, silently
            // dropping the date change the moment it mattered most.
            $growthLabels = collect();
            $growthShortLabels = collect();
            $growthDateKeys = collect();
            $growthValues = collect();
            $cursor = $postedFrom->clone()->startOfHour();

            while ($cursor->lessThanOrEqualTo($postedTo)) {
                $growthLabels->push($cursor->translatedFormat('d M, H:00'));
                $growthShortLabels->push($cursor->translatedFormat('H:00'));
                $growthDateKeys->push($cursor->format('Y-m-d'));
                $growthValues->push((int) ($countsByHour[$cursor->format('Y-m-d H:00:00')] ?? 0));
                $cursor->addHour();
            }
        } else {
            // "Jumlah Post per Tanggal" — how many matching posts were actually posted on each
            // date (by posted_at, not last_seen_at). Deliberately a per-date count, not a
            // running/cumulative total — per the user's explicit call, and consistent with the
            // summary table's own "Total Sepanjang Waktu" being clearly labeled as the one
            // cumulative figure on this page.
            $postsByDay = HashtagPost::query()
                ->whereNotNull('posted_at')
                ->when($selectedHashtagId, fn ($q) => $q->where('hashtag_id', $selectedHashtagId))
                ->when($selectedPlatform, fn ($q) => $q->where('platform', $selectedPlatform))
                ->tap(fn ($q) => $noPersonalRegion ? $q->whereRaw('1 = 0') : $this->applyHashtagRegionFilter($q, $selectedUnionId, $selectedConferenceId))
                ->selectRaw('DATE(posted_at) as post_date, COUNT(*) as total')
                ->groupBy('post_date')
                ->orderBy('post_date')
                ->get();

            $growthLabels = $postsByDay->pluck('post_date')->map(fn ($d) => Carbon::parse($d)->translatedFormat('d M'));
            // No date/time split to collapse for a per-date chart (each label already IS just a
            // date) — $growthDateKeys are simply distinct per point, so the component's own
            // consecutive-shown-point comparison never treats two different dates as "the same".
            $growthShortLabels = $growthLabels;
            $growthDateKeys = $postsByDay->pluck('post_date');
            $growthValues = $postsByDay->pluck('total')->map(fn ($total) => (int) $total);
        }

        $posts = HashtagPost::query()
            ->with(['hashtag', 'churchSocial'])
            ->when($selectedHashtagId, fn ($q) => $q->where('hashtag_id', $selectedHashtagId))
            ->when($selectedPlatform, fn ($q) => $q->where('platform', $selectedPlatform))
            ->when($isMonitoringWindow, fn ($q) => $q->whereBetween('posted_at', [$postedFrom, $postedTo]))
            ->tap(fn ($q) => $noPersonalRegion ? $q->whereRaw('1 = 0') : $this->applyHashtagRegionFilter($q, $selectedUnionId, $selectedConferenceId))
            ->orderByDesc('posted_at')
            ->paginate(40, ['*'], 'hashtag_page')
            ->withQueryString();

        return [
            'hashtags' => $hashtags,
            'platforms' => $platforms,
            'lastUpdatedAt' => $lastUpdatedAt,
            'rows' => $rows,
            'grandTotal' => $rows->sum('total'),
            'grandTotalByPlatform' => $grandTotalByPlatform,
            'growthLabels' => $growthLabels,
            'growthShortLabels' => $growthShortLabels,
            'growthDateKeys' => $growthDateKeys,
            'growthValues' => $growthValues,
            'isMonitoringWindow' => $isMonitoringWindow,
            'selectedPostedFrom' => $postedFrom?->format('Y-m-d\TH:i'),
            'selectedPostedTo' => $postedTo?->format('Y-m-d\TH:i'),
            'posts' => $posts,
            'selectedHashtagId' => $selectedHashtagId,
            'selectedPlatform' => $selectedPlatform,
            'isNasionalView' => $this->isNasionalView(),
            'isUniView' => $isUniView,
            'unionOptions' => $unionOptions,
            'conferenceOptions' => $conferenceOptions,
            'selectedUnionId' => $selectedUnionId,
            'selectedConferenceId' => $selectedConferenceId,
        ];
    }

    public function show(Church $church)
    {
        $church->load(['socials' => fn ($query) => $query->where('is_active', true)->with('latestStat')]);

        $history = $church->socials->mapWithKeys(
            fn ($social) => [$social->id => $social->stats()->limit(30)->get()]
        );

        $growthScore = $this->growthScoreHistory($church->socials);

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
            'scoreHistory' => $growthScore['history'],
            'scoreMetrics' => $growthScore['metrics'],
            'scoreBreakdown' => $growthScore['breakdown'],
            'scoreSampleCount' => $growthScore['sampleCount'],
            'scoreSampleSum' => $growthScore['sampleSum'],
            'ownStats' => $ownStats,
        ]);
    }

    public function showInstitution(Institution $institution)
    {
        $institution->load(['socials' => fn ($query) => $query->where('is_active', true)->with('latestStat')]);

        $history = $institution->socials->mapWithKeys(
            fn ($social) => [$social->id => $social->stats()->limit(30)->get()]
        );

        $growthScore = $this->growthScoreHistory($institution->socials);

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
            'scoreHistory' => $growthScore['history'],
            'scoreMetrics' => $growthScore['metrics'],
            'scoreBreakdown' => $growthScore['breakdown'],
            'scoreSampleCount' => $growthScore['sampleCount'],
            'scoreSampleSum' => $growthScore['sampleSum'],
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

        $growthScore = $this->growthScoreHistory($union->socials);

        return view('organizations.show', [
            'organization' => $union,
            'history' => $history,
            'scoreHistory' => $growthScore['history'],
            'scoreMetrics' => $growthScore['metrics'],
            'scoreBreakdown' => $growthScore['breakdown'],
            'scoreSampleCount' => $growthScore['sampleCount'],
            'scoreSampleSum' => $growthScore['sampleSum'],
        ]);
    }

    /** Same as showUnion(), for Conference instead of Union. */
    public function showConference(Conference $conference)
    {
        $conference->load(['socials' => fn ($query) => $query->where('is_active', true)->with('latestStat')]);

        $history = $conference->socials->mapWithKeys(
            fn ($social) => [$social->id => $social->stats()->limit(30)->get()]
        );

        $growthScore = $this->growthScoreHistory($conference->socials);

        return view('organizations.show', [
            'organization' => $conference,
            'history' => $history,
            'scoreHistory' => $growthScore['history'],
            'scoreMetrics' => $growthScore['metrics'],
            'scoreBreakdown' => $growthScore['breakdown'],
            'scoreSampleCount' => $growthScore['sampleCount'],
            'scoreSampleSum' => $growthScore['sampleSum'],
        ]);
    }

    /** Same as showUnion(), for Division instead of Union. */
    public function showDivision(Division $division)
    {
        $division->load(['socials' => fn ($query) => $query->where('is_active', true)->with('latestStat')]);

        $history = $division->socials->mapWithKeys(
            fn ($social) => [$social->id => $social->stats()->limit(30)->get()]
        );

        $growthScore = $this->growthScoreHistory($division->socials);

        return view('organizations.show', [
            'organization' => $division,
            'history' => $history,
            'scoreHistory' => $growthScore['history'],
            'scoreMetrics' => $growthScore['metrics'],
            'scoreBreakdown' => $growthScore['breakdown'],
            'scoreSampleCount' => $growthScore['sampleCount'],
            'scoreSampleSum' => $growthScore['sampleSum'],
        ]);
    }
}

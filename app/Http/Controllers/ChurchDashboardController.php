<?php

namespace App\Http\Controllers;

use App\Enums\SocialPlatform;
use App\Http\Controllers\Concerns\BuildsLeaderboards;
use App\Models\Church;
use App\Models\ChurchStat;
use App\Models\Institution;
use App\Models\Person;
use App\Support\ComparisonScope;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

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
            ->with(['socials' => fn ($query) => $query->where('is_active', true)->with('latestStat')])
            ->orderBy('name')
            ->get();

        $allSocials = $churches->flatMap->socials;

        $reachCountField = fn ($social) => $social->platform === SocialPlatform::YouTube ? 'subscribers_count' : 'followers_count';

        $totalReachChurch = $allSocials->sum(fn ($social) => $social->latestStat?->{$reachCountField($social)} ?? 0);

        $mapChurchSource = $isGerejaLevel
            ? Church::query()->where('is_active', true)->with(['socials' => fn ($query) => $query->where('is_active', true)->with('latestStat')])->get()
            : $churches;

        $mapChurches = $mapChurchSource->filter(fn ($church) => $church->latitude !== null && $church->longitude !== null)
            ->map(fn ($church) => [
                'name' => $church->name,
                'city' => $church->city,
                'url' => route('churches.show', $church),
                'lat' => $church->latitude,
                'lng' => $church->longitude,
                'reach' => $church->socials->sum(fn ($social) => $social->latestStat?->{$reachCountField($social)} ?? 0),
            ])
            ->values();

        $unmappedCount = $mapChurchSource->count() - $mapChurches->count();

        $people = $this->analyticsPersonScope(Person::query()->where('is_active', true))
            ->with(['socials' => fn ($query) => $query->where('is_active', true)->with('latestStat')])
            ->get();

        $personalSocials = $people->flatMap->socials;

        $totalReachPersonal = $personalSocials->sum(fn ($social) => $social->latestStat?->{$reachCountField($social)} ?? 0);

        $mapPeopleSource = $isGerejaLevel
            ? Person::query()->where('is_active', true)->with(['socials' => fn ($query) => $query->where('is_active', true)->with('latestStat')])->get()
            : $people;

        $mapPeople = $mapPeopleSource->filter(fn ($person) => $person->latitude !== null && $person->longitude !== null)
            ->map(fn ($person) => [
                'name' => $person->name,
                'city' => $person->city,
                'url' => route('people.show', $person),
                'lat' => $person->latitude,
                'lng' => $person->longitude,
                'reach' => $person->socials->sum(fn ($social) => $social->latestStat?->{$reachCountField($social)} ?? 0),
            ])
            ->values();

        $unmappedPeopleCount = $mapPeopleSource->count() - $mapPeople->count();

        $institutions = $this->analyticsInstitutionScope(Institution::query()->where('is_active', true))
            ->with(['socials' => fn ($query) => $query->where('is_active', true)->with('latestStat')])
            ->get();

        $institutionSocials = $institutions->flatMap->socials;

        $totalReachInstitution = $institutionSocials->sum(fn ($social) => $social->latestStat?->{$reachCountField($social)} ?? 0);

        $mapInstitutionSource = $isGerejaLevel
            ? Institution::query()->where('is_active', true)->with(['socials' => fn ($query) => $query->where('is_active', true)->with('latestStat')])->get()
            : $institutions;

        $mapInstitutions = $mapInstitutionSource->filter(fn ($institution) => $institution->latitude !== null && $institution->longitude !== null)
            ->map(fn ($institution) => [
                'name' => $institution->name,
                'city' => $institution->city,
                'url' => route('institutions.show', $institution),
                'lat' => $institution->latitude,
                'lng' => $institution->longitude,
                'reach' => $institution->socials->sum(fn ($social) => $social->latestStat?->{$reachCountField($social)} ?? 0),
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

        $platformScoreRows = $this->growthScoreRowsByPlatform($this->activeSocials(scoped: ! $isGerejaLevel));

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
            'totalPersonalSocials' => $personalSocials->count(),
            'totalInstitutions' => $institutions->count(),
            'totalInstitutionSocials' => $institutionSocials->count(),
            'totalReachChurch' => $totalReachChurch,
            'totalReachPersonal' => $totalReachPersonal,
            'totalReachInstitution' => $totalReachInstitution,
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
            'platformLabels' => ['youtube' => 'YouTube', 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'facebook' => 'Facebook'],
        ]);
    }

    public function needsAttention()
    {
        $socials = $this->accountsNeedingAttentionQuery()
            ->with(['church', 'person'])
            ->orderByDesc('last_fetched_at')
            ->get();

        return view('churches.needs-attention', ['socials' => $socials]);
    }

    public function personalMetricComparison()
    {
        $metricLabels = ['reach' => 'Followers/Subscribers', 'views' => 'Views', 'likes' => 'Likes', 'posts' => 'Post / Video'];

        $scoreRows = $this->growthScoreRowsPersonal()->map(fn ($row) => [
            'entity' => $row['person'],
            'score' => $row['score'],
            'metrics' => $row['metrics'],
            'accountCount' => $row['accountCount'],
        ]);

        return view('churches.metric-comparison', [
            'scope' => ComparisonScope::personal(),
            'metricLabels' => $metricLabels,
            'scoreRows' => $scoreRows,
        ]);
    }

    public function personalLeaderboard(Request $request, string $metric)
    {
        $titles = $this->leaderboardTitles();
        $metricLabels = ['reach' => 'Followers/Subscribers', 'views' => 'Views', 'likes' => 'Likes', 'posts' => 'Post / Video'];

        abort_unless(isset($titles[$metric]), 404);

        $sort = $request->query('sort') === 'value' ? 'value' : 'delta';

        [$socials, $field] = $this->metricDefinition($metric, $this->activeSocialsPersonal());

        $rows = $this->buildLeaderboard($socials, $field, null, $sort);

        return view('churches.leaderboard', [
            'scope' => ComparisonScope::personal(),
            'metric' => $metric,
            'metricLabels' => $metricLabels,
            'title' => $titles[$metric]['title'],
            'subtitle' => $titles[$metric]['subtitle'],
            'sort' => $sort,
            'rows' => $rows,
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

        if (! $metric) {
            $applicableMetrics = collect($metricPlatforms)
                ->filter(fn ($platforms) => in_array($platform, $platforms, true))
                ->keys();

            $sections = $applicableMetrics->mapWithKeys(fn ($m) => [
                $m => $this->metricComparisonRowsPersonal($m, $platform, $sort),
            ]);

            $platformScoreRows = $platform === 'semua' ? $this->growthScoreRowsByPlatform($this->activeSocialsPersonal()) : null;

            return view('churches.platform-comparison-overview', [
                'scope' => ComparisonScope::personal(),
                'platform' => $platform,
                'platformLabels' => $platformLabels,
                'metricLabels' => $metricLabels,
                'sort' => $sort,
                'sections' => $sections,
                'platformScoreRows' => $platformScoreRows,
            ]);
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

        return view('churches.platform-comparison', [
            'scope' => ComparisonScope::personal(),
            'platform' => $platform,
            'platformLabels' => $platformLabels,
            'metric' => $metric,
            'metricLabels' => $metricLabels,
            'metricPlatforms' => $metricPlatforms[$metric],
            'sort' => $sort,
            'rows' => $this->metricComparisonRowsPersonal($metric, $platform, $sort),
        ]);
    }

    public function institutionMetricComparison()
    {
        $metricLabels = ['reach' => 'Followers/Subscribers', 'views' => 'Views', 'likes' => 'Likes', 'posts' => 'Post / Video'];

        $scoreRows = $this->growthScoreRowsInstitution()->map(fn ($row) => [
            'entity' => $row['institution'],
            'score' => $row['score'],
            'metrics' => $row['metrics'],
            'accountCount' => $row['accountCount'],
        ]);

        return view('churches.metric-comparison', [
            'scope' => ComparisonScope::institution(),
            'metricLabels' => $metricLabels,
            'scoreRows' => $scoreRows,
        ]);
    }

    public function institutionLeaderboard(Request $request, string $metric)
    {
        $titles = $this->leaderboardTitles();
        $metricLabels = ['reach' => 'Followers/Subscribers', 'views' => 'Views', 'likes' => 'Likes', 'posts' => 'Post / Video'];

        abort_unless(isset($titles[$metric]), 404);

        $sort = $request->query('sort') === 'value' ? 'value' : 'delta';

        [$socials, $field] = $this->metricDefinition($metric, $this->activeSocialsInstitution());

        $rows = $this->buildLeaderboard($socials, $field, null, $sort);

        return view('churches.leaderboard', [
            'scope' => ComparisonScope::institution(),
            'metric' => $metric,
            'metricLabels' => $metricLabels,
            'title' => $titles[$metric]['title'],
            'subtitle' => $titles[$metric]['subtitle'],
            'sort' => $sort,
            'rows' => $rows,
        ]);
    }

    public function institutionPlatformComparison(Request $request, string $platform = 'semua')
    {
        $platformLabels = ['semua' => 'Semua', 'youtube' => 'YouTube', 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'facebook' => 'Facebook'];
        $metricLabels = ['reach' => 'Followers/Subscribers', 'views' => 'Views', 'likes' => 'Likes', 'posts' => 'Post / Video'];
        $metricPlatforms = $this->metricPlatforms();

        abort_unless(isset($platformLabels[$platform]), 404);

        $sort = $request->query('sort') === 'value' ? 'value' : 'delta';
        $metric = $request->query('metric');

        if (! $metric) {
            $applicableMetrics = collect($metricPlatforms)
                ->filter(fn ($platforms) => in_array($platform, $platforms, true))
                ->keys();

            $sections = $applicableMetrics->mapWithKeys(fn ($m) => [
                $m => $this->metricComparisonRowsInstitution($m, $platform, $sort),
            ]);

            $platformScoreRows = $platform === 'semua' ? $this->growthScoreRowsByPlatform($this->activeSocialsInstitution()) : null;

            return view('churches.platform-comparison-overview', [
                'scope' => ComparisonScope::institution(),
                'platform' => $platform,
                'platformLabels' => $platformLabels,
                'metricLabels' => $metricLabels,
                'sort' => $sort,
                'sections' => $sections,
                'platformScoreRows' => $platformScoreRows,
            ]);
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

        return view('churches.platform-comparison', [
            'scope' => ComparisonScope::institution(),
            'platform' => $platform,
            'platformLabels' => $platformLabels,
            'metric' => $metric,
            'metricLabels' => $metricLabels,
            'metricPlatforms' => $metricPlatforms[$metric],
            'sort' => $sort,
            'rows' => $this->metricComparisonRowsInstitution($metric, $platform, $sort),
        ]);
    }

    public function analytics(Request $request)
    {
        $selectedPlatform = $request->query('platform');

        $churches = $this->analyticsChurchScope(Church::query()->where('is_active', true))
            ->with(['socials' => fn ($query) => $query->where('is_active', true)->with('latestStat')])
            ->orderBy('name')
            ->get();

        $selectedChurchId = $request->query('church_id');

        $growthOverTimeChurch = $this->growthOverTime('church_id', 'churches', $selectedChurchId, $selectedPlatform, $churches->pluck('id'));

        $filteredChurches = $churches
            ->when($selectedChurchId, fn ($collection) => $collection->where('id', (int) $selectedChurchId))
            ->when($selectedPlatform, fn ($collection) => $collection->filter(
                fn ($church) => $church->socials->contains(fn ($social) => $social->platform->value === $selectedPlatform)
            ));

        $people = $this->analyticsPersonScope(Person::query()->where('is_active', true))
            ->with(['socials' => fn ($query) => $query->where('is_active', true)->with('latestStat')])
            ->orderBy('name')
            ->get();

        $selectedPersonId = $request->query('person_id');

        $growthOverTimePersonal = $this->growthOverTime('person_id', 'people', $selectedPersonId, $selectedPlatform, $people->pluck('id'));

        $filteredPeople = $people
            ->when($selectedPersonId, fn ($collection) => $collection->where('id', (int) $selectedPersonId))
            ->when($selectedPlatform, fn ($collection) => $collection->filter(
                fn ($person) => $person->socials->contains(fn ($social) => $social->platform->value === $selectedPlatform)
            ));

        $institutions = $this->analyticsInstitutionScope(Institution::query()->where('is_active', true))
            ->with(['socials' => fn ($query) => $query->where('is_active', true)->with('latestStat')])
            ->orderBy('name')
            ->get();

        $selectedInstitutionId = $request->query('institution_id');

        $growthOverTimeInstitution = $this->growthOverTime('institution_id', 'institutions', $selectedInstitutionId, $selectedPlatform, $institutions->pluck('id'));

        $filteredInstitutions = $institutions
            ->when($selectedInstitutionId, fn ($collection) => $collection->where('id', (int) $selectedInstitutionId))
            ->when($selectedPlatform, fn ($collection) => $collection->filter(
                fn ($institution) => $institution->socials->contains(fn ($social) => $social->platform->value === $selectedPlatform)
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
            'selectedPlatform' => $selectedPlatform,
        ]);
    }

    /**
     * Weekly aggregate stats (reach/views/likes/posts) for church-, person-, or institution-owned
     * accounts. $ownerColumn is 'church_id', 'person_id', or 'institution_id' on church_socials;
     * $ownerTable is the matching owner table ('churches', 'people', or 'institutions') — both
     * are internal constants, never user input. $visibleIds restricts to IDs the viewing user's
     * role/scope may see (pre-resolved via Church::visibleTo()/Person::visibleTo()/
     * Institution::visibleTo(), rather than duplicating that hierarchy SQL here).
     */
    private function growthOverTime(string $ownerColumn, string $ownerTable, ?string $ownerId, ?string $platform, Collection $visibleIds)
    {
        return ChurchStat::query()
            ->join('church_socials', 'church_socials.id', '=', 'church_stats.church_social_id')
            ->join($ownerTable, "{$ownerTable}.id", '=', "church_socials.{$ownerColumn}")
            ->where('church_socials.is_active', true)
            ->where("{$ownerTable}.is_active", true)
            ->whereIn("{$ownerTable}.id", $visibleIds)
            ->when($ownerId, fn ($query) => $query->where("{$ownerTable}.id", $ownerId))
            ->when($platform, fn ($query) => $query->where('church_socials.platform', $platform))
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
        $activeTab = in_array($request->query('tab'), ['personal', 'institusi'], true) ? $request->query('tab') : 'gereja';

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
        $churches = Church::query()
            ->where('is_active', true)
            ->when($search, fn ($q) => $q->where(fn ($q2) => $q2
                ->where('name', 'like', "%{$search}%")
                ->orWhere('city', 'like', "%{$search}%"),
            ))
            ->when($hideEmptyChurches, fn ($q) => $q->whereHas('socials', $socialsFilter))
            ->with(['socials' => $socialsFilter])
            ->orderBy('name')
            ->paginate(20, ['*'], 'churches_page')
            ->withQueryString()
            // Tab switching is client-side only (no reload), so a link built from the request
            // that first rendered the page would still point at whatever tab was active then —
            // force it back to "gereja" regardless.
            ->appends(['tab' => 'gereja']);

        $people = Person::query()
            ->where('is_active', true)
            ->when($search, fn ($q) => $q->where(fn ($q2) => $q2
                ->where('name', 'like', "%{$search}%")
                ->orWhere('city', 'like', "%{$search}%"),
            ))
            ->when($hideEmptyPeople, fn ($q) => $q->whereHas('socials', $socialsFilter))
            ->with(['socials' => $socialsFilter])
            ->orderBy('name')
            ->paginate(20, ['*'], 'people_page')
            ->withQueryString()
            ->appends(['tab' => 'personal']);

        $institutions = Institution::query()
            ->where('is_active', true)
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->when($hideEmptyInstitutions, fn ($q) => $q->whereHas('socials', $socialsFilter))
            ->with(['socials' => $socialsFilter])
            ->orderBy('name')
            ->paginate(20, ['*'], 'institutions_page')
            ->withQueryString()
            ->appends(['tab' => 'institusi']);

        return view('churches.directory', [
            'churches' => $churches,
            'people' => $people,
            'institutions' => $institutions,
            'selectedPlatform' => $selectedPlatform,
            'search' => $search,
            'autoFetch' => $autoFetch,
            'hideEmptyChurches' => $hideEmptyChurches,
            'hideEmptyPeople' => $hideEmptyPeople,
            'hideEmptyInstitutions' => $hideEmptyInstitutions,
            'activeTab' => $activeTab,
        ]);
    }

    public function metricComparison()
    {
        $metricLabels = ['reach' => 'Followers/Subscribers', 'views' => 'Views', 'likes' => 'Likes', 'posts' => 'Post / Video'];

        $scoreRows = $this->growthScoreRows()->map(fn ($row) => [
            'entity' => $row['church'],
            'score' => $row['score'],
            'metrics' => $row['metrics'],
            'accountCount' => $row['accountCount'],
        ]);

        return view('churches.metric-comparison', [
            'scope' => ComparisonScope::church(),
            'metricLabels' => $metricLabels,
            'scoreRows' => $scoreRows,
        ]);
    }

    public function leaderboard(Request $request, string $metric)
    {
        $titles = $this->leaderboardTitles();
        $metricLabels = ['reach' => 'Followers/Subscribers', 'views' => 'Views', 'likes' => 'Likes', 'posts' => 'Post / Video'];

        abort_unless(isset($titles[$metric]), 404);

        $sort = $request->query('sort') === 'value' ? 'value' : 'delta';

        [$socials, $field] = $this->metricDefinition($metric, $this->activeSocials());

        $rows = $this->buildLeaderboard($socials, $field, null, $sort);

        return view('churches.leaderboard', [
            'scope' => ComparisonScope::church(),
            'metric' => $metric,
            'metricLabels' => $metricLabels,
            'title' => $titles[$metric]['title'],
            'subtitle' => $titles[$metric]['subtitle'],
            'sort' => $sort,
            'rows' => $rows,
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

        // No metric picked: show every metric that applies to this platform together on one page.
        if (! $metric) {
            $applicableMetrics = collect($metricPlatforms)
                ->filter(fn ($platforms) => in_array($platform, $platforms, true))
                ->keys();

            $sections = $applicableMetrics->mapWithKeys(fn ($m) => [
                $m => $this->metricComparisonRows($m, $platform, $sort),
            ]);

            // "Semua" compares platforms against each other, so it gets a composite score
            // card instead of per-metric church leaderboards (which don't apply platform-vs-platform).
            $platformScoreRows = $platform === 'semua' ? $this->growthScoreRowsByPlatform($this->activeSocials()) : null;

            return view('churches.platform-comparison-overview', [
                'scope' => ComparisonScope::church(),
                'platform' => $platform,
                'platformLabels' => $platformLabels,
                'metricLabels' => $metricLabels,
                'sort' => $sort,
                'sections' => $sections,
                'platformScoreRows' => $platformScoreRows,
            ]);
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

        return view('churches.platform-comparison', [
            'scope' => ComparisonScope::church(),
            'platform' => $platform,
            'platformLabels' => $platformLabels,
            'metric' => $metric,
            'metricLabels' => $metricLabels,
            'metricPlatforms' => $metricPlatforms[$metric],
            'sort' => $sort,
            'rows' => $this->metricComparisonRows($metric, $platform, $sort),
        ]);
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
}

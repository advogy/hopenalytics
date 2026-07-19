<?php

namespace App\Http\Controllers;

use App\Enums\SocialPlatform;
use App\Http\Controllers\Concerns\BuildsLeaderboards;
use App\Models\Church;
use App\Models\ChurchSocial;
use App\Models\ChurchStat;
use App\Models\Person;
use App\Support\ComparisonScope;
use Illuminate\Http\Request;

class ChurchDashboardController extends Controller
{
    use BuildsLeaderboards;

    public function index()
    {
        $churches = Church::query()
            ->where('is_active', true)
            ->with(['socials' => fn ($query) => $query->where('is_active', true)->with('latestStat')])
            ->orderBy('name')
            ->get();

        $allSocials = $churches->flatMap->socials;

        $reachCountField = fn ($social) => $social->platform === SocialPlatform::YouTube ? 'subscribers_count' : 'followers_count';

        $totalReachChurch = $allSocials->sum(fn ($social) => $social->latestStat?->{$reachCountField($social)} ?? 0);

        $mapChurches = $churches->filter(fn ($church) => $church->latitude !== null && $church->longitude !== null)
            ->map(fn ($church) => [
                'name' => $church->name,
                'city' => $church->city,
                'url' => route('churches.show', $church),
                'lat' => $church->latitude,
                'lng' => $church->longitude,
                'reach' => $church->socials->sum(fn ($social) => $social->latestStat?->{$reachCountField($social)} ?? 0),
            ])
            ->values();

        $unmappedCount = $churches->count() - $mapChurches->count();

        $people = Person::query()
            ->where('is_active', true)
            ->with(['socials' => fn ($query) => $query->where('is_active', true)->with('latestStat')])
            ->get();

        $personalSocials = $people->flatMap->socials;

        $totalReachPersonal = $personalSocials->sum(fn ($social) => $social->latestStat?->{$reachCountField($social)} ?? 0);

        $mapPeople = $people->filter(fn ($person) => $person->latitude !== null && $person->longitude !== null)
            ->map(fn ($person) => [
                'name' => $person->name,
                'city' => $person->city,
                'url' => route('people.show', $person),
                'lat' => $person->latitude,
                'lng' => $person->longitude,
                'reach' => $person->socials->sum(fn ($social) => $social->latestStat?->{$reachCountField($social)} ?? 0),
            ])
            ->values();

        $unmappedPeopleCount = $people->count() - $mapPeople->count();

        $allGrowthScores = $this->growthScoreRows();

        $mapScoreRow = fn ($row) => [
            'entity' => $row['church'],
            'score' => $row['score'],
            'metrics' => $row['metrics'],
            'accountCount' => $row['accountCount'],
        ];

        $topGrowthScores = $allGrowthScores->take(5)->map($mapScoreRow);
        $bottomGrowthScores = $allGrowthScores->sortBy('score')->take(5)->values()->map($mapScoreRow);

        $platformScoreRows = $this->growthScoreRowsByPlatform($this->activeSocials());

        // "Pertumbuhan minggu ini" combines church and personal accounts (unlike the leaderboard above,
        // which stays church-only since it ranks churches against each other).
        [$combinedReachSocials, $combinedReachField] = $this->metricDefinition('reach', $allSocials->merge($personalSocials));
        $weeklyGrowth = $this->buildLeaderboard($combinedReachSocials, $combinedReachField, null)->sum('delta');

        $accountsNeedingAttention = $this->accountsNeedingAttentionQuery()->count();

        return view('churches.index', [
            'totalChurches' => $churches->count(),
            'totalSocials' => $allSocials->count(),
            'totalPeople' => $people->count(),
            'totalPersonalSocials' => $personalSocials->count(),
            'totalReachChurch' => $totalReachChurch,
            'totalReachPersonal' => $totalReachPersonal,
            'weeklyGrowth' => $weeklyGrowth,
            'accountsNeedingAttention' => $accountsNeedingAttention,
            'mapChurches' => $mapChurches,
            'unmappedCount' => $unmappedCount,
            'mapPeople' => $mapPeople,
            'unmappedPeopleCount' => $unmappedPeopleCount,
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

    /**
     * Active, auto-fetchable accounts whose last fetch attempt failed — the same eligibility
     * check "Dapatkan Data Terbaru" uses, so this always reflects accounts that button would
     * refresh but are currently broken.
     */
    private function accountsNeedingAttentionQuery()
    {
        return ChurchSocial::query()
            ->where('is_active', true)
            ->where('is_auto_fetch', true)
            ->where('last_fetch_status', 'failed')
            ->where(fn ($q) => $q
                ->whereHas('church', fn ($q2) => $q2->where('is_active', true))
                ->orWhereHas('person', fn ($q2) => $q2->where('is_active', true)),
            );
    }

    public function analytics(Request $request)
    {
        $selectedPlatform = $request->query('platform');

        $churches = Church::query()
            ->where('is_active', true)
            ->with(['socials' => fn ($query) => $query->where('is_active', true)->with('latestStat')])
            ->orderBy('name')
            ->get();

        $selectedChurchId = $request->query('church_id');

        $growthOverTimeChurch = $this->growthOverTime('church_id', 'churches', $selectedChurchId, $selectedPlatform);

        $filteredChurches = $churches
            ->when($selectedChurchId, fn ($collection) => $collection->where('id', (int) $selectedChurchId))
            ->when($selectedPlatform, fn ($collection) => $collection->filter(
                fn ($church) => $church->socials->contains(fn ($social) => $social->platform->value === $selectedPlatform)
            ));

        $people = Person::query()
            ->where('is_active', true)
            ->with(['socials' => fn ($query) => $query->where('is_active', true)->with('latestStat')])
            ->orderBy('name')
            ->get();

        $selectedPersonId = $request->query('person_id');

        $growthOverTimePersonal = $this->growthOverTime('person_id', 'people', $selectedPersonId, $selectedPlatform);

        $filteredPeople = $people
            ->when($selectedPersonId, fn ($collection) => $collection->where('id', (int) $selectedPersonId))
            ->when($selectedPlatform, fn ($collection) => $collection->filter(
                fn ($person) => $person->socials->contains(fn ($social) => $social->platform->value === $selectedPlatform)
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
            'selectedPlatform' => $selectedPlatform,
        ]);
    }

    /**
     * Weekly aggregate stats (reach/views/likes/posts) for church- or person-owned accounts.
     * $ownerColumn is 'church_id' or 'person_id' on church_socials; $ownerTable is the matching
     * owner table ('churches' or 'people') — both are internal constants, never user input.
     */
    private function growthOverTime(string $ownerColumn, string $ownerTable, ?string $ownerId, ?string $platform)
    {
        return ChurchStat::query()
            ->join('church_socials', 'church_socials.id', '=', 'church_stats.church_social_id')
            ->join($ownerTable, "{$ownerTable}.id", '=', "church_socials.{$ownerColumn}")
            ->where('church_socials.is_active', true)
            ->where("{$ownerTable}.is_active", true)
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
        $activeTab = $request->query('tab') === 'personal' ? 'personal' : 'gereja';

        $churches = Church::query()
            ->where('is_active', true)
            ->when($search, fn ($q) => $q->where(fn ($q2) => $q2
                ->where('name', 'like', "%{$search}%")
                ->orWhere('city', 'like', "%{$search}%"),
            ))
            ->with(['socials' => fn ($query) => $query
                ->where('is_active', true)
                ->when($selectedPlatform, fn ($q) => $q->where('platform', $selectedPlatform))
                ->when($autoFetch, fn ($q) => $q->where('is_auto_fetch', $autoFetch === 'auto')),
            ])
            ->orderBy('name')
            ->paginate(20, ['*'], 'churches_page')
            ->withQueryString();

        $people = Person::query()
            ->where('is_active', true)
            ->when($search, fn ($q) => $q->where(fn ($q2) => $q2
                ->where('name', 'like', "%{$search}%")
                ->orWhere('city', 'like', "%{$search}%"),
            ))
            ->with(['socials' => fn ($query) => $query
                ->where('is_active', true)
                ->when($selectedPlatform, fn ($q) => $q->where('platform', $selectedPlatform))
                ->when($autoFetch, fn ($q) => $q->where('is_auto_fetch', $autoFetch === 'auto')),
            ])
            ->orderBy('name')
            ->paginate(20, ['*'], 'people_page')
            ->withQueryString();

        return view('churches.directory', [
            'churches' => $churches,
            'people' => $people,
            'selectedPlatform' => $selectedPlatform,
            'search' => $search,
            'autoFetch' => $autoFetch,
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
        $churches = Church::query()->where('is_active', true)->get();

        $scores = $this->growthScoreRows()->keyBy(fn ($row) => $row['church']->id);

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
            'totalSocials' => $this->activeSocials()->count(),
            'avgScore' => $scoredRows->isNotEmpty() ? round($scoredRows->avg('score'), 2) : null,
        ]);
    }

    public function personalPresentation()
    {
        $countField = ['youtube' => 'subscribers_count', 'instagram' => 'followers_count', 'tiktok' => 'followers_count', 'facebook' => 'followers_count'];

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
        $people = Person::query()->where('is_active', true)->get();

        $scores = $this->growthScoreRowsPersonal()->keyBy(fn ($row) => $row['person']->id);

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
            'totalSocials' => $this->activeSocialsPersonal()->count(),
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

        return view('churches.show', [
            'church' => $church,
            'history' => $history,
            'scoreHistory' => $scoreHistory,
        ]);
    }
}

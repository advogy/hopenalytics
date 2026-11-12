<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsCombinedGrowth;
use App\Http\Controllers\Concerns\BuildsLeaderboards;
use App\Models\Church;
use App\Models\Conference;
use App\Models\MembershipPeriod;
use App\Models\Union;
use App\Support\MemberEngagement;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * The full Tujuan page — data only; the settings behind it live in the account menu. The
 * dashboard shows a summary and links here.
 *
 * Two sections: the metric goals (same numbers as the dashboard's goal cards), and Keterlibatan
 * Anggota — each region's active members against its target share of recorded membership,
 * drilling down Uni → Daerah → Gereja. Each viewer starts at the widest level they may see:
 * a Uni-level user at their Uni's Daerah, a Daerah-level user at their Daerah's churches.
 */
class MemberEngagementController extends Controller
{
    use BuildsCombinedGrowth, BuildsLeaderboards;

    public function index(Request $request)
    {
        $user = $request->user();

        // applyCeiling: true, same as the dashboard's goal cards, so both show the same numbers.
        // latestStat is loaded up front; goalProgressRows() reads it for every account.
        $socialSets = array_map(fn ($socials) => $socials->loadMissing('latestStat'), [
            $this->activeSocials(applyCeiling: true),
            $this->activeSocialsInstitution(applyCeiling: true),
            $this->activeSocialsPersonal(applyCeiling: true),
            $this->activeSocialsOrganization(applyCeiling: true),
        ]);

        $view = [
            'goalRows' => $this->goalProgressRows(...$socialSets),
            'showEngagement' => $user->can('view-member-engagement'),
        ];

        if (! $view['showEngagement']) {
            return view('goals.index', $view);
        }

        $view += $this->engagementTable($request);

        return view('goals.index', $view);
    }

    /**
     * The Keterlibatan Anggota table for the request's period, measure and drill-down (Uni →
     * Daerah → Gereja), within what the user may see. Shared by the Tujuan page and its export
     * (ExportController::engagementDataset()) so both always show the same numbers.
     */
    public function engagementTable(Request $request): array
    {
        $user = $request->user();
        $periods = MembershipPeriod::query()->orderByDesc('starts_on')->get();
        $period = $periods->firstWhere('id', (int) $request->query('periode')) ?? MembershipPeriod::current();
        $measure = in_array($request->query('ukuran'), MemberEngagement::MEASURES, true) ? $request->query('ukuran') : 'akun';

        $view = ['periods' => $periods, 'period' => $period, 'measure' => $measure, 'rows' => collect(), 'totalRow' => null, 'breadcrumbs' => [], 'regionLabel' => null, 'sort' => 'active_percent', 'direction' => 'desc'];

        if ($period === null) {
            return $view;
        }

        // Drill-down links keep the chosen sort.
        $sortParams = array_filter($request->only(['urut', 'arah']));
        $link = fn (array $extra = []) => route('goals.index', ['periode' => $period->id, 'ukuran' => $measure] + $extra + $sortParams).'#keterlibatan';
        $members = MemberEngagement::membersByChurch($period);

        $unions = Union::query()->visibleTo($user)->where('is_active', true)->orderBy('name')->get();
        $conferences = Conference::query()->visibleTo($user)->where('is_active', true)->orderBy('name')->get();
        $level = $user->role?->level();

        // Where to start, then any drill-down the viewer picked within what they may see.
        $conference = $conferences->firstWhere('id', (int) $request->query('daerah'))
            ?? ($level === 'daerah' ? $conferences->first() : null);
        $union = $conference
            ? $unions->firstWhere('id', $conference->union_id)
            : ($unions->firstWhere('id', (int) $request->query('uni')) ?? ($unions->count() === 1 ? $unions->first() : null));

        if ($level !== 'daerah') {
            if ($unions->count() > 1) {
                $view['breadcrumbs'][] = ['label' => __('engagement.all_unions'), 'url' => $link()];
            }
            if ($union) {
                $view['breadcrumbs'][] = ['label' => $union->name, 'url' => $link(['uni' => $union->id])];
            }
            if ($conference) {
                $view['breadcrumbs'][] = ['label' => $conference->name, 'url' => null];
            }
        }

        $churchCounts = Church::query()->where('is_active', true)->whereNotNull('conference_id')
            ->selectRaw('conference_id, COUNT(*) as total')->groupBy('conference_id')
            ->pluck('total', 'conference_id');

        if ($conference) {
            $active = MemberEngagement::countsByChurch($measure, $period);

            $churches = Church::query()->where('conference_id', $conference->id)->where('is_active', true)->orderBy('name')->get();
            $growth = $this->combinedGrowthScores('church', $churches->pluck('id'));

            $view['regionLabel'] = __('engagement.col_church');
            $view['rows'] = $churches->map(fn (Church $church) => $this->row($period, $church->name, $members->get($church->id)?->members_count, $active[$church->id] ?? 0)
                + ['growth' => $growth->get($church->id)]);
            $view['totalRow'] = $this->conferenceRow($period, $conference, $members, $churchCounts, MemberEngagement::countsByConference($measure, $period))
                + ['growth' => $this->combinedGrowthScores('conference', [$conference->id])->get($conference->id)];
        } elseif ($union) {
            $active = MemberEngagement::countsByConference($measure, $period);

            $growth = $this->combinedGrowthScores('conference', $conferences->where('union_id', $union->id)->pluck('id'));

            $view['regionLabel'] = __('engagement.col_conference');
            $view['rows'] = $conferences->where('union_id', $union->id)
                ->map(fn (Conference $c) => $this->conferenceRow($period, $c, $members, $churchCounts, $active) + ['url' => $link(['daerah' => $c->id]), 'growth' => $growth->get($c->id)]);
            $view['totalRow'] = $this->unionRow($period, $union, $conferences, $members, $churchCounts, MemberEngagement::countsByUnion($measure, $period))
                + ['growth' => $this->combinedGrowthScores('union', [$union->id])->get($union->id)];
        } else {
            $active = MemberEngagement::countsByUnion($measure, $period);

            $growth = $this->combinedGrowthScores('union', $unions->pluck('id'));

            $view['regionLabel'] = __('engagement.col_union');
            $view['rows'] = $unions->map(fn (Union $u) => $this->unionRow($period, $u, $conferences, $members, $churchCounts, $active) + ['url' => $link(['uni' => $u->id]), 'growth' => $growth->get($u->id)]);
            $view['totalRow'] = $this->row(
                $period,
                __('engagement.total'),
                $view['rows']->every(fn ($row) => $row['members'] === null) ? null : $view['rows']->sum('members'),
                $view['rows']->sum('active'),
            ) + ['filled' => $view['rows']->sum('filled'), 'churches' => $view['rows']->sum('churches')];
        }

        [$view['sort'], $view['direction']] = $this->sortFrom($request);
        $view['rows'] = $this->sortRows($view['rows'], $view['sort'], $view['direction']);

        return $view;

    }

    /** Columns the table can be sorted by (?urut=), each read from a row. */
    public const SORTABLE = ['name', 'members', 'target', 'active', 'active_percent', 'progress_percent', 'growth'];

    /** @return array{0: string, 1: string} column and direction, default highest % aktif first */
    private function sortFrom(Request $request): array
    {
        $sort = in_array($request->query('urut'), self::SORTABLE, true) ? $request->query('urut') : 'active_percent';
        $direction = in_array($request->query('arah'), ['asc', 'desc'], true)
            ? $request->query('arah')
            : ($sort === 'name' ? 'asc' : 'desc');

        return [$sort, $direction];
    }

    /** Rows without a value for the column (no figures yet, no growth data) always go last. */
    private function sortRows(Collection $rows, string $sort, string $direction): Collection
    {
        $value = fn (array $row) => match ($sort) {
            'growth' => $row['growth']['score'] ?? null,
            'name' => mb_strtolower($row['name']),
            default => $row[$sort] ?? null,
        };

        [$withValue, $without] = $rows->partition(fn ($row) => $value($row) !== null);

        $sorted = $direction === 'asc' ? $withValue->sortBy($value) : $withValue->sortByDesc($value);

        return $sorted->concat($without->sortBy(fn ($row) => mb_strtolower($row['name'])))->values();
    }

    private function unionRow(MembershipPeriod $period, Union $union, Collection $conferences, Collection $members, Collection $churchCounts, Collection $active): array
    {
        $conferenceIds = $conferences->where('union_id', $union->id)->pluck('id')->all();

        return $this->regionRow($period, $union->name, $conferenceIds, $members, $churchCounts, $active[$union->id] ?? 0);
    }

    private function conferenceRow(MembershipPeriod $period, Conference $conference, Collection $members, Collection $churchCounts, Collection $active): array
    {
        return $this->regionRow($period, $conference->name, [$conference->id], $members, $churchCounts, $active[$conference->id] ?? 0);
    }

    /** A region's membership is the sum of whichever of its churches have a confirmed figure. */
    private function regionRow(MembershipPeriod $period, string $name, array $conferenceIds, Collection $members, Collection $churchCounts, int $active): array
    {
        $filled = $members->whereIn('conference_id', $conferenceIds);

        return $this->row($period, $name, $filled->isEmpty() ? null : (int) $filled->sum('members_count'), $active)
            + ['filled' => $filled->count(), 'churches' => (int) collect($conferenceIds)->sum(fn ($id) => $churchCounts[$id] ?? 0)];
    }

    private function row(MembershipPeriod $period, string $name, ?int $members, int $active): array
    {
        return MemberEngagement::row($period, $members, $active) + ['name' => $name];
    }
}

<?php

namespace App\Support;

use App\Models\Church;
use App\Models\Conference;
use App\Models\MembershipPeriod;
use App\Models\Union;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Tujuan → "Anggota & Target": just the recorded membership and the target it implies for a
 * period, per church — or added up per Daerah or per Uni — without the activity statistics of
 * the Capaian tab. Membership is the same figure the Capaian tab uses (each church's latest
 * confirmed figure up to the period's end, see MemberEngagement::membersByChurch()).
 *
 * Shared by MembershipTargetController (the page) and ExportController (its export).
 */
class MembershipTargetList
{
    public const GROUPS = ['gereja', 'daerah', 'uni'];

    public const SORTABLE = ['name', 'members', 'target', 'as_of', 'churches'];

    public static function build(Request $request, User $user): array
    {
        $periods = MembershipPeriod::query()->orderByDesc('starts_on')->get();
        $period = $periods->firstWhere('id', (int) $request->query('periode')) ?? MembershipPeriod::current();

        $unions = Union::query()->visibleTo($user)->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $conferences = Conference::query()->visibleTo($user)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'union_id']);
        $isDaerah = $user->role?->level() === 'daerah';

        // Daerah-level users only ever see their own Daerah, so "per Uni" isn't offered to them.
        $groups = $isDaerah ? ['gereja'] : self::GROUPS;
        $group = in_array($request->query('ringkas'), $groups, true) ? $request->query('ringkas') : 'gereja';

        $unionId = $isDaerah ? null : $unions->firstWhere('id', (int) $request->query('uni'))?->id;
        $conferenceId = $conferences
            ->when($unionId, fn ($c) => $c->where('union_id', $unionId))
            ->firstWhere('id', (int) $request->query('daerah'))?->id;
        $search = trim((string) $request->query('cari'));

        [$sort, $direction] = self::sortFrom($request);

        $result = [
            'periods' => $periods, 'period' => $period, 'groups' => $groups, 'group' => $group,
            'unions' => $unions, 'conferences' => $conferences, 'unionId' => $unionId, 'conferenceId' => $conferenceId,
            'search' => $search, 'sort' => $sort, 'direction' => $direction,
            'rows' => collect(), 'totals' => null,
        ];

        if ($period === null) {
            return $result;
        }

        $members = MemberEngagement::membersByChurch($period);
        $conferencesById = $conferences->keyBy('id');
        $unionsById = $unions->keyBy('id');

        // Only churches in an active, visible Daerah — the same set the Capaian tab counts, so
        // the per-church list and the per-Daerah/Uni summaries always add up to the same total.
        $churches = Church::query()->visibleTo($user)->where('is_active', true)
            ->whereIn('conference_id', $conferences->pluck('id'))
            ->when($conferenceId, fn ($q) => $q->where('conference_id', $conferenceId))
            ->when(! $conferenceId && $unionId, fn ($q) => $q->whereIn('conference_id', $conferences->where('union_id', $unionId)->pluck('id')))
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'conference_id'])
            ->map(function (Church $church) use ($members, $conferencesById, $unionsById, $period) {
                $figure = $members->get($church->id);
                $conference = $conferencesById->get($church->conference_id);

                return [
                    'name' => $church->name,
                    'url' => route('churches.show', $church),
                    'conference' => $conference?->name,
                    'conference_id' => $conference?->id,
                    'union' => $unionsById->get($conference?->union_id)?->name,
                    'union_id' => $conference?->union_id,
                    'members' => $figure?->members_count,
                    'as_of' => $figure?->as_of,
                    'target' => $period->targetFor($figure?->members_count),
                ];
            });

        $rows = match ($group) {
            'daerah' => self::summarise($churches, 'conference_id', fn ($first) => ['name' => $first['conference'], 'union' => $first['union']], $period),
            'uni' => self::summarise($churches, 'union_id', fn ($first) => ['name' => $first['union']], $period),
            default => $churches,
        };

        if ($search !== '') {
            $rows = $rows->filter(fn ($row) => Str::contains(Str::lower((string) $row['name']), Str::lower($search)));
        }

        // Totals cover exactly the churches behind the listed rows (after any search), so they
        // match the rows whether listed per church or added up per Daerah/Uni.
        $countedChurches = match ($group) {
            'daerah' => $churches->whereIn('conference_id', $rows->pluck('id')),
            'uni' => $churches->whereIn('union_id', $rows->pluck('id')),
            default => $rows,
        };
        $countedFilled = $countedChurches->filter(fn ($c) => $c['members'] !== null);
        $countedMembers = $countedFilled->isEmpty() ? null : (int) $countedFilled->sum('members');

        $result['rows'] = self::sort($rows->values(), $sort, $direction);
        $result['totals'] = [
            'rows' => $rows->count(),
            'churches' => $countedChurches->count(),
            'filled' => $countedFilled->count(),
            'members' => $countedMembers,
            'target' => $period->targetFor($countedMembers),
        ];

        return $result;
    }

    private static function summarise(Collection $churches, string $key, callable $label, MembershipPeriod $period): Collection
    {
        return $churches->filter(fn ($c) => $c[$key] !== null)->groupBy($key)->map(function (Collection $group) use ($key, $label, $period) {
            $filled = $group->filter(fn ($c) => $c['members'] !== null);
            $members = $filled->isEmpty() ? null : (int) $filled->sum('members');

            return $label($group->first()) + [
                'id' => $group->first()[$key],
                'url' => null,
                'churches' => $group->count(),
                'filled' => $filled->count(),
                'members' => $members,
                'as_of' => $filled->max('as_of'),
                'target' => $period->targetFor($members),
            ];
        })->values();
    }

    /** @return array{0: string, 1: string} */
    private static function sortFrom(Request $request): array
    {
        $sort = in_array($request->query('urut'), self::SORTABLE, true) ? $request->query('urut') : 'name';
        $direction = in_array($request->query('arah'), ['asc', 'desc'], true) ? $request->query('arah') : ($sort === 'name' ? 'asc' : 'desc');

        return [$sort, $direction];
    }

    /** Rows without a value for the column always go last, then by name. */
    private static function sort(Collection $rows, string $sort, string $direction): Collection
    {
        $value = fn ($row) => $sort === 'name' ? Str::lower((string) $row['name']) : ($row[$sort] ?? null);
        [$with, $without] = $rows->partition(fn ($row) => $value($row) !== null);

        return ($direction === 'asc' ? $with->sortBy($value) : $with->sortByDesc($value))
            ->concat($without->sortBy(fn ($row) => Str::lower((string) $row['name'])))
            ->values();
    }
}

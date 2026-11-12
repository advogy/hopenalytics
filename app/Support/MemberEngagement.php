<?php

namespace App\Support;

use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\Conference;
use App\Models\MembershipPeriod;
use App\Models\Person;
use App\Models\Union;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Recorded membership and "actively involved" members per church, Daerah and Uni.
 *
 * Membership is per church as of a date (ChurchMembership); a period uses each church's latest
 * confirmed figure up to the period's end, and Daerah/Uni totals add up their churches.
 *
 * The comparison page lets the
 * viewer choose which of three definitions counts as active:
 *  - terdaftar: the person has a Hopenalytics login (a linked user account)
 *  - akun:      the person has an active personal social account with consent given
 *  - posting:   one of the person's accounts posted during the period
 */
class MemberEngagement
{
    public const MEASURES = ['terdaftar', 'akun', 'posting'];

    /**
     * Stats are fetched weekly, so a period's first snapshot alone can't show whether anything
     * was posted early in the period. Reaching back this many days picks up the snapshot just
     * before it as the starting point.
     */
    private const POSTING_LOOKBACK_DAYS = 7;

    public static function activePeople(string $measure, MembershipPeriod $period): Builder
    {
        $query = Person::query()->where('people.is_active', true);

        return match ($measure) {
            'terdaftar' => $query->whereNotNull('people.user_id'),
            'akun' => $query->whereHas('socials', fn (Builder $q) => self::personalAccounts($q)),
            'posting' => $query->whereHas('socials', fn (Builder $q) => self::personalAccounts($q)
                ->where(fn (Builder $q2) => $q2
                    ->whereIn('church_socials.id', self::postedInPeriod($period))
                    ->orWhereIn('church_socials.id', DB::table('hashtag_posts')
                        ->select('church_social_id')
                        ->whereBetween('posted_at', [$period->starts_on->copy()->startOfDay(), $period->ends_on->copy()->endOfDay()])))),
            default => throw new \InvalidArgumentException("Unknown engagement measure [{$measure}]."),
        };
    }

    /** Each active church's latest confirmed membership dated on or before the period's end. */
    public static function membersByChurch(MembershipPeriod $period): Collection
    {
        return self::latestConfirmed($period->ends_on->toDateString());
    }

    /**
     * The one place "a church's latest confirmed membership figure" is worked out — used by the
     * engagement pages, the upload template and the membership exports. One row per church,
     * through a MAX(as_of) join rather than loading every church's full history.
     *
     * @param  string|null  $until  only figures dated on or before this date (Y-m-d)
     * @param  iterable<int>|null  $churchIds  only these churches
     * @return Collection<int, object{church_id:int, conference_id:int, members_count:int, as_of:string}> keyed by church id
     */
    public static function latestConfirmed(?string $until = null, ?iterable $churchIds = null): Collection
    {
        $ids = $churchIds === null ? null : collect($churchIds)->all();

        $latest = DB::table('church_memberships')
            ->select('church_id', DB::raw('MAX(as_of) as latest_as_of'))
            ->where('status', ChurchMembership::CONFIRMED)
            ->when($until, fn ($q) => $q->where('as_of', '<=', $until))
            ->when($ids !== null, fn ($q) => $q->whereIn('church_id', $ids))
            ->groupBy('church_id');

        return DB::table('church_memberships as cm')
            ->joinSub($latest, 'latest', fn ($join) => $join
                ->on('latest.church_id', '=', 'cm.church_id')
                ->on('latest.latest_as_of', '=', 'cm.as_of'))
            ->join('churches', 'churches.id', '=', 'cm.church_id')
            ->where('churches.is_active', true)
            ->get(['cm.church_id', 'churches.conference_id', 'cm.members_count', 'cm.as_of'])
            ->keyBy('church_id');
    }

    /**
     * For each pending figure, the church's last confirmed figure dated BEFORE it — what the
     * Union admin should compare it with. A confirmed figure dated after a back-dated upload
     * isn't its "previous" one.
     *
     * @param  Collection<int, ChurchMembership>  $pending
     * @return Collection<int, ChurchMembership> keyed by the pending figure's id
     */
    public static function previousConfirmed(Collection $pending): Collection
    {
        $confirmed = ChurchMembership::query()
            ->whereIn('church_id', $pending->pluck('church_id')->unique())
            ->where('status', ChurchMembership::CONFIRMED)
            ->orderBy('as_of')
            ->get(['id', 'church_id', 'as_of', 'members_count'])
            ->groupBy('church_id');

        return $pending->mapWithKeys(fn (ChurchMembership $m) => [
            $m->id => $confirmed->get($m->church_id)?->filter(fn ($c) => $c->as_of->lt($m->as_of))->last(),
        ])->filter();
    }

    /** @return Collection<int, int> active count keyed by church id */
    public static function countsByChurch(string $measure, MembershipPeriod $period): Collection
    {
        return self::activePeople($measure, $period)
            ->whereNotNull('people.church_id')
            ->selectRaw('people.church_id as region_id, COUNT(*) as total')
            ->groupBy('people.church_id')
            ->toBase()
            ->pluck('total', 'region_id')
            ->map(fn ($total) => (int) $total);
    }

    /**
     * A person's Daerah is their own conference_id or, when they only chose a church, that
     * church's Daerah — members who picked a church in Lengkapi Profil often have no Daerah of
     * their own, and would otherwise count for their church but not its Daerah or Uni.
     */
    private const CONFERENCE_OF_PERSON = 'COALESCE(people.conference_id, member_churches.conference_id)';

    /** @return Collection<int, int> active count keyed by conference id */
    public static function countsByConference(string $measure, MembershipPeriod $period): Collection
    {
        return self::activePeople($measure, $period)
            ->leftJoin('churches as member_churches', 'member_churches.id', '=', 'people.church_id')
            ->selectRaw(self::CONFERENCE_OF_PERSON.' as region_id, COUNT(*) as total')
            ->whereRaw(self::CONFERENCE_OF_PERSON.' IS NOT NULL')
            ->groupByRaw(self::CONFERENCE_OF_PERSON)
            ->toBase()
            ->pluck('total', 'region_id')
            ->map(fn ($total) => (int) $total);
    }

    /**
     * A person belongs to a Uni directly (union_id) or through their Daerah (see
     * CONFERENCE_OF_PERSON), so people recorded at Uni level without a Daerah still count.
     *
     * @return Collection<int, int> active count keyed by union id
     */
    public static function countsByUnion(string $measure, MembershipPeriod $period): Collection
    {
        $unionId = 'COALESCE(people.union_id, region_conferences.union_id)';

        return self::activePeople($measure, $period)
            ->leftJoin('churches as member_churches', 'member_churches.id', '=', 'people.church_id')
            ->leftJoin('conferences as region_conferences', fn ($join) => $join->on(
                DB::raw('region_conferences.id'), '=', DB::raw(self::CONFERENCE_OF_PERSON)
            ))
            ->selectRaw("{$unionId} as region_id, COUNT(*) as total")
            ->whereRaw("{$unionId} IS NOT NULL")
            ->groupByRaw($unionId)
            ->toBase()
            ->pluck('total', 'region_id')
            ->map(fn ($total) => (int) $total);
    }

    /** Members, target, active and the two percentages for one region (or a total). */
    public static function row(MembershipPeriod $period, ?int $members, int $active): array
    {
        $target = $period->targetFor($members);

        return [
            'members' => $members,
            'target' => $target,
            'active' => $active,
            // Two decimals below 1%, so a small share (167 of 428,000) doesn't read as 0%.
            'active_percent' => $members ? round($active / $members * 100, $active * 100 < $members ? 2 : 1) : null,
            'progress_percent' => $target ? round($active / $target * 100) : null,
        ];
    }

    /**
     * The viewer's whole region in one row, for the dashboard summary. Adds up the same way as
     * the Tujuan page: a Daerah-level viewer by their Daerah, everyone else by their Uni.
     */
    public static function summaryFor(User $user, MembershipPeriod $period, string $measure = 'akun'): array
    {
        $churchIds = Church::query()->visibleTo($user)->where('is_active', true)->pluck('id')->all();
        $memberships = self::membersByChurch($period)->only($churchIds);

        $active = $user->role?->level() === 'daerah'
            ? self::countsByConference($measure, $period)
                ->only(Conference::query()->visibleTo($user)->pluck('id')->all())->sum()
            : self::countsByUnion($measure, $period)
                ->only(Union::query()->visibleTo($user)->where('is_active', true)->pluck('id')->all())->sum();

        return self::row($period, $memberships->isEmpty() ? null : (int) $memberships->sum('members_count'), (int) $active);
    }

    private static function personalAccounts(Builder $query): Builder
    {
        return $query->where('church_socials.is_active', true)
            ->where('church_socials.category', 'personal')
            ->whereNotNull('church_socials.consent_at');
    }

    /**
     * Accounts that posted in the period: their post or video count went up between snapshots,
     * or a fetch's sample of recent posts included one published in the period. The second
     * covers Facebook and Threads, which have no post total to compare (see latest_post_at).
     */
    private static function postedInPeriod(MembershipPeriod $period): \Illuminate\Database\Query\Builder
    {
        $grew = DB::table('church_stats')
            ->select('church_social_id')
            ->whereBetween('recorded_at', [
                $period->starts_on->copy()->subDays(self::POSTING_LOOKBACK_DAYS)->toDateString(),
                $period->ends_on->toDateString(),
            ])
            ->groupBy('church_social_id')
            ->havingRaw('MAX(posts_count) > MIN(posts_count) OR MAX(videos_count) > MIN(videos_count)');

        return DB::table('church_stats')
            ->select('church_social_id')
            ->whereBetween('latest_post_at', [$period->starts_on->copy()->startOfDay(), $period->ends_on->copy()->endOfDay()])
            ->union($grew);
    }
}

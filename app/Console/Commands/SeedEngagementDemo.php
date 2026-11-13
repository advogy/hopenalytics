<?php

namespace App\Console\Commands;

use App\Enums\SocialPlatform;
use App\Enums\UserRole;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\ChurchSocial;
use App\Models\ChurchStat;
use App\Models\Conference;
use App\Models\MembershipPeriod;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Dummy data for trying out Tujuan & Keanggotaan and the combined growth score on a local
 * database: engagement periods with their target share, church membership figures in every
 * review state (confirmed, pending, rejected, missing), a few churches "created by upload", and
 * two weekly snapshots for members' personal accounts.
 *
 * Every row it creates is recorded in a file, so `--remove` deletes exactly those rows and
 * nothing else. Local environment only.
 */
class SeedEngagementDemo extends Command
{
    protected $signature = 'demo:engagement {--remove : Delete the dummy data created earlier}';

    protected $description = 'Create (or remove) dummy data for Tujuan & Keanggotaan and combined growth (local only)';

    private const NEW_CHURCH_NAMES = ['GMAHK Demo Sukamaju', 'GMAHK Demo Harapan Baru', 'GMAHK Demo Bukit Sion'];

    public function handle(): int
    {
        if (! app()->environment('local')) {
            $this->error('Only for a local database (APP_ENV=local).');

            return self::FAILURE;
        }

        return $this->option('remove') ? $this->remove() : $this->seed();
    }

    private function registryPath(): string
    {
        return storage_path('app/demo-engagement.json');
    }

    private function seed(): int
    {
        if (file_exists($this->registryPath())) {
            $this->error('Dummy data already exists. Run `php artisan demo:engagement --remove` first.');

            return self::FAILURE;
        }

        mt_srand(2026); // same data every run

        $created = ['periods' => [], 'churches' => [], 'memberships' => [], 'stats' => []];

        DB::transaction(function () use (&$created) {
            $created['periods'] = $this->seedPeriods();
            $created['churches'] = $this->seedNewChurches();
            $created['memberships'] = $this->seedMemberships();
            $created['stats'] = $this->seedPersonalStats();
        });

        file_put_contents($this->registryPath(), json_encode($created, JSON_PRETTY_PRINT));

        $statuses = ChurchMembership::query()->whereIn('id', $created['memberships'])
            ->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        $this->info('Dummy data created:');
        $this->table(['Data', 'Count'], [
            ['Engagement periods', count($created['periods'])],
            ['New churches (as if created by upload)', count($created['churches'])],
            ['Church membership figures', count($created['memberships'])],
            ['  confirmed / pending / rejected', ($statuses['confirmed'] ?? 0).' / '.($statuses['pending'] ?? 0).' / '.($statuses['rejected'] ?? 0)],
            ['Weekly snapshots for personal accounts', count($created['stats'])],
        ]);
        $this->line('Remove it again with: php artisan demo:engagement --remove');

        return self::SUCCESS;
    }

    /** @return list<int> */
    private function seedPeriods(): array
    {
        $periods = [
            ['name' => 'Tahun 2025', 'starts_on' => '2025-01-01', 'ends_on' => '2025-12-31', 'target_percent' => 8],
            ['name' => 'Tahun 2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'target_percent' => 10],
            ['name' => 'Semester 1 2027', 'starts_on' => '2027-01-01', 'ends_on' => '2027-06-30', 'target_percent' => 12.5],
        ];

        return collect($periods)->map(fn ($period) => MembershipPeriod::create($period)->id)->all();
    }

    /** @return list<int> */
    private function seedNewChurches(): array
    {
        $conferences = Conference::query()->where('is_active', true)->inRandomOrder(2026)->limit(count(self::NEW_CHURCH_NAMES))->get();

        return collect(self::NEW_CHURCH_NAMES)->values()->map(function ($name, $i) use ($conferences) {
            return Church::create([
                'conference_id' => $conferences[$i % $conferences->count()]->id,
                'name' => $name,
                'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
                'is_active' => true,
            ])->id;
        })->all();
    }

    /**
     * Two figures per church: end of 2025 (all confirmed) and end of October 2026 (mostly
     * confirmed, some pending or rejected). About one church in seven has no figures at all.
     *
     * @return list<int>
     */
    private function seedMemberships(): array
    {
        $uploader = User::query()->where('role', UserRole::AdminDivisi)->value('id')
            ?? User::query()->where('role', UserRole::SuperAdmin)->value('id');
        $reviewerByUnion = User::query()->where('role', UserRole::AdminUni)->whereNotNull('union_id')
            ->get(['id', 'union_id'])->unique('union_id')->pluck('id', 'union_id');
        $fallbackReviewer = User::query()->where('role', UserRole::SuperAdmin)->value('id');

        $churches = Church::query()->where('is_active', true)->with('conference:id,union_id')->get(['id', 'conference_id']);
        $existing = ChurchMembership::query()->get(['church_id', 'as_of'])
            ->map(fn ($m) => $m->church_id.'|'.$m->as_of->toDateString())->flip();

        $ids = [];

        foreach ($churches as $church) {
            if (mt_rand(1, 7) === 1) {
                continue; // no figures for this church
            }

            // Mostly small congregations, a few large ones.
            $base = mt_rand(1, 10) === 1 ? mt_rand(400, 1500) : mt_rand(25, 380);
            $reviewer = $reviewerByUnion[$church->conference?->union_id] ?? $fallbackReviewer;

            $figures = [
                ['2025-12-31', $base, ChurchMembership::CONFIRMED],
                ['2026-10-31', (int) round($base * mt_rand(95, 108) / 100), match (true) {
                    mt_rand(1, 10) <= 7 => ChurchMembership::CONFIRMED,
                    mt_rand(1, 3) <= 2 => ChurchMembership::PENDING,
                    default => ChurchMembership::REJECTED,
                }],
            ];

            foreach ($figures as [$asOf, $count, $status]) {
                if ($existing->has($church->id.'|'.$asOf)) {
                    continue;
                }

                $reviewed = $status !== ChurchMembership::PENDING;

                $ids[] = ChurchMembership::create([
                    'church_id' => $church->id,
                    'as_of' => $asOf,
                    'members_count' => $count,
                    'status' => $status,
                    'uploaded_by' => $uploader,
                    'reviewed_by' => $reviewed ? $reviewer : null,
                    'reviewed_at' => $reviewed ? Carbon::parse($asOf)->addDays(mt_rand(2, 10)) : null,
                ])->id;
            }
        }

        return $ids;
    }

    /**
     * Two snapshots a week apart (7 days ago and today) for every consented personal account,
     * so members' accounts show up in the combined growth score and the "posting" measure.
     *
     * @return list<int>
     */
    private function seedPersonalStats(): array
    {
        $socials = ChurchSocial::query()
            ->where('is_active', true)->whereNotNull('person_id')->whereNotNull('consent_at')
            ->with('latestStat')->get();

        $today = now()->startOfDay();
        $weekAgo = $today->copy()->subDays(7);
        $taken = ChurchStat::query()->whereIn('church_social_id', $socials->pluck('id'))
            ->whereIn('recorded_at', [$weekAgo->toDateString(), $today->toDateString()])
            ->get(['church_social_id', 'recorded_at'])
            ->map(fn ($s) => $s->church_social_id.'|'.$s->recorded_at->toDateString())->flip();

        $ids = [];

        foreach ($socials as $social) {
            $isYoutube = $social->platform === SocialPlatform::YouTube;
            $latest = $social->latestStat;

            $followers = (int) ($isYoutube ? $latest?->subscribers_count : $latest?->followers_count) ?: mt_rand(80, 3000);
            $posts = (int) ($isYoutube ? $latest?->videos_count : $latest?->posts_count) ?: mt_rand(10, 400);
            $views = (int) ($latest?->views_count ?: mt_rand(2000, 90000));

            $grownFollowers = (int) round($followers * mt_rand(985, 1060) / 1000);
            $grownPosts = $posts + mt_rand(0, 4);
            $grownViews = (int) round($views * mt_rand(1000, 1080) / 1000);

            foreach ([[$weekAgo, $followers, $posts, $views], [$today, $grownFollowers, $grownPosts, $grownViews]] as [$date, $f, $p, $v]) {
                if ($taken->has($social->id.'|'.$date->toDateString())) {
                    continue;
                }

                $ids[] = ChurchStat::create([
                    'church_social_id' => $social->id,
                    'recorded_at' => $date->toDateString(),
                    $isYoutube ? 'subscribers_count' : 'followers_count' => $f,
                    $isYoutube ? 'videos_count' : 'posts_count' => $p,
                    'views_count' => $isYoutube ? $v : null,
                ])->id;
            }
        }

        return $ids;
    }

    private function remove(): int
    {
        if (! file_exists($this->registryPath())) {
            $this->warn('No dummy data recorded — nothing to remove.');

            return self::SUCCESS;
        }

        $created = json_decode(file_get_contents($this->registryPath()), true);

        DB::transaction(function () use ($created) {
            foreach (array_chunk($created['stats'] ?? [], 1000) as $chunk) {
                ChurchStat::query()->whereIn('id', $chunk)->delete();
            }
            foreach (array_chunk($created['memberships'] ?? [], 1000) as $chunk) {
                ChurchMembership::query()->whereIn('id', $chunk)->delete();
            }
            // Only the churches this command created; their memberships cascade with them.
            Church::query()->whereIn('id', $created['churches'] ?? [])->whereIn('name', self::NEW_CHURCH_NAMES)->delete();
            MembershipPeriod::query()->whereIn('id', $created['periods'] ?? [])->delete();
        });

        unlink($this->registryPath());

        $this->info(sprintf(
            'Removed %d periods, %d churches, %d membership figures and %d snapshots.',
            count($created['periods'] ?? []), count($created['churches'] ?? []), count($created['memberships'] ?? []), count($created['stats'] ?? []),
        ));

        return self::SUCCESS;
    }
}

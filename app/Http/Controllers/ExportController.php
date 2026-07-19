<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsLeaderboards;
use App\Models\Church;
use App\Models\ChurchSocial;
use App\Models\Person;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use PhpOffice\PhpWord\PhpWord;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportController extends Controller
{
    use BuildsLeaderboards;

    private array $platformLabels = ['semua' => 'Semua', 'youtube' => 'YouTube', 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'facebook' => 'Facebook'];

    private array $categoryLabels = ['gereja' => 'Akun Gereja', 'umum' => 'Akun Umum', 'personal' => 'Akun Personal'];

    private array $countField = ['youtube' => 'subscribers_count', 'instagram' => 'followers_count', 'tiktok' => 'followers_count', 'facebook' => 'followers_count'];

    private array $postField = ['youtube' => 'videos_count', 'instagram' => 'posts_count', 'tiktok' => 'posts_count'];

    private array $metricLabels = ['reach' => 'Followers/Subscribers', 'views' => 'Views', 'likes' => 'Likes', 'posts' => 'Post / Video'];

    public function leaderboardPreview(string $metric)
    {
        $sort = request()->query('sort') === 'value' ? 'value' : 'delta';
        $dataset = $this->leaderboardDataset($metric, $sort);

        $downloadUrl = route('export.leaderboard.download', array_filter(['metric' => $metric, 'format' => 'pdf', 'sort' => $sort === 'value' ? 'value' : null]));

        return $this->preview($dataset, $downloadUrl);
    }

    public function leaderboardDownload(string $metric, string $format): BinaryFileResponse|Response
    {
        $sort = request()->query('sort') === 'value' ? 'value' : 'delta';

        return $this->download($this->leaderboardDataset($metric, $sort), $format, 'leaderboard-'.$metric);
    }

    public function metricComparisonPreview()
    {
        $sort = request()->query('sort') === 'value' ? 'value' : 'delta';
        $dataset = $this->metricComparisonDataset($sort);

        $downloadUrl = route('export.metric-comparison.download', array_filter(['format' => 'pdf', 'sort' => $sort === 'value' ? 'value' : null]));

        return $this->preview($dataset, $downloadUrl);
    }

    public function metricComparisonDownload(string $format): BinaryFileResponse|Response
    {
        $sort = request()->query('sort') === 'value' ? 'value' : 'delta';

        return $this->download($this->metricComparisonDataset($sort), $format, 'perbandingan-metrik');
    }

    public function personalLeaderboardPreview(string $metric)
    {
        $sort = request()->query('sort') === 'value' ? 'value' : 'delta';
        $dataset = $this->leaderboardDatasetPersonal($metric, $sort);

        $downloadUrl = route('export.personal-leaderboard.download', array_filter(['metric' => $metric, 'format' => 'pdf', 'sort' => $sort === 'value' ? 'value' : null]));

        return $this->preview($dataset, $downloadUrl);
    }

    public function personalLeaderboardDownload(string $metric, string $format): BinaryFileResponse|Response
    {
        $sort = request()->query('sort') === 'value' ? 'value' : 'delta';

        return $this->download($this->leaderboardDatasetPersonal($metric, $sort), $format, 'leaderboard-personal-'.$metric);
    }

    public function personalMetricComparisonPreview()
    {
        $sort = request()->query('sort') === 'value' ? 'value' : 'delta';
        $dataset = $this->metricComparisonDatasetPersonal($sort);

        $downloadUrl = route('export.personal-metric-comparison.download', array_filter(['format' => 'pdf', 'sort' => $sort === 'value' ? 'value' : null]));

        return $this->preview($dataset, $downloadUrl);
    }

    public function personalMetricComparisonDownload(string $format): BinaryFileResponse|Response
    {
        $sort = request()->query('sort') === 'value' ? 'value' : 'delta';

        return $this->download($this->metricComparisonDatasetPersonal($sort), $format, 'perbandingan-metrik-personal');
    }

    public function directoryPreview()
    {
        $platform = request()->query('platform');
        $type = request()->query('type');
        $dataset = $this->directoryDataset($platform, $type);

        $downloadUrl = route('export.directory.download', array_filter(['format' => 'pdf', 'platform' => $platform, 'type' => $type]));

        return $this->preview($dataset, $downloadUrl);
    }

    public function directoryDownload(string $format): BinaryFileResponse|Response
    {
        $platform = request()->query('platform');
        $type = request()->query('type');
        $filename = 'direktori-akun'.($type ? "-{$type}" : '').($platform ? "-{$platform}" : '');

        return $this->download($this->directoryDataset($platform, $type), $format, $filename);
    }

    public function churchPreview(Church $church)
    {
        $dataset = $this->churchDataset($church);

        return $this->preview($dataset, route('export.church.download', ['church' => $church, 'format' => 'pdf']));
    }

    public function churchDownload(Church $church, string $format): BinaryFileResponse|Response
    {
        return $this->download($this->churchDataset($church), $format, 'gereja-'.$church->slug);
    }

    public function personPreview(Person $person)
    {
        $dataset = $this->personDataset($person);

        return $this->preview($dataset, route('export.person.download', ['person' => $person, 'format' => 'pdf']));
    }

    public function personDownload(Person $person, string $format): BinaryFileResponse|Response
    {
        return $this->download($this->personDataset($person), $format, 'personal-'.Str::slug($person->name));
    }

    public function socialHistoryPreview(ChurchSocial $social)
    {
        $dataset = $this->socialHistoryDataset($social);

        return $this->preview($dataset, route('export.social-history.download', ['social' => $social, 'format' => 'pdf']));
    }

    public function socialHistoryDownload(ChurchSocial $social, string $format): BinaryFileResponse|Response
    {
        return $this->download($this->socialHistoryDataset($social), $format, 'histori-'.Str::slug($social->display_handle));
    }

    public function platformComparisonPreview(string $platform)
    {
        abort_unless(isset($this->platformLabels[$platform]), 404);

        $metric = request()->query('metric', 'reach');
        abort_unless(isset($this->metricLabels[$metric]), 404);

        $sort = request()->query('sort') === 'value' ? 'value' : 'delta';
        $dataset = $this->platformComparisonDataset($platform, $metric, $sort);

        $downloadUrl = route('export.platform.download', array_filter([
            'platform' => $platform,
            'format' => 'pdf',
            'metric' => $metric === 'reach' ? null : $metric,
            'sort' => $sort === 'value' ? 'value' : null,
        ]));

        return $this->preview($dataset, $downloadUrl);
    }

    public function platformComparisonDownload(string $platform, string $format): BinaryFileResponse|Response
    {
        abort_unless(isset($this->platformLabels[$platform]), 404);

        $metric = request()->query('metric', 'reach');
        abort_unless(isset($this->metricLabels[$metric]), 404);

        $sort = request()->query('sort') === 'value' ? 'value' : 'delta';

        return $this->download($this->platformComparisonDataset($platform, $metric, $sort), $format, "perbandingan-{$platform}-{$metric}");
    }

    public function platformOverviewPreview(string $platform)
    {
        abort_unless(isset($this->platformLabels[$platform]), 404);

        $dataset = $this->platformOverviewDataset($platform);

        $downloadUrl = route('export.platform-overview.download', ['platform' => $platform, 'format' => 'pdf']);

        return $this->preview($dataset, $downloadUrl);
    }

    public function platformOverviewDownload(string $platform, string $format): BinaryFileResponse|Response
    {
        abort_unless(isset($this->platformLabels[$platform]), 404);

        return $this->download($this->platformOverviewDataset($platform), $format, "ringkasan-perbandingan-{$platform}");
    }

    public function personalPlatformComparisonPreview(string $platform)
    {
        abort_unless(isset($this->platformLabels[$platform]), 404);

        $metric = request()->query('metric', 'reach');
        abort_unless(isset($this->metricLabels[$metric]), 404);

        $sort = request()->query('sort') === 'value' ? 'value' : 'delta';
        $dataset = $this->platformComparisonDatasetPersonal($platform, $metric, $sort);

        $downloadUrl = route('export.personal-platform.download', array_filter([
            'platform' => $platform,
            'format' => 'pdf',
            'metric' => $metric === 'reach' ? null : $metric,
            'sort' => $sort === 'value' ? 'value' : null,
        ]));

        return $this->preview($dataset, $downloadUrl);
    }

    public function personalPlatformComparisonDownload(string $platform, string $format): BinaryFileResponse|Response
    {
        abort_unless(isset($this->platformLabels[$platform]), 404);

        $metric = request()->query('metric', 'reach');
        abort_unless(isset($this->metricLabels[$metric]), 404);

        $sort = request()->query('sort') === 'value' ? 'value' : 'delta';

        return $this->download($this->platformComparisonDatasetPersonal($platform, $metric, $sort), $format, "perbandingan-personal-{$platform}-{$metric}");
    }

    public function personalPlatformOverviewPreview(string $platform)
    {
        abort_unless(isset($this->platformLabels[$platform]), 404);

        $dataset = $this->platformOverviewDatasetPersonal($platform);

        $downloadUrl = route('export.personal-platform-overview.download', ['platform' => $platform, 'format' => 'pdf']);

        return $this->preview($dataset, $downloadUrl);
    }

    public function personalPlatformOverviewDownload(string $platform, string $format): BinaryFileResponse|Response
    {
        abort_unless(isset($this->platformLabels[$platform]), 404);

        return $this->download($this->platformOverviewDatasetPersonal($platform), $format, "ringkasan-perbandingan-personal-{$platform}");
    }

    public function analyticsPreview()
    {
        $churchId = request()->query('church_id');
        $platform = request()->query('platform');

        $dataset = $this->analyticsDataset($churchId, $platform);

        $downloadUrl = route('export.analytics.download', array_filter(['format' => 'pdf', 'church_id' => $churchId, 'platform' => $platform]));

        return $this->preview($dataset, $downloadUrl);
    }

    public function analyticsDownload(string $format): BinaryFileResponse|Response
    {
        $churchId = request()->query('church_id');
        $platform = request()->query('platform');

        $filename = 'analitik'.($churchId ? "-gereja-{$churchId}" : '').($platform ? "-{$platform}" : '');

        return $this->download($this->analyticsDataset($churchId, $platform), $format, $filename);
    }

    public function analyticsPersonalPreview()
    {
        $personId = request()->query('person_id');
        $platform = request()->query('platform');

        $dataset = $this->analyticsDatasetPersonal($personId, $platform);

        $downloadUrl = route('export.personal-analytics.download', array_filter(['format' => 'pdf', 'person_id' => $personId, 'platform' => $platform]));

        return $this->preview($dataset, $downloadUrl);
    }

    public function analyticsPersonalDownload(string $format): BinaryFileResponse|Response
    {
        $personId = request()->query('person_id');
        $platform = request()->query('platform');

        $filename = 'analitik-personal'.($personId ? "-{$personId}" : '').($platform ? "-{$platform}" : '');

        return $this->download($this->analyticsDatasetPersonal($personId, $platform), $format, $filename);
    }

    private function leaderboardDataset(string $metric, string $sortBy = 'delta'): array
    {
        $titles = $this->leaderboardTitles();

        abort_unless(isset($titles[$metric]), 404);

        [$socials, $field] = $this->metricDefinition($metric, $this->activeSocials());
        $rows = $this->buildLeaderboard($socials, $field, null, $sortBy);

        $title = $sortBy === 'value' ? $titles[$metric]['title'] : 'Pertumbuhan '.$titles[$metric]['title'];

        return [
            'title' => "{$title} Gereja",
            'subtitle' => $sortBy === 'value' ? 'Diurutkan berdasarkan nilai saat ini' : $titles[$metric]['subtitle'],
            'headers' => ['#', 'Gereja', 'Platform', 'Akun', 'Pertumbuhan', 'Saat Ini'],
            'rows' => $rows->values()->map(fn ($row, $i) => [
                $i + 1,
                $row['social']->church->name,
                $this->platformLabels[$row['social']->platform->value],
                $row['social']->display_handle,
                ($row['delta'] > 0 ? '+' : '').number_format($row['delta']),
                number_format($row['latest']),
            ])->all(),
        ];
    }

    private function leaderboardDatasetPersonal(string $metric, string $sortBy = 'delta'): array
    {
        $titles = $this->leaderboardTitles();

        abort_unless(isset($titles[$metric]), 404);

        [$socials, $field] = $this->metricDefinition($metric, $this->activeSocialsPersonal());
        $rows = $this->buildLeaderboard($socials, $field, null, $sortBy);

        $title = $sortBy === 'value' ? $titles[$metric]['title'] : 'Pertumbuhan '.$titles[$metric]['title'];

        return [
            'title' => "{$title} Personal",
            'subtitle' => $sortBy === 'value' ? 'Diurutkan berdasarkan nilai saat ini' : $titles[$metric]['subtitle'],
            'headers' => ['#', 'Nama', 'Platform', 'Akun', 'Pertumbuhan', 'Saat Ini'],
            'rows' => $rows->values()->map(fn ($row, $i) => [
                $i + 1,
                $row['social']->person->name,
                $this->platformLabels[$row['social']->platform->value],
                $row['social']->display_handle,
                ($row['delta'] > 0 ? '+' : '').number_format($row['delta']),
                number_format($row['latest']),
            ])->all(),
        ];
    }

    private function metricComparisonDataset(string $sortBy = 'delta'): array
    {
        $titles = $this->leaderboardTitles();
        $activeSocials = $this->activeSocials();

        $rows = [];

        foreach ($titles as $metric => $title) {
            [$socials, $field] = $this->metricDefinition($metric, $activeSocials);

            foreach ($this->buildLeaderboard($socials, $field, null, $sortBy)->values() as $i => $row) {
                $rows[] = [
                    $title['title'],
                    $i + 1,
                    $row['social']->church->name,
                    $this->platformLabels[$row['social']->platform->value],
                    $row['social']->display_handle,
                    ($row['delta'] > 0 ? '+' : '').number_format($row['delta']),
                    number_format($row['latest']),
                ];
            }
        }

        $subtitle = $sortBy === 'value'
            ? 'Peringkat akun media sosial berdasarkan nilai saat ini — subscriber/followers, views, likes, dan post, untuk semua gereja'
            : 'Peringkat akun media sosial berdasarkan pertumbuhan mingguan tertinggi — subscriber/followers, views, likes, dan post, untuk semua gereja';

        return [
            'title' => 'Perbandingan Metrik Gereja',
            'subtitle' => $subtitle,
            'headers' => ['Metrik', '#', 'Gereja', 'Platform', 'Akun', 'Pertumbuhan', 'Saat Ini'],
            'rows' => $rows,
        ];
    }

    private function metricComparisonDatasetPersonal(string $sortBy = 'delta'): array
    {
        $titles = $this->leaderboardTitles();
        $activeSocials = $this->activeSocialsPersonal();

        $rows = [];

        foreach ($titles as $metric => $title) {
            [$socials, $field] = $this->metricDefinition($metric, $activeSocials);

            foreach ($this->buildLeaderboard($socials, $field, null, $sortBy)->values() as $i => $row) {
                $rows[] = [
                    $title['title'],
                    $i + 1,
                    $row['social']->person->name,
                    $this->platformLabels[$row['social']->platform->value],
                    $row['social']->display_handle,
                    ($row['delta'] > 0 ? '+' : '').number_format($row['delta']),
                    number_format($row['latest']),
                ];
            }
        }

        $subtitle = $sortBy === 'value'
            ? 'Peringkat akun media sosial berdasarkan nilai saat ini — subscriber/followers, views, likes, dan post, untuk semua akun personal'
            : 'Peringkat akun media sosial berdasarkan pertumbuhan mingguan tertinggi — subscriber/followers, views, likes, dan post, untuk semua akun personal';

        return [
            'title' => 'Perbandingan Metrik Personal',
            'subtitle' => $subtitle,
            'headers' => ['Metrik', '#', 'Nama', 'Platform', 'Akun', 'Pertumbuhan', 'Saat Ini'],
            'rows' => $rows,
        ];
    }

    /**
     * $type narrows the export to one tab of the directory page — 'gereja' or 'personal' —
     * or includes both when null.
     */
    private function directoryDataset(?string $platform = null, ?string $type = null): array
    {
        $rows = [];

        if ($type !== 'personal') {
            $churches = Church::query()
                ->where('is_active', true)
                ->with(['socials' => fn ($q) => $q
                    ->where('is_active', true)
                    ->when($platform, fn ($query) => $query->where('platform', $platform)),
                ])
                ->orderBy('name')
                ->get();

            foreach ($churches as $church) {
                foreach ($church->socials as $social) {
                    $row = [$church->name];

                    if ($type === null) {
                        $row[] = $church->city ?? '—';
                    }

                    $row[] = $this->categoryLabels[$social->category->value];
                    $row[] = $this->platformLabels[$social->platform->value];
                    $row[] = $social->display_handle;

                    $rows[] = $row;
                }
            }
        }

        if ($type !== 'gereja') {
            $people = Person::query()
                ->where('is_active', true)
                ->with(['socials' => fn ($q) => $q
                    ->where('is_active', true)
                    ->when($platform, fn ($query) => $query->where('platform', $platform)),
                ])
                ->orderBy('name')
                ->get();

            foreach ($people as $person) {
                foreach ($person->socials as $social) {
                    $row = [$person->name];

                    if ($type === null) {
                        $row[] = '—';
                    }

                    $row[] = $this->categoryLabels[$social->category->value];
                    $row[] = $this->platformLabels[$social->platform->value];
                    $row[] = $social->display_handle;

                    $rows[] = $row;
                }
            }
        }

        $scopeLabel = match ($type) {
            'gereja' => 'semua gereja',
            'personal' => 'semua akun personal',
            default => 'semua gereja dan akun personal',
        };

        $subtitle = $platform
            ? "Daftar akun {$this->platformLabels[$platform]} {$scopeLabel}"
            : "Daftar lengkap handle media sosial {$scopeLabel}";

        $headers = match ($type) {
            'personal' => ['Nama', 'Kategori', 'Platform', 'Akun'],
            'gereja' => ['Gereja', 'Kota', 'Kategori', 'Platform', 'Akun'],
            default => ['Nama', 'Kota', 'Kategori', 'Platform', 'Akun'],
        };

        return [
            'title' => 'Direktori Akun',
            'subtitle' => $subtitle,
            'headers' => $headers,
            'rows' => $rows,
        ];
    }

    private function churchDataset(Church $church): array
    {
        $church->load(['socials' => fn ($q) => $q->where('is_active', true)->with('latestStat')]);

        $statusLabels = ['success' => 'Berhasil', 'failed' => 'Gagal', 'pending' => 'Menunggu'];

        $rows = $church->socials->map(function ($social) use ($statusLabels) {
            $latest = $social->latestStat;
            $field = $this->countField[$social->platform->value];

            $status = ! $social->is_auto_fetch
                ? 'Manual'
                : ($statusLabels[$social->last_fetch_status] ?? ($social->last_fetched_at ? 'Berhasil' : 'Menunggu'));

            return [
                $this->platformLabels[$social->platform->value],
                $this->categoryLabels[$social->category->value],
                $social->display_handle,
                $latest ? number_format($latest->{$field} ?? 0) : '—',
                $latest ? number_format($latest->following_count ?? 0) : '—',
                $latest ? number_format($latest->posts_count ?? $latest->videos_count ?? 0) : '—',
                $latest && $latest->views_count ? number_format($latest->views_count) : '—',
                $latest && $latest->likes_count ? number_format($latest->likes_count) : '—',
                $status,
            ];
        })->all();

        return [
            'title' => $church->name,
            'subtitle' => $church->city ? "Data akun media sosial — {$church->city}" : 'Data akun media sosial',
            'headers' => ['Platform', 'Kategori', 'Akun', 'Followers/Subs', 'Following', 'Post/Video', 'Views', 'Likes', 'Status'],
            'rows' => $rows,
        ];
    }

    private function personDataset(Person $person): array
    {
        $person->load(['socials' => fn ($q) => $q->where('is_active', true)->with('latestStat')]);

        $statusLabels = ['success' => 'Berhasil', 'failed' => 'Gagal', 'pending' => 'Menunggu'];

        $rows = $person->socials->map(function ($social) use ($statusLabels) {
            $latest = $social->latestStat;
            $field = $this->countField[$social->platform->value];

            $status = ! $social->is_auto_fetch
                ? 'Manual'
                : ($statusLabels[$social->last_fetch_status] ?? ($social->last_fetched_at ? 'Berhasil' : 'Menunggu'));

            return [
                $this->platformLabels[$social->platform->value],
                $social->display_handle,
                $latest ? number_format($latest->{$field} ?? 0) : '—',
                $latest ? number_format($latest->following_count ?? 0) : '—',
                $latest ? number_format($latest->posts_count ?? $latest->videos_count ?? 0) : '—',
                $latest && $latest->views_count ? number_format($latest->views_count) : '—',
                $latest && $latest->likes_count ? number_format($latest->likes_count) : '—',
                $status,
            ];
        })->all();

        return [
            'title' => $person->name,
            'subtitle' => $person->city ? "Data akun media sosial — {$person->city}" : 'Data akun media sosial',
            'headers' => ['Platform', 'Akun', 'Followers/Subs', 'Following', 'Post/Video', 'Views', 'Likes', 'Status'],
            'rows' => $rows,
        ];
    }

    private function socialHistoryDataset(ChurchSocial $social): array
    {
        $owner = $social->church ?? $social->person;
        $field = $this->countField[$social->platform->value];

        $rows = $social->stats()->orderByDesc('recorded_at')->get()->map(fn ($stat) => [
            $stat->recorded_at->translatedFormat('d M Y'),
            number_format($stat->{$field} ?? 0),
            number_format($stat->following_count ?? 0),
            number_format($stat->posts_count ?? $stat->videos_count ?? 0),
            $stat->views_count ? number_format($stat->views_count) : '—',
            $stat->likes_count ? number_format($stat->likes_count) : '—',
        ])->all();

        return [
            'title' => "Histori {$social->display_handle}",
            'subtitle' => "{$this->platformLabels[$social->platform->value]} — {$owner->name}",
            'headers' => ['Tanggal', 'Followers/Subs', 'Following', 'Post/Video', 'Views', 'Likes'],
            'rows' => $rows,
        ];
    }

    private function platformOverviewDataset(string $platform): array
    {
        $applicableMetrics = collect($this->metricPlatforms())
            ->filter(fn ($platforms) => in_array($platform, $platforms, true))
            ->keys();

        $rowsByMetric = $applicableMetrics->mapWithKeys(fn ($metric) => [
            $metric => $this->metricComparisonRows($metric, $platform)->keyBy(fn ($row) => $row['church']->id),
        ]);

        $churches = $rowsByMetric
            ->flatMap(fn ($rows) => $rows->pluck('church'))
            ->unique('id')
            ->sortBy('name')
            ->values();

        $headers = ['Gereja'];
        foreach ($applicableMetrics as $metric) {
            $valueHeader = match (true) {
                $metric !== 'reach' => $this->metricLabels[$metric],
                $platform === 'youtube' => 'Subscribers',
                $platform === 'semua' => 'Jangkauan',
                default => 'Followers',
            };
            $headers[] = $valueHeader;
            $headers[] = "Pertumbuhan {$valueHeader}";
        }

        $rows = $churches->map(function ($church) use ($applicableMetrics, $rowsByMetric) {
            $row = [$church->name];

            foreach ($applicableMetrics as $metric) {
                $entry = $rowsByMetric[$metric]->get($church->id);
                $row[] = $entry ? number_format($entry['value']) : '—';
                $row[] = ($entry && $entry['delta'] !== null) ? (($entry['delta'] > 0 ? '+' : '').number_format($entry['delta'])) : '—';
            }

            return $row;
        })->all();

        $platformLabel = $this->platformLabels[$platform];
        $metricNames = $applicableMetrics->map(fn ($m) => $this->metricLabels[$m])->implode(', ');

        return [
            'title' => "Ringkasan Perbandingan {$platformLabel} Gereja",
            'subtitle' => "{$metricNames} — semua gereja, diurutkan berdasarkan nama.",
            'headers' => $headers,
            'rows' => $rows,
        ];
    }

    private function platformComparisonDataset(string $platform, string $metric, string $sortBy = 'delta'): array
    {
        $rows = $this->metricComparisonRows($metric, $platform, $sortBy);
        $platformLabel = $this->platformLabels[$platform];
        $valueHeader = match (true) {
            $metric !== 'reach' => $this->metricLabels[$metric],
            $platform === 'youtube' => 'Subscribers',
            $platform === 'semua' => 'Jangkauan',
            default => 'Followers',
        };

        $subtitle = $sortBy === 'delta'
            ? "Peringkat gereja berdasarkan pertumbuhan mingguan {$valueHeader} {$platformLabel}"
            : "Peringkat gereja berdasarkan {$valueHeader} {$platformLabel} saat ini";

        return [
            'title' => "Perbandingan {$valueHeader} {$platformLabel} Gereja",
            'subtitle' => $subtitle,
            'headers' => ['#', 'Gereja', $valueHeader, 'Pertumbuhan Mingguan'],
            'rows' => $rows->values()->map(fn ($row, $i) => [
                $i + 1,
                $row['label'],
                number_format($row['value']),
                $row['delta'] === null ? '—' : ($row['delta'] > 0 ? '+' : '').number_format($row['delta']),
            ])->all(),
        ];
    }

    private function platformOverviewDatasetPersonal(string $platform): array
    {
        $applicableMetrics = collect($this->metricPlatforms())
            ->filter(fn ($platforms) => in_array($platform, $platforms, true))
            ->keys();

        $rowsByMetric = $applicableMetrics->mapWithKeys(fn ($metric) => [
            $metric => $this->metricComparisonRowsPersonal($metric, $platform)->keyBy(fn ($row) => $row['person']->id),
        ]);

        $people = $rowsByMetric
            ->flatMap(fn ($rows) => $rows->pluck('person'))
            ->unique('id')
            ->sortBy('name')
            ->values();

        $headers = ['Nama'];
        foreach ($applicableMetrics as $metric) {
            $valueHeader = match (true) {
                $metric !== 'reach' => $this->metricLabels[$metric],
                $platform === 'youtube' => 'Subscribers',
                $platform === 'semua' => 'Jangkauan',
                default => 'Followers',
            };
            $headers[] = $valueHeader;
            $headers[] = "Pertumbuhan {$valueHeader}";
        }

        $rows = $people->map(function ($person) use ($applicableMetrics, $rowsByMetric) {
            $row = [$person->name];

            foreach ($applicableMetrics as $metric) {
                $entry = $rowsByMetric[$metric]->get($person->id);
                $row[] = $entry ? number_format($entry['value']) : '—';
                $row[] = ($entry && $entry['delta'] !== null) ? (($entry['delta'] > 0 ? '+' : '').number_format($entry['delta'])) : '—';
            }

            return $row;
        })->all();

        $platformLabel = $this->platformLabels[$platform];
        $metricNames = $applicableMetrics->map(fn ($m) => $this->metricLabels[$m])->implode(', ');

        return [
            'title' => "Ringkasan Perbandingan {$platformLabel} Personal",
            'subtitle' => "{$metricNames} — semua akun personal, diurutkan berdasarkan nama.",
            'headers' => $headers,
            'rows' => $rows,
        ];
    }

    private function platformComparisonDatasetPersonal(string $platform, string $metric, string $sortBy = 'delta'): array
    {
        $rows = $this->metricComparisonRowsPersonal($metric, $platform, $sortBy);
        $platformLabel = $this->platformLabels[$platform];
        $valueHeader = match (true) {
            $metric !== 'reach' => $this->metricLabels[$metric],
            $platform === 'youtube' => 'Subscribers',
            $platform === 'semua' => 'Jangkauan',
            default => 'Followers',
        };

        $subtitle = $sortBy === 'delta'
            ? "Peringkat personal berdasarkan pertumbuhan mingguan {$valueHeader} {$platformLabel}"
            : "Peringkat personal berdasarkan {$valueHeader} {$platformLabel} saat ini";

        return [
            'title' => "Perbandingan {$valueHeader} {$platformLabel} Personal",
            'subtitle' => $subtitle,
            'headers' => ['#', 'Nama', $valueHeader, 'Pertumbuhan Mingguan'],
            'rows' => $rows->values()->map(fn ($row, $i) => [
                $i + 1,
                $row['label'],
                number_format($row['value']),
                $row['delta'] === null ? '—' : ($row['delta'] > 0 ? '+' : '').number_format($row['delta']),
            ])->all(),
        ];
    }

    private function analyticsDataset(?string $churchId, ?string $platform): array
    {
        $churches = Church::query()
            ->where('is_active', true)
            ->with(['socials' => fn ($q) => $q->where('is_active', true)->with('latestStat')])
            ->when($churchId, fn ($q) => $q->where('id', $churchId))
            ->orderBy('name')
            ->get()
            ->when($platform, fn ($collection) => $collection->filter(
                fn ($church) => $church->socials->contains(fn ($social) => $social->platform->value === $platform)
            ));

        $rows = $churches->map(function ($church) use ($platform) {
            $displaySocials = $platform
                ? $church->socials->filter(fn ($s) => $s->platform->value === $platform)
                : $church->socials;

            $socialsByCategory = $displaySocials->groupBy(fn ($s) => $s->category->value);

            $reach = $displaySocials->sum(fn ($s) => $s->latestStat?->{$this->countField[$s->platform->value]} ?? 0);
            $views = $displaySocials->sum(fn ($s) => $s->latestStat?->views_count ?? 0);
            $likes = $displaySocials->sum(fn ($s) => $s->latestStat?->likes_count ?? 0);
            $posts = $displaySocials->sum(
                fn ($s) => isset($this->postField[$s->platform->value]) ? ($s->latestStat?->{$this->postField[$s->platform->value]} ?? 0) : 0
            );

            return [
                $church->name,
                $church->city ?? '—',
                $socialsByCategory->get('gereja', collect())->count(),
                $socialsByCategory->get('umum', collect())->count(),
                number_format($reach),
                $views ? number_format($views) : '—',
                $likes ? number_format($likes) : '—',
                $posts ? number_format($posts) : '—',
            ];
        })->all();

        $filterParts = array_filter([
            $churchId ? $churches->first()?->name : null,
            $platform ? ($this->platformLabels[$platform] ?? null) : null,
        ]);

        $subtitle = $filterParts ? 'Filter: '.implode(', ', $filterParts) : 'Semua gereja, semua media sosial';

        return [
            'title' => 'Analitik & Grafik',
            'subtitle' => $subtitle,
            'headers' => ['Gereja', 'Kota', 'Akun Gereja', 'Akun Umum', 'Total Jangkauan', 'Total Views', 'Total Likes', 'Total Post'],
            'rows' => $rows,
        ];
    }

    private function analyticsDatasetPersonal(?string $personId, ?string $platform): array
    {
        $people = Person::query()
            ->where('is_active', true)
            ->with(['socials' => fn ($q) => $q->where('is_active', true)->with('latestStat')])
            ->when($personId, fn ($q) => $q->where('id', $personId))
            ->orderBy('name')
            ->get()
            ->when($platform, fn ($collection) => $collection->filter(
                fn ($person) => $person->socials->contains(fn ($social) => $social->platform->value === $platform)
            ));

        $rows = $people->map(function ($person) use ($platform) {
            $displaySocials = $platform
                ? $person->socials->filter(fn ($s) => $s->platform->value === $platform)
                : $person->socials;

            $reach = $displaySocials->sum(fn ($s) => $s->latestStat?->{$this->countField[$s->platform->value]} ?? 0);
            $views = $displaySocials->sum(fn ($s) => $s->latestStat?->views_count ?? 0);
            $likes = $displaySocials->sum(fn ($s) => $s->latestStat?->likes_count ?? 0);
            $posts = $displaySocials->sum(
                fn ($s) => isset($this->postField[$s->platform->value]) ? ($s->latestStat?->{$this->postField[$s->platform->value]} ?? 0) : 0
            );

            return [
                $person->name,
                $displaySocials->count(),
                number_format($reach),
                $views ? number_format($views) : '—',
                $likes ? number_format($likes) : '—',
                $posts ? number_format($posts) : '—',
            ];
        })->all();

        $filterParts = array_filter([
            $personId ? $people->first()?->name : null,
            $platform ? ($this->platformLabels[$platform] ?? null) : null,
        ]);

        $subtitle = $filterParts ? 'Filter: '.implode(', ', $filterParts) : 'Semua akun personal, semua media sosial';

        return [
            'title' => 'Analitik & Grafik Personal',
            'subtitle' => $subtitle,
            'headers' => ['Nama', 'Jumlah Akun', 'Total Jangkauan', 'Total Views', 'Total Likes', 'Total Post'],
            'rows' => $rows,
        ];
    }

    private function preview(array $dataset, string $pdfDownloadUrl)
    {
        $data = [
            'dataset' => $dataset,
            'footer' => $this->footerText(),
            'pdfDownloadUrl' => $pdfDownloadUrl,
            'wordDownloadUrl' => str_replace('/pdf', '/word', $pdfDownloadUrl),
            'excelDownloadUrl' => str_replace('/pdf', '/excel', $pdfDownloadUrl),
        ];

        // The export button loads this fragment into a modal via fetch; a direct visit gets the full page.
        return request()->boolean('partial')
            ? view('exports._content', $data)
            : view('exports.preview', $data);
    }

    private function download(array $dataset, string $format, string $filenameBase): BinaryFileResponse|Response
    {
        $footer = $this->footerText();
        $filenameBase = Str::slug(config('app.name')).'_'.$filenameBase.'_'.Carbon::now('Asia/Jakarta')->format('Y-m-d');

        return match ($format) {
            'pdf' => Pdf::setOptions(['isPhpEnabled' => true])
                ->loadView('exports.pdf', ['dataset' => $dataset, 'footer' => $footer])
                ->download("{$filenameBase}.pdf"),
            'word' => $this->downloadWord($dataset, $footer, "{$filenameBase}.docx"),
            'excel' => $this->downloadExcel($dataset, $footer, "{$filenameBase}.xlsx"),
            default => abort(404),
        };
    }

    private function footerText(): string
    {
        $text = 'Diunduh dari Churchnalytics pada '.Carbon::now('Asia/Jakarta')->translatedFormat('d M Y H:i').' WIB';

        if (auth()->check()) {
            $text .= ' oleh '.auth()->user()->name;
        }

        return $text;
    }

    private function downloadWord(array $dataset, string $footer, string $filename): BinaryFileResponse
    {
        $phpWord = new PhpWord;
        $section = $phpWord->addSection();

        $section->addText($dataset['title'], ['bold' => true, 'size' => 16]);
        if ($dataset['subtitle']) {
            $section->addText($dataset['subtitle'], ['size' => 10, 'color' => '666666']);
        }
        $section->addTextBreak(1);

        $columnWidth = (int) (9000 / max(count($dataset['headers']), 1));
        $table = $section->addTable(['borderSize' => 6, 'borderColor' => 'cccccc', 'cellMargin' => 80]);

        $table->addRow();
        foreach ($dataset['headers'] as $header) {
            $table->addCell($columnWidth, ['bgColor' => 'f1f5f9'])->addText($header, ['bold' => true, 'size' => 9]);
        }

        foreach ($dataset['rows'] as $row) {
            $table->addRow();
            foreach ($row as $cell) {
                $table->addCell($columnWidth)->addText((string) $cell, ['size' => 9]);
            }
        }

        $footerSection = $section->addFooter();
        $footerSection->addText($footer, ['size' => 8, 'color' => '999999']);

        $tempPath = tempnam(sys_get_temp_dir(), 'export').'.docx';
        WordIOFactory::createWriter($phpWord, 'Word2007')->save($tempPath);

        return response()->download($tempPath, $filename)->deleteFileAfterSend(true);
    }

    private function downloadExcel(array $dataset, string $footer, string $filename): BinaryFileResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Data');

        $sheet->fromArray($dataset['headers'], null, 'A1');
        $sheet->fromArray($dataset['rows'], null, 'A2');

        $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($dataset['headers']));
        $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true);

        foreach (range('A', $lastColumn) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $footerRow = count($dataset['rows']) + 3;
        $sheet->setCellValue("A{$footerRow}", $footer);
        $sheet->getStyle("A{$footerRow}")->getFont()->setItalic(true)->setSize(9);

        $tempPath = tempnam(sys_get_temp_dir(), 'export').'.xlsx';
        (new Xlsx($spreadsheet))->save($tempPath);

        return response()->download($tempPath, $filename)->deleteFileAfterSend(true);
    }
}

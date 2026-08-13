<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsLeaderboards;
use App\Models\AppSetting;
use App\Models\Church;
use App\Models\ChurchSocial;
use App\Models\Conference;
use App\Models\Institution;
use App\Models\Person;
use App\Models\Union;
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

    // Enabled-platforms-only + 'semua' — a property default can't call a method, so this
    // is built in the constructor below instead of at declaration time. A disabled
    // platform therefore also 404s out of every isset($this->platformLabels[...]) guard
    // in this file, same as it disappears everywhere else.
    private array $platformLabels;

    private array $categoryLabels = ['gereja' => 'Akun Gereja', 'umum' => 'Akun Umum', 'personal' => 'Akun Personal'];

    private array $countField = ['youtube' => 'subscribers_count', 'instagram' => 'followers_count', 'tiktok' => 'followers_count', 'facebook' => 'followers_count', 'x' => 'followers_count'];

    private array $postField = ['youtube' => 'videos_count', 'instagram' => 'posts_count', 'tiktok' => 'posts_count', 'facebook' => 'recent_posts_count', 'x' => 'posts_count'];

    // Instagram/TikTok are recent-sample view counts (last ~10-12 posts/videos), not a lifetime
    // total like YouTube's views_count. Facebook and X have no view-count field scraped at all —
    // a lookup miss falls through to 'views_count' (always null on those rows) via ?? below.
    private array $viewsField = ['youtube' => 'views_count', 'instagram' => 'recent_reels_views', 'tiktok' => 'recent_video_plays'];

    private array $metricLabels = ['reach' => 'Followers/Subscribers', 'views' => 'Views', 'likes' => 'Likes', 'posts' => 'Post / Video'];

    public function __construct()
    {
        $this->platformLabels = ['semua' => 'Semua'] + AppSetting::current()->enabledPlatformLabels();
    }

    public function leaderboardPreview(string $metric)
    {
        $sort = request()->query('sort') === 'value' ? 'value' : 'delta';
        $category = request()->query('category');
        $dataset = $this->leaderboardDataset($metric, $sort, $category);

        $downloadUrl = route('export.leaderboard.download', array_filter(['metric' => $metric, 'format' => 'pdf', 'sort' => $sort === 'value' ? 'value' : null, 'category' => $category]));

        return $this->preview($dataset, $downloadUrl);
    }

    public function leaderboardDownload(string $metric, string $format): BinaryFileResponse|Response
    {
        $sort = request()->query('sort') === 'value' ? 'value' : 'delta';
        $category = request()->query('category');

        return $this->download($this->leaderboardDataset($metric, $sort, $category), $format, 'leaderboard-'.$metric);
    }

    public function metricComparisonPreview()
    {
        $sort = request()->query('sort') === 'value' ? 'value' : 'delta';
        $category = request()->query('category');
        $dataset = $this->metricComparisonDataset($sort, $category);

        $downloadUrl = route('export.metric-comparison.download', array_filter(['format' => 'pdf', 'sort' => $sort === 'value' ? 'value' : null, 'category' => $category]));

        return $this->preview($dataset, $downloadUrl);
    }

    public function metricComparisonDownload(string $format): BinaryFileResponse|Response
    {
        $sort = request()->query('sort') === 'value' ? 'value' : 'delta';
        $category = request()->query('category');

        return $this->download($this->metricComparisonDataset($sort, $category), $format, 'perbandingan-metrik');
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

    public function institutionLeaderboardPreview(string $metric)
    {
        $sort = request()->query('sort') === 'value' ? 'value' : 'delta';
        $dataset = $this->leaderboardDatasetInstitution($metric, $sort);

        $downloadUrl = route('export.institution-leaderboard.download', array_filter(['metric' => $metric, 'format' => 'pdf', 'sort' => $sort === 'value' ? 'value' : null]));

        return $this->preview($dataset, $downloadUrl);
    }

    public function institutionLeaderboardDownload(string $metric, string $format): BinaryFileResponse|Response
    {
        $sort = request()->query('sort') === 'value' ? 'value' : 'delta';

        return $this->download($this->leaderboardDatasetInstitution($metric, $sort), $format, 'leaderboard-institusi-'.$metric);
    }

    public function institutionMetricComparisonPreview()
    {
        $sort = request()->query('sort') === 'value' ? 'value' : 'delta';
        $dataset = $this->metricComparisonDatasetInstitution($sort);

        $downloadUrl = route('export.institution-metric-comparison.download', array_filter(['format' => 'pdf', 'sort' => $sort === 'value' ? 'value' : null]));

        return $this->preview($dataset, $downloadUrl);
    }

    public function institutionMetricComparisonDownload(string $format): BinaryFileResponse|Response
    {
        $sort = request()->query('sort') === 'value' ? 'value' : 'delta';

        return $this->download($this->metricComparisonDatasetInstitution($sort), $format, 'perbandingan-metrik-institusi');
    }

    public function institutionPlatformComparisonPreview(string $platform)
    {
        abort_unless(isset($this->platformLabels[$platform]), 404);

        $metric = request()->query('metric', 'reach');
        abort_unless(isset($this->metricLabels[$metric]), 404);

        $sort = request()->query('sort') === 'value' ? 'value' : 'delta';
        $dataset = $this->platformComparisonDatasetInstitution($platform, $metric, $sort);

        $downloadUrl = route('export.institution-platform.download', array_filter([
            'platform' => $platform,
            'format' => 'pdf',
            'metric' => $metric === 'reach' ? null : $metric,
            'sort' => $sort === 'value' ? 'value' : null,
        ]));

        return $this->preview($dataset, $downloadUrl);
    }

    public function institutionPlatformComparisonDownload(string $platform, string $format): BinaryFileResponse|Response
    {
        abort_unless(isset($this->platformLabels[$platform]), 404);

        $metric = request()->query('metric', 'reach');
        abort_unless(isset($this->metricLabels[$metric]), 404);

        $sort = request()->query('sort') === 'value' ? 'value' : 'delta';

        return $this->download($this->platformComparisonDatasetInstitution($platform, $metric, $sort), $format, "perbandingan-institusi-{$platform}-{$metric}");
    }

    public function institutionPlatformOverviewPreview(string $platform)
    {
        abort_unless(isset($this->platformLabels[$platform]), 404);

        $dataset = $this->platformOverviewDatasetInstitution($platform);

        $downloadUrl = route('export.institution-platform-overview.download', ['platform' => $platform, 'format' => 'pdf']);

        return $this->preview($dataset, $downloadUrl);
    }

    public function institutionPlatformOverviewDownload(string $platform, string $format): BinaryFileResponse|Response
    {
        abort_unless(isset($this->platformLabels[$platform]), 404);

        return $this->download($this->platformOverviewDatasetInstitution($platform), $format, "ringkasan-perbandingan-institusi-{$platform}");
    }

    public function analyticsInstitutionPreview()
    {
        $institutionId = request()->query('institution_id');
        $platform = request()->query('platform');

        $dataset = $this->analyticsDatasetInstitution($institutionId, $platform);

        $downloadUrl = route('export.institution-analytics.download', array_filter(['format' => 'pdf', 'institution_id' => $institutionId, 'platform' => $platform]));

        return $this->preview($dataset, $downloadUrl);
    }

    public function analyticsInstitutionDownload(string $format): BinaryFileResponse|Response
    {
        $institutionId = request()->query('institution_id');
        $platform = request()->query('platform');

        $filename = 'analitik-institusi'.($institutionId ? "-{$institutionId}" : '').($platform ? "-{$platform}" : '');

        return $this->download($this->analyticsDatasetInstitution($institutionId, $platform), $format, $filename);
    }

    public function organizationLeaderboardPreview(string $metric)
    {
        $sort = request()->query('sort') === 'value' ? 'value' : 'delta';
        $dataset = $this->leaderboardDatasetOrganization($metric, $sort);

        $downloadUrl = route('export.organization-leaderboard.download', array_filter(['metric' => $metric, 'format' => 'pdf', 'sort' => $sort === 'value' ? 'value' : null]));

        return $this->preview($dataset, $downloadUrl);
    }

    public function organizationLeaderboardDownload(string $metric, string $format): BinaryFileResponse|Response
    {
        $sort = request()->query('sort') === 'value' ? 'value' : 'delta';

        return $this->download($this->leaderboardDatasetOrganization($metric, $sort), $format, 'leaderboard-organisasi-'.$metric);
    }

    public function organizationMetricComparisonPreview()
    {
        $sort = request()->query('sort') === 'value' ? 'value' : 'delta';
        $dataset = $this->metricComparisonDatasetOrganization($sort);

        $downloadUrl = route('export.organization-metric-comparison.download', array_filter(['format' => 'pdf', 'sort' => $sort === 'value' ? 'value' : null]));

        return $this->preview($dataset, $downloadUrl);
    }

    public function organizationMetricComparisonDownload(string $format): BinaryFileResponse|Response
    {
        $sort = request()->query('sort') === 'value' ? 'value' : 'delta';

        return $this->download($this->metricComparisonDatasetOrganization($sort), $format, 'perbandingan-metrik-organisasi');
    }

    public function organizationPlatformComparisonPreview(string $platform)
    {
        abort_unless(isset($this->platformLabels[$platform]), 404);

        $metric = request()->query('metric', 'reach');
        abort_unless(isset($this->metricLabels[$metric]), 404);

        $sort = request()->query('sort') === 'value' ? 'value' : 'delta';
        $dataset = $this->platformComparisonDatasetOrganization($platform, $metric, $sort);

        $downloadUrl = route('export.organization-platform.download', array_filter([
            'platform' => $platform,
            'format' => 'pdf',
            'metric' => $metric === 'reach' ? null : $metric,
            'sort' => $sort === 'value' ? 'value' : null,
        ]));

        return $this->preview($dataset, $downloadUrl);
    }

    public function organizationPlatformComparisonDownload(string $platform, string $format): BinaryFileResponse|Response
    {
        abort_unless(isset($this->platformLabels[$platform]), 404);

        $metric = request()->query('metric', 'reach');
        abort_unless(isset($this->metricLabels[$metric]), 404);

        $sort = request()->query('sort') === 'value' ? 'value' : 'delta';

        return $this->download($this->platformComparisonDatasetOrganization($platform, $metric, $sort), $format, "perbandingan-organisasi-{$platform}-{$metric}");
    }

    public function organizationPlatformOverviewPreview(string $platform)
    {
        abort_unless(isset($this->platformLabels[$platform]), 404);

        $dataset = $this->platformOverviewDatasetOrganization($platform);

        $downloadUrl = route('export.organization-platform-overview.download', ['platform' => $platform, 'format' => 'pdf']);

        return $this->preview($dataset, $downloadUrl);
    }

    public function organizationPlatformOverviewDownload(string $platform, string $format): BinaryFileResponse|Response
    {
        abort_unless(isset($this->platformLabels[$platform]), 404);

        return $this->download($this->platformOverviewDatasetOrganization($platform), $format, "ringkasan-perbandingan-organisasi-{$platform}");
    }

    public function analyticsOrganizationPreview()
    {
        $organizationKey = request()->query('organization_id');
        $platform = request()->query('platform');

        $dataset = $this->analyticsDatasetOrganization($organizationKey, $platform);

        $downloadUrl = route('export.organization-analytics.download', array_filter(['format' => 'pdf', 'organization_id' => $organizationKey, 'platform' => $platform]));

        return $this->preview($dataset, $downloadUrl);
    }

    public function analyticsOrganizationDownload(string $format): BinaryFileResponse|Response
    {
        $organizationKey = request()->query('organization_id');
        $platform = request()->query('platform');

        $filename = 'analitik-organisasi'.($organizationKey ? '-'.Str::slug($organizationKey) : '').($platform ? "-{$platform}" : '');

        return $this->download($this->analyticsDatasetOrganization($organizationKey, $platform), $format, $filename);
    }

    public function institutionPreview(Institution $institution)
    {
        $dataset = $this->institutionDataset($institution);

        return $this->preview($dataset, route('export.institution.download', ['institution' => $institution, 'format' => 'pdf']));
    }

    public function institutionDownload(Institution $institution, string $format): BinaryFileResponse|Response
    {
        return $this->download($this->institutionDataset($institution), $format, 'institusi-'.$institution->slug);
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
        $category = request()->query('category');
        $dataset = $this->platformComparisonDataset($platform, $metric, $sort, $category);

        $downloadUrl = route('export.platform.download', array_filter([
            'platform' => $platform,
            'format' => 'pdf',
            'metric' => $metric === 'reach' ? null : $metric,
            'sort' => $sort === 'value' ? 'value' : null,
            'category' => $category,
        ]));

        return $this->preview($dataset, $downloadUrl);
    }

    public function platformComparisonDownload(string $platform, string $format): BinaryFileResponse|Response
    {
        abort_unless(isset($this->platformLabels[$platform]), 404);

        $metric = request()->query('metric', 'reach');
        abort_unless(isset($this->metricLabels[$metric]), 404);

        $sort = request()->query('sort') === 'value' ? 'value' : 'delta';
        $category = request()->query('category');

        return $this->download($this->platformComparisonDataset($platform, $metric, $sort, $category), $format, "perbandingan-{$platform}-{$metric}");
    }

    public function platformOverviewPreview(string $platform)
    {
        abort_unless(isset($this->platformLabels[$platform]), 404);

        $category = request()->query('category');
        $dataset = $this->platformOverviewDataset($platform, $category);

        $downloadUrl = route('export.platform-overview.download', array_filter(['platform' => $platform, 'format' => 'pdf', 'category' => $category]));

        return $this->preview($dataset, $downloadUrl);
    }

    public function platformOverviewDownload(string $platform, string $format): BinaryFileResponse|Response
    {
        abort_unless(isset($this->platformLabels[$platform]), 404);

        $category = request()->query('category');

        return $this->download($this->platformOverviewDataset($platform, $category), $format, "ringkasan-perbandingan-{$platform}");
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
        $category = request()->query('category');

        $dataset = $this->analyticsDataset($churchId, $platform, $category);

        $downloadUrl = route('export.analytics.download', array_filter(['format' => 'pdf', 'church_id' => $churchId, 'platform' => $platform, 'category' => $category]));

        return $this->preview($dataset, $downloadUrl);
    }

    public function analyticsDownload(string $format): BinaryFileResponse|Response
    {
        $churchId = request()->query('church_id');
        $platform = request()->query('platform');
        $category = request()->query('category');

        $filename = 'analitik'.($churchId ? "-gereja-{$churchId}" : '').($platform ? "-{$platform}" : '').($category ? "-{$category}" : '');

        return $this->download($this->analyticsDataset($churchId, $platform, $category), $format, $filename);
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

    private function leaderboardDataset(string $metric, string $sortBy = 'delta', ?string $category = null): array
    {
        $titles = $this->leaderboardTitles();

        abort_unless(isset($titles[$metric]), 404);

        [$socials, $field] = $this->metricDefinition($metric, $this->activeSocials(category: $category));
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
            'summary' => [['label' => "Total {$titles[$metric]['title']}", 'value' => number_format($rows->sum('latest'))]],
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
            'summary' => [['label' => "Total {$titles[$metric]['title']}", 'value' => number_format($rows->sum('latest'))]],
        ];
    }

    private function metricComparisonDataset(string $sortBy = 'delta', ?string $category = null): array
    {
        $titles = $this->leaderboardTitles();
        $activeSocials = $this->activeSocials(category: $category);

        $rows = [];
        $totals = [];

        foreach ($titles as $metric => $title) {
            [$socials, $field] = $this->metricDefinition($metric, $activeSocials);
            $metricRows = $this->buildLeaderboard($socials, $field, null, $sortBy);
            $totals[$metric] = $metricRows->sum('latest');

            foreach ($metricRows->values() as $i => $row) {
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
            'summary' => collect($titles)->map(fn ($title, $metric) => ['label' => "Total {$title['title']}", 'value' => number_format($totals[$metric] ?? 0)])->values()->all(),
        ];
    }

    private function metricComparisonDatasetPersonal(string $sortBy = 'delta'): array
    {
        $titles = $this->leaderboardTitles();
        $activeSocials = $this->activeSocialsPersonal();

        $rows = [];
        $totals = [];

        foreach ($titles as $metric => $title) {
            [$socials, $field] = $this->metricDefinition($metric, $activeSocials);
            $metricRows = $this->buildLeaderboard($socials, $field, null, $sortBy);
            $totals[$metric] = $metricRows->sum('latest');

            foreach ($metricRows->values() as $i => $row) {
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
            'summary' => collect($titles)->map(fn ($title, $metric) => ['label' => "Total {$title['title']}", 'value' => number_format($totals[$metric] ?? 0)])->values()->all(),
        ];
    }

    private function leaderboardDatasetInstitution(string $metric, string $sortBy = 'delta'): array
    {
        $titles = $this->leaderboardTitles();

        abort_unless(isset($titles[$metric]), 404);

        [$socials, $field] = $this->metricDefinition($metric, $this->activeSocialsInstitution());
        $rows = $this->buildLeaderboard($socials, $field, null, $sortBy);

        $title = $sortBy === 'value' ? $titles[$metric]['title'] : 'Pertumbuhan '.$titles[$metric]['title'];

        return [
            'title' => "{$title} Institusi",
            'subtitle' => $sortBy === 'value' ? 'Diurutkan berdasarkan nilai saat ini' : $titles[$metric]['subtitle'],
            'headers' => ['#', 'Institusi', 'Platform', 'Akun', 'Pertumbuhan', 'Saat Ini'],
            'rows' => $rows->values()->map(fn ($row, $i) => [
                $i + 1,
                $row['social']->institution->name,
                $this->platformLabels[$row['social']->platform->value],
                $row['social']->display_handle,
                ($row['delta'] > 0 ? '+' : '').number_format($row['delta']),
                number_format($row['latest']),
            ])->all(),
            'summary' => [['label' => "Total {$titles[$metric]['title']}", 'value' => number_format($rows->sum('latest'))]],
        ];
    }

    private function metricComparisonDatasetInstitution(string $sortBy = 'delta'): array
    {
        $titles = $this->leaderboardTitles();
        $activeSocials = $this->activeSocialsInstitution();

        $rows = [];
        $totals = [];

        foreach ($titles as $metric => $title) {
            [$socials, $field] = $this->metricDefinition($metric, $activeSocials);
            $metricRows = $this->buildLeaderboard($socials, $field, null, $sortBy);
            $totals[$metric] = $metricRows->sum('latest');

            foreach ($metricRows->values() as $i => $row) {
                $rows[] = [
                    $title['title'],
                    $i + 1,
                    $row['social']->institution->name,
                    $this->platformLabels[$row['social']->platform->value],
                    $row['social']->display_handle,
                    ($row['delta'] > 0 ? '+' : '').number_format($row['delta']),
                    number_format($row['latest']),
                ];
            }
        }

        $subtitle = $sortBy === 'value'
            ? 'Peringkat akun media sosial berdasarkan nilai saat ini — subscriber/followers, views, likes, dan post, untuk semua institusi'
            : 'Peringkat akun media sosial berdasarkan pertumbuhan mingguan tertinggi — subscriber/followers, views, likes, dan post, untuk semua institusi';

        return [
            'title' => 'Perbandingan Metrik Institusi',
            'subtitle' => $subtitle,
            'headers' => ['Metrik', '#', 'Institusi', 'Platform', 'Akun', 'Pertumbuhan', 'Saat Ini'],
            'rows' => $rows,
            'summary' => collect($titles)->map(fn ($title, $metric) => ['label' => "Total {$title['title']}", 'value' => number_format($totals[$metric] ?? 0)])->values()->all(),
        ];
    }

    /**
     * Same shape as leaderboardDataset()/leaderboardDatasetInstitution() — a row's owner is a
     * Union or a Conference (never both), so the name is read off whichever relation is
     * actually populated rather than a single fixed owner column.
     */
    private function leaderboardDatasetOrganization(string $metric, string $sortBy = 'delta'): array
    {
        $titles = $this->leaderboardTitles();

        abort_unless(isset($titles[$metric]), 404);

        [$socials, $field] = $this->metricDefinition($metric, $this->activeSocialsOrganization());
        $rows = $this->buildLeaderboard($socials, $field, null, $sortBy);

        $title = $sortBy === 'value' ? $titles[$metric]['title'] : 'Pertumbuhan '.$titles[$metric]['title'];

        return [
            'title' => "{$title} Uni/Daerah",
            'subtitle' => $sortBy === 'value' ? 'Diurutkan berdasarkan nilai saat ini' : $titles[$metric]['subtitle'],
            'headers' => ['#', 'Uni/Daerah', 'Platform', 'Akun', 'Pertumbuhan', 'Saat Ini'],
            'rows' => $rows->values()->map(fn ($row, $i) => [
                $i + 1,
                $row['social']->union?->name ?? $row['social']->conference?->name,
                $this->platformLabels[$row['social']->platform->value],
                $row['social']->display_handle,
                ($row['delta'] > 0 ? '+' : '').number_format($row['delta']),
                number_format($row['latest']),
            ])->all(),
            'summary' => [['label' => "Total {$titles[$metric]['title']}", 'value' => number_format($rows->sum('latest'))]],
        ];
    }

    private function metricComparisonDatasetOrganization(string $sortBy = 'delta'): array
    {
        $titles = $this->leaderboardTitles();
        $activeSocials = $this->activeSocialsOrganization();

        $rows = [];
        $totals = [];

        foreach ($titles as $metric => $title) {
            [$socials, $field] = $this->metricDefinition($metric, $activeSocials);
            $metricRows = $this->buildLeaderboard($socials, $field, null, $sortBy);
            $totals[$metric] = $metricRows->sum('latest');

            foreach ($metricRows->values() as $i => $row) {
                $rows[] = [
                    $title['title'],
                    $i + 1,
                    $row['social']->union?->name ?? $row['social']->conference?->name,
                    $this->platformLabels[$row['social']->platform->value],
                    $row['social']->display_handle,
                    ($row['delta'] > 0 ? '+' : '').number_format($row['delta']),
                    number_format($row['latest']),
                ];
            }
        }

        $subtitle = $sortBy === 'value'
            ? 'Peringkat akun media sosial berdasarkan nilai saat ini — subscriber/followers, views, likes, dan post, untuk semua Uni/Daerah'
            : 'Peringkat akun media sosial berdasarkan pertumbuhan mingguan tertinggi — subscriber/followers, views, likes, dan post, untuk semua Uni/Daerah';

        return [
            'title' => 'Perbandingan Metrik Uni/Daerah',
            'subtitle' => $subtitle,
            'headers' => ['Metrik', '#', 'Uni/Daerah', 'Platform', 'Akun', 'Pertumbuhan', 'Saat Ini'],
            'rows' => $rows,
            'summary' => collect($titles)->map(fn ($title, $metric) => ['label' => "Total {$title['title']}", 'value' => number_format($totals[$metric] ?? 0)])->values()->all(),
        ];
    }

    /**
     * [type, id] composite key ("union-3"/"conference-5") for a Union/Conference row — same
     * format as BuildsLeaderboards::parseOrganizationKey() expects, used here just to
     * disambiguate Union #3 from Conference #3 when keying a collection by id alone would
     * collide (they're different tables, so nothing stops both from having the same id).
     */
    private function organizationKey(Union|Conference $organization): string
    {
        return $organization instanceof Union ? "union-{$organization->id}" : "conference-{$organization->id}";
    }

    private function platformOverviewDatasetOrganization(string $platform): array
    {
        $applicableMetrics = collect($this->metricPlatforms())
            ->filter(fn ($platforms) => in_array($platform, $platforms, true))
            ->keys();

        $rowsByMetric = $applicableMetrics->mapWithKeys(fn ($metric) => [
            $metric => $this->metricComparisonRowsOrganization($metric, $platform)->keyBy(fn ($row) => $this->organizationKey($row['organization'])),
        ]);

        $organizations = $rowsByMetric
            ->flatMap(fn ($rows) => $rows->pluck('organization'))
            ->unique(fn ($org) => $this->organizationKey($org))
            ->sortBy('name')
            ->values();

        $headers = ['Uni/Daerah'];
        $valueHeaders = [];
        foreach ($applicableMetrics as $metric) {
            $valueHeader = match (true) {
                $metric !== 'reach' => $this->metricLabels[$metric],
                $platform === 'youtube' => 'Subscribers',
                $platform === 'semua' => 'Jangkauan',
                default => 'Followers',
            };
            $valueHeaders[$metric] = $valueHeader;
            $headers[] = $valueHeader;
            $headers[] = "Pertumbuhan {$valueHeader}";
        }

        $rows = $organizations->map(function ($organization) use ($applicableMetrics, $rowsByMetric) {
            $key = $this->organizationKey($organization);
            $row = [$organization->name];

            foreach ($applicableMetrics as $metric) {
                $entry = $rowsByMetric[$metric]->get($key);
                $row[] = $entry ? number_format($entry['value']) : '—';
                $row[] = ($entry && $entry['delta'] !== null) ? (($entry['delta'] > 0 ? '+' : '').number_format($entry['delta'])) : '—';
            }

            return $row;
        })->all();

        $platformLabel = $this->platformLabels[$platform];
        $metricNames = $applicableMetrics->map(fn ($m) => $this->metricLabels[$m])->implode(', ');

        return [
            'title' => "Ringkasan Perbandingan {$platformLabel} Uni/Daerah",
            'subtitle' => "{$metricNames} — semua Uni/Daerah, diurutkan berdasarkan nama.",
            'headers' => $headers,
            'rows' => $rows,
            'summary' => $applicableMetrics->map(fn ($metric) => ['label' => "Total {$valueHeaders[$metric]}", 'value' => number_format($rowsByMetric[$metric]->sum('value'))])->values()->all(),
        ];
    }

    private function platformComparisonDatasetOrganization(string $platform, string $metric, string $sortBy = 'delta'): array
    {
        $rows = $this->metricComparisonRowsOrganization($metric, $platform, $sortBy);
        $platformLabel = $this->platformLabels[$platform];
        $valueHeader = match (true) {
            $metric !== 'reach' => $this->metricLabels[$metric],
            $platform === 'youtube' => 'Subscribers',
            $platform === 'semua' => 'Jangkauan',
            default => 'Followers',
        };

        $subtitle = $sortBy === 'delta'
            ? "Peringkat Uni/Daerah berdasarkan pertumbuhan mingguan {$valueHeader} {$platformLabel}"
            : "Peringkat Uni/Daerah berdasarkan {$valueHeader} {$platformLabel} saat ini";

        return [
            'title' => "Perbandingan {$valueHeader} {$platformLabel} Uni/Daerah",
            'subtitle' => $subtitle,
            'headers' => ['#', 'Uni/Daerah', $valueHeader, 'Pertumbuhan Mingguan'],
            'rows' => $rows->values()->map(fn ($row, $i) => [
                $i + 1,
                $row['label'],
                number_format($row['value']),
                $row['delta'] === null ? '—' : ($row['delta'] > 0 ? '+' : '').number_format($row['delta']),
            ])->all(),
            'summary' => [['label' => "Total {$valueHeader} {$platformLabel}", 'value' => number_format($rows->sum('value'))]],
        ];
    }

    /**
     * Same shape as analyticsDatasetInstitution(), except the entity list has to come from two
     * separate models (Union + Conference) concatenated together — see
     * ChurchDashboardController::analytics()'s own Organisasi-tab section, which this mirrors
     * exactly (same analyticsUnionScope()/analyticsConferenceScope() region scoping, same
     * "union-ID"/"conference-ID" composite $organizationKey filter via parseOrganizationKey()).
     */
    private function analyticsDatasetOrganization(?string $organizationKey, ?string $platform): array
    {
        [$selectedType, $selectedId] = $this->parseOrganizationKey($organizationKey);

        $unions = $this->analyticsUnionScope(Union::query()->where('is_active', true))
            ->with(['socials' => fn ($q) => $q->where('is_active', true)->with('latestStat')])
            ->get();

        $conferences = $this->analyticsConferenceScope(Conference::query()->where('is_active', true))
            ->with(['socials' => fn ($q) => $q->where('is_active', true)->with('latestStat')])
            ->get();

        $organizations = $unions->concat($conferences)
            ->sortBy('name')
            ->values()
            ->when($organizationKey, fn ($collection) => $collection->filter(fn ($org) => match ($selectedType) {
                'union' => $org instanceof Union && (string) $org->id === (string) $selectedId,
                'conference' => $org instanceof Conference && (string) $org->id === (string) $selectedId,
                default => true,
            }))
            ->when($platform, fn ($collection) => $collection->filter(
                fn ($org) => $org->socials->contains(fn ($social) => $social->platform->value === $platform)
            ));

        $rows = $organizations->map(function ($organization) use ($platform) {
            $displaySocials = $platform
                ? $organization->socials->filter(fn ($s) => $s->platform->value === $platform)
                : $organization->socials;

            $reach = $displaySocials->sum(fn ($s) => $s->latestStat?->{$this->countField[$s->platform->value]} ?? 0);
            $views = $displaySocials->sum(fn ($s) => $s->latestStat?->{$this->viewsField[$s->platform->value] ?? 'views_count'} ?? 0);
            $likes = $displaySocials->sum(fn ($s) => $s->latestStat?->likes_count ?? 0);
            $posts = $displaySocials->sum(
                fn ($s) => isset($this->postField[$s->platform->value]) ? ($s->latestStat?->{$this->postField[$s->platform->value]} ?? 0) : 0
            );

            return [
                $organization->name,
                $organization instanceof Union ? 'Uni' : 'Daerah',
                $displaySocials->count(),
                number_format($reach),
                $views ? number_format($views) : '—',
                $likes ? number_format($likes) : '—',
                $posts ? number_format($posts) : '—',
            ];
        })->all();

        $filterParts = array_filter([
            $organizationKey ? $organizations->first()?->name : null,
            $platform ? ($this->platformLabels[$platform] ?? null) : null,
        ]);

        $subtitle = $filterParts ? 'Filter: '.implode(', ', $filterParts) : 'Semua Uni/Daerah, semua media sosial';

        return [
            'title' => 'Analitik & Grafik Uni/Daerah',
            'subtitle' => $subtitle,
            'headers' => ['Uni/Daerah', 'Level', 'Jumlah Akun', 'Total Jangkauan', 'Total Views', 'Total Likes', 'Total Post'],
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
                ->visibleTo(auth()->user())
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
                ->visibleTo(auth()->user())
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
            $viewsField = $this->viewsField[$social->platform->value] ?? 'views_count';

            $status = ! $social->is_auto_fetch
                ? 'Manual'
                : ($statusLabels[$social->last_fetch_status] ?? ($social->last_fetched_at ? 'Berhasil' : 'Menunggu'));

            return [
                $this->platformLabels[$social->platform->value],
                $this->categoryLabels[$social->category->value],
                $social->display_handle,
                $latest ? number_format($latest->{$field} ?? 0) : '—',
                $latest ? number_format($latest->following_count ?? 0) : '—',
                $latest ? number_format($latest->posts_count ?? $latest->videos_count ?? $latest->recent_posts_count ?? 0) : '—',
                $latest && $latest->{$viewsField} ? number_format($latest->{$viewsField}) : '—',
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
            $viewsField = $this->viewsField[$social->platform->value] ?? 'views_count';

            $status = ! $social->is_auto_fetch
                ? 'Manual'
                : ($statusLabels[$social->last_fetch_status] ?? ($social->last_fetched_at ? 'Berhasil' : 'Menunggu'));

            return [
                $this->platformLabels[$social->platform->value],
                $social->display_handle,
                $latest ? number_format($latest->{$field} ?? 0) : '—',
                $latest ? number_format($latest->following_count ?? 0) : '—',
                $latest ? number_format($latest->posts_count ?? $latest->videos_count ?? $latest->recent_posts_count ?? 0) : '—',
                $latest && $latest->{$viewsField} ? number_format($latest->{$viewsField}) : '—',
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

    private function institutionDataset(Institution $institution): array
    {
        $institution->load(['socials' => fn ($q) => $q->where('is_active', true)->with('latestStat')]);

        $statusLabels = ['success' => 'Berhasil', 'failed' => 'Gagal', 'pending' => 'Menunggu'];

        $rows = $institution->socials->map(function ($social) use ($statusLabels) {
            $latest = $social->latestStat;
            $field = $this->countField[$social->platform->value];
            $viewsField = $this->viewsField[$social->platform->value] ?? 'views_count';

            $status = ! $social->is_auto_fetch
                ? 'Manual'
                : ($statusLabels[$social->last_fetch_status] ?? ($social->last_fetched_at ? 'Berhasil' : 'Menunggu'));

            return [
                $this->platformLabels[$social->platform->value],
                $social->display_handle,
                $latest ? number_format($latest->{$field} ?? 0) : '—',
                $latest ? number_format($latest->following_count ?? 0) : '—',
                $latest ? number_format($latest->posts_count ?? $latest->videos_count ?? $latest->recent_posts_count ?? 0) : '—',
                $latest && $latest->{$viewsField} ? number_format($latest->{$viewsField}) : '—',
                $latest && $latest->likes_count ? number_format($latest->likes_count) : '—',
                $status,
            ];
        })->all();

        return [
            'title' => $institution->name,
            'subtitle' => 'Data akun media sosial',
            'headers' => ['Platform', 'Akun', 'Followers/Subs', 'Following', 'Post/Video', 'Views', 'Likes', 'Status'],
            'rows' => $rows,
        ];
    }

    private function socialHistoryDataset(ChurchSocial $social): array
    {
        $owner = $social->church ?? $social->person;
        $field = $this->countField[$social->platform->value];
        $viewsField = $this->viewsField[$social->platform->value] ?? 'views_count';

        $rows = $social->stats()->orderByDesc('recorded_at')->get()->map(fn ($stat) => [
            $stat->recorded_at->translatedFormat('d M Y'),
            number_format($stat->{$field} ?? 0),
            number_format($stat->following_count ?? 0),
            number_format($stat->posts_count ?? $stat->videos_count ?? $stat->recent_posts_count ?? 0),
            $stat->{$viewsField} ? number_format($stat->{$viewsField}) : '—',
            $stat->likes_count ? number_format($stat->likes_count) : '—',
        ])->all();

        return [
            'title' => "Histori {$social->display_handle}",
            'subtitle' => "{$this->platformLabels[$social->platform->value]} — {$owner->name}",
            'headers' => ['Tanggal', 'Followers/Subs', 'Following', 'Post/Video', 'Views', 'Likes'],
            'rows' => $rows,
        ];
    }

    private function platformOverviewDataset(string $platform, ?string $category = null): array
    {
        $applicableMetrics = collect($this->metricPlatforms())
            ->filter(fn ($platforms) => in_array($platform, $platforms, true))
            ->keys();

        $rowsByMetric = $applicableMetrics->mapWithKeys(fn ($metric) => [
            $metric => $this->metricComparisonRows($metric, $platform, category: $category)->keyBy(fn ($row) => $row['church']->id),
        ]);

        $churches = $rowsByMetric
            ->flatMap(fn ($rows) => $rows->pluck('church'))
            ->unique('id')
            ->sortBy('name')
            ->values();

        $headers = ['Gereja'];
        $valueHeaders = [];
        foreach ($applicableMetrics as $metric) {
            $valueHeader = match (true) {
                $metric !== 'reach' => $this->metricLabels[$metric],
                $platform === 'youtube' => 'Subscribers',
                $platform === 'semua' => 'Jangkauan',
                default => 'Followers',
            };
            $valueHeaders[$metric] = $valueHeader;
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
            'summary' => $applicableMetrics->map(fn ($metric) => ['label' => "Total {$valueHeaders[$metric]}", 'value' => number_format($rowsByMetric[$metric]->sum('value'))])->values()->all(),
        ];
    }

    private function platformComparisonDataset(string $platform, string $metric, string $sortBy = 'delta', ?string $category = null): array
    {
        $rows = $this->metricComparisonRows($metric, $platform, $sortBy, $category);
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
            'summary' => [['label' => "Total {$valueHeader} {$platformLabel}", 'value' => number_format($rows->sum('value'))]],
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
        $valueHeaders = [];
        foreach ($applicableMetrics as $metric) {
            $valueHeader = match (true) {
                $metric !== 'reach' => $this->metricLabels[$metric],
                $platform === 'youtube' => 'Subscribers',
                $platform === 'semua' => 'Jangkauan',
                default => 'Followers',
            };
            $valueHeaders[$metric] = $valueHeader;
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
            'summary' => $applicableMetrics->map(fn ($metric) => ['label' => "Total {$valueHeaders[$metric]}", 'value' => number_format($rowsByMetric[$metric]->sum('value'))])->values()->all(),
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
            'summary' => [['label' => "Total {$valueHeader} {$platformLabel}", 'value' => number_format($rows->sum('value'))]],
        ];
    }

    private function platformOverviewDatasetInstitution(string $platform): array
    {
        $applicableMetrics = collect($this->metricPlatforms())
            ->filter(fn ($platforms) => in_array($platform, $platforms, true))
            ->keys();

        $rowsByMetric = $applicableMetrics->mapWithKeys(fn ($metric) => [
            $metric => $this->metricComparisonRowsInstitution($metric, $platform)->keyBy(fn ($row) => $row['institution']->id),
        ]);

        $institutions = $rowsByMetric
            ->flatMap(fn ($rows) => $rows->pluck('institution'))
            ->unique('id')
            ->sortBy('name')
            ->values();

        $headers = ['Institusi'];
        $valueHeaders = [];
        foreach ($applicableMetrics as $metric) {
            $valueHeader = match (true) {
                $metric !== 'reach' => $this->metricLabels[$metric],
                $platform === 'youtube' => 'Subscribers',
                $platform === 'semua' => 'Jangkauan',
                default => 'Followers',
            };
            $valueHeaders[$metric] = $valueHeader;
            $headers[] = $valueHeader;
            $headers[] = "Pertumbuhan {$valueHeader}";
        }

        $rows = $institutions->map(function ($institution) use ($applicableMetrics, $rowsByMetric) {
            $row = [$institution->name];

            foreach ($applicableMetrics as $metric) {
                $entry = $rowsByMetric[$metric]->get($institution->id);
                $row[] = $entry ? number_format($entry['value']) : '—';
                $row[] = ($entry && $entry['delta'] !== null) ? (($entry['delta'] > 0 ? '+' : '').number_format($entry['delta'])) : '—';
            }

            return $row;
        })->all();

        $platformLabel = $this->platformLabels[$platform];
        $metricNames = $applicableMetrics->map(fn ($m) => $this->metricLabels[$m])->implode(', ');

        return [
            'title' => "Ringkasan Perbandingan {$platformLabel} Institusi",
            'subtitle' => "{$metricNames} — semua institusi, diurutkan berdasarkan nama.",
            'headers' => $headers,
            'rows' => $rows,
            'summary' => $applicableMetrics->map(fn ($metric) => ['label' => "Total {$valueHeaders[$metric]}", 'value' => number_format($rowsByMetric[$metric]->sum('value'))])->values()->all(),
        ];
    }

    private function platformComparisonDatasetInstitution(string $platform, string $metric, string $sortBy = 'delta'): array
    {
        $rows = $this->metricComparisonRowsInstitution($metric, $platform, $sortBy);
        $platformLabel = $this->platformLabels[$platform];
        $valueHeader = match (true) {
            $metric !== 'reach' => $this->metricLabels[$metric],
            $platform === 'youtube' => 'Subscribers',
            $platform === 'semua' => 'Jangkauan',
            default => 'Followers',
        };

        $subtitle = $sortBy === 'delta'
            ? "Peringkat institusi berdasarkan pertumbuhan mingguan {$valueHeader} {$platformLabel}"
            : "Peringkat institusi berdasarkan {$valueHeader} {$platformLabel} saat ini";

        return [
            'title' => "Perbandingan {$valueHeader} {$platformLabel} Institusi",
            'subtitle' => $subtitle,
            'headers' => ['#', 'Institusi', $valueHeader, 'Pertumbuhan Mingguan'],
            'rows' => $rows->values()->map(fn ($row, $i) => [
                $i + 1,
                $row['label'],
                number_format($row['value']),
                $row['delta'] === null ? '—' : ($row['delta'] > 0 ? '+' : '').number_format($row['delta']),
            ])->all(),
            'summary' => [['label' => "Total {$valueHeader} {$platformLabel}", 'value' => number_format($rows->sum('value'))]],
        ];
    }

    private function analyticsDatasetInstitution(?string $institutionId, ?string $platform): array
    {
        $institutions = Institution::query()
            ->where('is_active', true)
            ->visibleTo(auth()->user())
            ->with(['socials' => fn ($q) => $q->where('is_active', true)->with('latestStat')])
            ->when($institutionId, fn ($q) => $q->where('id', $institutionId))
            ->orderBy('name')
            ->get()
            ->when($platform, fn ($collection) => $collection->filter(
                fn ($institution) => $institution->socials->contains(fn ($social) => $social->platform->value === $platform)
            ));

        $rows = $institutions->map(function ($institution) use ($platform) {
            $displaySocials = $platform
                ? $institution->socials->filter(fn ($s) => $s->platform->value === $platform)
                : $institution->socials;

            $reach = $displaySocials->sum(fn ($s) => $s->latestStat?->{$this->countField[$s->platform->value]} ?? 0);
            $views = $displaySocials->sum(fn ($s) => $s->latestStat?->{$this->viewsField[$s->platform->value] ?? 'views_count'} ?? 0);
            $likes = $displaySocials->sum(fn ($s) => $s->latestStat?->likes_count ?? 0);
            $posts = $displaySocials->sum(
                fn ($s) => isset($this->postField[$s->platform->value]) ? ($s->latestStat?->{$this->postField[$s->platform->value]} ?? 0) : 0
            );

            return [
                $institution->name,
                $displaySocials->count(),
                number_format($reach),
                $views ? number_format($views) : '—',
                $likes ? number_format($likes) : '—',
                $posts ? number_format($posts) : '—',
            ];
        })->all();

        $filterParts = array_filter([
            $institutionId ? $institutions->first()?->name : null,
            $platform ? ($this->platformLabels[$platform] ?? null) : null,
        ]);

        $subtitle = $filterParts ? 'Filter: '.implode(', ', $filterParts) : 'Semua institusi, semua media sosial';

        return [
            'title' => 'Analitik & Grafik Institusi',
            'subtitle' => $subtitle,
            'headers' => ['Institusi', 'Jumlah Akun', 'Total Jangkauan', 'Total Views', 'Total Likes', 'Total Post'],
            'rows' => $rows,
        ];
    }

    private function analyticsDataset(?string $churchId, ?string $platform, ?string $category = null): array
    {
        $churches = Church::query()
            ->where('is_active', true)
            ->visibleTo(auth()->user())
            ->with(['socials' => fn ($q) => $q->where('is_active', true)->with('latestStat')])
            ->when($churchId, fn ($q) => $q->where('id', $churchId))
            ->orderBy('name')
            ->get()
            ->when($platform, fn ($collection) => $collection->filter(
                fn ($church) => $church->socials->contains(fn ($social) => $social->platform->value === $platform)
            ))
            ->when($category, fn ($collection) => $collection->filter(
                fn ($church) => $church->socials->contains(fn ($social) => $social->category->value === $category)
            ));

        $rows = $churches->map(function ($church) use ($platform, $category) {
            $displaySocials = $church->socials
                ->when($platform, fn ($socials) => $socials->filter(fn ($s) => $s->platform->value === $platform))
                ->when($category, fn ($socials) => $socials->filter(fn ($s) => $s->category->value === $category));

            $socialsByCategory = $displaySocials->groupBy(fn ($s) => $s->category->value);

            $reach = $displaySocials->sum(fn ($s) => $s->latestStat?->{$this->countField[$s->platform->value]} ?? 0);
            $views = $displaySocials->sum(fn ($s) => $s->latestStat?->{$this->viewsField[$s->platform->value] ?? 'views_count'} ?? 0);
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
            $category ? ($this->categoryLabels[$category] ?? null) : null,
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
            ->visibleTo(auth()->user())
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
            $views = $displaySocials->sum(fn ($s) => $s->latestStat?->{$this->viewsField[$s->platform->value] ?? 'views_count'} ?? 0);
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
            'pdf' => $this->downloadPdf($dataset, $footer, "{$filenameBase}.pdf"),
            'word' => $this->downloadWord($dataset, $footer, "{$filenameBase}.docx"),
            'excel' => $this->downloadExcel($dataset, $footer, "{$filenameBase}.xlsx"),
            default => abort(404),
        };
    }

    /**
     * The footer (divider line, left-aligned footer text, right-aligned page number) is
     * drawn here via the canvas's page_script() rather than the view's old inline
     * <script type="text/php"> tag. That tag only ever ran once — wherever that DOM node
     * happened to land during rendering (in practice, the last page) — since inline PHP
     * script nodes aren't looped per page the way a canvas-level page_script() callback is.
     * Registering it here also hands us the real, final $pageNumber/$pageCount at draw time
     * for each page, so the page-number text can be measured and right-aligned against its
     * actual width instead of the literal "{PAGE_NUM}"/"{PAGE_COUNT}" token text (which is
     * longer than the digits that replace it, leaving the text short of the margin).
     *
     * page_script() loops over whatever pages already exist on the canvas *right when it's
     * called* — it doesn't defer to run later. Registering it before render() sees only the
     * single page that exists prior to layout/pagination, so every page created afterward as
     * content overflows (page 2, 3, ...) would silently get no footer at all. render() must
     * run first so every page actually exists, then page_script() can reach all of them;
     * download()'s own render() call is a no-op afterward since it only renders once.
     */
    private function downloadPdf(array $dataset, string $footer, string $filename): Response
    {
        $pdf = Pdf::loadView('exports.pdf', ['dataset' => $dataset]);
        $pdf->render();

        $pdf->getDomPDF()->getCanvas()->page_script(function ($pageNumber, $pageCount, $canvas, $fontMetrics) use ($footer) {
            $font = $fontMetrics->getFont('Helvetica', 'normal');
            $size = 8;
            $color = [0.58, 0.64, 0.72];
            $y = $canvas->get_height() - 34;

            $canvas->line(40, $y - 8, $canvas->get_width() - 40, $y - 8, [0.89, 0.91, 0.94], 1);
            $canvas->text(40, $y, $footer, $font, $size, $color);

            $pageText = "Halaman {$pageNumber} dari {$pageCount}";
            $pageWidth = $fontMetrics->getTextWidth($pageText, $font, $size);
            $canvas->text($canvas->get_width() - 40 - $pageWidth, $y, $pageText, $font, $size, $color);
        });

        return $pdf->download($filename);
    }

    private function footerText(): string
    {
        $text = 'Diunduh dari Hopenalytics pada '.Carbon::now('Asia/Jakarta')->translatedFormat('d M Y H:i').' WIB';

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

        if (! empty($dataset['summary'])) {
            $summaryText = $section->addTextRun();
            foreach ($dataset['summary'] as $i => $item) {
                if ($i > 0) {
                    $summaryText->addText('    ');
                }
                $summaryText->addText("{$item['label']}: ", ['size' => 9, 'color' => '666666']);
                $summaryText->addText($item['value'], ['bold' => true, 'size' => 9]);
            }
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

        $headerRow = 1;

        if (! empty($dataset['summary'])) {
            foreach ($dataset['summary'] as $i => $item) {
                $sheet->setCellValue('A'.($i + 1), "{$item['label']}: {$item['value']}");
                $sheet->getStyle('A'.($i + 1))->getFont()->setBold(true);
            }
            $headerRow = count($dataset['summary']) + 2;
        }

        $sheet->fromArray($dataset['headers'], null, "A{$headerRow}");
        $sheet->fromArray($dataset['rows'], null, 'A'.($headerRow + 1));

        $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($dataset['headers']));
        $sheet->getStyle("A{$headerRow}:{$lastColumn}{$headerRow}")->getFont()->setBold(true);

        foreach (range('A', $lastColumn) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $footerRow = $headerRow + count($dataset['rows']) + 2;
        $sheet->setCellValue("A{$footerRow}", $footer);
        $sheet->getStyle("A{$footerRow}")->getFont()->setItalic(true)->setSize(9);

        $tempPath = tempnam(sys_get_temp_dir(), 'export').'.xlsx';
        (new Xlsx($spreadsheet))->save($tempPath);

        return response()->download($tempPath, $filename)->deleteFileAfterSend(true);
    }
}

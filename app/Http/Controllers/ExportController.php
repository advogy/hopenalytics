<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsLeaderboards;
use App\Models\AppSetting;
use App\Models\Church;
use App\Models\ChurchSocial;
use App\Models\Conference;
use App\Models\Division;
use App\Models\Hashtag;
use App\Models\HashtagPost;
use App\Models\Institution;
use App\Models\Person;
use App\Models\Union;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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

    // Built in the constructor below (like $platformLabels above) since a property default
    // can't call __().
    private array $categoryLabels;

    private array $countField = ['youtube' => 'subscribers_count', 'instagram' => 'followers_count', 'tiktok' => 'followers_count', 'facebook' => 'followers_count', 'x' => 'followers_count'];

    private array $postField = ['youtube' => 'videos_count', 'instagram' => 'posts_count', 'tiktok' => 'posts_count', 'facebook' => 'recent_posts_count', 'x' => 'posts_count'];

    // Instagram/TikTok are recent-sample view counts (last ~10-12 posts/videos), not a lifetime
    // total like YouTube's views_count. Facebook and X have no view-count field scraped at all —
    // a lookup miss falls through to 'views_count' (always null on those rows) via ?? below.
    private array $viewsField = ['youtube' => 'views_count', 'instagram' => 'recent_reels_views', 'tiktok' => 'recent_video_plays'];

    // Built in the constructor below (like $platformLabels above) since a property default
    // can't call __().
    private array $metricLabels;

    public function __construct()
    {
        $this->platformLabels = ['semua' => __('comparison.sort_all')] + AppSetting::current()->enabledPlatformLabels();

        $this->categoryLabels = [
            'gereja' => __('directory.church_accounts'),
            'umum' => __('directory.general_accounts'),
            'personal' => __('entity.personal_account'),
        ];

        $this->metricLabels = [
            'reach' => __('export.metric_reach'),
            'views' => __('common.metric_views'),
            'likes' => __('common.metric_likes'),
            'posts' => __('common.metric_posts'),
        ];
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
        $params = $this->directoryRequestParams();
        $dataset = $this->directoryDataset($params['platform'] ?? null, $params['type'] ?? null);

        $downloadUrl = route('export.directory.download', array_merge(['format' => 'pdf'], $params));

        return $this->preview($dataset, $downloadUrl);
    }

    public function directoryDownload(string $format): BinaryFileResponse|Response
    {
        $params = $this->directoryRequestParams();
        $filename = 'direktori-akun'.(isset($params['type']) ? "-{$params['type']}" : '').(isset($params['platform']) ? "-{$params['platform']}" : '');

        return $this->download($this->directoryDataset($params['platform'] ?? null, $params['type'] ?? null), $format, $filename);
    }

    /**
     * Every filter directoryDataset() reads off the request, collected once — directoryPreview()'s
     * "Download PDF/Word/Excel" links are plain hrefs to directoryDownload(), not a resubmission
     * of the filter form, so anything left out here would silently revert to the unfiltered/
     * default view the moment the download button is clicked, no matter what the preview itself
     * was actually showing.
     */
    private function directoryRequestParams(): array
    {
        return array_filter([
            'platform' => request()->query('platform'),
            'type' => request()->query('type'),
            'search' => request()->query('search'),
            'auto_fetch' => request()->query('auto_fetch'),
            'hide_empty_churches' => request()->boolean('hide_empty_churches') ? 1 : null,
            'hide_empty_people' => request()->boolean('hide_empty_people') ? 1 : null,
            'hide_empty_institutions' => request()->boolean('hide_empty_institutions') ? 1 : null,
            'hide_empty_organizations' => request()->boolean('hide_empty_organizations') ? 1 : null,
            'sort_gereja' => request()->query('sort_gereja'),
            'sort_institusi' => request()->query('sort_institusi'),
            'sort_personal' => request()->query('sort_personal'),
            'sort_organisasi' => request()->query('sort_organisasi'),
            'union_id' => request()->query('union_id'),
            'conference_id' => request()->query('conference_id'),
        ]);
    }

    public function hashtagPreview()
    {
        $params = $this->hashtagRequestParams();
        $dataset = $this->hashtagDataset($params['hashtag'] ?? null, $params['platform'] ?? null);

        $downloadUrl = route('export.hashtag.download', array_merge(['format' => 'pdf'], $params));

        return $this->preview($dataset, $downloadUrl);
    }

    public function hashtagDownload(string $format): BinaryFileResponse|Response
    {
        $params = $this->hashtagRequestParams();

        return $this->download($this->hashtagDataset($params['hashtag'] ?? null, $params['platform'] ?? null), $format, 'perbandingan-hastag');
    }

    /**
     * Every filter Perbandingan Hastag's own filter card offers — hashtag, platform, and the
     * Uni/Daerah region filter (see BuildsLeaderboards::applyHashtagRegionFilter()) — collected
     * once so hashtagPreview()'s "Download PDF/Word/Excel" links carry all of them forward into
     * hashtagDownload()'s own separate request, same reasoning as directoryRequestParams() above.
     */
    private function hashtagRequestParams(): array
    {
        return array_filter([
            'hashtag' => request()->query('hashtag'),
            'platform' => request()->query('platform'),
            'union_id' => request()->query('union_id'),
            'conference_id' => request()->query('conference_id'),
        ]);
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

    /**
     * Narrows an activeSocials()-shaped collection (raw ChurchSocial rows) to the same Uni/
     * Daerah the equivalent live page's own region filter would — confirmed missing here
     * entirely (a dedicated audit found every leaderboard/metric-comparison/platform-comparison/
     * platform-overview export ignoring union_id/conference_id across all four scopes, so an
     * export triggered from a filtered page silently reverted to the exporter's FULL region
     * instead of the narrower one they were actually looking at). Reads the filter straight off
     * the request rather than threading it through every dataset method's signature — every
     * caller already runs inside the same preview/download request these query params arrived
     * on. Reuses BuildsLeaderboards::matchesRegionFilter() — the exact same closure the live
     * pages already filter their own rows with — rather than reimplementing the Church/Person/
     * Institution/Union/Conference/Division entity-resolution chain a second time.
     */
    private function applyRegionFilter(Collection $socials, string $scope): Collection
    {
        return $this->regionFilterFor($scope)($socials, fn ($social) => match ($scope) {
            'gereja' => $social->church,
            'personal' => $social->person,
            'institusi' => $social->institution,
            'organisasi' => $social->division ?? $social->union ?? $social->conference,
        });
    }

    /**
     * Same idea as applyRegionFilter() above, but for a Collection of the trait's own
     * ['church'|'person'|'institution'|'organization' => entity, ...] comparison rows (see
     * BuildsLeaderboards::metricComparisonRows()/metricComparisonRowsPersonal()/
     * metricComparisonRowsInstitution()/metricComparisonRowsOrganization()) instead of raw
     * ChurchSocial models — used by the platform-overview/platform-comparison datasets, which
     * build off those row shapes rather than activeSocials() directly.
     */
    private function applyRegionFilterToRows(Collection $rows, string $scope): Collection
    {
        $rowKey = match ($scope) {
            'gereja' => 'church',
            'personal' => 'person',
            'institusi' => 'institution',
            'organisasi' => 'organization',
        };

        return $this->regionFilterFor($scope)($rows, fn ($row) => $row[$rowKey]);
    }

    /**
     * Same idea again, but for a Collection of Church/Person/Institution models directly (the
     * item itself IS the entity to match) — used by analyticsDataset()/analyticsDatasetPersonal()/
     * analyticsDatasetInstitution(), which mirror ChurchDashboardController::analytics()'s own
     * ->filter($matchesRegionFilter) call on its $churches/$people/$institutions collections.
     */
    private function applyRegionFilterToEntities(Collection $entities, string $scope): Collection
    {
        return $this->regionFilterFor($scope)($entities, fn ($entity) => $entity);
    }

    /** @return \Closure(Collection, \Closure): Collection */
    private function regionFilterFor(string $scope): \Closure
    {
        $unionId = request()->query('union_id');
        $conferenceId = request()->query('conference_id');

        if (! $unionId && ! $conferenceId) {
            return fn (Collection $items) => $items;
        }

        $matches = $this->matchesRegionFilter($unionId, $conferenceId);

        return fn (Collection $items, \Closure $entityOf) => $items
            ->filter(fn ($item) => ($entity = $entityOf($item)) !== null && $matches($entity))
            ->values();
    }

    /**
     * The handful of sentence/label shapes repeated verbatim across the ~15 dataset-building
     * methods below — extracted once here so each shape only needs translating in one place
     * (see ExportController's own investigation notes: every leaderboard/metric-comparison
     * dataset method built the exact same "Pertumbuhan :title" / "Diurutkan berdasarkan..." /
     * "Total :metric" / header-row text independently before this).
     */
    private function growthPrefixedTitle(string $baseTitle, string $sortBy): string
    {
        return $sortBy === 'value' ? $baseTitle : __('export.growth_prefix', ['title' => $baseTitle]);
    }

    private function sortedSubtitle(string $sortBy, string $growthSubtitle): string
    {
        return $sortBy === 'value' ? __('export.sorted_by_current_value') : $growthSubtitle;
    }

    private function totalLabel(string $metricTitle): string
    {
        return __('export.total_label', ['metric' => $metricTitle]);
    }

    /**
     * Same "+X.X%" / "X.X%" / "—" formatting as partials/growth-score-row.blade.php's own
     * `{{ $value > 0 ? '+' : '' }}{{ number_format($value, 1) }}%` — used by
     * metricComparisonDataset() and its Personal/Institution/Organization counterparts, which
     * now mirror that same growth-score-card content instead of a leaderboard table (see this
     * commit's own message for why: the export used to build a completely different report —
     * a per-metric leaderboard — than what the live "Perbandingan Metrik" page actually shows).
     */
    private function formatPercent(?float $value): string
    {
        return $value === null ? '—' : ($value > 0 ? '+' : '').number_format($value, 1).'%';
    }

    private function leaderboardHeaders(string $entityColumn): array
    {
        return ['#', $entityColumn, __('common.platform'), __('common.account'), __('comparison.growth'), __('export.col_current')];
    }

    private function leaderboardDataset(string $metric, string $sortBy = 'delta', ?string $category = null): array
    {
        $titles = $this->leaderboardTitles();

        abort_unless(isset($titles[$metric]), 404);

        [$socials, $field] = $this->metricDefinition($metric, $this->applyRegionFilter($this->activeSocials(category: $category), 'gereja'));
        $rows = $this->buildLeaderboard($socials, $field, null, $sortBy);

        $title = $this->growthPrefixedTitle($titles[$metric]['title'], $sortBy);

        return [
            'title' => $title.__('comparison.title_suffix_church'),
            'subtitle' => $this->sortedSubtitle($sortBy, $titles[$metric]['subtitle']),
            'headers' => $this->leaderboardHeaders(__('common.church')),
            'rows' => $rows->values()->map(fn ($row, $i) => [
                $i + 1,
                $row['social']->church->name,
                $this->platformLabels[$row['social']->platform->value],
                $row['social']->display_handle,
                ($row['delta'] > 0 ? '+' : '').number_format($row['delta']),
                number_format($row['latest']),
            ])->all(),
            'summary' => [['label' => $this->totalLabel($titles[$metric]['title']), 'value' => number_format($rows->sum('latest'))]],
        ];
    }

    private function leaderboardDatasetPersonal(string $metric, string $sortBy = 'delta'): array
    {
        $titles = $this->leaderboardTitles();

        abort_unless(isset($titles[$metric]), 404);

        [$socials, $field] = $this->metricDefinition($metric, $this->applyRegionFilter($this->activeSocialsPersonal(), 'personal'));
        $rows = $this->buildLeaderboard($socials, $field, null, $sortBy);

        $title = $this->growthPrefixedTitle($titles[$metric]['title'], $sortBy);

        return [
            'title' => $title.__('comparison.title_suffix_personal'),
            'subtitle' => $this->sortedSubtitle($sortBy, $titles[$metric]['subtitle']),
            'headers' => $this->leaderboardHeaders(__('common.name')),
            'rows' => $rows->values()->map(fn ($row, $i) => [
                $i + 1,
                $row['social']->person->name,
                $this->platformLabels[$row['social']->platform->value],
                $row['social']->display_handle,
                ($row['delta'] > 0 ? '+' : '').number_format($row['delta']),
                number_format($row['latest']),
            ])->all(),
            'summary' => [['label' => $this->totalLabel($titles[$metric]['title']), 'value' => number_format($rows->sum('latest'))]],
        ];
    }

    /**
     * Matches churches/metric-comparison.blade.php's actual content — the composite weekly
     * growth SCORE per church (growthScoreRows()), not a per-metric leaderboard. $sortBy is
     * unused: unlike the leaderboard/platform-comparison pages, "Perbandingan Metrik" has no
     * value/delta sort toggle at all — it's always ranked by score, same as the live page.
     */
    private function metricComparisonDataset(string $sortBy = 'delta', ?string $category = null): array
    {
        $scoreRows = $this->applyRegionFilterToRows($this->growthScoreRows(category: $category), 'gereja');

        return [
            'title' => __('comparison.metric_comparison_title', ['label' => __('common.church')]),
            'subtitle' => __('comparison.metric_comparison_subtitle_score', ['scope' => __('comparison.for_all_churches')]),
            'headers' => [
                '#', __('common.church'), __('entity.city'), __('export.col_account_count'),
                __('common.metric_reach'), __('common.metric_views'), __('common.metric_likes'), __('common.metric_posts'),
                __('export.col_score'),
            ],
            'rows' => $scoreRows->values()->map(fn ($row, $i) => [
                $i + 1,
                $row['church']->name,
                $row['church']->city ?? '—',
                $row['accountCount'],
                $this->formatPercent($row['metrics']['reach'] ?? null),
                $this->formatPercent($row['metrics']['views'] ?? null),
                $this->formatPercent($row['metrics']['likes'] ?? null),
                $this->formatPercent($row['metrics']['posts'] ?? null),
                $this->formatPercent($row['score']),
            ])->all(),
        ];
    }

    /** Same "match the live page's actual content" fix as metricComparisonDataset() — see its own doc comment. */
    private function metricComparisonDatasetPersonal(string $sortBy = 'delta'): array
    {
        $scoreRows = $this->applyRegionFilterToRows($this->growthScoreRowsPersonal(), 'personal');

        return [
            'title' => __('comparison.metric_comparison_title', ['label' => __('common.personal')]),
            'subtitle' => __('comparison.metric_comparison_subtitle_score', ['scope' => __('comparison.for_all_personal')]),
            'headers' => [
                '#', __('common.name'), __('entity.city'), __('export.col_account_count'),
                __('common.metric_reach'), __('common.metric_views'), __('common.metric_likes'), __('common.metric_posts'),
                __('export.col_score'),
            ],
            'rows' => $scoreRows->values()->map(fn ($row, $i) => [
                $i + 1,
                $row['person']->name,
                $row['person']->city ?? '—',
                $row['accountCount'],
                $this->formatPercent($row['metrics']['reach'] ?? null),
                $this->formatPercent($row['metrics']['views'] ?? null),
                $this->formatPercent($row['metrics']['likes'] ?? null),
                $this->formatPercent($row['metrics']['posts'] ?? null),
                $this->formatPercent($row['score']),
            ])->all(),
        ];
    }

    private function leaderboardDatasetInstitution(string $metric, string $sortBy = 'delta'): array
    {
        $titles = $this->leaderboardTitles();

        abort_unless(isset($titles[$metric]), 404);

        [$socials, $field] = $this->metricDefinition($metric, $this->applyRegionFilter($this->activeSocialsInstitution(), 'institusi'));
        $rows = $this->buildLeaderboard($socials, $field, null, $sortBy);

        $title = $this->growthPrefixedTitle($titles[$metric]['title'], $sortBy);

        return [
            'title' => $title.__('comparison.title_suffix_institution'),
            'subtitle' => $this->sortedSubtitle($sortBy, $titles[$metric]['subtitle']),
            'headers' => $this->leaderboardHeaders(__('common.institution')),
            'rows' => $rows->values()->map(fn ($row, $i) => [
                $i + 1,
                $row['social']->institution->name,
                $this->platformLabels[$row['social']->platform->value],
                $row['social']->display_handle,
                ($row['delta'] > 0 ? '+' : '').number_format($row['delta']),
                number_format($row['latest']),
            ])->all(),
            'summary' => [['label' => $this->totalLabel($titles[$metric]['title']), 'value' => number_format($rows->sum('latest'))]],
        ];
    }

    /** Same "match the live page's actual content" fix as metricComparisonDataset() — see its own doc comment. */
    private function metricComparisonDatasetInstitution(string $sortBy = 'delta'): array
    {
        $scoreRows = $this->applyRegionFilterToRows($this->growthScoreRowsInstitution(), 'institusi');

        return [
            'title' => __('comparison.metric_comparison_title', ['label' => __('common.institution')]),
            'subtitle' => __('comparison.metric_comparison_subtitle_score', ['scope' => __('comparison.for_all_institutions')]),
            'headers' => [
                '#', __('common.institution'), __('entity.city'), __('export.col_account_count'),
                __('common.metric_reach'), __('common.metric_views'), __('common.metric_likes'), __('common.metric_posts'),
                __('export.col_score'),
            ],
            'rows' => $scoreRows->values()->map(fn ($row, $i) => [
                $i + 1,
                $row['institution']->name,
                $row['institution']->city ?? '—',
                $row['accountCount'],
                $this->formatPercent($row['metrics']['reach'] ?? null),
                $this->formatPercent($row['metrics']['views'] ?? null),
                $this->formatPercent($row['metrics']['likes'] ?? null),
                $this->formatPercent($row['metrics']['posts'] ?? null),
                $this->formatPercent($row['score']),
            ])->all(),
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

        [$socials, $field] = $this->metricDefinition($metric, $this->applyRegionFilter($this->activeSocialsOrganization(), 'organisasi'));
        $rows = $this->buildLeaderboard($socials, $field, null, $sortBy);

        $title = $this->growthPrefixedTitle($titles[$metric]['title'], $sortBy);

        return [
            'title' => $title.__('comparison.title_suffix_organization'),
            'subtitle' => $this->sortedSubtitle($sortBy, $titles[$metric]['subtitle']),
            'headers' => $this->leaderboardHeaders(__('comparison.organization_label')),
            'rows' => $rows->values()->map(fn ($row, $i) => [
                $i + 1,
                $row['social']->division?->name ?? $row['social']->union?->name ?? $row['social']->conference?->name,
                $this->platformLabels[$row['social']->platform->value],
                $row['social']->display_handle,
                ($row['delta'] > 0 ? '+' : '').number_format($row['delta']),
                number_format($row['latest']),
            ])->all(),
            'summary' => [['label' => $this->totalLabel($titles[$metric]['title']), 'value' => number_format($rows->sum('latest'))]],
        ];
    }

    /**
     * Same "match the live page's actual content" fix as metricComparisonDataset() — see its
     * own doc comment. No City column here (unlike the other three scopes) — Division/Union/
     * Conference have no city of their own.
     */
    private function metricComparisonDatasetOrganization(string $sortBy = 'delta'): array
    {
        $scoreRows = $this->applyRegionFilterToRows($this->growthScoreRowsOrganization(), 'organisasi');

        return [
            'title' => __('comparison.metric_comparison_title', ['label' => __('comparison.organization_label')]),
            'subtitle' => __('comparison.metric_comparison_subtitle_score', ['scope' => __('comparison.for_all_organizations')]),
            'headers' => [
                '#', __('comparison.organization_label'), __('export.col_account_count'),
                __('common.metric_reach'), __('common.metric_views'), __('common.metric_likes'), __('common.metric_posts'),
                __('export.col_score'),
            ],
            'rows' => $scoreRows->values()->map(fn ($row, $i) => [
                $i + 1,
                $row['organization']->name,
                $row['accountCount'],
                $this->formatPercent($row['metrics']['reach'] ?? null),
                $this->formatPercent($row['metrics']['views'] ?? null),
                $this->formatPercent($row['metrics']['likes'] ?? null),
                $this->formatPercent($row['metrics']['posts'] ?? null),
                $this->formatPercent($row['score']),
            ])->all(),
        ];
    }

    /**
     * [type, id] composite key ("union-3"/"conference-5"/"division-2") for a Division/Union/
     * Conference row — same format as BuildsLeaderboards::parseOrganizationKey() expects, used
     * here just to disambiguate Union #3 from Conference #3 when keying a collection by id alone
     * would collide (they're different tables, so nothing stops both from having the same id).
     */
    private function organizationKey(Division|Union|Conference $organization): string
    {
        return match (true) {
            $organization instanceof Division => "division-{$organization->id}",
            $organization instanceof Union => "union-{$organization->id}",
            default => "conference-{$organization->id}",
        };
    }

    /**
     * The "which column header applies" match repeated identically 8 times across every
     * platform-overview/platform-comparison dataset method (church/personal/institution/
     * organization) — extracted once here, same reasoning as the other shared helpers above.
     */
    private function valueHeaderLabel(string $metric, string $platform): string
    {
        return match (true) {
            $metric !== 'reach' => $this->metricLabels[$metric],
            $platform === 'youtube' => __('export.value_header_subscribers'),
            $platform === 'semua' => __('export.value_header_reach'),
            default => __('export.value_header_followers'),
        };
    }

    private function platformOverviewDatasetOrganization(string $platform): array
    {
        $applicableMetrics = collect($this->metricPlatforms())
            ->filter(fn ($platforms) => in_array($platform, $platforms, true))
            ->keys();

        $rowsByMetric = $applicableMetrics->mapWithKeys(fn ($metric) => [
            $metric => $this->applyRegionFilterToRows($this->metricComparisonRowsOrganization($metric, $platform), 'organisasi')->keyBy(fn ($row) => $this->organizationKey($row['organization'])),
        ]);

        $organizations = $rowsByMetric
            ->flatMap(fn ($rows) => $rows->pluck('organization'))
            ->unique(fn ($org) => $this->organizationKey($org))
            ->sortBy('name')
            ->values();

        $headers = [__('comparison.organization_label')];
        $valueHeaders = [];
        foreach ($applicableMetrics as $metric) {
            $valueHeader = $this->valueHeaderLabel($metric, $platform);
            $valueHeaders[$metric] = $valueHeader;
            $headers[] = $valueHeader;
            $headers[] = __('export.growth_prefix', ['title' => $valueHeader]);
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
            'title' => __('export.platform_overview_title', ['platform' => $platformLabel]).__('comparison.title_suffix_organization'),
            'subtitle' => __('export.platform_overview_subtitle', ['metrics' => $metricNames, 'scope' => __('comparison.for_all_organizations')]),
            'headers' => $headers,
            'rows' => $rows,
            'summary' => $applicableMetrics->map(fn ($metric) => ['label' => $this->totalLabel($valueHeaders[$metric]), 'value' => number_format($rowsByMetric[$metric]->sum('value'))])->values()->all(),
        ];
    }

    private function platformComparisonDatasetOrganization(string $platform, string $metric, string $sortBy = 'delta'): array
    {
        $rows = $this->applyRegionFilterToRows($this->metricComparisonRowsOrganization($metric, $platform, $sortBy), 'organisasi');
        $platformLabel = $this->platformLabels[$platform];
        $valueHeader = $this->valueHeaderLabel($metric, $platform);

        $subtitle = $sortBy === 'delta'
            ? __('export.platform_ranking_subtitle_delta', ['scope' => __('comparison.organization_label'), 'value' => $valueHeader, 'platform' => $platformLabel])
            : __('export.platform_ranking_subtitle_value', ['scope' => __('comparison.organization_label'), 'value' => $valueHeader, 'platform' => $platformLabel]);

        return [
            'title' => __('export.platform_ranking_title', ['value' => $valueHeader, 'platform' => $platformLabel]).__('comparison.title_suffix_organization'),
            'subtitle' => $subtitle,
            'headers' => ['#', __('comparison.organization_label'), $valueHeader, __('comparison.weekly_growth')],
            'rows' => $rows->values()->map(fn ($row, $i) => [
                $i + 1,
                $row['label'],
                number_format($row['value']),
                $row['delta'] === null ? '—' : ($row['delta'] > 0 ? '+' : '').number_format($row['delta']),
            ])->all(),
            'summary' => [['label' => __('export.total_value_platform', ['value' => $valueHeader, 'platform' => $platformLabel]), 'value' => number_format($rows->sum('value'))]],
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

        $divisions = $this->analyticsDivisionScope(Division::query()->where('is_active', true))
            ->with(['socials' => fn ($q) => $q->where('is_active', true)->with('latestStat')])
            ->get();

        $unions = $this->analyticsUnionScope(Union::query()->where('is_active', true))
            ->with(['socials' => fn ($q) => $q->where('is_active', true)->with('latestStat')])
            ->get();

        $conferences = $this->analyticsConferenceScope(Conference::query()->where('is_active', true))
            ->with(['socials' => fn ($q) => $q->where('is_active', true)->with('latestStat')])
            ->get();

        // applyRegionFilterToEntities() mirrors analytics()'s own organisasi-tab
        // ->filter($matchesRegionFilter) on top of the specific organizationKey selection below
        // — missing here too before this fix (a gap the audit didn't call out explicitly, but
        // confirmed the same way as the other three tabs once checked against the live method).
        $organizations = $this->applyRegionFilterToEntities($divisions->concat($unions)->concat($conferences), 'organisasi')
            ->sortBy('name')
            ->values()
            ->when($organizationKey, fn ($collection) => $collection->filter(fn ($org) => match ($selectedType) {
                'division' => $org instanceof Division && (string) $org->id === (string) $selectedId,
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
                match (true) {
                    $organization instanceof Division => __('common.division'),
                    $organization instanceof Union => __('common.union'),
                    default => __('common.conference'),
                },
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

        $subtitle = $filterParts
            ? __('export.analytics_filter_prefix', ['filters' => implode(', ', $filterParts)])
            : __('export.analytics_no_filter', ['scope' => __('comparison.organization_label')]);

        return [
            'title' => __('export.analytics_title').__('comparison.title_suffix_organization'),
            'subtitle' => $subtitle,
            'headers' => [__('comparison.organization_label'), __('export.col_level'), __('export.col_account_count'), $this->totalLabel(__('comparison.reach_label')), $this->totalLabel(__('common.metric_views')), $this->totalLabel(__('common.metric_likes')), $this->totalLabel(__('common.metric_posts_count'))],
            'rows' => $rows,
        ];
    }

    /**
     * $type selects one tab of the Direktori Akun page — 'organisasi' (the live page's own
     * default when no tab is chosen), 'gereja', 'institusi', or 'personal'. Mirrors
     * ChurchDashboardController::directory() filter-for-filter: search, platform, auto_fetch,
     * hide_empty_*, sort_*, and the union_id/conference_id region filter — the latter applied
     * the same way every other export in this audit applies it, via applyRegionFilterToEntities()
     * (matchesRegionFilter() already handles Church/Person/Institution/Union/Conference/Division
     * identically to directory()'s own 4 query-level closures), except for Division rows, which
     * — same as the live page — are never affected by that filter at all (a Division has no
     * union_id/conference_id column of its own to narrow by).
     *
     * Previously this only recognised 'gereja'/'personal'/null via a broken two-branch
     * "if ($type !== 'personal') … if ($type !== 'gereja') …" structure: for 'institusi' and
     * 'organisasi' BOTH conditions were true, so it silently exported the combined Church+Person
     * list instead of institutions or Divisi/Uni/Daerah rows — and had none of directory()'s
     * other filters at all.
     */
    private function directoryDataset(?string $platform = null, ?string $type = null): array
    {
        $type = in_array($type, ['gereja', 'personal', 'institusi', 'organisasi'], true) ? $type : 'organisasi';

        $search = trim((string) request()->query('search'));
        $autoFetchQuery = request()->query('auto_fetch');
        $autoFetch = in_array($autoFetchQuery, ['auto', 'manual'], true) ? $autoFetchQuery : null;

        // Shared with whereHas() below so "no data" means the same thing as what the account
        // columns actually show — not just "no accounts at all" while ignoring the platform/
        // auto_fetch filters currently applied. Same closure shape as directory()'s own.
        $socialsFilter = fn ($query) => $query->where('is_active', true)
            ->when($platform, fn ($q) => $q->where('platform', $platform))
            ->when($autoFetch, fn ($q) => $q->where('is_auto_fetch', $autoFetch === 'auto'));

        [$rows, $headers] = match ($type) {
            'gereja' => $this->directoryChurchRows($search, $socialsFilter),
            'personal' => $this->directoryPersonRows($search, $socialsFilter),
            'institusi' => $this->directoryInstitutionRows($search, $socialsFilter),
            'organisasi' => $this->directoryOrganizationRows($search, $socialsFilter),
        };

        $scopeLabel = match ($type) {
            'gereja' => __('comparison.for_all_churches'),
            'personal' => __('comparison.for_all_personal'),
            'institusi' => __('comparison.for_all_institutions'),
            'organisasi' => __('comparison.for_all_organizations'),
        };

        $subtitle = $platform
            ? __('export.directory_subtitle_with_platform', ['platform' => $this->platformLabels[$platform], 'scope' => $scopeLabel])
            : __('export.directory_subtitle_no_platform', ['scope' => $scopeLabel]);

        return [
            'title' => __('nav.directory'),
            'subtitle' => $subtitle,
            'headers' => $headers,
            'rows' => $rows,
        ];
    }

    /**
     * The Conference-else-Union-else-null name for a region-groupable entity, as a single
     * orderable SQL expression — same shape as ChurchDashboardController's own
     * directoryScopeOrderExpression()/institutionRegionOrderExpression()/
     * personScopeOrderExpression(), just re-declared here since those are private to their own
     * controllers (this file has no shared trait with them for it).
     */
    private function directoryScopeOrderExpression(string $table)
    {
        return DB::raw("COALESCE(
            (SELECT name FROM conferences WHERE conferences.id = {$table}.conference_id),
            (SELECT name FROM unions WHERE unions.id = {$table}.union_id)
        )");
    }

    /** One export row per (church, social account) pair — matches the Gereja tab. */
    private function directoryChurchRows(string $search, \Closure $socialsFilter): array
    {
        $churches = Church::query()
            ->where('is_active', true)
            ->when($search, fn ($q) => $q->where(fn ($q2) => $q2
                ->where('name', 'like', "%{$search}%")
                ->orWhere('city', 'like', "%{$search}%"),
            ))
            ->when(request()->boolean('hide_empty_churches'), fn ($q) => $q->whereHas('socials', $socialsFilter))
            ->with(['socials' => $socialsFilter, 'conference.union.division'])
            ->tap(fn ($q) => match (request()->query('sort_gereja', 'name_asc')) {
                'name_desc' => $q->orderByDesc('name'),
                'city_asc' => $q->orderBy('city')->orderBy('name'),
                'city_desc' => $q->orderByDesc('city')->orderBy('name'),
                'daerah_asc' => $q->orderBy(Conference::select('name')->whereColumn('conferences.id', 'churches.conference_id'))->orderBy('name'),
                'daerah_desc' => $q->orderByDesc(Conference::select('name')->whereColumn('conferences.id', 'churches.conference_id'))->orderBy('name'),
                default => $q->orderBy('name'),
            })
            ->get();

        $rows = [];
        foreach ($this->applyRegionFilterToEntities($churches, 'gereja') as $church) {
            foreach ($church->socials as $social) {
                $rows[] = [
                    $church->name,
                    $church->city ?? '—',
                    $this->categoryLabels[$social->category->value],
                    $this->platformLabels[$social->platform->value],
                    $social->display_handle,
                ];
            }
        }

        return [$rows, [__('common.church'), __('entity.city'), __('entity.category'), __('common.platform'), __('common.account')]];
    }

    /** One export row per (person, social account) pair — matches the Personal tab. */
    private function directoryPersonRows(string $search, \Closure $socialsFilter): array
    {
        $people = Person::query()
            ->where('is_active', true)
            ->when($search, fn ($q) => $q->where(fn ($q2) => $q2
                ->where('name', 'like', "%{$search}%")
                ->orWhere('city', 'like', "%{$search}%"),
            ))
            ->when(request()->boolean('hide_empty_people'), fn ($q) => $q->whereHas('socials', $socialsFilter))
            ->with(['socials' => $socialsFilter, 'conference.union.division', 'union.division'])
            ->tap(fn ($q) => match (request()->query('sort_personal', 'name_asc')) {
                'name_desc' => $q->orderByDesc('name'),
                'city_asc' => $q->orderBy('city')->orderBy('name'),
                'city_desc' => $q->orderByDesc('city')->orderBy('name'),
                'scope_asc' => $q->orderBy($this->directoryScopeOrderExpression('people'))->orderBy('name'),
                'scope_desc' => $q->orderByDesc($this->directoryScopeOrderExpression('people'))->orderBy('name'),
                default => $q->orderBy('name'),
            })
            ->get();

        // No category column — unlike a church, a person-owned social account has no gereja/umum
        // choice at all (PersonSocialController::store() always hardcodes category to 'personal'),
        // and the live Personal tab table shows no such column either.
        $rows = [];
        foreach ($this->applyRegionFilterToEntities($people, 'personal') as $person) {
            foreach ($person->socials as $social) {
                $rows[] = [$person->name, $this->platformLabels[$social->platform->value], $social->display_handle];
            }
        }

        return [$rows, [__('common.name'), __('common.platform'), __('common.account')]];
    }

    /** One export row per (institution, social account) pair — matches the Institusi tab. */
    private function directoryInstitutionRows(string $search, \Closure $socialsFilter): array
    {
        $institutions = Institution::query()
            ->where('is_active', true)
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->when(request()->boolean('hide_empty_institutions'), fn ($q) => $q->whereHas('socials', $socialsFilter))
            ->with(['socials' => $socialsFilter, 'conference.union.division', 'union.division'])
            ->tap(fn ($q) => match (request()->query('sort_institusi', 'name_asc')) {
                'name_desc' => $q->orderByDesc('name'),
                'region_asc' => $q->orderBy($this->directoryScopeOrderExpression('institutions'))->orderBy('name'),
                'region_desc' => $q->orderByDesc($this->directoryScopeOrderExpression('institutions'))->orderBy('name'),
                default => $q->orderBy('name'),
            })
            ->get();

        // No category column — an institution-owned social account is always fixed to the
        // 'organisasi' category (no gereja/umum/personal picker), and the live Institusi tab
        // table shows no such column either. No city column either — the live table doesn't show
        // one for institutions (only name + accounts), even though Institution does have a city.
        $rows = [];
        foreach ($this->applyRegionFilterToEntities($institutions, 'institusi') as $institution) {
            foreach ($institution->socials as $social) {
                $rows[] = [$institution->name, $this->platformLabels[$social->platform->value], $social->display_handle];
            }
        }

        return [$rows, [__('common.institution'), __('common.platform'), __('common.account')]];
    }

    /**
     * One export row per (Divisi/Uni/Daerah, social account) pair — matches the Organisasi tab.
     * Divisions, Unions, and Conferences are queried (and region-filtered) separately, same as
     * directory() does, then concatenated in that same Divisi-then-Uni-then-Daerah order — a
     * Division has no union_id/conference_id column of its own, so (unlike Union/Conference) it
     * is never narrowed by the union_id/conference_id filter at all, exactly as the live page's
     * own comment on this explains.
     */
    private function directoryOrganizationRows(string $search, \Closure $socialsFilter): array
    {
        $sortOrganisasi = request()->query('sort_organisasi', 'name_asc');
        $hideEmpty = request()->boolean('hide_empty_organizations');
        $sortDirection = $sortOrganisasi === 'name_desc' ? 'desc' : 'asc';

        $divisions = Division::query()
            ->where('is_active', true)
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->when($hideEmpty, fn ($q) => $q->whereHas('socials', $socialsFilter))
            ->with(['socials' => $socialsFilter])
            ->orderBy('name', $sortDirection)
            ->get();

        $unions = Union::query()
            ->where('is_active', true)
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->when($hideEmpty, fn ($q) => $q->whereHas('socials', $socialsFilter))
            ->with(['socials' => $socialsFilter, 'division'])
            ->orderBy('name', $sortDirection)
            ->get();
        $unions = $this->applyRegionFilterToEntities($unions, 'organisasi');

        $conferences = Conference::query()
            ->where('is_active', true)
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->when($hideEmpty, fn ($q) => $q->whereHas('socials', $socialsFilter))
            ->with(['socials' => $socialsFilter, 'union.division'])
            ->orderBy('name', $sortDirection)
            ->get();
        $conferences = $this->applyRegionFilterToEntities($conferences, 'organisasi');

        $organizations = $divisions->concat($unions)->concat($conferences);

        $rows = [];
        foreach ($organizations as $organization) {
            $level = match (true) {
                $organization instanceof Division => __('analytics.organization_level_division'),
                $organization instanceof Union => __('analytics.organization_level_union'),
                default => __('analytics.organization_level_conference'),
            };

            foreach ($organization->socials as $social) {
                $rows[] = [$organization->name, $level, $this->platformLabels[$social->platform->value], $social->display_handle];
            }
        }

        return [$rows, [__('comparison.organization_name_label'), __('export.col_level'), __('common.platform'), __('common.account')]];
    }

    /**
     * Matches Perbandingan Hastag's own posts list — one row per post, same hashtag/platform/
     * Uni-Daerah filters as the live page (see BuildsLeaderboards::applyHashtagRegionFilter()),
     * ordered the same way (most recent first). The live page also shows a grand-total summary
     * table above the post list, but that's an aggregate of THIS SAME data — exporting the
     * underlying rows lets whoever downloads it re-aggregate however they need, rather than
     * flattening two very differently-shaped tables into one export.
     */
    private function hashtagDataset(?string $selectedHashtagId, ?string $selectedPlatform): array
    {
        $user = auth()->user();
        $isUniView = $this->isUniView();
        $selectedUnionId = $isUniView ? (string) $user->union_id : request()->query('union_id');
        $selectedConferenceId = request()->query('conference_id');

        // An export must always narrow exactly the same way the live page it's exporting does
        // — see the matching comment in ChurchDashboardController::hashtagComparisonData().
        if (! $selectedUnionId && ! $selectedConferenceId) {
            [$selectedUnionId, $selectedConferenceId] = $this->defaultHashtagRegionScope();
        }

        $noPersonalRegion = $user->role === null && ! $selectedUnionId && ! $selectedConferenceId;

        $posts = HashtagPost::query()
            ->with(['hashtag', 'churchSocial'])
            ->when($selectedHashtagId, fn ($q) => $q->where('hashtag_id', $selectedHashtagId))
            ->when($selectedPlatform, fn ($q) => $q->where('platform', $selectedPlatform))
            ->tap(fn ($q) => $noPersonalRegion ? $q->whereRaw('1 = 0') : $this->applyHashtagRegionFilter($q, $selectedUnionId, $selectedConferenceId))
            ->orderByDesc('posted_at')
            ->get();

        $rows = $posts->map(fn ($post) => [
            $this->platformLabels[$post->platform->value],
            $post->hashtag->display_tag,
            $post->churchSocial?->display_name ?? ($post->author_handle ?? '—'),
            $post->caption ?? '—',
            $post->likes_count !== null ? number_format($post->likes_count) : '—',
            $post->views_count !== null ? number_format($post->views_count) : '—',
            $post->posted_at?->translatedFormat('d M Y') ?? '—',
        ])->all();

        $selectedHashtag = $selectedHashtagId ? Hashtag::find($selectedHashtagId) : null;
        $scopeLabel = $selectedHashtag ? $selectedHashtag->display_tag : __('hashtag.all_hashtags');

        $subtitle = $selectedPlatform
            ? __('export.directory_subtitle_with_platform', ['platform' => $this->platformLabels[$selectedPlatform], 'scope' => $scopeLabel])
            : __('export.directory_subtitle_no_platform', ['scope' => $scopeLabel]);

        return [
            'title' => __('hashtag.comparison_title'),
            'subtitle' => $subtitle,
            'headers' => [__('common.platform'), __('hashtag.col_tag'), __('hashtag.col_author'), __('hashtag.col_caption'), __('hashtag.col_likes'), __('common.metric_views'), __('hashtag.col_posted_at')],
            'rows' => $rows,
        ];
    }

    /**
     * The identical is_auto_fetch/last_fetch_status → label logic repeated 3 times across
     * churchDataset()/personDataset()/institutionDataset() — same reasoning as the other shared
     * helpers above. 'success' has no existing UI-wide label to reuse (entity.status_auto means
     * something different — "set to auto-fetch," not "last attempt succeeded" — see the
     * i18n-audit note this was found under), so it gets its own export-only key.
     */
    private function fetchStatusLabel(ChurchSocial $social): string
    {
        if (! $social->is_auto_fetch) {
            return __('entity.status_manual');
        }

        return match ($social->last_fetch_status) {
            'failed' => __('entity.status_failed'),
            default => $social->last_fetched_at ? __('export.status_success') : __('entity.status_pending'),
        };
    }

    /** Same profile-table header row shared by church/person/institution single-entity exports. */
    private function socialProfileHeaders(bool $withCategory): array
    {
        $headers = [__('common.platform')];

        if ($withCategory) {
            $headers[] = __('entity.category');
        }

        $headers[] = __('common.account');
        $headers[] = __('export.col_followers_subs');
        $headers[] = __('common.metric_following');
        $headers[] = __('export.col_post_video');
        $headers[] = __('common.metric_views');
        $headers[] = __('common.metric_likes');
        $headers[] = __('common.status');

        return $headers;
    }

    private function churchDataset(Church $church): array
    {
        $church->load(['socials' => fn ($q) => $q->where('is_active', true)->with('latestStat')]);

        $rows = $church->socials->map(function ($social) {
            $latest = $social->latestStat;
            $field = $this->countField[$social->platform->value];
            $viewsField = $this->viewsField[$social->platform->value] ?? 'views_count';

            return [
                $this->platformLabels[$social->platform->value],
                $this->categoryLabels[$social->category->value],
                $social->display_handle,
                $latest ? number_format($latest->{$field} ?? 0) : '—',
                $latest ? number_format($latest->following_count ?? 0) : '—',
                $latest ? number_format($latest->posts_count ?? $latest->videos_count ?? $latest->recent_posts_count ?? 0) : '—',
                $latest && $latest->{$viewsField} ? number_format($latest->{$viewsField}) : '—',
                $latest && $latest->likes_count ? number_format($latest->likes_count) : '—',
                $this->fetchStatusLabel($social),
            ];
        })->all();

        return [
            'title' => $church->name,
            'subtitle' => $church->city ? __('export.social_data_subtitle_with_city', ['city' => $church->city]) : __('export.social_data_subtitle'),
            'headers' => $this->socialProfileHeaders(withCategory: true),
            'rows' => $rows,
        ];
    }

    private function personDataset(Person $person): array
    {
        $person->load(['socials' => fn ($q) => $q->where('is_active', true)->with('latestStat')]);

        $rows = $person->socials->map(function ($social) {
            $latest = $social->latestStat;
            $field = $this->countField[$social->platform->value];
            $viewsField = $this->viewsField[$social->platform->value] ?? 'views_count';

            return [
                $this->platformLabels[$social->platform->value],
                $social->display_handle,
                $latest ? number_format($latest->{$field} ?? 0) : '—',
                $latest ? number_format($latest->following_count ?? 0) : '—',
                $latest ? number_format($latest->posts_count ?? $latest->videos_count ?? $latest->recent_posts_count ?? 0) : '—',
                $latest && $latest->{$viewsField} ? number_format($latest->{$viewsField}) : '—',
                $latest && $latest->likes_count ? number_format($latest->likes_count) : '—',
                $this->fetchStatusLabel($social),
            ];
        })->all();

        return [
            'title' => $person->name,
            'subtitle' => $person->city ? __('export.social_data_subtitle_with_city', ['city' => $person->city]) : __('export.social_data_subtitle'),
            'headers' => $this->socialProfileHeaders(withCategory: false),
            'rows' => $rows,
        ];
    }

    private function institutionDataset(Institution $institution): array
    {
        $institution->load(['socials' => fn ($q) => $q->where('is_active', true)->with('latestStat')]);

        $rows = $institution->socials->map(function ($social) {
            $latest = $social->latestStat;
            $field = $this->countField[$social->platform->value];
            $viewsField = $this->viewsField[$social->platform->value] ?? 'views_count';

            return [
                $this->platformLabels[$social->platform->value],
                $social->display_handle,
                $latest ? number_format($latest->{$field} ?? 0) : '—',
                $latest ? number_format($latest->following_count ?? 0) : '—',
                $latest ? number_format($latest->posts_count ?? $latest->videos_count ?? $latest->recent_posts_count ?? 0) : '—',
                $latest && $latest->{$viewsField} ? number_format($latest->{$viewsField}) : '—',
                $latest && $latest->likes_count ? number_format($latest->likes_count) : '—',
                $this->fetchStatusLabel($social),
            ];
        })->all();

        return [
            'title' => $institution->name,
            'subtitle' => __('export.social_data_subtitle'),
            'headers' => $this->socialProfileHeaders(withCategory: false),
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
            'title' => __('export.history_title', ['handle' => $social->display_handle]),
            'subtitle' => "{$this->platformLabels[$social->platform->value]} — {$owner->name}",
            'headers' => [__('export.col_date'), __('export.col_followers_subs'), __('common.metric_following'), __('export.col_post_video'), __('common.metric_views'), __('common.metric_likes')],
            'rows' => $rows,
            // One row per date for this SAME account — see addTotalsRow()'s own doc comment for
            // why a bottom Total row would be actively misleading here (repeated snapshots of a
            // running total, not distinct entities to sum together).
            'suppressTotals' => true,
        ];
    }

    private function platformOverviewDataset(string $platform, ?string $category = null): array
    {
        $applicableMetrics = collect($this->metricPlatforms())
            ->filter(fn ($platforms) => in_array($platform, $platforms, true))
            ->keys();

        $rowsByMetric = $applicableMetrics->mapWithKeys(fn ($metric) => [
            $metric => $this->applyRegionFilterToRows($this->metricComparisonRows($metric, $platform, category: $category), 'gereja')->keyBy(fn ($row) => $row['church']->id),
        ]);

        $churches = $rowsByMetric
            ->flatMap(fn ($rows) => $rows->pluck('church'))
            ->unique('id')
            ->sortBy('name')
            ->values();

        $headers = [__('common.church')];
        $valueHeaders = [];
        foreach ($applicableMetrics as $metric) {
            $valueHeader = $this->valueHeaderLabel($metric, $platform);
            $valueHeaders[$metric] = $valueHeader;
            $headers[] = $valueHeader;
            $headers[] = __('export.growth_prefix', ['title' => $valueHeader]);
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
            'title' => __('export.platform_overview_title', ['platform' => $platformLabel]).__('comparison.title_suffix_church'),
            'subtitle' => __('export.platform_overview_subtitle', ['metrics' => $metricNames, 'scope' => __('comparison.for_all_churches')]),
            'headers' => $headers,
            'rows' => $rows,
            'summary' => $applicableMetrics->map(fn ($metric) => ['label' => $this->totalLabel($valueHeaders[$metric]), 'value' => number_format($rowsByMetric[$metric]->sum('value'))])->values()->all(),
        ];
    }

    private function platformComparisonDataset(string $platform, string $metric, string $sortBy = 'delta', ?string $category = null): array
    {
        $rows = $this->applyRegionFilterToRows($this->metricComparisonRows($metric, $platform, $sortBy, $category), 'gereja');
        $platformLabel = $this->platformLabels[$platform];
        $valueHeader = $this->valueHeaderLabel($metric, $platform);

        $subtitle = $sortBy === 'delta'
            ? __('export.platform_ranking_subtitle_delta', ['scope' => __('comparison.scope_church'), 'value' => $valueHeader, 'platform' => $platformLabel])
            : __('export.platform_ranking_subtitle_value', ['scope' => __('comparison.scope_church'), 'value' => $valueHeader, 'platform' => $platformLabel]);

        return [
            'title' => __('export.platform_ranking_title', ['value' => $valueHeader, 'platform' => $platformLabel]).__('comparison.title_suffix_church'),
            'subtitle' => $subtitle,
            'headers' => ['#', __('common.church'), $valueHeader, __('comparison.weekly_growth')],
            'rows' => $rows->values()->map(fn ($row, $i) => [
                $i + 1,
                $row['label'],
                number_format($row['value']),
                $row['delta'] === null ? '—' : ($row['delta'] > 0 ? '+' : '').number_format($row['delta']),
            ])->all(),
            'summary' => [['label' => __('export.total_value_platform', ['value' => $valueHeader, 'platform' => $platformLabel]), 'value' => number_format($rows->sum('value'))]],
        ];
    }

    private function platformOverviewDatasetPersonal(string $platform): array
    {
        $applicableMetrics = collect($this->metricPlatforms())
            ->filter(fn ($platforms) => in_array($platform, $platforms, true))
            ->keys();

        $rowsByMetric = $applicableMetrics->mapWithKeys(fn ($metric) => [
            $metric => $this->applyRegionFilterToRows($this->metricComparisonRowsPersonal($metric, $platform), 'personal')->keyBy(fn ($row) => $row['person']->id),
        ]);

        $people = $rowsByMetric
            ->flatMap(fn ($rows) => $rows->pluck('person'))
            ->unique('id')
            ->sortBy('name')
            ->values();

        $headers = [__('common.name')];
        $valueHeaders = [];
        foreach ($applicableMetrics as $metric) {
            $valueHeader = $this->valueHeaderLabel($metric, $platform);
            $valueHeaders[$metric] = $valueHeader;
            $headers[] = $valueHeader;
            $headers[] = __('export.growth_prefix', ['title' => $valueHeader]);
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
            'title' => __('export.platform_overview_title', ['platform' => $platformLabel]).__('comparison.title_suffix_personal'),
            'subtitle' => __('export.platform_overview_subtitle', ['metrics' => $metricNames, 'scope' => __('comparison.for_all_personal')]),
            'headers' => $headers,
            'rows' => $rows,
            'summary' => $applicableMetrics->map(fn ($metric) => ['label' => $this->totalLabel($valueHeaders[$metric]), 'value' => number_format($rowsByMetric[$metric]->sum('value'))])->values()->all(),
        ];
    }

    private function platformComparisonDatasetPersonal(string $platform, string $metric, string $sortBy = 'delta'): array
    {
        $rows = $this->applyRegionFilterToRows($this->metricComparisonRowsPersonal($metric, $platform, $sortBy), 'personal');
        $platformLabel = $this->platformLabels[$platform];
        $valueHeader = $this->valueHeaderLabel($metric, $platform);

        $subtitle = $sortBy === 'delta'
            ? __('export.platform_ranking_subtitle_delta', ['scope' => __('comparison.scope_personal'), 'value' => $valueHeader, 'platform' => $platformLabel])
            : __('export.platform_ranking_subtitle_value', ['scope' => __('comparison.scope_personal'), 'value' => $valueHeader, 'platform' => $platformLabel]);

        return [
            'title' => __('export.platform_ranking_title', ['value' => $valueHeader, 'platform' => $platformLabel]).__('comparison.title_suffix_personal'),
            'subtitle' => $subtitle,
            'headers' => ['#', __('common.name'), $valueHeader, __('comparison.weekly_growth')],
            'rows' => $rows->values()->map(fn ($row, $i) => [
                $i + 1,
                $row['label'],
                number_format($row['value']),
                $row['delta'] === null ? '—' : ($row['delta'] > 0 ? '+' : '').number_format($row['delta']),
            ])->all(),
            'summary' => [['label' => __('export.total_value_platform', ['value' => $valueHeader, 'platform' => $platformLabel]), 'value' => number_format($rows->sum('value'))]],
        ];
    }

    private function platformOverviewDatasetInstitution(string $platform): array
    {
        $applicableMetrics = collect($this->metricPlatforms())
            ->filter(fn ($platforms) => in_array($platform, $platforms, true))
            ->keys();

        $rowsByMetric = $applicableMetrics->mapWithKeys(fn ($metric) => [
            $metric => $this->applyRegionFilterToRows($this->metricComparisonRowsInstitution($metric, $platform), 'institusi')->keyBy(fn ($row) => $row['institution']->id),
        ]);

        $institutions = $rowsByMetric
            ->flatMap(fn ($rows) => $rows->pluck('institution'))
            ->unique('id')
            ->sortBy('name')
            ->values();

        $headers = [__('common.institution')];
        $valueHeaders = [];
        foreach ($applicableMetrics as $metric) {
            $valueHeader = $this->valueHeaderLabel($metric, $platform);
            $valueHeaders[$metric] = $valueHeader;
            $headers[] = $valueHeader;
            $headers[] = __('export.growth_prefix', ['title' => $valueHeader]);
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
            'title' => __('export.platform_overview_title', ['platform' => $platformLabel]).__('comparison.title_suffix_institution'),
            'subtitle' => __('export.platform_overview_subtitle', ['metrics' => $metricNames, 'scope' => __('comparison.for_all_institutions')]),
            'headers' => $headers,
            'rows' => $rows,
            'summary' => $applicableMetrics->map(fn ($metric) => ['label' => $this->totalLabel($valueHeaders[$metric]), 'value' => number_format($rowsByMetric[$metric]->sum('value'))])->values()->all(),
        ];
    }

    private function platformComparisonDatasetInstitution(string $platform, string $metric, string $sortBy = 'delta'): array
    {
        $rows = $this->applyRegionFilterToRows($this->metricComparisonRowsInstitution($metric, $platform, $sortBy), 'institusi');
        $platformLabel = $this->platformLabels[$platform];
        $valueHeader = $this->valueHeaderLabel($metric, $platform);

        $subtitle = $sortBy === 'delta'
            ? __('export.platform_ranking_subtitle_delta', ['scope' => __('comparison.scope_institution'), 'value' => $valueHeader, 'platform' => $platformLabel])
            : __('export.platform_ranking_subtitle_value', ['scope' => __('comparison.scope_institution'), 'value' => $valueHeader, 'platform' => $platformLabel]);

        return [
            'title' => __('export.platform_ranking_title', ['value' => $valueHeader, 'platform' => $platformLabel]).__('comparison.title_suffix_institution'),
            'subtitle' => $subtitle,
            'headers' => ['#', __('common.institution'), $valueHeader, __('comparison.weekly_growth')],
            'rows' => $rows->values()->map(fn ($row, $i) => [
                $i + 1,
                $row['label'],
                number_format($row['value']),
                $row['delta'] === null ? '—' : ($row['delta'] > 0 ? '+' : '').number_format($row['delta']),
            ])->all(),
            'summary' => [['label' => __('export.total_value_platform', ['value' => $valueHeader, 'platform' => $platformLabel]), 'value' => number_format($rows->sum('value'))]],
        ];
    }

    private function analyticsDatasetInstitution(?string $institutionId, ?string $platform): array
    {
        // analyticsInstitutionScope() (BuildsLeaderboards), not a plain visibleTo() — same
        // bug/fix as analyticsDataset() above. applyRegionFilterToEntities() mirrors
        // analytics()'s own ->filter($matchesRegionFilter) on $institutions, missing here too
        // before this fix.
        $institutions = $this->applyRegionFilterToEntities(
            $this->analyticsInstitutionScope(Institution::query()->where('is_active', true))
                ->with(['socials' => fn ($q) => $q->where('is_active', true)->with('latestStat')])
                ->when($institutionId, fn ($q) => $q->where('id', $institutionId))
                ->orderBy('name')
                ->get(),
            'institusi'
        )
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

        $subtitle = $filterParts
            ? __('export.analytics_filter_prefix', ['filters' => implode(', ', $filterParts)])
            : __('export.analytics_no_filter', ['scope' => __('comparison.scope_institution')]);

        return [
            'title' => __('export.analytics_title').__('comparison.title_suffix_institution'),
            'subtitle' => $subtitle,
            'headers' => [__('common.institution'), __('export.col_account_count'), $this->totalLabel(__('comparison.reach_label')), $this->totalLabel(__('common.metric_views')), $this->totalLabel(__('common.metric_likes')), $this->totalLabel(__('common.metric_posts_count'))],
            'rows' => $rows,
        ];
    }

    private function analyticsDataset(?string $churchId, ?string $platform, ?string $category = null): array
    {
        // analyticsChurchScope() (BuildsLeaderboards), not a plain visibleTo() — that plain call
        // was a confirmed bug: it returned an empty result for a plain member (role === null,
        // which visibleTo() treats as "sees nothing") and only the viewer's own single church
        // for admin_gereja, when the live Analitik & Grafik page this mirrors deliberately widens
        // both cases (a plain member sees everything unscoped, admin_gereja sees their whole
        // Daerah/Konferens) — see analyticsChurchScope()'s own doc comment for why.
        // ->filter($matchesRegionFilter) applied right after get(), same as
        // ChurchDashboardController::analytics()'s own $churches — a confirmed gap the audit
        // found: union_id/conference_id was never read here at all.
        $churches = $this->applyRegionFilterToEntities(
            $this->analyticsChurchScope(Church::query()->where('is_active', true))
                ->with(['socials' => fn ($q) => $q->where('is_active', true)->with('latestStat')])
                ->when($churchId, fn ($q) => $q->where('id', $churchId))
                ->orderBy('name')
                ->get(),
            'gereja'
        )
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

        $subtitle = $filterParts
            ? __('export.analytics_filter_prefix', ['filters' => implode(', ', $filterParts)])
            : __('export.analytics_no_filter', ['scope' => __('comparison.scope_church')]);

        return [
            'title' => __('export.analytics_title'),
            'subtitle' => $subtitle,
            'headers' => [__('common.church'), __('entity.city'), __('directory.church_accounts'), __('directory.general_accounts'), $this->totalLabel(__('comparison.reach_label')), $this->totalLabel(__('common.metric_views')), $this->totalLabel(__('common.metric_likes')), $this->totalLabel(__('common.metric_posts_count'))],
            'rows' => $rows,
        ];
    }

    private function analyticsDatasetPersonal(?string $personId, ?string $platform): array
    {
        // analyticsPersonScope() (BuildsLeaderboards), not a plain visibleTo() — same bug/fix as
        // analyticsDataset() above. applyRegionFilterToEntities() mirrors analytics()'s own
        // ->filter($matchesRegionFilter) on $people, missing here too before this fix.
        $people = $this->applyRegionFilterToEntities(
            $this->analyticsPersonScope(Person::query()->where('is_active', true))
                ->with(['socials' => fn ($q) => $q->where('is_active', true)->with('latestStat')])
                ->when($personId, fn ($q) => $q->where('id', $personId))
                ->orderBy('name')
                ->get(),
            'personal'
        )
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

        $subtitle = $filterParts
            ? __('export.analytics_filter_prefix', ['filters' => implode(', ', $filterParts)])
            : __('export.analytics_no_filter', ['scope' => __('comparison.scope_personal')]);

        return [
            'title' => __('export.analytics_title').__('comparison.title_suffix_personal'),
            'subtitle' => $subtitle,
            'headers' => [__('common.name'), __('export.col_account_count'), $this->totalLabel(__('comparison.reach_label')), $this->totalLabel(__('common.metric_views')), $this->totalLabel(__('common.metric_likes')), $this->totalLabel(__('common.metric_posts_count'))],
            'rows' => $rows,
        ];
    }

    /**
     * A "Total" row auto-appended to the bottom of any export table that has at least one
     * summable numeric column — per the user's explicit call, for every export with a list
     * and categorized numeric data (e.g. Analitik & Grafik's Reach/Views/Likes/Posts columns
     * per church, or Direktori's per-entity account counts), not just the handful of
     * datasets that already carry a single-metric 'summary' line above the table (that one
     * answers a different question — "what's the total for the one metric already selected"
     * — and both can coexist without conflict). Centralized here rather than in each of the
     * ~25 *Dataset() builders, since every one of them already funnels through preview()/
     * download() with the same ['headers' => ..., 'rows' => ...] shape.
     */
    private function addTotalsRow(array $dataset): array
    {
        // socialHistoryDataset() is the one dataset shape this auto-detection would get wrong:
        // each row is the SAME account sampled at a different date, not a different entity —
        // summing "Followers" down that column would add up repeated snapshots of one running
        // total into a meaningless number, not an actual total of anything. Every other
        // dataset in this file is one row per distinct entity (church/person/post/etc.), where
        // summing down a numeric column is a real grand total.
        $dataset['totals'] = ($dataset['suppressTotals'] ?? false)
            ? null
            : $this->buildTotalsRow($dataset['headers'], $dataset['rows']);

        return $dataset;
    }

    /**
     * Null when no column in $rows is safely summable — e.g. a pure-text listing (Direktori's
     * name/handle/city columns) or a table with only percentage/growth columns (those are
     * deliberately excluded, see summableColumnIndexes()) gets no Total row at all, rather
     * than a row of meaningless blanks.
     */
    private function buildTotalsRow(array $headers, array $rows): ?array
    {
        $summableColumns = $this->summableColumnIndexes($headers, $rows);

        if ($summableColumns === []) {
            return null;
        }

        $totals = array_fill(0, count($headers), '');
        $labelPlaced = false;

        foreach ($totals as $column => $ignored) {
            if (in_array($column, $summableColumns, true)) {
                $sum = collect($rows)->sum(fn ($row) => $this->parseExportNumber($row[$column] ?? null));
                $totals[$column] = number_format($sum);
            } elseif (! $labelPlaced) {
                // The first non-summable column reads "Total" (usually the entity name/label
                // column) — every other non-summable column between it and the next summable
                // one stays blank, same as a normal table footer.
                $totals[$column] = __('export.total_row_label');
                $labelPlaced = true;
            }
        }

        return $totals;
    }

    /**
     * A column counts as summable when every one of its cells is either a blank/dash
     * placeholder or a plain formatted integer (what every count/reach/views/likes/posts/
     * growth-delta column in this file's *Dataset() builders actually renders via
     * number_format()) — deliberately excludes a "#" rank column by header text, and
     * excludes percentage columns (formatPercent()'s "+12.3%"/"—") and decimal values
     * automatically, since neither matches the plain-integer pattern below and summing
     * either would be meaningless.
     */
    private function summableColumnIndexes(array $headers, array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $summable = [];

        foreach (array_keys($headers) as $column) {
            if (trim((string) $headers[$column]) === '#') {
                continue;
            }

            $sawNumber = false;
            $allNumericOrBlank = true;

            foreach ($rows as $row) {
                $cell = trim((string) ($row[$column] ?? ''));

                if ($cell === '' || $cell === '—' || $cell === '-') {
                    continue;
                }

                if (! preg_match('/^[+-]?\d{1,3}(,\d{3})*$/', $cell)) {
                    $allNumericOrBlank = false;

                    break;
                }

                $sawNumber = true;
            }

            if ($allNumericOrBlank && $sawNumber) {
                $summable[] = $column;
            }
        }

        return $summable;
    }

    private function parseExportNumber(mixed $cell): int
    {
        $cell = trim((string) $cell);

        return ($cell === '' || $cell === '—' || $cell === '-') ? 0 : (int) str_replace(',', '', $cell);
    }

    private function preview(array $dataset, string $pdfDownloadUrl)
    {
        $dataset = $this->addTotalsRow($dataset);

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
        $dataset = $this->addTotalsRow($dataset);
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

            $pageText = __('export.pdf_page_of', ['current' => $pageNumber, 'total' => $pageCount]);
            $pageWidth = $fontMetrics->getTextWidth($pageText, $font, $size);
            $canvas->text($canvas->get_width() - 40 - $pageWidth, $y, $pageText, $font, $size, $color);
        });

        return $pdf->download($filename);
    }

    private function footerText(): string
    {
        $text = __('export.downloaded_footer', ['datetime' => Carbon::now('Asia/Jakarta')->translatedFormat('d M Y H:i')]);

        if (auth()->check()) {
            $text .= __('export.downloaded_footer_by', ['name' => auth()->user()->name]);
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

        if (! empty($dataset['totals'])) {
            $table->addRow();
            foreach ($dataset['totals'] as $cell) {
                $table->addCell($columnWidth, ['bgColor' => 'f1f5f9'])->addText((string) $cell, ['bold' => true, 'size' => 9]);
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
        $sheet->setTitle(__('export.sheet_title'));

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

        $lastDataRow = $headerRow + count($dataset['rows']);

        if (! empty($dataset['totals'])) {
            $totalsRow = $lastDataRow + 1;
            $sheet->fromArray($dataset['totals'], null, "A{$totalsRow}");
            $sheet->getStyle("A{$totalsRow}:{$lastColumn}{$totalsRow}")->getFont()->setBold(true);
            $sheet->getStyle("A{$totalsRow}:{$lastColumn}{$totalsRow}")->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('F1F5F9');
            $lastDataRow = $totalsRow;
        }

        foreach (range('A', $lastColumn) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $footerRow = $lastDataRow + 2;
        $sheet->setCellValue("A{$footerRow}", $footer);
        $sheet->getStyle("A{$footerRow}")->getFont()->setItalic(true)->setSize(9);

        $tempPath = tempnam(sys_get_temp_dir(), 'export').'.xlsx';
        (new Xlsx($spreadsheet))->save($tempPath);

        return response()->download($tempPath, $filename)->deleteFileAfterSend(true);
    }
}

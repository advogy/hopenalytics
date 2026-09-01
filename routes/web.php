<?php

use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\ConferenceController;
use App\Http\Controllers\Admin\DivisionController;
use App\Http\Controllers\Admin\HashtagController;
use App\Http\Controllers\Admin\InstitutionController;
use App\Http\Controllers\Admin\OrganizationSocialController;
use App\Http\Controllers\Admin\UnionController;
use App\Http\Controllers\Admin\UserAssignmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChurchController;
use App\Http\Controllers\ChurchDashboardController;
use App\Http\Controllers\ChurchRefreshController;
use App\Http\Controllers\ChurchSocialController;
use App\Http\Controllers\CompleteProfileController;
use App\Http\Controllers\DeployController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\GoalController;
use App\Http\Controllers\LinkPersonController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MyAccountController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\PersonSocialController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QueueMonitorController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SocialStatController;
use App\Http\Middleware\RedirectUnassignedMembers;
use Illuminate\Support\Facades\Route;

Route::get('/locale/{locale}', [LocaleController::class, 'switch'])->name('locale.switch');

// CI's zip-extract + migrate trigger (see .github/workflows/deploy.yml) — token-guarded, see
// App\Http\Middleware\VerifyDeployToken. throttle on top of the token check as defense in depth.
Route::post('/deploy/run', [DeployController::class, 'run'])
    ->name('deploy.run')
    ->middleware(['throttle:6,1', \App\Http\Middleware\VerifyDeployToken::class]);

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.attempt')->middleware('throttle:login');
Route::get('/register', [RegisterController::class, 'showRegister'])->name('register');
Route::post('/register', [RegisterController::class, 'register'])->name('register.attempt');

Route::get('/verify-otp', [RegisterController::class, 'showVerifyOtp'])->name('verify-otp');
Route::post('/verify-otp', [RegisterController::class, 'verifyOtp'])->name('verify-otp.attempt')->middleware('throttle:verify-otp');
Route::post('/verify-otp/resend', [RegisterController::class, 'resendOtp'])->name('verify-otp.resend')->middleware('throttle:3,10');
Route::post('/verify-otp/cancel', [RegisterController::class, 'cancelRegistration'])->name('verify-otp.cancel');

// This app verifies email via the custom OTP flow above, not Laravel's default email-link
// flow — but the 'verified' middleware guarding the whole logged-in area below (see
// EnsureEmailIsVerified) still redirects to the framework's default 'verification.notice' route
// name whenever it encounters an authenticated-but-unverified session. AuthController::login()
// already screens this out on a normal password login, but any other way a session ends up
// authenticated-yet-unverified (a stale/"remember me" cookie predating that check, etc.) would
// otherwise crash with RouteNotFoundException instead of a clean redirect — confirmed happening
// to real users in production logs. This is the fallback that keeps that failure mode a redirect.
Route::get('/verifikasi-email', fn () => redirect()->route('verify-otp'))->name('verification.notice');

Route::get('/forgot-password', [ForgotPasswordController::class, 'show'])->name('forgot-password');
Route::post('/forgot-password', [ForgotPasswordController::class, 'send'])->name('forgot-password.send')->middleware('throttle:3,10');
Route::get('/reset-password', [ForgotPasswordController::class, 'showReset'])->name('reset-password');
Route::post('/reset-password', [ForgotPasswordController::class, 'reset'])->name('reset-password.attempt')->middleware('throttle:reset-password');
Route::post('/reset-password/resend', [ForgotPasswordController::class, 'resend'])->name('reset-password.resend')->middleware('throttle:3,10');
Route::post('/reset-password/cancel', [ForgotPasswordController::class, 'cancel'])->name('reset-password.cancel');

// Public: meant to be shown fullscreen on a screen/projector during events, so it can't
// require anyone to be logged in. Unauthenticated visibility falls back to "everything"
// (see Church::scopeVisibleTo()), matching what these pages showed before RBAC existed.
Route::get('/gereja/presentation', [ChurchDashboardController::class, 'presentation'])->name('churches.presentation');
Route::get('/gereja/presentation/growth', [ChurchDashboardController::class, 'presentationGrowth'])->name('churches.presentation-growth');
Route::get('/personal/presentation', [ChurchDashboardController::class, 'personalPresentation'])->name('people.presentation');
Route::get('/personal/presentation/growth', [ChurchDashboardController::class, 'personalPresentationGrowth'])->name('people.presentation-growth');

// Public mirror of the logged-in /about page (same content, see partials/about-content) — for
// the login page's "Tentang" link, which by definition has to work before anyone's logged in.
Route::view('/tentang', 'about-public')->name('about.public');

Route::middleware(['auth', 'verified', RedirectUnassignedMembers::class])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [MyAccountController::class, 'index'])->name('akun-saya');

    Route::get('/lengkapi-profil', [CompleteProfileController::class, 'show'])->name('profile.complete');
    Route::post('/lengkapi-profil', [CompleteProfileController::class, 'store'])->name('profile.complete.store');
    Route::post('/lengkapi-profil/skip', [CompleteProfileController::class, 'skip'])->name('profile.complete.skip');

    Route::get('/pilih-personal', [LinkPersonController::class, 'show'])->name('link-person');
    Route::post('/pilih-personal', [LinkPersonController::class, 'store'])->name('link-person.store');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');
    Route::get('/profile/verify-email', [ProfileController::class, 'showVerifyEmailChange'])->name('profile.verify-email');
    Route::post('/profile/verify-email', [ProfileController::class, 'verifyEmailChange'])->name('profile.verify-email.attempt')->middleware('throttle:verify-email-change');
    Route::post('/profile/verify-email/resend', [ProfileController::class, 'resendEmailChangeOtp'])->name('profile.verify-email.resend')->middleware('throttle:3,10');
    Route::post('/profile/verify-email/cancel', [ProfileController::class, 'cancelEmailChange'])->name('profile.verify-email.cancel');

    Route::middleware('can:manage-queue')->group(function () {
        Route::get('/queue', [QueueMonitorController::class, 'index'])->name('queue.index');
        Route::post('/queue/{batch}/cancel', [QueueMonitorController::class, 'cancelBatch'])->name('queue.cancel-batch');
        Route::post('/queue/clear', [QueueMonitorController::class, 'clearQueue'])->name('queue.clear');
        Route::post('/queue/failed/clear', [QueueMonitorController::class, 'clearFailed'])->name('queue.clear-failed');
        Route::post('/queue/failed/{id}/delete', [QueueMonitorController::class, 'deleteFailed'])->name('queue.delete-failed');
        Route::post('/queue/failed/{id}/retry', [QueueMonitorController::class, 'retryFailed'])->name('queue.retry-failed');
        Route::post('/queue/batches/clear', [QueueMonitorController::class, 'clearCompletedBatches'])->name('queue.clear-completed-batches');
        Route::post('/queue/batches/{batch}/delete', [QueueMonitorController::class, 'deleteBatch'])->name('queue.delete-batch');
    });

    Route::get('/', [ChurchDashboardController::class, 'index'])->name('churches.index');
    Route::get('/analytics', [ChurchDashboardController::class, 'analytics'])->name('churches.analytics')->middleware('can:view-analytics');
    Route::get('/gereja/metrik', [ChurchDashboardController::class, 'metricComparison'])->name('churches.metric-comparison')->middleware('can:view-analytics');
    Route::get('/gereja/metrik/{metric}', [ChurchDashboardController::class, 'leaderboard'])->name('churches.leaderboard')->middleware('can:view-analytics');
    Route::get('/directory', [ChurchDashboardController::class, 'directory'])->name('churches.directory')->middleware('can:view-directory');
    Route::get('/akun-bermasalah', [ChurchDashboardController::class, 'needsAttention'])->name('churches.needs-attention')->middleware('can:view-analytics');
    Route::get('/akun-otomatis', [ChurchDashboardController::class, 'autoFetchAccounts'])->name('churches.auto-fetch-accounts')->middleware('can:view-analytics');
    Route::get('/akun-manual', [ChurchDashboardController::class, 'manualAccounts'])->name('churches.manual-accounts')->middleware('can:view-analytics');
    Route::get('/gereja/platform/{platform?}', [ChurchDashboardController::class, 'platformComparison'])->name('churches.platform-comparison')->middleware('can:view-analytics');
    Route::get('/gereja/hastag', [ChurchDashboardController::class, 'hashtagComparison'])->name('churches.hashtag-comparison')->middleware('can:view-analytics');

    Route::view('/about', 'about')->name('about');

    Route::middleware('can:manage-settings')->group(function () {
        Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
    });

    Route::middleware('can:manage-settings')->prefix('admin/brands')->name('admin.brands.')->group(function () {
        Route::get('/', [BrandController::class, 'index'])->name('index');
        Route::get('/create', [BrandController::class, 'create'])->name('create');
        Route::post('/', [BrandController::class, 'store'])->name('store');
        Route::get('/{brand}/edit', [BrandController::class, 'edit'])->name('edit');
        Route::put('/{brand}', [BrandController::class, 'update'])->name('update');
        Route::delete('/{brand}', [BrandController::class, 'destroy'])->name('destroy');
    });

    Route::middleware('can:manage-settings')->prefix('admin/hashtags')->name('admin.hashtags.')->group(function () {
        Route::get('/', [HashtagController::class, 'index'])->name('index');
        Route::post('/', [HashtagController::class, 'store'])->name('store');
        Route::patch('/{hashtag}/toggle-active', [HashtagController::class, 'toggleActive'])->name('toggle-active');
        Route::delete('/{hashtag}', [HashtagController::class, 'destroy'])->name('destroy');
    });

    Route::middleware('can:manage-goals')->group(function () {
        Route::get('/tujuan', [GoalController::class, 'edit'])->name('goals.edit');
        Route::put('/tujuan', [GoalController::class, 'update'])->name('goals.update');
    });

    Route::middleware('can:create,App\Models\Church')->group(function () {
        Route::get('/churches/create', [ChurchController::class, 'create'])->name('churches.create');
        Route::post('/churches', [ChurchController::class, 'store'])->name('churches.store');
    });
    Route::get('/churches/similar', [ChurchController::class, 'similar'])->name('churches.similar');
    Route::get('/churches/{church:slug}/edit', [ChurchController::class, 'edit'])->name('churches.edit')->middleware('can:update,church');
    Route::put('/churches/{church:slug}', [ChurchController::class, 'update'])->name('churches.update')->middleware('can:update,church');
    Route::patch('/churches/{church:slug}/toggle-active', [ChurchController::class, 'toggleActive'])->name('churches.toggle-active')->middleware('can:delete,church');
    Route::delete('/churches/{church:slug}', [ChurchController::class, 'destroy'])->name('churches.destroy')->middleware('can:delete,church');

    Route::get('/socials/similar', [ChurchSocialController::class, 'similar'])->name('socials.similar');

    Route::get('/churches/{church:slug}/socials', [ChurchSocialController::class, 'index'])->name('churches.socials.index')->middleware('can:update,church');
    Route::get('/churches/{church:slug}/socials/create', [ChurchSocialController::class, 'create'])->name('socials.create')->middleware('can:update,church');
    Route::post('/churches/{church:slug}/socials', [ChurchSocialController::class, 'store'])->name('socials.store')->middleware('can:update,church');
    Route::get('/socials/{social}/edit', [ChurchSocialController::class, 'edit'])->name('socials.edit')->middleware('can:update,social');
    Route::get('/socials/{social}/stats/create', [SocialStatController::class, 'create'])->name('socials.stats.create')->middleware('can:update,social');
    Route::post('/socials/{social}/stats', [SocialStatController::class, 'store'])->name('socials.stats.store')->middleware('can:update,social');

    Route::get('/socials/{social}/history', [SocialStatController::class, 'history'])->name('socials.history.index')->middleware('can:manage-social-history');
    Route::get('/socials/stats/{stat}/edit', [SocialStatController::class, 'editStat'])->name('socials.stats.edit')->middleware('can:manage-social-history');
    Route::put('/socials/stats/{stat}', [SocialStatController::class, 'update'])->name('socials.stats.update')->middleware('can:manage-social-history');
    Route::delete('/socials/stats/{stat}', [SocialStatController::class, 'destroy'])->name('socials.stats.destroy')->middleware('can:manage-social-history');
    Route::put('/socials/{social}', [ChurchSocialController::class, 'update'])->name('socials.update')->middleware('can:update,social');
    Route::delete('/socials/{social}', [ChurchSocialController::class, 'destroy'])->name('socials.destroy')->middleware('can:update,social');

    Route::get('/personal/metrik', [ChurchDashboardController::class, 'personalMetricComparison'])->name('people.metric-comparison')->middleware('can:view-analytics');
    Route::get('/personal/metrik/{metric}', [ChurchDashboardController::class, 'personalLeaderboard'])->name('people.leaderboard')->middleware('can:view-analytics');
    Route::get('/personal/platform/{platform?}', [ChurchDashboardController::class, 'personalPlatformComparison'])->name('people.platform-comparison')->middleware('can:view-analytics');
    Route::get('/personal/hastag', [ChurchDashboardController::class, 'personalHashtagComparison'])->name('people.hashtag-comparison')->middleware('can:view-analytics');

    Route::get('/institusi/metrik', [ChurchDashboardController::class, 'institutionMetricComparison'])->name('institutions.metric-comparison')->middleware('can:view-analytics');
    Route::get('/institusi/metrik/{metric}', [ChurchDashboardController::class, 'institutionLeaderboard'])->name('institutions.leaderboard')->middleware('can:view-analytics');
    Route::get('/institusi/platform/{platform?}', [ChurchDashboardController::class, 'institutionPlatformComparison'])->name('institutions.platform-comparison')->middleware('can:view-analytics');
    Route::get('/institusi/hastag', [ChurchDashboardController::class, 'institutionHashtagComparison'])->name('institutions.hashtag-comparison')->middleware('can:view-analytics');

    Route::get('/organisasi/metrik', [ChurchDashboardController::class, 'organizationMetricComparison'])->name('organizations.metric-comparison')->middleware('can:view-analytics');
    Route::get('/organisasi/metrik/{metric}', [ChurchDashboardController::class, 'organizationLeaderboard'])->name('organizations.leaderboard')->middleware('can:view-analytics');
    Route::get('/organisasi/platform/{platform?}', [ChurchDashboardController::class, 'organizationPlatformComparison'])->name('organizations.platform-comparison')->middleware('can:view-analytics');
    Route::get('/organisasi/hastag', [ChurchDashboardController::class, 'organizationHashtagComparison'])->name('organizations.hashtag-comparison')->middleware('can:view-analytics');
    Route::get('/organisasi/divisi/{division:slug}', [ChurchDashboardController::class, 'showDivision'])->name('divisions.show')->middleware('can:view,division');
    Route::get('/organisasi/uni/{union:slug}', [ChurchDashboardController::class, 'showUnion'])->name('unions.show')->middleware('can:view,union');
    Route::get('/organisasi/daerah/{conference:slug}', [ChurchDashboardController::class, 'showConference'])->name('conferences.show')->middleware('can:view,conference');
    Route::get('/institusi/{institution:slug}', [ChurchDashboardController::class, 'showInstitution'])->name('institutions.show')->middleware('can:view,institution');
    Route::middleware('can:create,App\Models\Person')->group(function () {
        Route::get('/personal/create', [PersonController::class, 'create'])->name('people.create');
        Route::post('/personal', [PersonController::class, 'store'])->name('people.store');
    });
    Route::get('/personal/similar', [PersonController::class, 'similar'])->name('people.similar');
    Route::get('/personal/{person}/edit', [PersonController::class, 'edit'])->name('people.edit')->middleware('can:update,person');
    Route::put('/personal/{person}', [PersonController::class, 'update'])->name('people.update')->middleware('can:update,person');
    Route::patch('/personal/{person}/toggle-active', [PersonController::class, 'toggleActive'])->name('people.toggle-active')->middleware('can:delete,person');
    Route::post('/personal/{person}/link-user', [PersonController::class, 'linkUser'])->name('people.link-user')->middleware('can:delete,person');
    Route::post('/personal/{person}/unlink-user', [PersonController::class, 'unlinkUser'])->name('people.unlink-user')->middleware('can:delete,person');
    Route::delete('/personal/{person}', [PersonController::class, 'destroy'])->name('people.destroy')->middleware('can:delete,person');

    Route::get('/personal/{person}/socials', [PersonSocialController::class, 'index'])->name('people.socials.index')->middleware('can:update,person');
    Route::get('/personal/{person}/socials/create', [PersonSocialController::class, 'create'])->name('people.socials.create')->middleware('can:update,person');
    Route::post('/personal/{person}/socials', [PersonSocialController::class, 'store'])->name('people.socials.store')->middleware('can:update,person');

    Route::get('/personal/{person}', [PersonController::class, 'show'])->name('people.show')->middleware('can:view,person');

    Route::get('/churches/{church:slug}', [ChurchDashboardController::class, 'show'])->name('churches.show')->middleware('can:view,church');

    Route::post('/refresh', [ChurchRefreshController::class, 'all'])->name('socials.refresh-all')->middleware(['can:trigger-refresh', 'throttle:3,10']);
    Route::get('/refresh/active', [ChurchRefreshController::class, 'active'])->name('socials.refresh-active');
    Route::get('/refresh/{batch}/status', [ChurchRefreshController::class, 'status'])->name('socials.refresh-status');
    Route::post('/socials/{social}/refresh', [ChurchRefreshController::class, 'single'])->name('socials.refresh')->middleware(['can:trigger-refresh', 'throttle:10,1']);

    // All exports below mirror the Analytics/Directory pages' own can:browse-directory-analytics
    // gate — the page itself being restricted doesn't stop someone from hitting its export URL
    // directly unless the export route is gated too.
    Route::middleware('can:browse-directory-analytics')->group(function () {
        Route::get('/export/gereja/leaderboard/{metric}/preview', [ExportController::class, 'leaderboardPreview'])->name('export.leaderboard.preview');
        Route::get('/export/gereja/leaderboard/{metric}/{format}', [ExportController::class, 'leaderboardDownload'])->name('export.leaderboard.download');

        Route::get('/export/gereja/metrik/preview', [ExportController::class, 'metricComparisonPreview'])->name('export.metric-comparison.preview');
        Route::get('/export/gereja/metrik/{format}', [ExportController::class, 'metricComparisonDownload'])->name('export.metric-comparison.download');

        Route::get('/export/personal/leaderboard/{metric}/preview', [ExportController::class, 'personalLeaderboardPreview'])->name('export.personal-leaderboard.preview');
        Route::get('/export/personal/leaderboard/{metric}/{format}', [ExportController::class, 'personalLeaderboardDownload'])->name('export.personal-leaderboard.download');

        Route::get('/export/personal/metrik/preview', [ExportController::class, 'personalMetricComparisonPreview'])->name('export.personal-metric-comparison.preview');
        Route::get('/export/personal/metrik/{format}', [ExportController::class, 'personalMetricComparisonDownload'])->name('export.personal-metric-comparison.download');

        Route::get('/export/personal/platform/{platform}/overview/preview', [ExportController::class, 'personalPlatformOverviewPreview'])->name('export.personal-platform-overview.preview');
        Route::get('/export/personal/platform/{platform}/overview/{format}', [ExportController::class, 'personalPlatformOverviewDownload'])->name('export.personal-platform-overview.download');

        Route::get('/export/personal/platform/{platform}/preview', [ExportController::class, 'personalPlatformComparisonPreview'])->name('export.personal-platform.preview');
        Route::get('/export/personal/platform/{platform}/{format}', [ExportController::class, 'personalPlatformComparisonDownload'])->name('export.personal-platform.download');

        Route::get('/export/personal/analytics/preview', [ExportController::class, 'analyticsPersonalPreview'])->name('export.personal-analytics.preview');
        Route::get('/export/personal/analytics/{format}', [ExportController::class, 'analyticsPersonalDownload'])->name('export.personal-analytics.download');

        Route::get('/export/directory/preview', [ExportController::class, 'directoryPreview'])->name('export.directory.preview');
        Route::get('/export/directory/{format}', [ExportController::class, 'directoryDownload'])->name('export.directory.download');

        Route::get('/export/gereja/platform/{platform}/overview/preview', [ExportController::class, 'platformOverviewPreview'])->name('export.platform-overview.preview');
        Route::get('/export/gereja/platform/{platform}/overview/{format}', [ExportController::class, 'platformOverviewDownload'])->name('export.platform-overview.download');

        Route::get('/export/gereja/platform/{platform}/preview', [ExportController::class, 'platformComparisonPreview'])->name('export.platform.preview');
        Route::get('/export/gereja/platform/{platform}/{format}', [ExportController::class, 'platformComparisonDownload'])->name('export.platform.download');

        Route::get('/export/gereja/analytics/preview', [ExportController::class, 'analyticsPreview'])->name('export.analytics.preview');
        Route::get('/export/gereja/analytics/{format}', [ExportController::class, 'analyticsDownload'])->name('export.analytics.download');

        Route::get('/export/institusi/leaderboard/{metric}/preview', [ExportController::class, 'institutionLeaderboardPreview'])->name('export.institution-leaderboard.preview');
        Route::get('/export/institusi/leaderboard/{metric}/{format}', [ExportController::class, 'institutionLeaderboardDownload'])->name('export.institution-leaderboard.download');

        Route::get('/export/institusi/metrik/preview', [ExportController::class, 'institutionMetricComparisonPreview'])->name('export.institution-metric-comparison.preview');
        Route::get('/export/institusi/metrik/{format}', [ExportController::class, 'institutionMetricComparisonDownload'])->name('export.institution-metric-comparison.download');

        Route::get('/export/institusi/platform/{platform}/overview/preview', [ExportController::class, 'institutionPlatformOverviewPreview'])->name('export.institution-platform-overview.preview');
        Route::get('/export/institusi/platform/{platform}/overview/{format}', [ExportController::class, 'institutionPlatformOverviewDownload'])->name('export.institution-platform-overview.download');

        Route::get('/export/institusi/platform/{platform}/preview', [ExportController::class, 'institutionPlatformComparisonPreview'])->name('export.institution-platform.preview');
        Route::get('/export/institusi/platform/{platform}/{format}', [ExportController::class, 'institutionPlatformComparisonDownload'])->name('export.institution-platform.download');

        Route::get('/export/institusi/analytics/preview', [ExportController::class, 'analyticsInstitutionPreview'])->name('export.institution-analytics.preview');
        Route::get('/export/institusi/analytics/{format}', [ExportController::class, 'analyticsInstitutionDownload'])->name('export.institution-analytics.download');

        Route::get('/export/organisasi/leaderboard/{metric}/preview', [ExportController::class, 'organizationLeaderboardPreview'])->name('export.organization-leaderboard.preview');
        Route::get('/export/organisasi/leaderboard/{metric}/{format}', [ExportController::class, 'organizationLeaderboardDownload'])->name('export.organization-leaderboard.download');

        Route::get('/export/organisasi/metrik/preview', [ExportController::class, 'organizationMetricComparisonPreview'])->name('export.organization-metric-comparison.preview');
        Route::get('/export/organisasi/metrik/{format}', [ExportController::class, 'organizationMetricComparisonDownload'])->name('export.organization-metric-comparison.download');

        Route::get('/export/organisasi/platform/{platform}/overview/preview', [ExportController::class, 'organizationPlatformOverviewPreview'])->name('export.organization-platform-overview.preview');
        Route::get('/export/organisasi/platform/{platform}/overview/{format}', [ExportController::class, 'organizationPlatformOverviewDownload'])->name('export.organization-platform-overview.download');

        Route::get('/export/organisasi/platform/{platform}/preview', [ExportController::class, 'organizationPlatformComparisonPreview'])->name('export.organization-platform.preview');
        Route::get('/export/organisasi/platform/{platform}/{format}', [ExportController::class, 'organizationPlatformComparisonDownload'])->name('export.organization-platform.download');

        Route::get('/export/organisasi/analytics/preview', [ExportController::class, 'analyticsOrganizationPreview'])->name('export.organization-analytics.preview');
        Route::get('/export/organisasi/analytics/{format}', [ExportController::class, 'analyticsOrganizationDownload'])->name('export.organization-analytics.download');
    });

    Route::get('/export/church/{church:slug}/preview', [ExportController::class, 'churchPreview'])->name('export.church.preview')->middleware('can:export,church');
    Route::get('/export/church/{church:slug}/{format}', [ExportController::class, 'churchDownload'])->name('export.church.download')->middleware('can:export,church');

    Route::get('/export/person/{person}/preview', [ExportController::class, 'personPreview'])->name('export.person.preview')->middleware('can:export,person');
    Route::get('/export/person/{person}/{format}', [ExportController::class, 'personDownload'])->name('export.person.download')->middleware('can:export,person');

    Route::get('/export/institution/{institution:slug}/preview', [ExportController::class, 'institutionPreview'])->name('export.institution.preview')->middleware('can:export,institution');
    Route::get('/export/institution/{institution:slug}/{format}', [ExportController::class, 'institutionDownload'])->name('export.institution.download')->middleware('can:export,institution');

    Route::get('/export/social/{social}/preview', [ExportController::class, 'socialHistoryPreview'])->name('export.social-history.preview')->middleware('can:view,social');
    Route::get('/export/social/{social}/{format}', [ExportController::class, 'socialHistoryDownload'])->name('export.social-history.download')->middleware('can:view,social');

    Route::middleware('can:delegate-users')->prefix('admin/users')->name('admin.users.')->group(function () {
        Route::get('/', [UserAssignmentController::class, 'index'])->name('index');
        Route::get('/{target}/edit', [UserAssignmentController::class, 'edit'])->name('edit');
        Route::put('/{target}', [UserAssignmentController::class, 'update'])->name('update');
        Route::post('/{target}/promote', [UserAssignmentController::class, 'promote'])->name('promote');
        Route::post('/{target}/revoke', [UserAssignmentController::class, 'revoke'])->name('revoke');
        Route::post('/{target}/release-region', [UserAssignmentController::class, 'releaseRegion'])->name('release-region');
        Route::post('/release-region-bulk', [UserAssignmentController::class, 'releaseRegionBulk'])->name('release-region-bulk');
        Route::post('/{target}/toggle-active', [UserAssignmentController::class, 'toggleActive'])->name('toggle-active');
        Route::post('/{target}/resend-otp', [UserAssignmentController::class, 'resendOtp'])->name('resend-otp');
        Route::delete('/{target}', [UserAssignmentController::class, 'destroy'])->name('destroy');
    });

    Route::middleware('can:manage-deleted-users')->prefix('admin/users')->name('admin.users.')->group(function () {
        Route::post('/{target}/restore', [UserAssignmentController::class, 'restore'])->name('restore')->withTrashed();
        Route::delete('/{target}/force', [UserAssignmentController::class, 'forceDelete'])->name('force-delete')->withTrashed();
    });

    Route::get('/admin/audit-log', [AuditLogController::class, 'index'])->name('admin.audit-log.index')->middleware('can:view-audit-log');

    // "Kelola Akun" — the merged Uni/Daerah/Gereja/Institusi/Personal page. Gated by
    // manage-people rather than manage-hierarchy: manage-people's requirement (any non-
    // read-only role) is strictly broader than manage-hierarchy's (same, minus gereja-level),
    // so this is exactly "manage-hierarchy OR manage-people" without needing a third gate —
    // it's what lets admin_gereja reach the page for the Personal tab even though every
    // organization tab stays invisible to them (see AccountController::visibleTabsFor()).
    Route::get('/admin/organization', [AccountController::class, 'index'])->name('admin.accounts.index')->middleware('can:manage-people');
    Route::get('/admin/organization/tanpa-akun-sosial', [AccountController::class, 'noSocials'])->name('admin.accounts.no-socials')->middleware('can:manage-people');

    Route::middleware('can:manage-hierarchy')->prefix('admin')->name('admin.')->group(function () {
        Route::middleware('can:create,App\Models\Division')->group(function () {
            Route::get('/divisions/create', [DivisionController::class, 'create'])->name('divisions.create');
            Route::post('/divisions', [DivisionController::class, 'store'])->name('divisions.store');
        });
        Route::get('/divisions/similar', [DivisionController::class, 'similar'])->name('divisions.similar');
        Route::get('/divisions/{division:slug}/edit', [DivisionController::class, 'edit'])->name('divisions.edit')->middleware('can:update,division');
        Route::put('/divisions/{division:slug}', [DivisionController::class, 'update'])->name('divisions.update')->middleware('can:update,division');
        Route::patch('/divisions/{division:slug}/toggle-active', [DivisionController::class, 'toggleActive'])->name('divisions.toggle-active')->middleware('can:update,division');
        Route::delete('/divisions/{division:slug}', [DivisionController::class, 'destroy'])->name('divisions.destroy')->middleware('can:delete,division');

        Route::middleware('can:update,division')->group(function () {
            Route::get('/divisions/{division:slug}/socials', [OrganizationSocialController::class, 'divisionIndex'])->name('divisions.socials.index');
            Route::get('/divisions/{division:slug}/socials/create', [OrganizationSocialController::class, 'divisionCreate'])->name('divisions.socials.create');
            Route::post('/divisions/{division:slug}/socials', [OrganizationSocialController::class, 'divisionStore'])->name('divisions.socials.store');
        });

        Route::middleware('can:create,App\Models\Union')->group(function () {
            Route::get('/unions/create', [UnionController::class, 'create'])->name('unions.create');
            Route::post('/unions', [UnionController::class, 'store'])->name('unions.store');
        });
        Route::get('/unions/similar', [UnionController::class, 'similar'])->name('unions.similar');
        Route::get('/unions/{union:slug}/edit', [UnionController::class, 'edit'])->name('unions.edit')->middleware('can:update,union');
        Route::put('/unions/{union:slug}', [UnionController::class, 'update'])->name('unions.update')->middleware('can:update,union');
        // Narrower than the above (manage-settings, not update,union) — see
        // UnionController::updateCoordinator()'s own docblock for why this needs its own gate.
        Route::patch('/unions/{union:slug}/coordinator', [UnionController::class, 'updateCoordinator'])->name('unions.update-coordinator')->middleware('can:manage-settings');
        Route::patch('/unions/{union:slug}/toggle-active', [UnionController::class, 'toggleActive'])->name('unions.toggle-active')->middleware('can:update,union');
        Route::delete('/unions/{union:slug}', [UnionController::class, 'destroy'])->name('unions.destroy')->middleware('can:delete,union');

        Route::middleware('can:update,union')->group(function () {
            Route::get('/unions/{union:slug}/socials', [OrganizationSocialController::class, 'unionIndex'])->name('unions.socials.index');
            Route::get('/unions/{union:slug}/socials/create', [OrganizationSocialController::class, 'unionCreate'])->name('unions.socials.create');
            Route::post('/unions/{union:slug}/socials', [OrganizationSocialController::class, 'unionStore'])->name('unions.socials.store');
        });

        Route::middleware('can:create,App\Models\Conference')->group(function () {
            Route::get('/conferences/create', [ConferenceController::class, 'create'])->name('conferences.create');
            Route::post('/conferences', [ConferenceController::class, 'store'])->name('conferences.store');
        });
        Route::get('/conferences/similar', [ConferenceController::class, 'similar'])->name('conferences.similar');
        Route::get('/conferences/{conference:slug}/edit', [ConferenceController::class, 'edit'])->name('conferences.edit')->middleware('can:update,conference');
        Route::put('/conferences/{conference:slug}', [ConferenceController::class, 'update'])->name('conferences.update')->middleware('can:update,conference');
        Route::patch('/conferences/{conference:slug}/toggle-active', [ConferenceController::class, 'toggleActive'])->name('conferences.toggle-active')->middleware('can:update,conference');
        Route::delete('/conferences/{conference:slug}', [ConferenceController::class, 'destroy'])->name('conferences.destroy')->middleware('can:delete,conference');

        Route::middleware('can:update,conference')->group(function () {
            Route::get('/conferences/{conference:slug}/socials', [OrganizationSocialController::class, 'conferenceIndex'])->name('conferences.socials.index');
            Route::get('/conferences/{conference:slug}/socials/create', [OrganizationSocialController::class, 'conferenceCreate'])->name('conferences.socials.create');
            Route::post('/conferences/{conference:slug}/socials', [OrganizationSocialController::class, 'conferenceStore'])->name('conferences.socials.store');
        });

        Route::middleware('can:create,App\Models\Institution')->group(function () {
            Route::get('/institutions/create', [InstitutionController::class, 'create'])->name('institutions.create');
            Route::post('/institutions', [InstitutionController::class, 'store'])->name('institutions.store');
        });
        Route::get('/institutions/similar', [InstitutionController::class, 'similar'])->name('institutions.similar');
        Route::get('/institutions/{institution:slug}/edit', [InstitutionController::class, 'edit'])->name('institutions.edit')->middleware('can:update,institution');
        Route::put('/institutions/{institution:slug}', [InstitutionController::class, 'update'])->name('institutions.update')->middleware('can:update,institution');
        Route::patch('/institutions/{institution:slug}/toggle-active', [InstitutionController::class, 'toggleActive'])->name('institutions.toggle-active')->middleware('can:update,institution');
        Route::delete('/institutions/{institution:slug}', [InstitutionController::class, 'destroy'])->name('institutions.destroy')->middleware('can:delete,institution');

        Route::middleware('can:update,institution')->group(function () {
            Route::get('/institutions/{institution:slug}/socials', [OrganizationSocialController::class, 'institutionIndex'])->name('institutions.socials.index');
            Route::get('/institutions/{institution:slug}/socials/create', [OrganizationSocialController::class, 'institutionCreate'])->name('institutions.socials.create');
            Route::post('/institutions/{institution:slug}/socials', [OrganizationSocialController::class, 'institutionStore'])->name('institutions.socials.store');
        });
    });

}); // end auth+verified+RedirectUnassignedMembers group

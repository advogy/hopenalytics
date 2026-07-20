<?php

use App\Http\Controllers\Admin\ConferenceController;
use App\Http\Controllers\Admin\HierarchyController;
use App\Http\Controllers\Admin\InstitutionController;
use App\Http\Controllers\Admin\UnionController;
use App\Http\Controllers\Admin\UserAssignmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChurchController;
use App\Http\Controllers\ChurchDashboardController;
use App\Http\Controllers\ChurchRefreshController;
use App\Http\Controllers\ChurchSocialController;
use App\Http\Controllers\CompleteProfileController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\ForgotPasswordController;
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

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.attempt')->middleware('throttle:login');
Route::get('/register', [RegisterController::class, 'showRegister'])->name('register');
Route::post('/register', [RegisterController::class, 'register'])->name('register.attempt');

Route::get('/verify-otp', [RegisterController::class, 'showVerifyOtp'])->name('verify-otp');
Route::post('/verify-otp', [RegisterController::class, 'verifyOtp'])->name('verify-otp.attempt')->middleware('throttle:verify-otp');
Route::post('/verify-otp/resend', [RegisterController::class, 'resendOtp'])->name('verify-otp.resend')->middleware('throttle:3,10');

Route::get('/forgot-password', [ForgotPasswordController::class, 'show'])->name('forgot-password');
Route::post('/forgot-password', [ForgotPasswordController::class, 'send'])->name('forgot-password.send')->middleware('throttle:3,10');
Route::get('/reset-password', [ForgotPasswordController::class, 'showReset'])->name('reset-password');
Route::post('/reset-password', [ForgotPasswordController::class, 'reset'])->name('reset-password.attempt')->middleware('throttle:reset-password');
Route::post('/reset-password/resend', [ForgotPasswordController::class, 'resend'])->name('reset-password.resend')->middleware('throttle:3,10');

// Public: meant to be shown fullscreen on a screen/projector during events, so it can't
// require anyone to be logged in. Unauthenticated visibility falls back to "everything"
// (see Church::scopeVisibleTo()), matching what these pages showed before RBAC existed.
Route::get('/gereja/presentation', [ChurchDashboardController::class, 'presentation'])->name('churches.presentation');
Route::get('/gereja/presentation/growth', [ChurchDashboardController::class, 'presentationGrowth'])->name('churches.presentation-growth');
Route::get('/personal/presentation', [ChurchDashboardController::class, 'personalPresentation'])->name('people.presentation');
Route::get('/personal/presentation/growth', [ChurchDashboardController::class, 'personalPresentationGrowth'])->name('people.presentation-growth');

Route::middleware(['auth', 'verified', RedirectUnassignedMembers::class])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [MyAccountController::class, 'index'])->name('akun-saya');

    Route::get('/lengkapi-profil', [CompleteProfileController::class, 'show'])->name('profile.complete');
    Route::post('/lengkapi-profil', [CompleteProfileController::class, 'store'])->name('profile.complete.store');
    Route::post('/lengkapi-profil/skip', [CompleteProfileController::class, 'skip'])->name('profile.complete.skip');

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
        Route::post('/queue/batches/clear', [QueueMonitorController::class, 'clearCompletedBatches'])->name('queue.clear-completed-batches');
        Route::post('/queue/batches/{batch}/delete', [QueueMonitorController::class, 'deleteBatch'])->name('queue.delete-batch');
    });

    Route::get('/', [ChurchDashboardController::class, 'index'])->name('churches.index');
    Route::get('/analytics', [ChurchDashboardController::class, 'analytics'])->name('churches.analytics')->middleware('can:browse-directory-analytics');
    Route::get('/gereja/metrik', [ChurchDashboardController::class, 'metricComparison'])->name('churches.metric-comparison');
    Route::get('/gereja/metrik/{metric}', [ChurchDashboardController::class, 'leaderboard'])->name('churches.leaderboard');
    Route::get('/directory', [ChurchDashboardController::class, 'directory'])->name('churches.directory')->middleware('can:browse-directory-analytics');
    Route::get('/akun-bermasalah', [ChurchDashboardController::class, 'needsAttention'])->name('churches.needs-attention');
    Route::get('/gereja/platform/{platform?}', [ChurchDashboardController::class, 'platformComparison'])->name('churches.platform-comparison');

    Route::view('/about', 'about')->name('about');

    Route::middleware('can:manage-settings')->group(function () {
        Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
    });

    Route::middleware('can:create,App\Models\Church')->group(function () {
        Route::get('/churches/create', [ChurchController::class, 'create'])->name('churches.create');
        Route::post('/churches', [ChurchController::class, 'store'])->name('churches.store');
    });
    Route::get('/churches/{church:slug}/edit', [ChurchController::class, 'edit'])->name('churches.edit')->middleware('can:update,church');
    Route::put('/churches/{church:slug}', [ChurchController::class, 'update'])->name('churches.update')->middleware('can:update,church');
    Route::patch('/churches/{church:slug}/toggle-active', [ChurchController::class, 'toggleActive'])->name('churches.toggle-active')->middleware('can:delete,church');
    Route::delete('/churches/{church:slug}', [ChurchController::class, 'destroy'])->name('churches.destroy')->middleware('can:delete,church');

    Route::get('/churches/{church:slug}/socials/create', [ChurchSocialController::class, 'create'])->name('socials.create')->middleware('can:update,church');
    Route::post('/churches/{church:slug}/socials', [ChurchSocialController::class, 'store'])->name('socials.store')->middleware('can:update,church');
    Route::get('/socials/{social}/edit', [ChurchSocialController::class, 'edit'])->name('socials.edit')->middleware('can:update,social');
    Route::get('/socials/{social}/stats/create', [SocialStatController::class, 'create'])->name('socials.stats.create')->middleware('can:update,social');
    Route::post('/socials/{social}/stats', [SocialStatController::class, 'store'])->name('socials.stats.store')->middleware('can:update,social');
    Route::put('/socials/{social}', [ChurchSocialController::class, 'update'])->name('socials.update')->middleware('can:update,social');
    Route::delete('/socials/{social}', [ChurchSocialController::class, 'destroy'])->name('socials.destroy')->middleware('can:update,social');

    Route::get('/personal/metrik', [ChurchDashboardController::class, 'personalMetricComparison'])->name('people.metric-comparison');
    Route::get('/personal/metrik/{metric}', [ChurchDashboardController::class, 'personalLeaderboard'])->name('people.leaderboard');
    Route::get('/personal/platform/{platform?}', [ChurchDashboardController::class, 'personalPlatformComparison'])->name('people.platform-comparison');
    Route::get('/admin/personal', [PersonController::class, 'index'])->name('admin.people.index')->middleware('can:browse-directory-analytics');
    Route::middleware('can:create,App\Models\Person')->group(function () {
        Route::get('/personal/create', [PersonController::class, 'create'])->name('people.create');
        Route::post('/personal', [PersonController::class, 'store'])->name('people.store');
    });
    Route::get('/personal/{person}/edit', [PersonController::class, 'edit'])->name('people.edit')->middleware('can:update,person');
    Route::put('/personal/{person}', [PersonController::class, 'update'])->name('people.update')->middleware('can:update,person');
    Route::patch('/personal/{person}/toggle-active', [PersonController::class, 'toggleActive'])->name('people.toggle-active')->middleware('can:delete,person');
    Route::delete('/personal/{person}', [PersonController::class, 'destroy'])->name('people.destroy')->middleware('can:delete,person');

    Route::get('/personal/{person}/socials/create', [PersonSocialController::class, 'create'])->name('people.socials.create')->middleware('can:update,person');
    Route::post('/personal/{person}/socials', [PersonSocialController::class, 'store'])->name('people.socials.store')->middleware('can:update,person');

    Route::get('/personal/{person}', [PersonController::class, 'show'])->name('people.show')->middleware('can:view,person');

    Route::get('/churches/{church:slug}', [ChurchDashboardController::class, 'show'])->name('churches.show')->middleware('can:view,church');

    Route::post('/refresh', [ChurchRefreshController::class, 'all'])->name('socials.refresh-all')->middleware(['can:trigger-refresh', 'throttle:3,10']);
    Route::get('/refresh/active', [ChurchRefreshController::class, 'active'])->name('socials.refresh-active');
    Route::get('/refresh/{batch}/status', [ChurchRefreshController::class, 'status'])->name('socials.refresh-status');
    Route::post('/socials/{social}/refresh', [ChurchRefreshController::class, 'single'])->name('socials.refresh')->middleware(['can:trigger-refresh', 'throttle:10,1']);

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

    Route::get('/export/church/{church:slug}/preview', [ExportController::class, 'churchPreview'])->name('export.church.preview')->middleware('can:view,church');
    Route::get('/export/church/{church:slug}/{format}', [ExportController::class, 'churchDownload'])->name('export.church.download')->middleware('can:view,church');

    Route::get('/export/person/{person}/preview', [ExportController::class, 'personPreview'])->name('export.person.preview')->middleware('can:view,person');
    Route::get('/export/person/{person}/{format}', [ExportController::class, 'personDownload'])->name('export.person.download')->middleware('can:view,person');

    Route::get('/export/social/{social}/preview', [ExportController::class, 'socialHistoryPreview'])->name('export.social-history.preview')->middleware('can:view,social');
    Route::get('/export/social/{social}/{format}', [ExportController::class, 'socialHistoryDownload'])->name('export.social-history.download')->middleware('can:view,social');

    Route::get('/export/gereja/platform/{platform}/overview/preview', [ExportController::class, 'platformOverviewPreview'])->name('export.platform-overview.preview');
    Route::get('/export/gereja/platform/{platform}/overview/{format}', [ExportController::class, 'platformOverviewDownload'])->name('export.platform-overview.download');

    Route::get('/export/gereja/platform/{platform}/preview', [ExportController::class, 'platformComparisonPreview'])->name('export.platform.preview');
    Route::get('/export/gereja/platform/{platform}/{format}', [ExportController::class, 'platformComparisonDownload'])->name('export.platform.download');

    Route::get('/export/gereja/analytics/preview', [ExportController::class, 'analyticsPreview'])->name('export.analytics.preview');
    Route::get('/export/gereja/analytics/{format}', [ExportController::class, 'analyticsDownload'])->name('export.analytics.download');

    Route::middleware('can:delegate-users')->prefix('admin/users')->name('admin.users.')->group(function () {
        Route::get('/', [UserAssignmentController::class, 'index'])->name('index');
        Route::post('/{target}/promote', [UserAssignmentController::class, 'promote'])->name('promote');
        Route::post('/{target}/revoke', [UserAssignmentController::class, 'revoke'])->name('revoke');
        Route::post('/{target}/toggle-active', [UserAssignmentController::class, 'toggleActive'])->name('toggle-active');
        Route::post('/{target}/resend-otp', [UserAssignmentController::class, 'resendOtp'])->name('resend-otp');
        Route::delete('/{target}', [UserAssignmentController::class, 'destroy'])->name('destroy');
    });

    Route::middleware('can:manage-hierarchy')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/hierarchy', [HierarchyController::class, 'index'])->name('hierarchy.index');

        Route::get('/unions/create', [UnionController::class, 'create'])->name('unions.create');
        Route::post('/unions', [UnionController::class, 'store'])->name('unions.store');
        Route::get('/unions/{union:slug}/edit', [UnionController::class, 'edit'])->name('unions.edit');
        Route::put('/unions/{union:slug}', [UnionController::class, 'update'])->name('unions.update');
        Route::patch('/unions/{union:slug}/toggle-active', [UnionController::class, 'toggleActive'])->name('unions.toggle-active');
        Route::delete('/unions/{union:slug}', [UnionController::class, 'destroy'])->name('unions.destroy');

        Route::get('/conferences/create', [ConferenceController::class, 'create'])->name('conferences.create');
        Route::post('/conferences', [ConferenceController::class, 'store'])->name('conferences.store');
        Route::get('/conferences/{conference:slug}/edit', [ConferenceController::class, 'edit'])->name('conferences.edit');
        Route::put('/conferences/{conference:slug}', [ConferenceController::class, 'update'])->name('conferences.update');
        Route::patch('/conferences/{conference:slug}/toggle-active', [ConferenceController::class, 'toggleActive'])->name('conferences.toggle-active');
        Route::delete('/conferences/{conference:slug}', [ConferenceController::class, 'destroy'])->name('conferences.destroy');

        Route::get('/institutions/create', [InstitutionController::class, 'create'])->name('institutions.create');
        Route::post('/institutions', [InstitutionController::class, 'store'])->name('institutions.store');
        Route::get('/institutions/{institution:slug}/edit', [InstitutionController::class, 'edit'])->name('institutions.edit');
        Route::put('/institutions/{institution:slug}', [InstitutionController::class, 'update'])->name('institutions.update');
        Route::patch('/institutions/{institution:slug}/toggle-active', [InstitutionController::class, 'toggleActive'])->name('institutions.toggle-active');
        Route::delete('/institutions/{institution:slug}', [InstitutionController::class, 'destroy'])->name('institutions.destroy');
    });

}); // end auth+verified+RedirectUnassignedMembers group

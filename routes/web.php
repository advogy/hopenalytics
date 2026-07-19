<?php

use App\Http\Controllers\ChurchController;
use App\Http\Controllers\ChurchDashboardController;
use App\Http\Controllers\ChurchRefreshController;
use App\Http\Controllers\ChurchSocialController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\PersonSocialController;
use App\Http\Controllers\QueueMonitorController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SocialStatController;
use Illuminate\Support\Facades\Route;

Route::get('/locale/{locale}', [LocaleController::class, 'switch'])->name('locale.switch');

Route::get('/queue', [QueueMonitorController::class, 'index'])->name('queue.index');
Route::post('/queue/{batch}/cancel', [QueueMonitorController::class, 'cancelBatch'])->name('queue.cancel-batch');
Route::post('/queue/clear', [QueueMonitorController::class, 'clearQueue'])->name('queue.clear');
Route::post('/queue/failed/clear', [QueueMonitorController::class, 'clearFailed'])->name('queue.clear-failed');
Route::post('/queue/failed/{id}/delete', [QueueMonitorController::class, 'deleteFailed'])->name('queue.delete-failed');
Route::post('/queue/batches/clear', [QueueMonitorController::class, 'clearCompletedBatches'])->name('queue.clear-completed-batches');
Route::post('/queue/batches/{batch}/delete', [QueueMonitorController::class, 'deleteBatch'])->name('queue.delete-batch');

Route::get('/', [ChurchDashboardController::class, 'index'])->name('churches.index');
Route::get('/analytics', [ChurchDashboardController::class, 'analytics'])->name('churches.analytics');
Route::get('/gereja/metrik', [ChurchDashboardController::class, 'metricComparison'])->name('churches.metric-comparison');
Route::get('/gereja/metrik/{metric}', [ChurchDashboardController::class, 'leaderboard'])->name('churches.leaderboard');
Route::get('/directory', [ChurchDashboardController::class, 'directory'])->name('churches.directory');
Route::get('/akun-bermasalah', [ChurchDashboardController::class, 'needsAttention'])->name('churches.needs-attention');
Route::get('/gereja/platform/{platform?}', [ChurchDashboardController::class, 'platformComparison'])->name('churches.platform-comparison');
Route::get('/gereja/presentation', [ChurchDashboardController::class, 'presentation'])->name('churches.presentation');
Route::get('/gereja/presentation/growth', [ChurchDashboardController::class, 'presentationGrowth'])->name('churches.presentation-growth');
Route::get('/personal/presentation', [ChurchDashboardController::class, 'personalPresentation'])->name('people.presentation');
Route::get('/personal/presentation/growth', [ChurchDashboardController::class, 'personalPresentationGrowth'])->name('people.presentation-growth');

Route::view('/about', 'about')->name('about');

Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');

Route::get('/churches/create', [ChurchController::class, 'create'])->name('churches.create');
Route::post('/churches', [ChurchController::class, 'store'])->name('churches.store');
Route::get('/churches/{church:slug}/edit', [ChurchController::class, 'edit'])->name('churches.edit');
Route::put('/churches/{church:slug}', [ChurchController::class, 'update'])->name('churches.update');
Route::delete('/churches/{church:slug}', [ChurchController::class, 'destroy'])->name('churches.destroy');

Route::get('/churches/{church:slug}/socials/create', [ChurchSocialController::class, 'create'])->name('socials.create');
Route::post('/churches/{church:slug}/socials', [ChurchSocialController::class, 'store'])->name('socials.store');
Route::get('/socials/{social}/edit', [ChurchSocialController::class, 'edit'])->name('socials.edit');
Route::get('/socials/{social}/stats/create', [SocialStatController::class, 'create'])->name('socials.stats.create');
Route::post('/socials/{social}/stats', [SocialStatController::class, 'store'])->name('socials.stats.store');
Route::put('/socials/{social}', [ChurchSocialController::class, 'update'])->name('socials.update');
Route::delete('/socials/{social}', [ChurchSocialController::class, 'destroy'])->name('socials.destroy');

Route::get('/personal/metrik', [ChurchDashboardController::class, 'personalMetricComparison'])->name('people.metric-comparison');
Route::get('/personal/metrik/{metric}', [ChurchDashboardController::class, 'personalLeaderboard'])->name('people.leaderboard');
Route::get('/personal/platform/{platform?}', [ChurchDashboardController::class, 'personalPlatformComparison'])->name('people.platform-comparison');
Route::get('/personal/create', [PersonController::class, 'create'])->name('people.create');
Route::post('/personal', [PersonController::class, 'store'])->name('people.store');
Route::get('/personal/{person}/edit', [PersonController::class, 'edit'])->name('people.edit');
Route::put('/personal/{person}', [PersonController::class, 'update'])->name('people.update');
Route::delete('/personal/{person}', [PersonController::class, 'destroy'])->name('people.destroy');

Route::get('/personal/{person}/socials/create', [PersonSocialController::class, 'create'])->name('people.socials.create');
Route::post('/personal/{person}/socials', [PersonSocialController::class, 'store'])->name('people.socials.store');

Route::get('/personal/{person}', [PersonController::class, 'show'])->name('people.show');

Route::get('/churches/{church:slug}', [ChurchDashboardController::class, 'show'])->name('churches.show');

Route::post('/refresh', [ChurchRefreshController::class, 'all'])->name('socials.refresh-all');
Route::get('/refresh/active', [ChurchRefreshController::class, 'active'])->name('socials.refresh-active');
Route::get('/refresh/{batch}/status', [ChurchRefreshController::class, 'status'])->name('socials.refresh-status');
Route::post('/socials/{social}/refresh', [ChurchRefreshController::class, 'single'])->name('socials.refresh');

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

Route::get('/export/church/{church:slug}/preview', [ExportController::class, 'churchPreview'])->name('export.church.preview');
Route::get('/export/church/{church:slug}/{format}', [ExportController::class, 'churchDownload'])->name('export.church.download');

Route::get('/export/person/{person}/preview', [ExportController::class, 'personPreview'])->name('export.person.preview');
Route::get('/export/person/{person}/{format}', [ExportController::class, 'personDownload'])->name('export.person.download');

Route::get('/export/social/{social}/preview', [ExportController::class, 'socialHistoryPreview'])->name('export.social-history.preview');
Route::get('/export/social/{social}/{format}', [ExportController::class, 'socialHistoryDownload'])->name('export.social-history.download');

Route::get('/export/gereja/platform/{platform}/overview/preview', [ExportController::class, 'platformOverviewPreview'])->name('export.platform-overview.preview');
Route::get('/export/gereja/platform/{platform}/overview/{format}', [ExportController::class, 'platformOverviewDownload'])->name('export.platform-overview.download');

Route::get('/export/gereja/platform/{platform}/preview', [ExportController::class, 'platformComparisonPreview'])->name('export.platform.preview');
Route::get('/export/gereja/platform/{platform}/{format}', [ExportController::class, 'platformComparisonDownload'])->name('export.platform.download');

Route::get('/export/gereja/analytics/preview', [ExportController::class, 'analyticsPreview'])->name('export.analytics.preview');
Route::get('/export/gereja/analytics/{format}', [ExportController::class, 'analyticsDownload'])->name('export.analytics.download');

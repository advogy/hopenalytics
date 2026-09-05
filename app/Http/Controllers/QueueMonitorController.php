<?php

namespace App\Http\Controllers;

use App\Jobs\FetchSingleChurchData;
use App\Models\ChurchSocial;
use App\Models\Union;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class QueueMonitorController extends Controller
{
    public function index()
    {
        $pendingByQueue = DB::table('jobs')
            ->select('queue', DB::raw('count(*) as total'))
            ->groupBy('queue')
            ->orderByDesc('total')
            ->get();

        $totalPending = $pendingByQueue->sum('total');

        $activeBatches = DB::table('job_batches')
            ->whereNull('finished_at')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn ($batch) => [
                'id' => $batch->id,
                'name' => $batch->name,
                'total' => $batch->total_jobs,
                'processed' => $batch->total_jobs - $batch->pending_jobs,
                'failed' => $batch->failed_jobs,
                'percent' => $batch->total_jobs > 0
                    ? (int) round((($batch->total_jobs - $batch->pending_jobs) / $batch->total_jobs) * 100)
                    : 100,
                'createdAt' => Carbon::createFromTimestamp($batch->created_at, config('app.timezone')),
            ]);

        $totalFailed = DB::table('failed_jobs')->count();

        $failedJobs = DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->paginate(30, ['*'], 'failed_page')
            ->withQueryString()
            ->through(fn ($row) => [
                'id' => $row->id,
                'queue' => $row->queue,
                'failedAt' => $row->failed_at,
                'message' => $this->humanizeFailedJobMessage(strtok($row->exception, "\n")),
                'account' => $this->failedJobAccountLabel($row->payload),
            ]);

        $completedBatches = DB::table('job_batches')
            ->whereNotNull('finished_at')
            ->orderByDesc('finished_at')
            ->limit(20)
            ->get()
            ->map(fn ($batch) => [
                'id' => $batch->id,
                'name' => $batch->name,
                'total' => $batch->total_jobs,
                'processed' => $batch->total_jobs - $batch->pending_jobs,
                'failed' => $batch->failed_jobs,
                'cancelled' => $batch->cancelled_at !== null,
                'finishedAt' => Carbon::createFromTimestamp($batch->finished_at, config('app.timezone')),
            ]);

        return view('admin.queue', [
            'pendingByQueue' => $pendingByQueue,
            'totalPending' => $totalPending,
            'activeBatches' => $activeBatches,
            'failedJobs' => $failedJobs,
            'totalFailed' => $totalFailed,
            'completedBatches' => $completedBatches,
            'unionFetchRows' => $this->unionFetchRows(),
            'globalFetchRow' => $this->globalFetchRow(),
        ]);
    }

    /**
     * "Fetch per Uni" — per the user's explicit call: the global refresh (ChurchRefreshController
     * ::all()) can take a long time across every account nationwide, so this lets an admin
     * trigger just one Union's worth first. Each Union's own account count is a live query (same
     * eligibility filters ChurchRefreshController::union() itself dispatches against, so the
     * number shown is exactly what a click would queue) and its "last fetched" timestamp/running
     * state come from job_batches, keyed off that Union's own uniquely-named batch
     * ("refresh-uni-{id}") — no separate tracking table needed, mirroring how
     * ChurchRefreshController::active() already reads 'refresh-socials' the same way for the
     * global button.
     */
    private function unionFetchRows()
    {
        return Union::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'slug', 'name'])
            ->map(function ($union) {
                $accountCount = ChurchSocial::query()
                    ->where('is_active', true)
                    ->where('is_auto_fetch', true)
                    ->ownerActive()
                    ->consentGranted()
                    ->inUnion($union->id)
                    ->count();

                return ['union' => $union, 'accountCount' => $accountCount] + $this->batchStatus('refresh-uni-'.$union->id);
            });
    }

    /**
     * The same "Fetch Now" row shape as unionFetchRows() above, for the nationwide total —
     * rendered as one extra row below the per-Union ones (per the user's explicit call), sharing
     * the exact eligibility filters ChurchRefreshController::all() itself dispatches against and
     * the exact 'refresh-socials' batch name ChurchRefreshController::active() already reads for
     * the Analitik & Grafik page's own global refresh button, so both buttons/trackers agree on
     * what's currently running.
     */
    private function globalFetchRow(): array
    {
        $accountCount = ChurchSocial::query()
            ->where('is_active', true)
            ->where('is_auto_fetch', true)
            ->ownerActive()
            ->consentGranted()
            ->count();

        return ['accountCount' => $accountCount] + $this->batchStatus('refresh-socials');
    }

    /** @return array{isRunning: bool, lastFetchedAt: ?Carbon} */
    private function batchStatus(string $batchName): array
    {
        $activeBatch = DB::table('job_batches')
            ->where('name', $batchName)
            ->whereNull('finished_at')
            ->orderByDesc('created_at')
            ->first();

        $lastFinished = DB::table('job_batches')
            ->where('name', $batchName)
            ->whereNotNull('finished_at')
            ->orderByDesc('finished_at')
            ->first();

        return [
            'isRunning' => $activeBatch !== null,
            'lastFetchedAt' => $lastFinished
                ? Carbon::createFromTimestamp($lastFinished->finished_at, config('app.timezone'))
                : null,
        ];
    }

    /**
     * Translates the first line of a stored failed_jobs.exception (Laravel's default
     * Throwable::__toString() shape: "ExceptionClass: message in /full/server/path:line") into
     * something a non-technical admin can actually act on — the raw form leaks server file
     * paths and PHP exception class names, which meant nothing to anyone but a developer. Only
     * recognizes the specific messages this app's own fetchers/jobs throw (see
     * app/Services/SocialStats/*.php and FetchSingleChurchData) — anything else falls back to a
     * generic line rather than guessing at an unfamiliar shape.
     */
    private function humanizeFailedJobMessage(string $rawFirstLine): string
    {
        if (! preg_match('/^[\w\\\\]+: (.*) in \/.*:\d+$/s', $rawFirstLine, $m)) {
            return __('queue.job_failed_generic');
        }

        $message = trim($m[1]);

        return match (true) {
            str_starts_with($message, 'Facebook page not found: ') =>
                __('queue.job_failed_facebook_not_found', ['detail' => substr($message, strlen('Facebook page not found: '))]),
            str_starts_with($message, 'TikTok profile not found: ') =>
                __('queue.job_failed_tiktok_not_found', ['detail' => substr($message, strlen('TikTok profile not found: '))]),
            str_starts_with($message, 'YouTube channel not found: ') =>
                __('queue.job_failed_youtube_not_found', ['detail' => substr($message, strlen('YouTube channel not found: '))]),
            str_contains($message, 'Missing YouTube channel ID or handle') =>
                __('queue.job_failed_youtube_missing_id'),
            str_contains($message, 'Missing Facebook page URL') =>
                __('queue.job_failed_facebook_missing_url'),
            str_contains(strtolower($message), 'usage hard limit') || str_contains(strtolower($message), 'insufficient-funds') =>
                __('queue.job_failed_credits_exhausted'),
            str_contains($message, 'returned no data') =>
                __('queue.job_failed_no_data'),
            str_starts_with($message, 'YouTube API error') =>
                __('queue.job_failed_youtube_api_error'),
            str_starts_with($message, 'Apify actor') =>
                __('queue.job_failed_apify_error'),
            default => Str::limit($message, 150),
        };
    }

    /**
     * A failed_jobs row only ever stores queue/timestamp/exception — nothing about which
     * account it was fetching, unlike churches/needs-attention.blade.php which reads that
     * straight off a live ChurchSocial. The account is still recoverable here: SerializesModels
     * swaps every Eloquent argument for a lightweight ModelIdentifier at serialize time, so
     * unserializing payload.data.command re-hydrates a real FetchSingleChurchData whose
     * ->churchSocial is a freshly-queried, live model — same display_name accessor reused for
     * consistency with every other social-account listing. Returns null (rendered as "—" in the
     * view) for any other job type, or if the account/job itself no longer exists (unserialize()
     * throws a ModelNotFoundException while waking up the ModelIdentifier in that case).
     */
    private function failedJobAccountLabel(string $rawPayload): ?string
    {
        try {
            $payload = json_decode($rawPayload, true, flags: JSON_THROW_ON_ERROR);
            $command = unserialize($payload['data']['command'] ?? '');

            return $command instanceof FetchSingleChurchData
                ? $command->churchSocial->display_name
                : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Cancel a batch — remaining un-run jobs skip their work (see
     * FetchSingleChurchData::middleware()) instead of the queue worker
     * continuing to burn through them after the user asked to stop.
     */
    public function cancelBatch(string $batch): RedirectResponse
    {
        $found = Bus::findBatch($batch);

        if ($found) {
            $found->cancel();

            return back()->with('status', __('queue.batch_cancelled', ['name' => $found->name]));
        }

        return back()->with('error', __('queue.batch_not_found'));
    }

    /**
     * Delete pending jobs outright — unlike cancelBatch(), this targets plain
     * queued jobs that aren't tracked by any batch (or clears everything at
     * once), since those have no batch id to cancel through.
     */
    public function clearQueue(Request $request): RedirectResponse
    {
        $queue = $request->input('queue');

        $count = $queue
            ? DB::table('jobs')->where('queue', $queue)->delete()
            : DB::table('jobs')->delete();

        $message = $queue
            ? __('queue.queue_cleared_named', ['count' => $count, 'queue' => $queue])
            : __('queue.queue_cleared_all', ['count' => $count]);

        return back()->with('status', $message);
    }

    /**
     * Delete every row from failed_jobs — these are already dead (retries
     * exhausted), so clearing just tidies up the history, unlike clearQueue()
     * which discards work that hasn't run yet.
     */
    public function clearFailed(): RedirectResponse
    {
        $count = DB::table('failed_jobs')->delete();

        return back()->with('status', __('queue.failed_cleared', ['count' => $count]));
    }

    /**
     * Delete a single failed_jobs row.
     */
    public function deleteFailed(int $id): RedirectResponse
    {
        DB::table('failed_jobs')->where('id', $id)->delete();

        return back()->with('status', __('queue.failed_deleted'));
    }

    /**
     * Re-queue a single failed job — e.g. after topping up Apify credit, an admin can retry
     * just that one account instead of waiting for next week's auto-fetch (or, if
     * apify_fallback_to_manual already flipped is_auto_fetch off, waiting forever — see
     * FetchSingleChurchData's ApifyCreditsExhaustedException handling). Delegates to Laravel's
     * own queue:retry rather than reimplementing pushRaw()/attempts-reset by hand — it already
     * re-queues the original payload on its original queue and removes the failed_jobs row.
     *
     * QUEUE_FAILED_DRIVER is database-uuids (see config/queue.php), so the failer resolves jobs
     * by uuid, not the numeric id every other method on this controller keys by (see
     * DatabaseUuidFailedJobProvider::find()) — that uuid has to be looked up first.
     */
    public function retryFailed(int $id): RedirectResponse
    {
        $uuid = DB::table('failed_jobs')->where('id', $id)->value('uuid');

        if ($uuid) {
            Artisan::call('queue:retry', ['id' => [$uuid]]);
        }

        return back()->with('status', __('queue.failed_retried'));
    }

    /**
     * Same as retryFailed() above, just for several rows picked via the Job Gagal table's
     * checkboxes at once instead of one at a time — queue:retry already accepts more than one
     * uuid in a single call.
     */
    public function retryFailedBatch(Request $request): RedirectResponse
    {
        $data = $request->validate(['ids' => ['required', 'array', 'min:1'], 'ids.*' => ['integer']]);

        $uuids = DB::table('failed_jobs')->whereIn('id', $data['ids'])->pluck('uuid')->all();

        if ($uuids !== []) {
            Artisan::call('queue:retry', ['id' => $uuids]);
        }

        return back()->with('status', __('queue.failed_retried_batch', ['count' => count($uuids)]));
    }

    /**
     * Same as deleteFailed() above, for several rows at once — shares the same checkboxes as
     * retryFailedBatch() (one submit button per action, both pointing at the one set of
     * checkboxes via their own formaction — see queue.blade.php).
     */
    public function deleteFailedBatch(Request $request): RedirectResponse
    {
        $data = $request->validate(['ids' => ['required', 'array', 'min:1'], 'ids.*' => ['integer']]);

        $count = DB::table('failed_jobs')->whereIn('id', $data['ids'])->delete();

        return back()->with('status', __('queue.failed_deleted_batch', ['count' => $count]));
    }

    /**
     * Delete every completed (finished_at not null) batch's history row —
     * these have already run to completion (or been cancelled), so this only
     * tidies up the "Batch Selesai" list, it doesn't touch any live work.
     */
    public function clearCompletedBatches(): RedirectResponse
    {
        $count = DB::table('job_batches')->whereNotNull('finished_at')->delete();

        return back()->with('status', __('queue.completed_cleared', ['count' => $count]));
    }

    /**
     * Delete a single completed batch's history row.
     */
    public function deleteBatch(string $batch): RedirectResponse
    {
        DB::table('job_batches')->where('id', $batch)->whereNotNull('finished_at')->delete();

        return back()->with('status', __('queue.completed_deleted'));
    }
}

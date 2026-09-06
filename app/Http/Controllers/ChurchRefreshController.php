<?php

namespace App\Http\Controllers;

use App\Jobs\FetchSingleChurchData;
use App\Models\ChurchSocial;
use App\Models\Union;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Throwable;

class ChurchRefreshController extends Controller
{
    /**
     * Queue a refresh for every active, auto-fetchable social account.
     * Queued (not run inline) since this can cover dozens of accounts — a queue worker processes them in the background.
     * Dispatched as a batch so the frontend can poll for progress instead of just a static "check back later" message.
     */
    public function all(Request $request): RedirectResponse|JsonResponse
    {
        ChurchSocial::disableAutoFetchForMissingFacebookProfileUrl();

        $socials = ChurchSocial::query()
            ->where('is_active', true)
            ->where('is_auto_fetch', true)
            ->ownerActive()
            ->consentGranted()
            ->readyToFetch()
            ->visibleTo($request->user())
            ->get();

        $delaySeconds = 0;
        $jobs = [];

        foreach ($socials as $social) {
            $jobs[] = (new FetchSingleChurchData($social))->delay(now()->addSeconds($delaySeconds));
            $delaySeconds += 3;
        }

        // allowFailures() is required — without it, Laravel cancels the *entire* batch the
        // moment any single account fails (e.g. one broken TikTok handle), silently skipping
        // every other account that hadn't run yet. finally() is required too — see its own doc
        // comment on markFinishedWhenAllJobsRan().
        $batch = Bus::batch($jobs)->name('refresh-socials')->allowFailures()->finally($this->markFinishedWhenAllJobsRan())->dispatch();

        if ($request->wantsJson()) {
            return response()->json([
                'batchId' => $batch->id,
                'total' => $socials->count(),
            ]);
        }

        return back()->with('status', __('dashboard.refresh_started', ['count' => $socials->count()]));
    }

    /**
     * Queue a refresh for just one Union's worth of accounts — Monitoring Antrean's "Fetch
     * per Uni" section, per the user's explicit call: the global refresh above can take a long
     * time (dozens/hundreds of accounts, 3s apart each), so this lets an admin fetch one Union
     * at a time instead of waiting on the whole thing. Named uniquely per Union
     * ("refresh-uni-{id}") rather than the shared 'refresh-socials' name all() uses, so
     * QueueMonitorController::index() can look up each Union's own last-finished batch
     * independently (see its own doc comment) without the two mechanisms colliding — a global
     * refresh and several per-Union refreshes can all be in flight at once, each tracked by its
     * own batch id.
     */
    public function union(Request $request, Union $union): RedirectResponse
    {
        ChurchSocial::disableAutoFetchForMissingFacebookProfileUrl();

        $socials = ChurchSocial::query()
            ->where('is_active', true)
            ->where('is_auto_fetch', true)
            ->ownerActive()
            ->consentGranted()
            ->readyToFetch()
            ->visibleTo($request->user())
            ->inUnion($union->id)
            ->get();

        if ($socials->isEmpty()) {
            return back()->with('error', __('queue.refresh_union_empty', ['union' => $union->name]));
        }

        $delaySeconds = 0;
        $jobs = [];

        foreach ($socials as $social) {
            $jobs[] = (new FetchSingleChurchData($social))->delay(now()->addSeconds($delaySeconds));
            $delaySeconds += 3;
        }

        Bus::batch($jobs)->name('refresh-uni-'.$union->id)->allowFailures()->finally($this->markFinishedWhenAllJobsRan())->dispatch();

        return back()->with('status', __('queue.refresh_union_started', ['union' => $union->name, 'count' => $socials->count()]));
    }

    /**
     * A real, confirmed gap in Laravel's own batching: Illuminate\Bus\Batch::recordFailedJob()
     * never decrements pending_jobs (only recordSuccessfulJob() does, and only checks
     * pendingJobs === 0 to mark the batch finished) — so a batch with even one PERMANENTLY
     * failed job (retries exhausted) never sets finished_at and never satisfies finished(),
     * no matter how long it's been since every job actually ran. Confirmed by dispatching a
     * real 2-job test batch (one guaranteed success, one guaranteed permanent failure): after
     * both had run, job_batches still showed pending_jobs=1/failed_jobs=1/finished_at=NULL —
     * exactly what the user was describing seeing live, and exactly why allowFailures() alone
     * isn't enough to get a truthful "100%, done" the moment every account has actually been
     * attempted (success or fail — the whole point of allowFailures() and of the separate
     * failed-count already shown elsewhere).
     *
     * Laravel's own UpdatedBatchJobCounts::allJobsHaveRanExactlyOnce() (pendingJobs - failedJobs
     * === 0) is exactly the "everyone has actually run" condition wanted — it's what already
     * gates the 'finally' callback itself, just without ever being wired to actually mark the
     * batch finished when the LAST job to resolve happens to be a failure rather than a success.
     * This closure closes that gap directly: once Laravel decides it's time to fire 'finally',
     * force finished_at to the current time if nothing already set it (a batch whose last job
     * succeeded will already have a real finished_at from Laravel's own path — whereNull() here
     * just avoids clobbering that with a slightly later timestamp).
     */
    private function markFinishedWhenAllJobsRan(): \Closure
    {
        return function ($batch) {
            DB::table('job_batches')->where('id', $batch->id)->whereNull('finished_at')
                ->update(['finished_at' => now()->getTimestamp()]);
        };
    }

    /**
     * Report progress for a batch dispatched by all(), polled by the dashboard's progress bar.
     */
    public function status(string $batch): JsonResponse
    {
        $batch = Bus::findBatch($batch);

        if (! $batch) {
            return response()->json(['finished' => true, 'percent' => 100, 'processed' => 0, 'total' => 0, 'failed' => 0]);
        }

        // Not $batch->progress()/processedJobs() — see markFinishedWhenAllJobsRan()'s own doc
        // comment for why: Laravel's own pendingJobs count never decrements for a permanently
        // failed job, so relying on it directly under-counts "processed" (and understates
        // percent) by however many accounts have already failed, right up until every last job
        // has resolved.
        $processed = $batch->totalJobs - $batch->pendingJobs + $batch->failedJobs;

        return response()->json([
            'finished' => $batch->finished(),
            'percent' => $batch->totalJobs > 0 ? (int) round(($processed / $batch->totalJobs) * 100) : 100,
            'processed' => $processed,
            'total' => $batch->totalJobs,
            'failed' => $batch->failedJobs,
        ]);
    }

    /**
     * Whether a bulk refresh is currently running — checked server-side (not just client
     * localStorage) so the button stays locked and the progress widget appears for anyone
     * loading the page, regardless of which tab/browser/session started the batch.
     */
    public function active(): JsonResponse
    {
        $batch = DB::table('job_batches')
            ->where('name', 'refresh-socials')
            ->whereNull('finished_at')
            ->orderByDesc('created_at')
            ->first();

        return response()->json(['batchId' => $batch->id ?? null]);
    }

    /**
     * Refresh a single social account immediately (fast enough to run inline within the request).
     */
    public function single(Request $request, ChurchSocial $social): RedirectResponse|JsonResponse
    {
        abort_unless(
            ChurchSocial::whereKey($social->id)->visibleTo($request->user())->exists(),
            404
        );

        if (! $social->is_auto_fetch) {
            $message = __('dashboard.refresh_manual_only', ['handle' => $social->display_handle]);

            return $request->wantsJson()
                ? response()->json(['success' => false, 'message' => $message], 422)
                : back()->with('error', $message);
        }

        // Personal-only, per the user's explicit call — distinct message from the is_auto_fetch
        // one above, since this is a privacy gate, not a "this account is manual" setting. See
        // ChurchSocial::scopeConsentGranted().
        if ($social->person_id !== null && $social->consent_at === null) {
            $message = __('dashboard.refresh_consent_missing', ['handle' => $social->display_handle]);

            return $request->wantsJson()
                ? response()->json(['success' => false, 'message' => $message], 422)
                : back()->with('error', $message);
        }

        try {
            FetchSingleChurchData::dispatchSync($social);
        } catch (Throwable $e) {
            $message = __('dashboard.refresh_failed_with_error', ['handle' => $social->display_handle, 'error' => $e->getMessage()]);

            return $request->wantsJson()
                ? response()->json(['success' => false, 'message' => $message], 500)
                : back()->with('error', $message);
        }

        // Checked from the persisted row rather than inferred purely from whether dispatchSync()
        // threw — an Apify-credits-exhausted failure calls $this->fail() instead of throwing (see
        // FetchSingleChurchData), so it still needs to be caught here.
        $social->refresh();

        if ($social->last_fetch_status === 'failed') {
            $message = __('dashboard.refresh_failed_with_error', ['handle' => $social->display_handle, 'error' => $social->last_fetch_error]);

            return $request->wantsJson()
                ? response()->json(['success' => false, 'message' => $message], 500)
                : back()->with('error', $message);
        }

        $message = __('dashboard.refresh_single_success', ['handle' => $social->display_handle]);

        return $request->wantsJson()
            ? response()->json(['success' => true, 'message' => $message])
            : back()->with('status', $message);
    }
}

<?php

namespace App\Http\Controllers;

use App\Jobs\FetchSingleChurchData;
use App\Models\ChurchSocial;
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
        $socials = ChurchSocial::query()
            ->where('is_active', true)
            ->where('is_auto_fetch', true)
            ->where(fn ($q) => $q
                ->whereHas('church', fn ($q2) => $q2->where('is_active', true))
                ->orWhereHas('person', fn ($q2) => $q2->where('is_active', true)),
            )
            ->get();

        $delaySeconds = 0;
        $jobs = [];

        foreach ($socials as $social) {
            $jobs[] = (new FetchSingleChurchData($social))->delay(now()->addSeconds($delaySeconds));
            $delaySeconds += 3;
        }

        // allowFailures() is required — without it, Laravel cancels the *entire* batch the
        // moment any single account fails (e.g. one broken TikTok handle), silently skipping
        // every other account that hadn't run yet.
        $batch = Bus::batch($jobs)->name('refresh-socials')->allowFailures()->dispatch();

        if ($request->wantsJson()) {
            return response()->json([
                'batchId' => $batch->id,
                'total' => $socials->count(),
            ]);
        }

        return back()->with('status', "Memperbarui {$socials->count()} akun di background — data akan muncul dalam beberapa menit.");
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

        return response()->json([
            'finished' => $batch->finished(),
            'percent' => $batch->progress(),
            'processed' => $batch->processedJobs(),
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
        if (! $social->is_auto_fetch) {
            $message = "Akun {$social->display_handle} ditandai manual dan tidak bisa di-refresh otomatis.";

            return $request->wantsJson()
                ? response()->json(['success' => false, 'message' => $message], 422)
                : back()->with('error', $message);
        }

        try {
            FetchSingleChurchData::dispatchSync($social);

            $message = "Akun {$social->display_handle} berhasil diperbarui.";

            return $request->wantsJson()
                ? response()->json(['success' => true, 'message' => $message])
                : back()->with('status', $message);
        } catch (Throwable $e) {
            $message = "Gagal memperbarui {$social->display_handle}: {$e->getMessage()}";

            return $request->wantsJson()
                ? response()->json(['success' => false, 'message' => $message], 500)
                : back()->with('error', $message);
        }
    }
}

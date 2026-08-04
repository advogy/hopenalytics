<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
            ->limit(20)
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'queue' => $row->queue,
                'failedAt' => $row->failed_at,
                'message' => $this->humanizeFailedJobMessage(strtok($row->exception, "\n")),
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
        ]);
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
            return 'Gagal memproses job ini.';
        }

        $message = trim($m[1]);

        return match (true) {
            str_starts_with($message, 'Facebook page not found: ') =>
                'Halaman Facebook tidak ditemukan: '.substr($message, strlen('Facebook page not found: ')),
            str_starts_with($message, 'TikTok profile not found: ') =>
                'Akun TikTok tidak ditemukan: '.substr($message, strlen('TikTok profile not found: ')),
            str_starts_with($message, 'YouTube channel not found: ') =>
                'Channel YouTube tidak ditemukan: '.substr($message, strlen('YouTube channel not found: ')),
            str_contains($message, 'Missing YouTube channel ID or handle') =>
                'Akun YouTube ini belum diisi ID channel atau handle-nya.',
            str_contains($message, 'Missing Facebook page URL') =>
                'Akun Facebook ini belum diisi link halamannya.',
            str_contains(strtolower($message), 'usage hard limit') || str_contains(strtolower($message), 'insufficient-funds') =>
                'Kredit Apify habis untuk bulan ini.',
            str_contains($message, 'returned no data') =>
                'Tidak ada data ditemukan untuk akun ini — cek lagi handle/link-nya.',
            str_starts_with($message, 'YouTube API error') =>
                'Gagal mengambil data dari YouTube, coba lagi nanti.',
            str_starts_with($message, 'Apify actor') =>
                'Gagal mengambil data dari Apify, coba lagi nanti.',
            default => Str::limit($message, 150),
        };
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

            return back()->with('status', "Batch \"{$found->name}\" dibatalkan.");
        }

        return back()->with('error', 'Batch tidak ditemukan (mungkin sudah selesai).');
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
            ? "{$count} job pada antrean \"{$queue}\" dibersihkan."
            : "{$count} job dibersihkan dari semua antrean.";

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

        return back()->with('status', "{$count} riwayat job gagal dibersihkan.");
    }

    /**
     * Delete a single failed_jobs row.
     */
    public function deleteFailed(int $id): RedirectResponse
    {
        DB::table('failed_jobs')->where('id', $id)->delete();

        return back()->with('status', 'Riwayat job gagal dihapus.');
    }

    /**
     * Delete every completed (finished_at not null) batch's history row —
     * these have already run to completion (or been cancelled), so this only
     * tidies up the "Batch Selesai" list, it doesn't touch any live work.
     */
    public function clearCompletedBatches(): RedirectResponse
    {
        $count = DB::table('job_batches')->whereNotNull('finished_at')->delete();

        return back()->with('status', "{$count} riwayat batch selesai dibersihkan.");
    }

    /**
     * Delete a single completed batch's history row.
     */
    public function deleteBatch(string $batch): RedirectResponse
    {
        DB::table('job_batches')->where('id', $batch)->whereNotNull('finished_at')->delete();

        return back()->with('status', 'Riwayat batch dihapus.');
    }
}

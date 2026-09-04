<?php

namespace App\Jobs;

use App\Mail\BulkAnnouncementMail;
use App\Models\EmailBroadcast;
use App\Models\User;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\SkipIfBatchCancelled;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/**
 * One recipient's send, dispatched as part of a Bus::batch() with each job's ->delay()
 * staggered by AppSetting::bulk_email_delay_seconds — same throttling shape
 * ChurchRefreshController::all() already uses successfully on this exact Hostinger setup, so a
 * "send to everyone" broadcast trickles out instead of tripping the same outbound rate limit
 * that broke OTP registrations (see the "451 ... hostinger_out_ratelimit" incident this whole
 * feature was scoped around).
 */
class SendBulkAnnouncementEmail implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(public int $broadcastId, public int $userId)
    {
        //
    }

    public function middleware(): array
    {
        return [new SkipIfBatchCancelled];
    }

    public function handle(): void
    {
        $broadcast = EmailBroadcast::find($this->broadcastId);
        $user = User::find($this->userId);

        // Either could be gone by the time this job actually runs (broadcast deleted, recipient
        // account removed) — skip rather than fail the batch over something no retry can fix.
        if ($broadcast === null || $user === null || $user->email === null) {
            return;
        }

        Mail::to($user->email)
            ->send((new BulkAnnouncementMail($broadcast, $user))->locale($user->locale ?? config('app.locale')));
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Jobs\SendBulkAnnouncementEmail;
use App\Models\AppSetting;
use App\Models\EmailBroadcast;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * "Kirim Email" — an Admin Global/Divisi tool for nudging chosen groups of social-media-owning
 * accounts (Admin Gereja/Institusi/Uni/Daerah, and Personal — see GROUPS) to update their data.
 * Built in direct response to a live production incident: a burst of registrations tripped
 * Hostinger's own outbound rate limit ("451 ... hostinger_out_ratelimit"), so a "send to
 * everyone" tool needed to be throttled from day one rather than sending inline. Reuses the
 * exact delay()-per-job + Bus::batch() pattern ChurchRefreshController::all() already proved
 * safe on this same hosting setup, rather than inventing a new mechanism.
 */
class EmailBroadcastController extends Controller
{
    /**
     * Every selectable recipient group, keyed by the value the create form's checkboxes submit —
     * 'personal' stands in for role === null since that's not itself a UserRole value. Order here
     * is display order everywhere (checkboxes, group labels).
     */
    private const GROUPS = ['admin_gereja', 'admin_institusi', 'admin_uni', 'admin_daerah', 'personal'];

    public function index(): View
    {
        Gate::authorize('send-bulk-email');

        $broadcasts = EmailBroadcast::with(['sender', 'division'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('admin.email-broadcasts.index', [
            'broadcasts' => $broadcasts,
            'groupLabels' => $this->groupLabels(),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('send-bulk-email');

        $actor = $request->user();

        // Per-group counts, not just one grand total — the create form uses these to total up
        // live as the admin checks/unchecks groups, with no AJAX round-trip needed (a role is
        // never in two groups at once, so summing the checked groups' counts is always exact).
        $groupCounts = collect(self::GROUPS)->mapWithKeys(
            fn (string $group) => [$group => $this->recipientsQuery($actor, [$group])->count()]
        );

        return view('admin.email-broadcasts.create', [
            'groupLabels' => $this->groupLabels(),
            'groupCounts' => $groupCounts,
            'delaySeconds' => max(1, AppSetting::current()->bulk_email_delay_seconds),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $actor = $request->user();
        Gate::authorize('send-bulk-email');

        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
            'groups' => ['required', 'array', 'min:1'],
            'groups.*' => [Rule::in(self::GROUPS)],
        ]);

        $broadcast = EmailBroadcast::create([
            'sender_id' => $actor->id,
            'division_id' => $actor->role === UserRole::AdminDivisi ? $actor->division_id : null,
            'subject' => $data['subject'],
            'body' => $data['body'],
            'groups' => array_values($data['groups']),
            'total_recipients' => 0,
        ]);

        $delayStep = max(1, AppSetting::current()->bulk_email_delay_seconds);
        $delaySeconds = 0;
        $jobs = [];

        // select('id') + chunkById rather than get() — this can legitimately be thousands of
        // rows nationwide; no reason to hydrate full User models just to read their id.
        $this->recipientsQuery($actor, $data['groups'])->select('id')->chunkById(500, function ($users) use (&$jobs, &$delaySeconds, $delayStep, $broadcast) {
            foreach ($users as $user) {
                $jobs[] = (new SendBulkAnnouncementEmail($broadcast->id, $user->id))->delay(now()->addSeconds($delaySeconds));
                $delaySeconds += $delayStep;
            }
        });

        if ($jobs === []) {
            $broadcast->delete();

            return back()->withInput()->with('error', __('email_broadcasts.no_recipients'));
        }

        // allowFailures() — one bad address (a hard bounce, a since-deleted account slipping
        // through) must never cancel the rest of a nationwide send. SkipIfBatchCancelled (on the
        // job itself) is what actually lets a cancel() take effect mid-send if ever needed.
        $batch = Bus::batch($jobs)->name('email-broadcast-'.$broadcast->id)->allowFailures()->dispatch();

        $broadcast->update(['batch_id' => $batch->id, 'total_recipients' => count($jobs)]);

        return redirect()->route('admin.email-broadcasts.index')
            ->with('status', __('email_broadcasts.sent', ['count' => count($jobs)]));
    }

    /** Polled by the history list to show a live progress bar for a still-sending broadcast. */
    public function status(EmailBroadcast $broadcast): JsonResponse
    {
        Gate::authorize('send-bulk-email');

        $batch = $broadcast->batch();

        return response()->json([
            'total' => $batch?->totalJobs ?? $broadcast->total_recipients,
            'processed' => $batch?->processedJobs() ?? 0,
            'failed' => $batch?->failedJobs ?? 0,
            'finished' => $batch?->finished() ?? true,
        ]);
    }

    /** value => translated label, in GROUPS' own display order. */
    private function groupLabels(): array
    {
        return collect(self::GROUPS)->mapWithKeys(
            fn (string $group) => [$group => __('email_broadcasts.group_'.$group)]
        )->all();
    }

    /**
     * Only the groups actually requested are matched — 'personal' stands in for role === null.
     * Global reaches nationwide; Divisi is narrowed to their own Division for every group,
     * mirroring the exact same union/conference/church/institution join shapes Church/
     * Institution's own scopeVisibleTo() already use for the equivalent divisi-level branch —
     * just applied here to find the OWNING USER of each entity rather than the entity itself,
     * since we need addresses to email, not records to list.
     *
     * @param  string[]  $groups  a subset of self::GROUPS
     */
    private function recipientsQuery(User $actor, array $groups): Builder
    {
        $query = User::query()->whereNotNull('email_verified_at');

        if ($groups === []) {
            return $query->whereRaw('1 = 0');
        }

        $divisionId = $actor->role === UserRole::AdminDivisi ? $actor->division_id : null;
        $isDivisiScoped = $actor->role === UserRole::AdminDivisi;

        return $query->where(function (Builder $q) use ($groups, $isDivisiScoped, $divisionId) {
            foreach ($groups as $group) {
                $q->orWhere(fn (Builder $q2) => $this->applyGroupFilter($q2, $group, $isDivisiScoped, $divisionId));
            }
        });
    }

    private function applyGroupFilter(Builder $query, string $group, bool $isDivisiScoped, ?int $divisionId): void
    {
        match ($group) {
            'admin_uni' => $query->where('role', UserRole::AdminUni->value)
                ->when($isDivisiScoped, fn (Builder $q) => $q->whereHas('union', fn (Builder $u) => $u->where('division_id', $divisionId))),
            'admin_daerah' => $query->where('role', UserRole::AdminDaerah->value)
                ->when($isDivisiScoped, fn (Builder $q) => $q->whereHas('conference.union', fn (Builder $u) => $u->where('division_id', $divisionId))),
            'admin_gereja' => $query->where('role', UserRole::AdminGereja->value)
                ->when($isDivisiScoped, fn (Builder $q) => $q->whereHas('church.conference.union', fn (Builder $u) => $u->where('division_id', $divisionId))),
            'admin_institusi' => $query->where('role', UserRole::AdminInstitusi->value)
                ->when($isDivisiScoped, fn (Builder $q) => $q->whereHas('institution', fn (Builder $i) => $i->where(
                    fn (Builder $i2) => $i2->whereHas('union', fn (Builder $u) => $u->where('division_id', $divisionId))
                        ->orWhereHas('conference.union', fn (Builder $u) => $u->where('division_id', $divisionId))
                ))),
            'personal' => $query->whereNull('role')
                ->when($isDivisiScoped, fn (Builder $q) => $q->whereHas('person', fn (Builder $p) => $p->where(
                    fn (Builder $p2) => $p2->whereHas('union', fn (Builder $u) => $u->where('division_id', $divisionId))
                        ->orWhereHas('conference.union', fn (Builder $u) => $u->where('division_id', $divisionId))
                ))),
            default => $query->whereRaw('1 = 0'),
        };
    }
}

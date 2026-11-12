<?php

namespace App\Http\Controllers;

use App\Models\Church;
use App\Models\ChurchMembership;
use App\Support\AuditLogger;
use App\Support\MemberEngagement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Union admins confirm (or reject) the membership figures a Division admin uploaded for the
 * churches in their Union. Only confirmed figures count toward member engagement.
 */
class MembershipConfirmationController extends Controller
{
    public function index(Request $request)
    {
        $pending = ChurchMembership::query()
            ->where('status', ChurchMembership::PENDING)
            ->whereIn('church_id', Church::query()->visibleTo($request->user())->select('id'))
            ->with(['church:id,name,conference_id', 'church.conference:id,name', 'uploader:id,name'])
            ->get()
            ->sortBy(fn (ChurchMembership $m) => [$m->church->conference?->name, $m->church->name, $m->as_of]);

        // Each figure's previous confirmed one (dated before it), to compare against.
        $previous = MemberEngagement::previousConfirmed($pending);

        return view('engagement.confirm', [
            'groups' => $pending->groupBy(fn (ChurchMembership $m) => $m->church->conference?->name ?? '—'),
            'previous' => $previous,
            'pendingCount' => $pending->count(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:confirm,reject'],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ], [
            'ids.required' => __('engagement.confirm_none_selected'),
        ]);

        $memberships = ChurchMembership::query()
            ->whereIn('id', $data['ids'])
            ->where('status', ChurchMembership::PENDING)
            ->whereIn('church_id', Church::query()->visibleTo($request->user())->select('id'))
            ->with('church:id,name')
            ->get();

        $status = $data['action'] === 'confirm' ? ChurchMembership::CONFIRMED : ChurchMembership::REJECTED;

        ChurchMembership::query()->whereIn('id', $memberships->pluck('id'))->update([
            'status' => $status,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        if ($memberships->isNotEmpty()) {
            $verb = $status === ChurchMembership::CONFIRMED ? 'Mengonfirmasi' : 'Menolak';
            AuditLogger::log("membership.{$status}", null, "{$verb} jumlah anggota {$memberships->count()} gereja: "
                .$memberships->map(fn ($m) => "{$m->church->name} ".number_format($m->members_count, 0, ',', '.')." per {$m->as_of->format('Y-m-d')}")->implode('; ').'.');
        }

        return redirect()->route('engagement.confirm.index')->with('status', __(
            $status === ChurchMembership::CONFIRMED ? 'engagement.confirmed' : 'engagement.rejected',
            ['count' => $memberships->count()],
        ));
    }
}

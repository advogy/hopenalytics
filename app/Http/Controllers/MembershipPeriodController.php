<?php

namespace App\Http\Controllers;

use App\Models\MembershipPeriod;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Global admins set the engagement periods and each period's target share. */
class MembershipPeriodController extends Controller
{
    public function index(Request $request)
    {
        $periods = MembershipPeriod::query()->orderByDesc('starts_on')->get();
        $editing = $periods->firstWhere('id', (int) $request->query('ubah'));

        return view('engagement.periods', ['periods' => $periods, 'editing' => $editing]);
    }

    public function store(Request $request): RedirectResponse
    {
        $period = MembershipPeriod::create($this->validated($request));

        AuditLogger::log('membership_period.created', $period, "Membuat periode keterlibatan \"{$period->name}\" ({$this->summary($period)}).");

        return redirect()->route('engagement.periods.index')->with('status', __('engagement.period_saved'));
    }

    public function update(Request $request, MembershipPeriod $period): RedirectResponse
    {
        $period->update($this->validated($request));

        AuditLogger::log('membership_period.updated', $period, "Memperbarui periode keterlibatan \"{$period->name}\" ({$this->summary($period)}).");

        return redirect()->route('engagement.periods.index')->with('status', __('engagement.period_saved'));
    }

    public function destroy(MembershipPeriod $period): RedirectResponse
    {
        AuditLogger::log('membership_period.deleted', $period, "Menghapus periode keterlibatan \"{$period->name}\".");

        $period->delete();

        return redirect()->route('engagement.periods.index')->with('status', __('engagement.period_deleted'));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'target_percent' => ['required', 'numeric', 'min:0.01', 'max:100'],
        ]) + ['updated_by' => $request->user()->id];
    }

    private function summary(MembershipPeriod $period): string
    {
        return $period->starts_on->format('d/m/Y').' – '.$period->ends_on->format('d/m/Y').', target '.rtrim(rtrim(number_format($period->target_percent, 2, ',', ''), '0'), ',').'%';
    }
}

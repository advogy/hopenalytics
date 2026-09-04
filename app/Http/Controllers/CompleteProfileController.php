<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FindsOrCreatesChurch;
use App\Models\Church;
use App\Models\Conference;
use App\Models\Union;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CompleteProfileController extends Controller
{
    use FindsOrCreatesChurch;

    /**
     * A one-time interstitial shown right after email verification (see
     * RegisterController::verifyOtp()) — asks a brand-new member which Uni/Daerah/Gereja they
     * belong to, so regional admins can find them later on the Kelola Pengguna page (see
     * UserAssignmentController) instead of that list staying an unscoped global dump. The
     * answer is stored on the member's own linked Person (see store() below), not on the User
     * row — every role === null member reaching this page is guaranteed to already have one,
     * either auto-created or linked to an existing admin-made record (see
     * RegisterController::verifyOtp() / LinkPersonController::store()).
     *
     * Skippable; a skip leaves the Person's union_id/conference_id/church_id null, which is
     * exactly the "nasional" (unscoped, nasional-admins-only) visibility bucket — but it can
     * always be completed later from the Wilayah section folded into Profil Saya's "Info
     * Personal" tab (see ProfileController::edit()), which posts to the same store() below.
     * Also skipped automatically — profile_step_completed_at backfilled without asking again —
     * when the linked Person already reports a region, e.g. an admin pre-created it with one
     * already set before this member ever registered.
     */
    public function show(Request $request)
    {
        $user = $request->user();

        if ($user->role !== null || $user->profile_step_completed_at !== null) {
            return redirect()->route('akun-saya');
        }

        if ($user->person && ($user->person->union_id || $user->person->conference_id)) {
            $user->forceFill(['profile_step_completed_at' => now()])->save();

            return redirect()->route('akun-saya');
        }

        return view('auth.complete-profile', [
            'unions' => Union::where('is_active', true)->orderBy('name')->get(),
            'conferences' => Conference::where('is_active', true)->orderBy('name')->get(['id', 'union_id', 'name']),
            'churches' => Church::where('is_active', true)->orderBy('name')->get(['id', 'conference_id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        // A role-holder's own union_id/conference_id/church_id means their assigned admin scope
        // (see UserAssignmentController::promote()), entirely unrelated to this endpoint (a
        // plain member's own self-reported home region, stored on their Person instead) — a
        // role-holder must go through the proper promote/revoke flow, never this one.
        abort_if($user->role !== null, 403);

        $data = $request->validate([
            'union_id' => ['required', 'integer', 'exists:unions,id'],
            'conference_id' => ['required', 'integer', 'exists:conferences,id'],
            'church_name' => ['required', 'string', 'max:255'],
        ]);

        $conference = Conference::findOrFail($data['conference_id']);
        abort_if($conference->union_id !== (int) $data['union_id'], 422);

        $church = $this->findExistingChurchOrSuggestAdmin($user, $conference->id, $data['church_name']);

        // Every role === null member reaching this endpoint already has a linked Person (see
        // this class's own doc comment above) — union_id stays null here, matching
        // PersonController::resolveOrgScope()'s "never both union_id and conference_id at once"
        // invariant elsewhere, since a Conference already implies its own Union. church_id stays
        // null when $church is null (a new-name suggestion is now pending review instead) — same
        // breadth as reporting this Daerah with no specific Gereja at all, until it's approved.
        $user->person->update([
            'union_id' => null,
            'conference_id' => $conference->id,
            'church_id' => $church?->id,
        ]);

        $user->forceFill(['profile_step_completed_at' => now()])->save();

        // The Wilayah fields now live folded into the "Info Personal" tab — see
        // profile/edit.blade.php.
        return redirect()->route('profile.edit', ['tab' => 'personal'])->with(
            'status',
            $church === null ? __('entity.admin_suggestion_pending', ['name' => trim($data['church_name'])]) : __('entity.profile_completed')
        );
    }

    public function skip(Request $request): RedirectResponse
    {
        abort_if($request->user()->role !== null, 403);

        $request->user()->forceFill(['profile_step_completed_at' => now()])->save();

        return redirect()->route('akun-saya');
    }
}

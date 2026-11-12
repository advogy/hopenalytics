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
     * A two-step wizard (?langkah=1|2): step 1 asks for the region, step 2 lets the member add
     * their personal social accounts (posted to PersonSocialController::store(), which sends
     * them back here). Both steps are skippable; skipping step 1 leaves the Person's
     * union_id/conference_id/church_id null, which is exactly the "nasional" (unscoped,
     * nasional-admins-only) visibility bucket. Either can be completed later from Profil Saya.
     * profile_step_completed_at is set only by finish(), so the wizard isn't shown again.
     */
    public function show(Request $request)
    {
        $user = $request->user();

        if ($user->role !== null || $user->profile_step_completed_at !== null) {
            return redirect()->route('akun-saya');
        }

        // Two steps, both skippable: 1 = Wilayah (region), 2 = personal social accounts.
        // A Person that already reports a region (e.g. an admin pre-created it with one)
        // starts on step 2 instead of being asked again.
        $person = $user->person;
        $hasRegion = $person && ($person->union_id || $person->conference_id);
        $step = (int) $request->query('langkah', $hasRegion ? 2 : 1);
        $step = $step === 2 && $person ? 2 : 1;

        $socials = $person
            ? $person->socials()->where('is_active', true)->orderBy('platform')->get()
            : collect();

        $data = [
            'step' => $step,
            'person' => $person,
            'hasRegion' => $hasRegion,
            'socials' => $socials,
        ];

        if ($step === 1) {
            $data += [
                'unions' => Union::where('is_active', true)->orderBy('name')->get(),
                'conferences' => Conference::where('is_active', true)->orderBy('name')->get(['id', 'union_id', 'name']),
                'churches' => Church::where('is_active', true)->orderBy('name')->get(['id', 'conference_id', 'name']),
                // Pre-fills step 1 when the member comes back to it from step 2.
                'selectedConferenceId' => $person?->conference_id,
                'selectedUnionId' => $person?->conference?->union_id ?? $person?->union_id,
                'selectedChurchName' => $person?->church?->name,
            ];
        }

        return view('auth.complete-profile', $data);
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

        $church = $this->findExistingChurchOrSuggestAdmin($user, $conference->id, $data['church_name'], $request->boolean('church_is_admin'));

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

        // Not marked complete yet — step 2 (social accounts) comes next; finish() closes the
        // wizard once the member is done with it or skips it.
        return redirect()->route('profile.complete', ['langkah' => 2])->with(
            'status',
            match (true) {
                $church !== null => __('entity.profile_completed'),
                $request->boolean('church_is_admin') => __('entity.admin_suggestion_pending', ['name' => trim($data['church_name'])]),
                default => __('entity.church_suggestion_pending', ['name' => trim($data['church_name'])]),
            }
        );
    }

    /** Ends the wizard from step 2, whether the member added accounts there or skipped it. */
    public function finish(Request $request): RedirectResponse
    {
        abort_if($request->user()->role !== null, 403);

        $request->user()->forceFill(['profile_step_completed_at' => now()])->save();

        return redirect()->route('akun-saya')->with('status', __('auth.onboarding_done', ['app' => config('app.name')]));
    }
}

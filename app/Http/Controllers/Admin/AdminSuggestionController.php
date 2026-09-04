<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminSuggestionStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Concerns\FindsOrCreatesChurch;
use App\Http\Controllers\Controller;
use App\Mail\AdminSuggestionApprovedMail;
use App\Models\AdminSuggestion;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;

/**
 * Approve/reject one member's claim (made by typing a not-yet-existing Gereja name during
 * Lengkapi Profil or Profil Saya's Wilayah section — see
 * FindsOrCreatesChurch::findExistingChurchOrSuggestAdmin()) that they should become that new
 * church's admin. Listed on Kelola Pengguna's own "Saran Admin" tab (see
 * UserAssignmentController::index()) rather than a separate page, since it's still fundamentally
 * a user-management action.
 */
class AdminSuggestionController extends Controller
{
    use FindsOrCreatesChurch;

    /**
     * Creates the Church (or reuses an exact-name match under the same Conference, in case a
     * second member's suggestion for the same real church landed here too — see
     * findOrCreateChurch()'s own doc comment) and promotes the requester to admin_gereja over
     * it — the same $target->update() field-setting shape UserAssignmentController::promote()
     * itself uses for a manual assignment, but authorized via AdminSuggestionPolicy::review()
     * rather than the generic 'promote' gate (see that policy's own doc comment for why —
     * Gate::authorize('promote', ...) would incorrectly reject an Admin Uni/Divisi here even
     * though they're allowed to review this exact suggestion).
     */
    public function approve(Request $request, AdminSuggestion $suggestion): RedirectResponse
    {
        $actor = $request->user();

        Gate::authorize('review', $suggestion);
        abort_unless($suggestion->status === AdminSuggestionStatus::Pending, 409);

        // Wrapped in a transaction — Church creation, the requester's promotion, their Person
        // sync, and marking this suggestion resolved are one atomic step; a failure partway
        // through (confirmed live while testing this: an unrelated DB error after the Church
        // and promotion had already gone through) must never leave a real Church + a freshly
        // admin_gereja'd user sitting behind a suggestion that STILL reads "pending" to the next
        // reviewer.
        $church = DB::transaction(function () use ($suggestion, $actor) {
            $church = $this->findOrCreateChurch($suggestion->conference_id, $suggestion->church_name);

            $suggestion->user->update([
                'role' => UserRole::AdminGereja,
                'division_id' => null,
                'union_id' => null,
                'conference_id' => null,
                'church_id' => $church->id,
                'institution_id' => null,
            ]);
            $suggestion->user->assignedUnions()->sync([]);

            $suggestion->person->update(['union_id' => null, 'conference_id' => null, 'church_id' => $church->id]);

            $suggestion->update([
                'status' => AdminSuggestionStatus::Approved,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'resulting_church_id' => $church->id,
            ]);

            return $church;
        });

        // Sent only here, after the transaction has actually committed — never on reject (see
        // that method) and never if the transaction above rolled back, so the requester is only
        // ever told "you're now admin" once it's actually true.
        //
        // ->locale(...) (Mailable's own built-in helper) renders this mail in the REQUESTER's
        // last-known language (see User::$locale, kept in sync by SetLocale), not the approving
        // admin's — the two are frequently different people with different language settings,
        // and the recipient has no session of their own active here for the app to read from
        // otherwise. Falls back to the app default for a user somehow never seen by SetLocale.
        Mail::to($suggestion->user->email)
            ->send((new AdminSuggestionApprovedMail($suggestion->user, $church))->locale($suggestion->user->locale ?? config('app.locale')));

        AuditLogger::log(
            'admin_suggestion.approved',
            $suggestion->user,
            "Menyetujui saran admin \"{$suggestion->user->name}\" untuk Gereja \"{$church->name}\" ({$suggestion->conference->name}), membuat gereja baru dan menugaskan sebagai {$church->name} admin."
        );

        return redirect()->route('admin.users.index', ['tab' => 'saran'])
            ->with('status', __('admin_suggestions.approved', ['name' => $suggestion->user->name, 'church' => $church->name]));
    }

    /**
     * The requester stays a plain member (role never left null in the first place — see
     * findExistingChurchOrSuggestAdmin(), which never touches User at all) and no Church is ever
     * created for this name. $reason is optional but, when given, is the one piece of feedback
     * the requester has any way of seeing (this app has no in-app notification system — see
     * admin/users/partials/suggestions-tab.blade.php for where it's shown back to them, in
     * whatever surface eventually exposes their own suggestion history).
     */
    public function reject(Request $request, AdminSuggestion $suggestion): RedirectResponse
    {
        $actor = $request->user();

        Gate::authorize('review', $suggestion);
        abort_unless($suggestion->status === AdminSuggestionStatus::Pending, 409);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);

        $suggestion->update([
            'status' => AdminSuggestionStatus::Rejected,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
            'rejection_reason' => $data['reason'] ?? null,
        ]);

        AuditLogger::log(
            'admin_suggestion.rejected',
            $suggestion->user,
            "Menolak saran admin \"{$suggestion->user->name}\" untuk Gereja \"{$suggestion->church_name}\" ({$suggestion->conference->name})."
        );

        return redirect()->route('admin.users.index', ['tab' => 'saran'])
            ->with('status', __('admin_suggestions.rejected', ['name' => $suggestion->user->name]));
    }
}

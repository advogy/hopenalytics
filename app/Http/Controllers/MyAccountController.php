<?php

namespace App\Http\Controllers;

use App\Models\Person;
use Illuminate\Http\Request;

class MyAccountController extends Controller
{
    /**
     * A plain member always has exactly one linked Person (created at OTP verification) —
     * renders PersonController::show()'s view directly (rather than redirecting to
     * /personal/{id}) so the URL bar stays on the stable /dashboard path instead of leaking
     * the numeric id. Admin/leadership accounts (console-created, or promoted before ever
     * registering) never get one, so they're sent to the main dashboard instead — NOT another
     * route inside RedirectUnassignedMembers's allow-list check, since /dashboard is a
     * role === null member's only allowed landing page and bouncing them elsewhere would just
     * redirect straight back here, looping forever. If a plain member is somehow missing their
     * Person (a data-integrity edge case, not a normal path), it's created on the fly instead
     * of crashing or risking that same loop.
     */
    public function index(Request $request, PersonController $personController)
    {
        $user = $request->user();
        $person = $user->person;

        if (! $person) {
            if ($user->role !== null) {
                return redirect()->route('churches.index');
            }

            $person = Person::create(['user_id' => $user->id, 'name' => $user->name]);
        }

        return $personController->show($person);
    }
}

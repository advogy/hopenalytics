<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FindsPersonCandidates;
use App\Models\Person;
use Illuminate\Http\Request;

class MyAccountController extends Controller
{
    use FindsPersonCandidates;

    /**
     * Renders PersonController::show()'s view directly (rather than redirecting to
     * /personal/{id}) so the URL bar stays on the stable /dashboard path instead of leaking
     * the numeric id.
     *
     * A plain member always has exactly one linked Person (created at OTP verification, or —
     * a data-integrity edge case, not a normal path — created here on the fly if somehow
     * missing). An admin/leadership account (console-created, or promoted before ever
     * registering) has no Person by default, but per the user's explicit call gets the same
     * auto-link-or-create treatment the very first time they open "Profil Saya", so they always
     * have somewhere to manage their own personal social accounts alongside whatever
     * church/institution/personal entities their role manages — findPersonCandidates() first,
     * in case an admin already exists as an independent Person someone else created (e.g. a
     * leader's bio tracked before they ever got a login), so they claim it instead of ending up
     * with two disconnected records for the same person.
     */
    public function index(Request $request, PersonController $personController)
    {
        $user = $request->user();
        $person = $user->person;

        if (! $person) {
            $candidates = $this->findPersonCandidates($user->name);

            if ($candidates->isNotEmpty()) {
                return redirect()->route('link-person');
            }

            $person = Person::create(['user_id' => $user->id, 'name' => $user->name]);
        }

        return $personController->show($person);
    }
}

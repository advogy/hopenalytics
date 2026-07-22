<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FindsPersonCandidates;
use App\Models\Person;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LinkPersonController extends Controller
{
    use FindsPersonCandidates;

    /**
     * A plain member sees this once, right after OTP verification, only when
     * findPersonCandidates() found a plausible match (see RegisterController::verifyOtp()) —
     * otherwise they never see this screen at all, and land on profile.complete next since
     * they're still mid-onboarding. An admin can also reach this directly from "Profil Saya"
     * (ProfileController::edit() — the page that link actually goes to) the first time they
     * have no linked Person — they skip profile.complete entirely (it's a self-registration
     * step; an admin's region is already assigned) and land back on the Media Sosial tab of
     * their own profile page instead.
     */
    public function show(Request $request)
    {
        $user = $request->user();

        if ($user->person) {
            return redirect($this->nextLocation($user));
        }

        $candidates = $this->findPersonCandidates($user->name);

        if ($candidates->isEmpty()) {
            return redirect($this->nextLocation($user));
        }

        return view('auth.link-person', ['candidates' => $candidates]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $nextLocation = $this->nextLocation($user);

        if ($user->person) {
            return redirect($nextLocation);
        }

        $data = $request->validate(['person_id' => ['required', 'string']]);

        if ($data['person_id'] === 'new') {
            Person::create(['user_id' => $user->id, 'name' => $user->name]);

            return redirect($nextLocation)->with('status', __('auth.person_created'));
        }

        // Re-derive the candidate list from the authenticated user's own name (never trust
        // the submitted id alone) and re-check whereNull('user_id') fresh from the DB, so a
        // tampered person_id can't claim an unrelated — or just-claimed-by-someone-else —
        // Person.
        $candidateIds = $this->findPersonCandidates($user->name)->pluck('id');

        $person = Person::whereKey($data['person_id'])->whereIn('id', $candidateIds)->whereNull('user_id')->first();

        if (! $person) {
            return redirect()->route('link-person')->with('error', __('auth.person_already_claimed'));
        }

        $person->update(['user_id' => $user->id]);

        return redirect($nextLocation)->with('status', __('auth.person_linked'));
    }

    private function nextLocation(User $user): string
    {
        return $user->role === null
            ? route('profile.complete')
            : route('profile.edit', ['tab' => 'sosial']);
    }
}

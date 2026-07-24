<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FindsPersonCandidates;
use App\Models\Person;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterController extends Controller
{
    use FindsPersonCandidates;

    public function showRegister()
    {
        if (Auth::check()) {
            return redirect()->route('churches.index');
        }

        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        // Only a *verified* email blocks a new registration — an unverified one can't do
        // anything but sit there waiting for its OTP (see cancelRegistration()'s doc comment),
        // so re-submitting the same email just resumes/resets that same pending signup below
        // instead of erroring out.
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->whereNotNull('email_verified_at')],
            'password' => ['required', 'string', Password::min(8)->uncompromised(), 'confirmed'],
        ]);

        $user = User::where('email', $data['email'])->whereNull('email_verified_at')->first();

        if ($user) {
            $user->forceFill([
                'name' => $data['name'],
                'password' => Hash::make($data['password']),
            ])->save();
        } else {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);
        }

        $this->generateAndSendOtp($user);

        $request->session()->put('otp_user_id', $user->id);

        return redirect()->route('verify-otp');
    }

    public function showVerifyOtp(Request $request)
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('register')->with('error', __('auth.no_pending_registration'));
        }

        return view('auth.verify-otp', ['email' => $user->email]);
    }

    public function verifyOtp(Request $request): RedirectResponse
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('register')->with('error', __('auth.no_pending_registration'));
        }

        $request->validate([
            'code' => ['required', 'string'],
        ]);

        if ($user->otp_code !== $request->input('code') || $user->otp_expires_at === null) {
            return back()->withErrors(['code' => __('auth.otp_invalid')]);
        }

        if ($user->otp_expires_at->isPast()) {
            return back()->withErrors(['code' => __('auth.otp_expired')]);
        }

        $user->forceFill([
            'email_verified_at' => now(),
            'otp_code' => null,
            'otp_expires_at' => null,
        ])->save();

        $request->session()->forget('otp_user_id');

        Auth::login($user);
        $request->session()->regenerate();

        // An admin may have already created a Person for this exact individual (e.g. before
        // they self-registered) — offer to link to it instead of silently creating a second,
        // disconnected record. Only shown when a plausible match actually exists; otherwise
        // this is the same one-step flow as before.
        if (! $user->person && $this->findPersonCandidates($user->name)->isNotEmpty()) {
            return redirect()->route('link-person');
        }

        if (! $user->person) {
            Person::create([
                'user_id' => $user->id,
                'name' => $user->name,
            ]);
        }

        return redirect()->route('profile.complete')->with('status', __('auth.otp_verified'));
    }

    /**
     * "Keluar" on the verify-otp page. Doesn't touch the unverified account itself — it can't do
     * anything without its OTP anyway (no session auth guard until verifyOtp() succeeds), and
     * register()'s unique-email check only cares about *verified* emails, so re-registering the
     * same address later just resumes this same pending signup instead of being blocked by it.
     */
    public function cancelRegistration(Request $request): RedirectResponse
    {
        $request->session()->forget('otp_user_id');

        return redirect()->route('login');
    }

    public function resendOtp(Request $request): RedirectResponse
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('register')->with('error', __('auth.no_pending_registration'));
        }

        $this->generateAndSendOtp($user);

        return back()->with('status', __('auth.otp_resent'));
    }

    private function pendingUser(Request $request): ?User
    {
        $userId = $request->session()->get('otp_user_id');

        if (! $userId) {
            return null;
        }

        return User::find($userId);
    }

    private function generateAndSendOtp(User $user): void
    {
        $user->sendVerificationOtp();
    }
}

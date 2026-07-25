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

    /**
     * Deliberately doesn't require session.otp_user_id to render — that's only used to pre-fill
     * the email field as a convenience right after register() redirects here. Verification
     * itself (verifyOtp()/resendOtp() below) is keyed off the email+code the visitor submits,
     * not session state, so a lost/expired session or a different device (e.g. registering on
     * desktop, checking the code on a phone) never strands anyone with no way back in — the
     * old session-only design bounced straight to /register with no path to use a code an admin
     * had just resent from Kelola Pengguna. Same reasoning as ForgotPasswordController.
     */
    public function showVerifyOtp(Request $request)
    {
        $pendingUser = $this->pendingUser($request);

        return view('auth.verify-otp', [
            'email' => old('email', $pendingUser?->email),
        ]);
    }

    public function verifyOtp(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->whereNull('email_verified_at')->first();

        // $user === null folds into the same "kode salah" branch as a real user's wrong code —
        // a distinct "no such pending registration" message here would let an attacker
        // enumerate which emails have a pending unverified signup, the same concern
        // ForgotPasswordController::reset() already guards against.
        if (! $user || $user->otp_code !== $data['code'] || $user->otp_expires_at === null) {
            return back()->withErrors(['code' => __('auth.otp_invalid')])->withInput();
        }

        if ($user->otp_expires_at->isPast()) {
            return back()->withErrors(['code' => __('auth.otp_expired')])->withInput();
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
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $data['email'])->whereNull('email_verified_at')->first();

        // Same "sent" response whether or not $user resolves — same anti-enumeration reasoning
        // as verifyOtp() above.
        if ($user) {
            $this->generateAndSendOtp($user);
        }

        return back()->with('status', __('auth.otp_resent'))->withInput();
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

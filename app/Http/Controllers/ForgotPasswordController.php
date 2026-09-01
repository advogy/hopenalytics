<?php

namespace App\Http\Controllers;

use App\Mail\PasswordResetOtpMail;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;

class ForgotPasswordController extends Controller
{
    public function show()
    {
        if (Auth::check()) {
            return redirect()->route('churches.index');
        }

        return view('auth.forgot-password');
    }

    /**
     * Deliberately doesn't reveal whether the email is registered: validation only checks
     * the email is well-formed (no `exists:users,email`), and the redirect/session shape is
     * identical either way. A non-existent email gets session user id -1 — a value
     * User::find() can never resolve, but still truthy so pendingUser() doesn't short-circuit
     * — so showReset() still renders the normal OTP form instead of bouncing back with a
     * distinguishable error. Without this the endpoint was a direct account-enumeration
     * oracle, and enumeration is the reconnaissance step of the account-takeover attempts
     * reset()'s throttling (see routes/web.php's `throttle:reset-password`) guards against.
     */
    public function send(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if ($user) {
            $this->generateAndSendOtp($user);
        }

        $request->session()->put('password_reset_user_id', $user->id ?? -1);
        // Always the address as typed, not $user->email — kept separate so showReset() can
        // display it back without ever depending on whether $user actually resolved.
        $request->session()->put('password_reset_email', $data['email']);

        return redirect()->route('reset-password');
    }

    public function showReset(Request $request)
    {
        if (! $this->hasPendingReset($request)) {
            return redirect()->route('forgot-password')->with('error', __('auth.no_pending_reset'));
        }

        return view('auth.reset-password', ['email' => $request->session()->get('password_reset_email')]);
    }

    /**
     * A fake pending state (see send()'s docblock) reaches here too — pendingUser() resolves
     * it to null just like an expired/tampered session would, so it falls into the exact same
     * "invalid code" branch as a real user typing the wrong code, rather than a distinguishable
     * early bounce. Only a session with no pending attempt *at all* redirects elsewhere.
     */
    public function reset(Request $request): RedirectResponse
    {
        if (! $this->hasPendingReset($request)) {
            return redirect()->route('forgot-password')->with('error', __('auth.no_pending_reset'));
        }

        $data = $request->validate([
            'code' => ['required', 'string'],
            'password' => ['required', 'string', Password::min(8)->uncompromised(), 'confirmed'],
        ]);

        $user = $this->pendingUser($request);

        if (! $user || $user->otp_code !== $data['code'] || $user->otp_expires_at === null) {
            return back()->withErrors(['code' => __('auth.otp_invalid')]);
        }

        if ($user->otp_expires_at->isPast()) {
            return back()->withErrors(['code' => __('auth.otp_expired')]);
        }

        $user->forceFill([
            'password' => Hash::make($data['password']),
            'otp_code' => null,
            'otp_expires_at' => null,
        ])->save();

        $request->session()->forget(['password_reset_user_id', 'password_reset_email']);

        return redirect()->route('login')->with('status', __('auth.password_reset_success'));
    }

    /**
     * Lets someone abandon a pending reset and go back to plain email entry — mirrors
     * RegisterController::cancelRegistration()'s exact shape for the same reason: without
     * this, the only way out of reset-password once you're on it is finishing it or closing
     * the tab, leaving the pending state (and its OTP) sitting in session either way.
     */
    public function cancel(Request $request): RedirectResponse
    {
        $request->session()->forget(['password_reset_user_id', 'password_reset_email']);

        return redirect()->route('forgot-password');
    }

    public function resend(Request $request): RedirectResponse
    {
        if (! $this->hasPendingReset($request)) {
            return redirect()->route('forgot-password')->with('error', __('auth.no_pending_reset'));
        }

        // Same "sent" response either way (see send()) — only actually mails a code when
        // the fake-pending state's user lookup fails to resolve.
        $user = $this->pendingUser($request);
        if ($user) {
            $this->generateAndSendOtp($user);
        }

        return back()->with('status', __('auth.otp_resent'));
    }

    private function hasPendingReset(Request $request): bool
    {
        return $request->session()->has('password_reset_user_id');
    }

    private function pendingUser(Request $request): ?User
    {
        $userId = $request->session()->get('password_reset_user_id');

        if (! $userId) {
            return null;
        }

        return User::find($userId);
    }

    private function generateAndSendOtp(User $user): void
    {
        $user->forceFill([
            'otp_code' => str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT),
            'otp_expires_at' => now()->addMinutes(10),
        ])->save();

        Mail::to($user->email)->send(new PasswordResetOtpMail($user));
    }
}

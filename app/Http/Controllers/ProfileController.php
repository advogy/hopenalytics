<?php

namespace App\Http\Controllers;

use App\Mail\OtpVerificationMail;
use App\Models\Church;
use App\Models\Conference;
use App\Models\Union;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        $user = $request->user();
        $person = $user->person;

        // The "Wilayah" tab reuses the Lengkapi Profil fields (see
        // CompleteProfileController) so a member who skipped it during registration can
        // still complete it later. It's plain-member-only — union_id/conference_id/church_id
        // double as an assigned admin's scope once they hold a role, so editing it here would
        // risk silently changing that assignment (see CompleteProfileController::store()).
        $canEditRegion = $user->role === null;

        $activeTab = in_array($request->query('tab'), ['personal', 'username', 'password', 'wilayah'], true)
            ? $request->query('tab')
            : 'username';

        return view('profile.edit', [
            'user' => $user,
            'person' => $person,
            'activeTab' => $activeTab,
            'canEditRegion' => $canEditRegion,
            'unions' => $canEditRegion ? Union::where('is_active', true)->orderBy('name')->get() : collect(),
            'conferences' => $canEditRegion ? Conference::where('is_active', true)->orderBy('name')->get(['id', 'union_id', 'name']) : collect(),
            'churches' => $canEditRegion ? Church::where('is_active', true)->orderBy('name')->get(['id', 'conference_id', 'name']) : collect(),
        ]);
    }

    /**
     * Updates name always; an email change is never applied immediately — it's held in
     * pending_email until the new address is verified via OTP (see verifyEmailChange()),
     * so no one can silently take over an account by changing its email to one they don't
     * control.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $changingEmail = $request->input('email') !== $user->email;

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            // Only the email change is sensitive enough to need re-confirming — a name-only
            // edit stays frictionless. Without this, a hijacked session (XSS, unlocked
            // device, leaked cookie) could redirect the account's email to one the attacker
            // controls with zero extra proof of identity.
            'current_password' => [Rule::requiredIf($changingEmail), 'nullable', 'current_password'],
        ]);

        if ($validator->fails()) {
            return redirect()->route('profile.edit', ['tab' => 'username'])
                ->withErrors($validator)->withInput();
        }

        $data = $validator->validated();

        $user->update(['name' => $data['name']]);

        // Kept in sync so the Info Personal tab (and the public-facing account page) never
        // shows a stale name — there's only one "name" field in the UI, on this tab.
        $user->person?->update(['name' => $data['name']]);

        if ($data['email'] === $user->email) {
            return redirect()->route('profile.edit', ['tab' => 'username'])
                ->with('status', 'Profil berhasil diperbarui.');
        }

        $user->forceFill(['pending_email' => $data['email']])->save();
        $this->generateAndSendOtp($user);

        return redirect()->route('profile.verify-email')
            ->with('status', "Nama diperbarui. Kode verifikasi telah dikirim ke {$data['email']} untuk mengonfirmasi email baru.");
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            // Same rationale as update()'s email-change check — a password change is the
            // most sensitive profile action there is, so it always needs re-confirming.
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', Password::min(8)->uncompromised(), 'confirmed'],
        ]);

        if ($validator->fails()) {
            return redirect()->route('profile.edit', ['tab' => 'password'])
                ->withErrors($validator);
        }

        $data = $validator->validated();

        $user->update(['password' => Hash::make($data['password'])]);

        return redirect()->route('profile.edit', ['tab' => 'password'])
            ->with('status', 'Kata sandi berhasil diperbarui.');
    }

    public function showVerifyEmailChange(Request $request)
    {
        $user = $request->user();

        if (! $user->pending_email) {
            return redirect()->route('profile.edit');
        }

        return view('profile.verify-email', ['pendingEmail' => $user->pending_email]);
    }

    public function verifyEmailChange(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->pending_email) {
            return redirect()->route('profile.edit');
        }

        $request->validate(['code' => ['required', 'string']]);

        if ($user->otp_code !== $request->input('code') || $user->otp_expires_at === null) {
            return back()->withErrors(['code' => __('auth.otp_invalid')]);
        }

        if ($user->otp_expires_at->isPast()) {
            return back()->withErrors(['code' => __('auth.otp_expired')]);
        }

        $user->forceFill([
            'email' => $user->pending_email,
            'pending_email' => null,
            'email_verified_at' => now(),
            'otp_code' => null,
            'otp_expires_at' => null,
        ])->save();

        return redirect()->route('profile.edit')->with('status', 'Email berhasil diperbarui dan diverifikasi.');
    }

    public function resendEmailChangeOtp(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->pending_email) {
            return redirect()->route('profile.edit');
        }

        $this->generateAndSendOtp($user);

        return back()->with('status', 'Kode OTP baru telah dikirim.');
    }

    public function cancelEmailChange(Request $request): RedirectResponse
    {
        $request->user()->forceFill([
            'pending_email' => null,
            'otp_code' => null,
            'otp_expires_at' => null,
        ])->save();

        return redirect()->route('profile.edit')->with('status', 'Perubahan email dibatalkan.');
    }

    private function generateAndSendOtp(User $user): void
    {
        $user->forceFill([
            'otp_code' => str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT),
            'otp_expires_at' => now()->addMinutes(10),
        ])->save();

        Mail::to($user->pending_email)->send(new OtpVerificationMail($user));
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FindsPersonCandidates;
use App\Mail\OtpVerificationMail;
use App\Models\Church;
use App\Models\Conference;
use App\Models\Person;
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
    use FindsPersonCandidates;

    /**
     * A plain member always has a linked Person by the time they reach here (created at OTP
     * verification). An admin/leadership account doesn't by default — but per the user's
     * explicit call, "Profil Saya" (this page, not the separate/unlinked akun-saya route) is
     * where they'd actually look for their own personal social accounts, so the very first
     * time they land here without one, this offers the same claim-an-existing-Person-or-create
     * flow a self-registering member gets (see LinkPersonController), rather than just hiding
     * the Media Sosial tab forever.
     */
    public function edit(Request $request)
    {
        $user = $request->user();
        $person = $user->person;

        if (! $person && $user->role !== null && $this->findPersonCandidates($user->name)->isNotEmpty()) {
            return redirect()->route('link-person');
        }

        if (! $person && $user->role !== null) {
            $person = Person::create(['user_id' => $user->id, 'name' => $user->name]);
        }

        // The Wilayah section (folded into the "Info Personal" tab alongside the Person fields
        // — see profile/edit.blade.php) reuses the Lengkapi Profil fields (see
        // CompleteProfileController) so anyone who skipped it (or never had it set — e.g.
        // promoted before ever filling it in) can still complete it later — writing directly to
        // $person's own union_id/conference_id/church_id (see resolveOrgScope()'s self-edit
        // branch, PersonController.php), never to a role-holder's own users.union_id/
        // conference_id/church_id (their separate, admin-assigned scope, untouched by this —
        // see UserAssignmentController::promote()). Open to every role, not just plain members:
        // an Admin/Pimpinan's own Person previously had no way at all to fix a blank/stale
        // Wilayah once promoted, since Kelola Akun's edit form for a linked Person always defers
        // back here regardless of role (see people/form.blade.php) — a dead end that left that
        // admin's own name undiscoverable under their actual Konferens/Gereja.
        $canEditRegion = $person !== null;

        $activeTab = in_array($request->query('tab'), ['personal', 'sosial', 'username', 'password'], true)
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
                ->with('status', __('entity.profile_updated'));
        }

        $user->forceFill(['pending_email' => $data['email']])->save();
        $this->generateAndSendOtp($user);

        return redirect()->route('profile.verify-email')
            ->with('status', __('entity.profile_name_updated_email_pending', ['email' => $data['email']]));
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
            ->with('status', __('entity.password_updated'));
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

        return redirect()->route('profile.edit')->with('status', __('entity.email_updated_verified'));
    }

    public function resendEmailChangeOtp(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->pending_email) {
            return redirect()->route('profile.edit');
        }

        $this->generateAndSendOtp($user);

        return back()->with('status', __('entity.otp_resent'));
    }

    public function cancelEmailChange(Request $request): RedirectResponse
    {
        $request->user()->forceFill([
            'pending_email' => null,
            'otp_code' => null,
            'otp_expires_at' => null,
        ])->save();

        return redirect()->route('profile.edit')->with('status', __('entity.email_change_cancelled'));
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

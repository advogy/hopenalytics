<?php

namespace App\Http\Controllers;

use App\Models\Church;
use App\Models\Conference;
use App\Models\Union;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CompleteProfileController extends Controller
{
    /**
     * A one-time interstitial shown right after email verification (see
     * RegisterController::verifyOtp()) — asks a brand-new member which Uni/Daerah/Gereja
     * they belong to, so regional admins can find them later on the Kelola Pengguna page
     * (see UserAssignmentController) instead of that list staying an unscoped global dump.
     * Skippable; a skip leaves union_id/conference_id/church_id null, which is exactly the
     * "nasional" (unscoped, nasional-admins-only) visibility bucket — but it can always be
     * completed later from the "Wilayah" tab on Profil Saya (see ProfileController::edit()),
     * which posts to the same store() below.
     */
    public function show(Request $request)
    {
        if ($request->user()->role !== null || $request->user()->profile_step_completed_at !== null) {
            return redirect()->route('akun-saya');
        }

        return view('auth.complete-profile', [
            'unions' => Union::where('is_active', true)->orderBy('name')->get(),
            'conferences' => Conference::where('is_active', true)->orderBy('name')->get(['id', 'union_id', 'name']),
            'churches' => Church::where('is_active', true)->orderBy('name')->get(['id', 'conference_id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        // union_id/conference_id/church_id double as an assigned admin's scope once they hold
        // a role (see UserAssignmentController::promote()) — this endpoint is only for a plain
        // member's own self-reported home region, so a role-holder must go through the proper
        // promote/revoke flow instead of quietly overwriting their assignment here.
        abort_if($request->user()->role !== null, 403);

        $data = $request->validate([
            'union_id' => ['required', 'integer', 'exists:unions,id'],
            'conference_id' => ['required', 'integer', 'exists:conferences,id'],
            'church_name' => ['required', 'string', 'max:255'],
        ]);

        $conference = Conference::findOrFail($data['conference_id']);
        abort_if($conference->union_id !== (int) $data['union_id'], 422);

        $church = $this->findOrCreateChurch($conference->id, $data['church_name']);

        $request->user()->forceFill([
            'union_id' => $data['union_id'],
            'conference_id' => $conference->id,
            'church_id' => $church->id,
            'profile_step_completed_at' => now(),
        ])->save();

        return redirect()->route('profile.edit', ['tab' => 'wilayah'])->with('status', __('entity.profile_completed'));
    }

    public function skip(Request $request): RedirectResponse
    {
        abort_if($request->user()->role !== null, 403);

        $request->user()->forceFill(['profile_step_completed_at' => now()])->save();

        return redirect()->route('akun-saya');
    }

    /**
     * Reuses an existing church by name within the chosen conference (case-insensitive, so
     * "GMAHK Kelapa Gading" and "gmahk kelapa gading" don't create duplicates) or creates a
     * new one — same slug-uniqueness pattern as ChurchController::store().
     */
    private function findOrCreateChurch(int $conferenceId, string $name): Church
    {
        $existing = Church::where('conference_id', $conferenceId)
            ->whereRaw('LOWER(name) = ?', [Str::lower(trim($name))])
            ->first();

        if ($existing) {
            return $existing;
        }

        $slug = Str::slug($name);
        $original = $slug;
        $i = 1;
        while (Church::where('slug', $slug)->exists()) {
            $slug = "{$original}-{$i}";
            $i++;
        }

        return Church::create([
            'conference_id' => $conferenceId,
            'name' => trim($name),
            'slug' => $slug,
            'is_active' => true,
        ]);
    }
}

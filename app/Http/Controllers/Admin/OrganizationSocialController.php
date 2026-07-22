<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SocialPlatform;
use App\Http\Controllers\Controller;
use App\Models\ChurchSocial;
use App\Models\Conference;
use App\Models\Institution;
use App\Models\Union;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Manage-accounts flow (index/create/store) for the three organization-level owner types —
 * a social account belonging directly to a Union, Conference, or Institution itself (e.g. an
 * official Instagram for "Uni Indonesia Kawasan Barat"), not any one church under it. Mirrors
 * ChurchSocialController/PersonSocialController's shape; edit/update/destroy stay centralized
 * there (see ChurchSocialController::edit()) since those are shared /socials/{social}/* routes
 * across all five owner types.
 */
class OrganizationSocialController extends Controller
{
    public function unionIndex(Union $union)
    {
        return view('admin.organization.social-list', [
            'owner' => $union,
            'backRoute' => ['admin.accounts.index', ['tab' => 'uni']],
            'createRoute' => ['admin.unions.socials.create', $union],
        ]);
    }

    public function unionCreate(Union $union)
    {
        return view('admin.organization.social-form', ['owner' => $union, 'social' => new ChurchSocial]);
    }

    public function unionStore(Request $request, Union $union): RedirectResponse
    {
        $data = $this->validated($request, 'union_id', $union->id, null);
        $social = $union->socials()->create($data);

        return redirect()->route('admin.unions.socials.index', $union)->with('status', "Akun {$social->display_handle} berhasil ditambahkan.");
    }

    public function conferenceIndex(Conference $conference)
    {
        return view('admin.organization.social-list', [
            'owner' => $conference,
            'backRoute' => ['admin.accounts.index', ['tab' => 'daerah']],
            'createRoute' => ['admin.conferences.socials.create', $conference],
        ]);
    }

    public function conferenceCreate(Conference $conference)
    {
        return view('admin.organization.social-form', ['owner' => $conference, 'social' => new ChurchSocial]);
    }

    public function conferenceStore(Request $request, Conference $conference): RedirectResponse
    {
        $data = $this->validated($request, 'conference_id', $conference->id, null);
        $social = $conference->socials()->create($data);

        return redirect()->route('admin.conferences.socials.index', $conference)->with('status', "Akun {$social->display_handle} berhasil ditambahkan.");
    }

    public function institutionIndex(Institution $institution)
    {
        return view('admin.organization.social-list', [
            'owner' => $institution,
            'backRoute' => ['admin.accounts.index', ['tab' => 'institusi']],
            'createRoute' => ['admin.institutions.socials.create', $institution],
        ]);
    }

    public function institutionCreate(Institution $institution)
    {
        return view('admin.organization.social-form', ['owner' => $institution, 'social' => new ChurchSocial]);
    }

    public function institutionStore(Request $request, Institution $institution): RedirectResponse
    {
        $data = $this->validated($request, 'institution_id', $institution->id, null);
        $social = $institution->socials()->create($data);

        return redirect()->route('admin.institutions.socials.index', $institution)->with('status', "Akun {$social->display_handle} berhasil ditambahkan.");
    }

    /**
     * Same shape as ChurchSocialController's private validated()/validatedOrganization() —
     * duplicated rather than shared (matching this codebase's existing PersonSocialController
     * precedent) since each owner type's controller only ever needs its own slice of it. Always
     * the fixed 'organisasi' category — no gereja/umum picker, these aren't church-owned.
     */
    private function validated(Request $request, string $ownerColumn, int $ownerId, ?int $ignoreId): array
    {
        $handle = ltrim((string) $request->input('handle'), '@');

        $data = $request->validate([
            'platform' => [
                'required',
                'string',
                'in:'.implode(',', array_column(SocialPlatform::cases(), 'value')),
                Rule::unique('church_socials', 'platform')
                    ->where(fn ($query) => $query->where($ownerColumn, $ownerId)->where('category', 'organisasi')->where('handle', $handle))
                    ->ignore($ignoreId),
            ],
            'handle' => ['required', 'string', 'max:255'],
            'profile_url' => ['nullable', 'url', 'max:2048'],
            'is_auto_fetch' => ['nullable', 'boolean'],
        ], [
            'platform.unique' => 'Akun dengan handle yang sama untuk platform ini sudah ada.',
        ]);

        $data['handle'] = ltrim($data['handle'], '@');
        $data['is_auto_fetch'] = $request->boolean('is_auto_fetch');
        $data['category'] = 'organisasi';

        return $data;
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\SocialPlatform;
use App\Models\Church;
use App\Models\ChurchSocial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ChurchSocialController extends Controller
{
    /**
     * The slim "manage accounts" list — reached only from Kelola Akun (see
     * admin.accounts.partials.row-actions), distinct from churches.show's read-only display
     * (reached from Analytics/dashboard/leaderboards), which has no add/edit affordances at all.
     */
    public function index(Church $church)
    {
        return view('churches.social-list', [
            'church' => $church,
            'socialsByCategory' => $church->socials->groupBy(fn ($social) => $social->category->value),
        ]);
    }

    public function create(Church $church)
    {
        return view('churches.social-form', ['church' => $church, 'social' => new ChurchSocial]);
    }

    public function store(Request $request, Church $church): RedirectResponse
    {
        $data = $this->validated($request, false, $church->id, null, null);

        $social = $church->socials()->create($data);

        // Deliberately no immediate fetch dispatch here, per the user's explicit call — a
        // newly-added account waits for the weekly schedule (see routes/console.php) or an
        // admin manually refreshing it, rather than pulling Apify data the moment it's typed
        // in (which was burning API calls on accounts that often turned out mistyped anyway).
        return redirect()->route('churches.socials.index', $church)->with('status', "Akun {$social->display_handle} berhasil ditambahkan.");
    }

    /**
     * Shared across all five owner types (church/person/union/conference/institution) via the
     * single /socials/{social}/edit route — church and person each get their own existing form
     * view (church's has a gereja/umum category picker; person's is fixed to 'personal'), the
     * other three share the generic admin.organization.social-form view (fixed to 'organisasi').
     */
    public function edit(ChurchSocial $social)
    {
        if ($social->person_id) {
            return view('people.social-form', ['person' => $social->person, 'social' => $social]);
        }

        if ($social->church_id) {
            return view('churches.social-form', ['church' => $social->church, 'social' => $social]);
        }

        return view('admin.organization.social-form', [
            'owner' => $social->union ?? $social->conference ?? $social->institution,
            'social' => $social,
        ]);
    }

    public function update(Request $request, ChurchSocial $social): RedirectResponse
    {
        $data = match (true) {
            $social->church_id !== null => $this->validated($request, false, $social->church_id, null, $social->id),
            $social->person_id !== null => $this->validated($request, true, null, $social->person_id, $social->id),
            $social->union_id !== null => $this->validatedOrganization($request, 'union_id', $social->union_id, $social->id),
            $social->conference_id !== null => $this->validatedOrganization($request, 'conference_id', $social->conference_id, $social->id),
            $social->institution_id !== null => $this->validatedOrganization($request, 'institution_id', $social->institution_id, $social->id),
        };

        $social->update($data);

        return redirect($this->manageLocation($request, $social))->with('status', "Akun {$social->display_handle} berhasil diperbarui.");
    }

    public function destroy(Request $request, ChurchSocial $social): RedirectResponse
    {
        $displayHandle = $social->display_handle;
        $location = $this->manageLocation($request, $social);

        $social->update(['is_active' => false]);

        return redirect($location)->with('status', "Akun {$displayHandle} dihapus.");
    }

    /**
     * A person-owned social belonging to the acting user's own linked Person goes back to
     * Profil Saya's Media Sosial tab (that's where they manage it now, in place — see
     * PersonSocialController::manageLocation()); every other owner type/case uses
     * ChurchSocial::manageRoute() as before.
     */
    private function manageLocation(Request $request, ChurchSocial $social): string
    {
        if ($social->person_id !== null) {
            return PersonSocialController::manageLocation($request, $social->person);
        }

        [$routeName, $routeParam] = $social->manageRoute();

        return route($routeName, $routeParam);
    }

    /**
     * A church/person can have more than one account per platform+category (e.g. an official
     * Instagram and a separate youth-ministry Instagram, both "gereja") — so the uniqueness
     * check only blocks the exact same handle being added twice, matching the
     * (church_id|person_id, platform, category, handle) unique index. Without this check at
     * all, a genuine accidental duplicate would still reach Eloquent's create()/update() and
     * bubble up as a raw UniqueConstraintViolationException instead of a normal validation
     * error. $churchId/$personId/$ignoreId scope it to the right owner and (on update) exclude
     * the row being edited.
     */
    private function validated(Request $request, bool $personal, ?int $churchId, ?int $personId, ?int $ignoreId): array
    {
        $category = $personal ? 'personal' : (string) $request->input('category');
        $handle = ltrim((string) $request->input('handle'), '@');

        $rules = [
            'platform' => [
                'required',
                'string',
                'in:'.implode(',', array_column(SocialPlatform::cases(), 'value')),
                Rule::unique('church_socials', 'platform')
                    ->where(function ($query) use ($churchId, $personId, $category, $handle) {
                        $query->where('category', $category)->where('handle', $handle);

                        if ($churchId !== null) {
                            $query->where('church_id', $churchId);
                        } else {
                            $query->where('person_id', $personId);
                        }
                    })
                    ->ignore($ignoreId),
            ],
            'handle' => ['required', 'string', 'max:255'],
            'profile_url' => ['nullable', 'url', 'max:2048'],
            'is_auto_fetch' => ['nullable', 'boolean'],
        ];

        if (! $personal) {
            $rules['category'] = ['required', 'string', 'in:gereja,umum'];
        }

        $data = $request->validate($rules, [
            'platform.unique' => 'Akun dengan handle yang sama untuk platform ini sudah ada.',
        ]);

        $data['handle'] = ltrim($data['handle'], '@');
        $data['is_auto_fetch'] = $request->boolean('is_auto_fetch');

        if ($personal) {
            $data['category'] = 'personal';
        }

        return $data;
    }

    /**
     * Same shape as validated() above, generalized for the three organization-owned owner
     * columns instead of hardcoded church_id/person_id — always the fixed 'organisasi' category
     * (no picker, unlike church's gereja/umum), same as OrganizationSocialController::validated()
     * uses for creating a new one.
     */
    private function validatedOrganization(Request $request, string $ownerColumn, int $ownerId, ?int $ignoreId): array
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

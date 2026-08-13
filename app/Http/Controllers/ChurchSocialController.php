<?php

namespace App\Http\Controllers;

use App\Enums\SocialPlatform;
use App\Models\Church;
use App\Models\ChurchSocial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ChurchSocialController extends Controller
{
    /**
     * Advisory "is this account already tracked" lookup, shared by every owner type's social
     * form (church/person/union/conference/institution) via one endpoint rather than five —
     * global, not owner-scoped, since the point is catching the SAME real-world account being
     * registered twice under two different owners. Not gated beyond plain auth: it only ever
     * surfaces a handle + which entity already has it, no more sensitive than the public
     * Directory page already showing every account. Exact match only (not fuzzy like
     * NameSimilarity) — an account handle either is or isn't the one already tracked.
     */
    public function similar(Request $request): JsonResponse
    {
        $platform = (string) $request->query('platform', '');
        $handle = Str::lower(ltrim((string) $request->query('handle', ''), '@'));
        $profileUrl = (string) $request->query('profile_url', '');
        $normalizedUrl = $profileUrl !== '' ? $this->normalizeProfileUrl($profileUrl) : null;

        if ($platform === '' || ($handle === '' && $normalizedUrl === null)) {
            return response()->json([]);
        }

        $matches = ChurchSocial::query()
            ->where('platform', $platform)
            ->where('is_active', true)
            ->when($request->query('exclude_id'), fn ($q, $id) => $q->whereKeyNot($id))
            ->where(function ($q) use ($handle, $normalizedUrl) {
                $q->when($handle !== '', fn ($q2) => $q2->whereRaw('LOWER(handle) = ?', [$handle]));
                $q->when($normalizedUrl !== null, fn ($q2) => $q2->orWhereRaw('LOWER(profile_url) LIKE ?', ["%{$normalizedUrl}%"]));
            })
            ->with(['church', 'person', 'institution', 'union', 'conference'])
            ->limit(5)
            ->get();

        return response()->json($matches->map(function (ChurchSocial $social) use ($request) {
            $owner = $social->church ?? $social->person ?? $social->institution ?? $social->union ?? $social->conference;

            return [
                'handle' => $social->display_handle,
                'owner' => $owner?->name,
                'url' => $this->manageLocation($request, $social),
            ];
        })->values());
    }

    /**
     * Strips scheme/www/trailing-slash/query-noise so two differently-formatted links to the
     * same page still match — EXCEPT facebook.com/profile.php links, where the numeric ?id=
     * is the only thing identifying which account it is (there's no vanity path). Blindly
     * stripping it like other query-string noise collapsed every profile.php?id=... URL down
     * to the same "facebook.com/profile.php" string, so any two unrelated accounts using that
     * URL format (the default for a Facebook page/profile with no claimed username) falsely
     * matched each other as duplicates.
     */
    private function normalizeProfileUrl(string $url): string
    {
        $url = Str::lower(trim($url));
        $url = preg_replace('#^https?://#', '', $url) ?? $url;
        $url = preg_replace('#^www\.#', '', $url) ?? $url;

        [$path, $query] = array_pad(explode('?', $url, 2), 2, '');
        $path = rtrim($path, '/');

        if ($query !== '') {
            parse_str($query, $params);

            if (! empty($params['id'])) {
                return $path.'?id='.$params['id'];
            }
        }

        return $path;
    }

    /**
     * Hard-blocks the same real-world account being registered a second time under ANY owner
     * (not just the one being edited) — per the user's explicit call ("kalau daftar akun media
     * sosial yg sama, maka tidak bisa"), upgraded from the advisory similar() lookup above to an
     * actual validation failure. Global on purpose: the existing platform.unique rule in
     * validated()/validatedOrganization() only ever catches the exact same owner+category+handle
     * combination, so a handle already tracked under a *different* church/person/institution/
     * union/conference would otherwise sail straight through. $ignoreId excludes the row being
     * edited so saving an unchanged account doesn't trip over itself. is_active = true only —
     * "delete" (see social-account-row.blade.php) just deactivates a row rather than removing
     * it, so a deactivated handle must be re-addable, not permanently blocked by its own ghost.
     */
    private function assertHandleNotAlreadyTracked(string $platform, string $handle, ?string $profileUrl, ?int $ignoreId): void
    {
        $normalizedHandle = Str::lower($handle);
        $normalizedUrl = $profileUrl ? $this->normalizeProfileUrl($profileUrl) : null;

        $duplicate = ChurchSocial::query()
            ->where('platform', $platform)
            ->where('is_active', true)
            ->when($ignoreId, fn ($q, $id) => $q->whereKeyNot($id))
            ->where(function ($q) use ($normalizedHandle, $normalizedUrl) {
                $q->whereRaw('LOWER(handle) = ?', [$normalizedHandle]);
                $q->when($normalizedUrl !== null, fn ($q2) => $q2->orWhereRaw('LOWER(profile_url) LIKE ?', ["%{$normalizedUrl}%"]));
            })
            ->with(['church', 'person', 'institution', 'union', 'conference'])
            ->first();

        if (! $duplicate) {
            return;
        }

        $owner = $duplicate->church ?? $duplicate->person ?? $duplicate->institution ?? $duplicate->union ?? $duplicate->conference;

        throw ValidationException::withMessages([
            'handle' => $owner
                ? __('entity.social_already_tracked', ['owner' => $owner->name])
                : __('entity.social_already_tracked_generic'),
        ]);
    }

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

        // A deactivated ("deleted" — see destroy() below) row already occupying this exact
        // slot gets reactivated instead of a new row being inserted: same row, so its history
        // (ChurchStat) picks back up rather than the "historical data stays saved" promise on
        // delete quietly breaking the moment the same handle is re-added. validated()'s
        // Rule::unique already lets this exact case through (is_active-filtered on create).
        $existing = $church->socials()
            ->where('platform', $data['platform'])->where('category', $data['category'])
            ->where('handle', $data['handle'])->where('is_active', false)
            ->first();

        if ($existing) {
            $existing->update($data + ['is_active' => true]);
            $social = $existing;
        } else {
            $social = $church->socials()->create($data);
        }

        // Deliberately no immediate fetch dispatch here, per the user's explicit call — a
        // newly-added account waits for the weekly schedule (see routes/console.php) or an
        // admin manually refreshing it, rather than pulling Apify data the moment it's typed
        // in (which was burning API calls on accounts that often turned out mistyped anyway).
        return redirect()->route('churches.socials.index', $church)->with('status', __('entity.social_created', ['handle' => $social->display_handle]));
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

        return redirect($this->manageLocation($request, $social))->with('status', __('entity.social_updated', ['handle' => $social->display_handle]));
    }

    public function destroy(Request $request, ChurchSocial $social): RedirectResponse
    {
        $displayHandle = $social->display_handle;
        $location = $this->manageLocation($request, $social);

        $social->update(['is_active' => false]);

        return redirect($location)->with('status', __('entity.social_deleted', ['handle' => $displayHandle]));
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
                // On CREATE ($ignoreId === null, from store()), a deactivated ("deleted") row
                // already occupying this slot doesn't count as a duplicate — store() reactivates
                // it instead of inserting a new row (see its own comment). On EDIT, keep
                // blocking on ANY match including inactive ones: there's no reactivate-and-merge
                // path for that rarer case, and letting it through would just trade this clean
                // message for a raw SQL 1062 crash against the DB's hard unique index on this
                // same (owner, category, handle) tuple.
                Rule::unique('church_socials', 'platform')
                    ->where(function ($query) use ($churchId, $personId, $category, $handle, $ignoreId) {
                        $query->where('category', $category)->where('handle', $handle);

                        if ($ignoreId === null) {
                            $query->where('is_active', true);
                        }

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

        $this->assertHandleNotAlreadyTracked($data['platform'], $data['handle'], $data['profile_url'] ?? null, $ignoreId);

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
                // See validated()'s same comment above — no is_active filter, the DB unique
                // index doesn't have one either.
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

        $this->assertHandleNotAlreadyTracked($data['platform'], $data['handle'], $data['profile_url'] ?? null, $ignoreId);

        return $data;
    }
}

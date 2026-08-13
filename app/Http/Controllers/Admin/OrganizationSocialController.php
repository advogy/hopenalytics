<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SocialPlatform;
use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\ChurchSocial;
use App\Models\Conference;
use App\Models\Institution;
use App\Models\Union;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
        $social = $this->createOrReactivate($union, $data);

        // Deliberately no immediate fetch dispatch here — see ChurchSocialController::store()'s
        // comment for why.
        return redirect()->route('admin.unions.socials.index', $union)->with('status', __('entity.social_created', ['handle' => $social->display_handle]));
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
        $social = $this->createOrReactivate($conference, $data);

        // Deliberately no immediate fetch dispatch here — see ChurchSocialController::store()'s
        // comment for why.
        return redirect()->route('admin.conferences.socials.index', $conference)->with('status', __('entity.social_created', ['handle' => $social->display_handle]));
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
        $social = $this->createOrReactivate($institution, $data);

        // Deliberately no immediate fetch dispatch here — see ChurchSocialController::store()'s
        // comment for why.
        return redirect()->route('admin.institutions.socials.index', $institution)->with('status', __('entity.social_created', ['handle' => $social->display_handle]));
    }

    /**
     * A deactivated ("deleted" — see ChurchSocialController::destroy()'s doc comment) row
     * already occupying this exact owner+platform+category+handle slot gets reactivated
     * instead of a new row being inserted, so its history (ChurchStat) picks back up rather
     * than the "historical data stays saved" promise on delete quietly breaking the moment the
     * same handle is re-added. validated()'s Rule::unique already lets this exact case through
     * (is_active-filtered, since this is always called from a store(), never edit).
     */
    private function createOrReactivate(Union|Conference|Institution $owner, array $data): ChurchSocial
    {
        $existing = $owner->socials()
            ->where('platform', $data['platform'])->where('category', $data['category'])
            ->where('handle', $data['handle'])->where('is_active', false)
            ->first();

        if ($existing) {
            $existing->update($data + ['is_active' => true]);

            return $existing;
        }

        return $owner->socials()->create($data);
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
                'in:'.implode(',', AppSetting::current()->enabledPlatformValues()),
                // is_active-filtered — this method is only ever used by the three store()
                // methods above (always create, $ignoreId always null), never edit, so a
                // deactivated ("deleted") row here doesn't count as a duplicate:
                // createOrReactivate() picks it back up instead of inserting a new row.
                Rule::unique('church_socials', 'platform')
                    ->where(fn ($query) => $query->where($ownerColumn, $ownerId)->where('category', 'organisasi')->where('handle', $handle)->where('is_active', true))
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

    /**
     * Same hard-block as ChurchSocialController::assertHandleNotAlreadyTracked() — duplicated
     * per this file's own established precedent (see validated()'s docblock) rather than
     * shared. Global across every owner type, not just the other two organization-level ones,
     * since the point is catching the same real-world account being registered twice anywhere.
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
}

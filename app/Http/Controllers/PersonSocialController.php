<?php

namespace App\Http\Controllers;

use App\Enums\SocialPlatform;
use App\Models\ChurchSocial;
use App\Models\Person;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PersonSocialController extends Controller
{
    /**
     * The slim "manage accounts" list — reached only from Kelola Akun's Personal tab (see
     * admin.accounts.partials.row-actions), distinct from people.show's read-only display
     * (reached from Analitik & Grafik Personal), which has no add/edit affordances at all.
     */
    public function index(Person $person)
    {
        return view('people.social-list', ['person' => $person]);
    }

    public function create(Person $person)
    {
        return view('people.social-form', ['person' => $person, 'social' => new ChurchSocial]);
    }

    public function store(Request $request, Person $person): RedirectResponse
    {
        $data = $this->validated($request, $person->id);

        $social = $person->socials()->create($data);

        // Deliberately no immediate fetch dispatch here — see ChurchSocialController::store()'s
        // comment for why.
        return redirect(self::manageLocation($request, $person))->with('status', __('entity.social_created', ['handle' => $social->display_handle]));
    }

    /**
     * A member managing their own linked Person lands back on Profil Saya's Media Sosial tab
     * (that's the whole tab's job now — edit/delete their own accounts in place, per the
     * user's explicit call); an admin managing someone else's lands on the shared
     * people.socials.index list instead. Shared with ChurchSocialController's update()/
     * destroy() for the same person-owned-social case.
     */
    public static function manageLocation(Request $request, Person $person): string
    {
        return $person->user_id === $request->user()->id
            ? route('profile.edit', ['tab' => 'sosial'])
            : route('people.socials.index', $person);
    }

    /**
     * A person can have more than one account per platform (e.g. two Instagram accounts) —
     * so the uniqueness check only blocks the exact same handle being added twice, matching
     * the (person_id, platform, category, handle) unique index. Without this check at all, a
     * genuine accidental duplicate would still reach Eloquent's create() and bubble up as a
     * raw UniqueConstraintViolationException instead of a normal validation error.
     */
    private function validated(Request $request, int $personId): array
    {
        $handle = ltrim((string) $request->input('handle'), '@');

        $data = $request->validate([
            'platform' => [
                'required',
                'string',
                'in:'.implode(',', array_column(SocialPlatform::cases(), 'value')),
                Rule::unique('church_socials', 'platform')
                    ->where(fn ($query) => $query->where('person_id', $personId)->where('category', 'personal')->where('handle', $handle)),
            ],
            'handle' => ['required', 'string', 'max:255'],
            'profile_url' => ['nullable', 'url', 'max:2048'],
            'is_auto_fetch' => ['nullable', 'boolean'],
        ], [
            'platform.unique' => 'Akun dengan handle yang sama untuk platform ini sudah ada.',
        ]);

        $data['handle'] = ltrim($data['handle'], '@');
        $data['is_auto_fetch'] = $request->boolean('is_auto_fetch');
        $data['category'] = 'personal';

        $this->assertHandleNotAlreadyTracked($data['platform'], $data['handle'], $data['profile_url'] ?? null);

        return $data;
    }

    /**
     * Same hard-block as ChurchSocialController::assertHandleNotAlreadyTracked() — duplicated
     * per this file's own established precedent (see validated()'s docblock) rather than
     * shared. Global across every owner type, not just other Person rows, since the point is
     * catching the same real-world account being registered twice anywhere in the system.
     */
    private function assertHandleNotAlreadyTracked(string $platform, string $handle, ?string $profileUrl): void
    {
        $normalizedHandle = Str::lower($handle);
        $normalizedUrl = $profileUrl ? $this->normalizeProfileUrl($profileUrl) : null;

        $duplicate = ChurchSocial::query()
            ->where('platform', $platform)
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

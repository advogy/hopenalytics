<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Controllers\Concerns\BuildsLeaderboards;
use App\Http\Controllers\Concerns\FindsOrCreatesChurch;
use App\Http\Controllers\Concerns\RedirectsToAccountsTab;
use App\Models\Church;
use App\Models\ChurchSocial;
use App\Models\Conference;
use App\Models\Person;
use App\Models\Union;
use App\Models\User;
use App\Services\GeocodingService;
use App\Support\AuditLogger;
use App\Support\NameSimilarity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class PersonController extends Controller
{
    use BuildsLeaderboards, FindsOrCreatesChurch, RedirectsToAccountsTab;

    /**
     * The list-with-search-and-pagination view ("Kelola Akun"'s Personal tab) lives in
     * Admin\AccountController::index() now — moved there so it renders alongside
     * Uni/Daerah/Gereja/Institusi on one page instead of a separate "Kelola Personal" page,
     * per the user's explicit call. The query logic itself moved verbatim; everything else on
     * this controller (show/create/store/edit/update/toggle/destroy/link) is unaffected.
     */
    public function show(Person $person)
    {
        $person->load(['socials' => fn ($query) => $query->where('is_active', true)->with('latestStat')]);

        $history = $person->socials->mapWithKeys(
            fn ($social) => [$social->id => $social->stats()->limit(30)->get()]
        );

        $growthScore = $this->growthScoreHistory($person->socials);

        // The dashboard-only widgets below (Tujuan Bersama, Pertumbuhan Wilayah, onboarding) are
        // only ever relevant to the Person's own linked account looking at their own page (see
        // MyAccountController::index() / akun-saya) — computed here so they never run the extra
        // queries when this same view renders for an admin looking at someone else's Person.
        $isOwnPerson = auth()->check() && $person->user_id === auth()->id();

        $goalRows = collect();
        $hasRegion = false;
        $regionLabel = null;
        $regionScoreHistory = [];
        $regionScoreMetrics = [];
        $regionScoreBreakdown = [];
        $regionScoreSampleCount = 0;
        $regionScoreSampleSum = 0;

        if ($isOwnPerson) {
            $hasRegion = (bool) ($person->union_id || $person->conference_id || $person->church_id);

            // "Tujuan Bersama" — reuses the exact same Goal-progress logic the admin "Ringkasan"
            // dashboard uses (BuildsLeaderboards::goalProgressRows()), scoped by the CURRENT
            // VIEWER's own role — for a role === null member that's already the isGlobal branch
            // (the one shared national goal), no extra logic needed here.
            // applyCeiling: true — same reasoning as ChurchDashboardController::index()'s own
            // calls: this widget has no region filter of its own to leave blank for "Global",
            // and the fair-share target above is deliberately divided down to exactly the
            // viewer's own ceiling, so widening "current" here without it would break that
            // comparison's math.
            $goalRows = $this->goalProgressRows(
                $this->activeSocials(applyCeiling: true),
                $this->activeSocialsInstitution(applyCeiling: true),
                $this->activeSocialsPersonal(applyCeiling: true),
                $this->activeSocialsOrganization(applyCeiling: true),
            );

            // "Pertumbuhan Wilayah Anda" — a different concept from the goal rows above: this is
            // scoped to the Person's own self-reported union/conference/church, not the viewer's
            // admin role, so an admin who also happens to attend a specific Gereja sees both
            // their admin-scope goal share AND their own congregation's growth, independently.
            $regionScope = $this->resolvePersonRegionSocials($person);

            if ($regionScope) {
                $regionLabel = $regionScope['label'];
                $regionGrowth = $this->growthScoreHistory($regionScope['socials']);
                $regionScoreHistory = $regionGrowth['history'];
                $regionScoreMetrics = $regionGrowth['metrics'];
                $regionScoreBreakdown = $regionGrowth['breakdown'];
                $regionScoreSampleCount = $regionGrowth['sampleCount'];
                $regionScoreSampleSum = $regionGrowth['sampleSum'];
            }
        }

        return view('people.show', [
            'person' => $person,
            'history' => $history,
            'scoreHistory' => $growthScore['history'],
            'scoreMetrics' => $growthScore['metrics'],
            'scoreBreakdown' => $growthScore['breakdown'],
            'scoreSampleCount' => $growthScore['sampleCount'],
            'scoreSampleSum' => $growthScore['sampleSum'],
            'isOwnPerson' => $isOwnPerson,
            'goalRows' => $goalRows,
            'hasRegion' => $hasRegion,
            'regionLabel' => $regionLabel,
            'regionScoreHistory' => $regionScoreHistory,
            'regionScoreMetrics' => $regionScoreMetrics,
            'regionScoreBreakdown' => $regionScoreBreakdown,
            'regionScoreSampleCount' => $regionScoreSampleCount,
            'regionScoreSampleSum' => $regionScoreSampleSum,
        ]);
    }

    /**
     * Most-specific-wins (church > conference > union) rollup of the ChurchSocial accounts
     * within a Person's own self-reported region — same whereHas('conference', ...)/
     * whereHas('church.conference', ...) idiom as Church::scopeVisibleTo(), just keyed off the
     * Person's own field instead of the viewer's admin scope. Null when the Person has no region
     * set, or whatever it points to was since deleted.
     */
    private function resolvePersonRegionSocials(Person $person): ?array
    {
        if ($person->church_id) {
            $church = Church::with('conference')->find($person->church_id);

            if (! $church) {
                return null;
            }

            return [
                'label' => $church->conference ? "{$church->name} ({$church->conference->name})" : $church->name,
                'socials' => ChurchSocial::where('church_id', $church->id)->where('is_active', true)->get(),
            ];
        }

        if ($person->conference_id) {
            $conference = Conference::with('union')->find($person->conference_id);

            if (! $conference) {
                return null;
            }

            return [
                'label' => $conference->union ? "{$conference->name} ({$conference->union->name})" : $conference->name,
                'socials' => ChurchSocial::whereHas('church', fn ($q) => $q->where('conference_id', $conference->id))->where('is_active', true)->get(),
            ];
        }

        if ($person->union_id) {
            $union = Union::find($person->union_id);

            if (! $union) {
                return null;
            }

            return [
                'label' => $union->name,
                'socials' => ChurchSocial::whereHas('church.conference', fn ($q) => $q->where('union_id', $union->id))->where('is_active', true)->get(),
            ];
        }

        return null;
    }

    public function create(Request $request)
    {
        return view('people.form', ['person' => new Person, 'linkableUsers' => collect()] + $this->personOrgScopeData($request));
    }

    /**
     * Advisory "did you mean" lookup for the name field — see NameSimilarity. Not gated behind
     * can:create,Person (a self-registered member can edit their own linked Person via
     * PersonPolicy's self-ownership carve-out, but never create() — see that policy), so this
     * stays open to plain auth like ChurchController::similar() for the same reason.
     */
    public function similar(Request $request): JsonResponse
    {
        $matches = NameSimilarity::findSimilar(
            (string) $request->query('name', ''),
            Person::where('is_active', true)
                ->when($request->query('exclude_id'), fn ($q, $id) => $q->whereKeyNot($id))
                ->get(['id', 'name', 'city']),
        );

        return response()->json($matches->map(fn ($m) => [
            'name' => $m['model']->name,
            'context' => $m['model']->city,
            'url' => route('people.edit', $m['model']),
        ])->values());
    }

    public function store(Request $request, GeocodingService $geocoding): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $data['latitude'] = $request->filled('latitude') ? (float) $data['latitude'] : null;
        $data['longitude'] = $request->filled('longitude') ? (float) $data['longitude'] : null;

        if ($data['latitude'] !== null && $data['longitude'] !== null) {
            $data['geocoded_at'] = null;
        } else {
            $this->applyGeocoding($data, $geocoding);
        }

        $data = array_merge($data, $this->resolveOrgScope($request, null, null, null, null));

        $person = Person::create($data);

        AuditLogger::log('person.created', $person, "Menambahkan Personal \"{$person->name}\".");

        // Straight to Kelola Akun Media Sosial rather than the (still-empty) profile page —
        // adding social accounts is always the very next thing an admin does right after
        // creating an entity, per the user's explicit call (see ChurchController::store()).
        return redirect()->route('people.socials.index', $person)->with('status', __('entity.person_created', ['name' => $person->name]));
    }

    public function edit(Request $request, Person $person)
    {
        return view('people.form', [
            'person' => $person,
            'linkableUsers' => Gate::allows('delete', $person) ? $this->linkableUsers() : collect(),
        ] + $this->personOrgScopeData($request));
    }

    public function update(Request $request, Person $person, GeocodingService $geocoding): RedirectResponse
    {
        // A self-editing member always lands back on Profil Saya's own "Info Personal" tab —
        // both on success and on a validation failure below — rather than the read-only
        // people.show page an admin gets redirected to instead; tab switching there is
        // client-side only (see partials/tab-script.blade.php), so the browser's own "previous
        // URL" can't be trusted to still say ?tab=personal by the time this request lands.
        $isOwnPerson = $person->user_id === $request->user()->id;
        $redirectTarget = $isOwnPerson ? route('profile.edit', ['tab' => 'personal']) : route('people.show', $person);

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        if ($validator->fails()) {
            return redirect($redirectTarget)->withErrors($validator)->withInput();
        }

        $data = $validator->validated();

        $data['latitude'] = $request->filled('latitude') ? (float) $data['latitude'] : null;
        $data['longitude'] = $request->filled('longitude') ? (float) $data['longitude'] : null;

        $coordsManuallyChanged = $data['latitude'] !== $person->latitude || $data['longitude'] !== $person->longitude;

        if ($coordsManuallyChanged) {
            $data['geocoded_at'] = null;
        } elseif ($data['city'] !== $person->city || $data['country'] !== $person->country) {
            $this->applyGeocoding($data, $geocoding);
        }

        $data = array_merge($data, $this->resolveOrgScope($request, $person->union_id, $person->conference_id, $person->church_id, $person->user_id));

        $person->update($data);

        // Skip logging when someone edits their own linked Person — that's a self-service
        // profile edit (Profil Saya's Info Personal tab), not the "admin acts on the system"
        // kind of thing this log is for, even when the actor happens to also hold a role
        // elsewhere. Editing anyone else's Person always implies a real non-read-only role
        // already (see PersonPolicy::update()), so no extra role check is needed here.
        if (! $isOwnPerson) {
            AuditLogger::log('person.updated', $person, "Memperbarui data Personal \"{$person->name}\".");
        }

        return redirect($redirectTarget)->with('status', __('entity.person_updated', ['name' => $person->name]));
    }

    /**
     * Never trust a client-submitted org assignment (mirrors ChurchController's
     * resolveConferenceId() / Admin\InstitutionController's resolveRegion()). A Person may be
     * independent (all three null, decision #2), scoped to a Union, or scoped to a
     * Conference — never union_id and conference_id both at once. The scope-picker UI (see
     * personOrgScopeData()) submits union_id/conference_id accordingly; every admin-facing
     * branch below re-validates whatever's submitted against the actor's own reach rather than
     * trusting it outright — except the self-report branch (see resolveSelfReportedScope()),
     * the one case where there's no "actor's own scope" to check against.
     */
    private function resolveOrgScope(Request $request, ?int $currentUnionId, ?int $currentConferenceId, ?int $currentChurchId, ?int $personUserId): array
    {
        $user = $request->user();

        // A member editing their own linked Person — Profil Saya's "Info Personal" tab folds
        // the Wilayah section into this very same form/submit (see profile/edit.blade.php), so
        // this is the one branch that trusts the submission outright: free choice of any Uni/
        // Daerah/Gereja, same as the original standalone Lengkapi Profil interstitial always
        // allowed (see CompleteProfileController::store()) — there's no "actor's own scope" to
        // validate a member's own self-report against.
        if ($personUserId !== null && $personUserId === $user->id) {
            return $this->resolveSelfReportedScope($request, $currentUnionId, $currentConferenceId, $currentChurchId);
        }

        // Linked to a DIFFERENT user — that user's own Wilayah section is the only place this
        // Person's scope is ever edited, so nobody else (admin included) changes it here.
        if ($personUserId !== null) {
            return ['union_id' => $currentUnionId, 'conference_id' => $currentConferenceId, 'church_id' => $currentChurchId];
        }

        // A self-registered member editing an unlinked Person holds no role and can never
        // assign its org scope (decision #2) — in practice unreachable, since PersonPolicy
        // never lets a role === null actor near a Person that isn't their own, but stays as a
        // defensive fallback.
        if ($user->role === null) {
            return ['union_id' => $currentUnionId, 'conference_id' => $currentConferenceId, 'church_id' => $currentChurchId];
        }

        $submittedUnionId = $request->filled('union_id') ? (int) $request->input('union_id') : null;
        $submittedConferenceId = $request->filled('conference_id') ? (int) $request->input('conference_id') : null;

        return match ($user->role->level()) {
            'daerah' => ['union_id' => null, 'conference_id' => $user->conference_id, 'church_id' => $currentChurchId],
            // A uni-level admin can only ever narrow a Person down to a Daerah within their own
            // Union, or leave it at their own Union — never past it, and never back out to fully
            // independent (matches Admin\InstitutionController::resolveRegion()'s uni arm, which
            // trusts $user->union_id directly rather than requiring the form to round-trip it).
            'uni' => $submittedConferenceId && Conference::whereKey($submittedConferenceId)->where('union_id', $user->union_id)->exists()
                ? ['union_id' => null, 'conference_id' => $submittedConferenceId, 'church_id' => $currentChurchId]
                : ['union_id' => $user->union_id, 'conference_id' => null, 'church_id' => $currentChurchId],
            'divisi' => match (true) {
                $submittedConferenceId && Conference::whereKey($submittedConferenceId)->whereHas('union', fn ($q) => $q->where('division_id', $user->division_id))->exists()
                    => ['union_id' => null, 'conference_id' => $submittedConferenceId, 'church_id' => $currentChurchId],
                $submittedUnionId && Union::whereKey($submittedUnionId)->where('division_id', $user->division_id)->exists()
                    => ['union_id' => $submittedUnionId, 'conference_id' => null, 'church_id' => $currentChurchId],
                default => ['union_id' => $currentUnionId, 'conference_id' => $currentConferenceId, 'church_id' => $currentChurchId],
            },
            'nasional' => match (true) {
                $submittedConferenceId && Conference::whereKey($submittedConferenceId)->whereIn('union_id', $user->assignedUnionIds())->exists()
                    => ['union_id' => null, 'conference_id' => $submittedConferenceId, 'church_id' => $currentChurchId],
                $submittedUnionId && in_array($submittedUnionId, $user->assignedUnionIds(), true)
                    => ['union_id' => $submittedUnionId, 'conference_id' => null, 'church_id' => $currentChurchId],
                default => ['union_id' => $currentUnionId, 'conference_id' => $currentConferenceId, 'church_id' => $currentChurchId],
            },
            default => $submittedUnionId || $submittedConferenceId
                ? ['union_id' => $submittedUnionId, 'conference_id' => $submittedConferenceId, 'church_id' => $currentChurchId]
                : ['union_id' => $currentUnionId, 'conference_id' => $currentConferenceId, 'church_id' => $currentChurchId],
        };
    }

    /**
     * The one resolveOrgScope() branch that trusts the submission outright — a member editing
     * their own linked Person's Wilayah section has no "actor's own scope" to be validated
     * against, unlike every admin-facing branch above. Falls back to preserving whatever was
     * already there if union_id/conference_id are missing or don't actually pair up (a
     * Conference always belongs to exactly one Union) — the same "never silently clear on a bad
     * submission" caution every branch above already follows, rather than treating an
     * incomplete/malformed request as "clear it back to independent".
     */
    private function resolveSelfReportedScope(Request $request, ?int $currentUnionId, ?int $currentConferenceId, ?int $currentChurchId): array
    {
        $unchanged = ['union_id' => $currentUnionId, 'conference_id' => $currentConferenceId, 'church_id' => $currentChurchId];

        if (! $request->filled('union_id') || ! $request->filled('conference_id')) {
            return $unchanged;
        }

        $conference = Conference::find((int) $request->input('conference_id'));

        if (! $conference || $conference->union_id !== (int) $request->input('union_id')) {
            return $unchanged;
        }

        // findExistingChurchOrSuggestAdmin(), not findOrCreateChurch() — same reasoning as
        // CompleteProfileController::store(): a genuinely new church name here creates a pending
        // AdminSuggestion instead of an unreviewed Church, so this path can't be used to bypass
        // that review by "editing Wilayah again" instead of going through Lengkapi Profil. A
        // null result (blank field, OR a new-name suggestion was just filed) falls through to
        // $currentChurchId below unchanged — a pending suggestion doesn't clear whatever church
        // this Person already had linked; that only changes once the suggestion is reviewed.
        $church = $request->filled('church_name')
            ? $this->findExistingChurchOrSuggestAdmin($request->user(), $conference->id, (string) $request->input('church_name'))
            : null;

        return ['union_id' => null, 'conference_id' => $conference->id, 'church_id' => $church?->id ?? $currentChurchId];
    }

    /**
     * Which Uni/Daerah picker (if any) the form shows depends on the actor's own level, mirroring
     * resolveOrgScope() above and Admin\InstitutionController::regionPickerData()'s shape exactly
     * (Person and Institution share the same independent/Union/Conference tri-state) —
     * nasional/global/divisi get the full Uni → Daerah cascade (both optional, for a fully
     * independent Person), admin_uni gets a flat Daerah list scoped to their own union (read-only
     * "own Union" label alongside it, no picker for the Union itself), admin_daerah and everyone
     * else (gereja/institusi-level, or a self-editing member) get no picker at all — just the
     * current assignment as read-only text.
     */
    private function personOrgScopeData(Request $request): array
    {
        $user = $request->user();

        if ($user->role?->hasGlobalAccess() || $user->role?->level() === 'global') {
            return [
                'canPickUnion' => true,
                'ownUnion' => null,
                'unions' => Union::where('is_active', true)->orderBy('name')->get(),
                'conferences' => Conference::where('is_active', true)->orderBy('name')->get(['id', 'union_id', 'name']),
            ];
        }

        if ($user->role?->level() === 'nasional') {
            $unionIds = $user->assignedUnionIds();

            return [
                'canPickUnion' => true,
                'ownUnion' => null,
                'unions' => Union::whereIn('id', $unionIds)->orderBy('name')->get(),
                'conferences' => Conference::whereIn('union_id', $unionIds)->where('is_active', true)->orderBy('name')->get(['id', 'union_id', 'name']),
            ];
        }

        if ($user->role?->level() === 'divisi') {
            return [
                'canPickUnion' => true,
                'ownUnion' => null,
                'unions' => Union::where('division_id', $user->division_id)->where('is_active', true)->orderBy('name')->get(),
                'conferences' => Conference::whereHas('union', fn ($q) => $q->where('division_id', $user->division_id))->where('is_active', true)->orderBy('name')->get(['id', 'union_id', 'name']),
            ];
        }

        if ($user->role?->level() === 'uni') {
            return [
                'canPickUnion' => false,
                'ownUnion' => Union::find($user->union_id),
                'unions' => collect(),
                'conferences' => Conference::where('union_id', $user->union_id)->where('is_active', true)->orderBy('name')->get(['id', 'union_id', 'name']),
            ];
        }

        return ['canPickUnion' => false, 'ownUnion' => null, 'unions' => collect(), 'conferences' => collect()];
    }

    /**
     * No city AND country, no attempt — see ChurchController::applyGeocoding()'s doc comment
     * (no name-based fallback exists anywhere anymore either — see
     * GeocodingService::placeQueryFor()).
     */
    private function applyGeocoding(array &$data, GeocodingService $geocoding): void
    {
        if (empty($data['city']) || empty($data['country'])) {
            return;
        }

        $result = $geocoding->geocode($geocoding->placeQueryFor($data['city'], $data['country']));

        if ($result) {
            $data['latitude'] = $result['lat'];
            $data['longitude'] = $result['lon'];
            $data['geocoded_at'] = now();
        }
    }

    public function toggleActive(Request $request, Person $person): RedirectResponse
    {
        $person->update(['is_active' => ! $person->is_active]);
        $status = $person->is_active ? __('accounts.status_reactivated') : __('accounts.status_deactivated');

        AuditLogger::log(
            $person->is_active ? 'person.activated' : 'person.deactivated',
            $person,
            ($person->is_active ? 'Mengaktifkan kembali' : 'Menonaktifkan')." Personal \"{$person->name}\"."
        );

        return $this->redirectToAccountsTab($request, 'personal')->with('status', __('entity.person_status_changed', ['name' => $person->name, 'status' => $status]));
    }

    /**
     * A real, permanent delete — distinct from toggleActive() above. Unlike Union/Conference/
     * Church/Institution, nothing restricts on a Person's deletion (church_socials.person_id
     * cascades, people.user_id is nullOnDelete from the User side only), so there's no
     * dependents guard needed — the confirm prompt just needs to be honest that this also
     * wipes their tracked social accounts and history, which toggleActive() never touches.
     */
    public function destroy(Request $request, Person $person): RedirectResponse
    {
        $name = $person->name;
        $person->delete();

        AuditLogger::log('person.deleted', $person, "Menghapus permanen Personal \"{$name}\".");

        return $this->redirectToAccountsTab($request, 'personal')->with('status', __('entity.person_deleted', ['name' => $name]));
    }

    /**
     * Manual counterpart to the self-registration name-matching in
     * RegisterController::verifyOtp()/LinkPersonController — for when a member registers
     * under a name too different to auto-match, or registered before the admin ever created
     * this Person. Only offers users with no Person of their own yet (people.user_id is
     * unique — a User can never hold two).
     */
    public function linkUser(Request $request, Person $person): RedirectResponse
    {
        $data = $request->validate(['user_id' => ['required', 'integer']]);

        $user = User::whereKey($data['user_id'])->whereDoesntHave('person')
            ->where(fn ($query) => $query->whereNull('role')->orWhere('role', '!=', UserRole::SuperAdmin))
            ->first();

        abort_if(! $user, 422, __('entity.user_not_found_or_already_linked'));

        // Whatever region this Person already had (admin-set, or blank) stays as-is — linking
        // doesn't move any data around anymore, since Person is already the sole place a
        // member's own region lives (see CompleteProfileController::store()); the newly-linked
        // user can now change it themselves going forward, from their own Wilayah section.
        $person->update(['user_id' => $user->id]);

        return redirect()->route('people.edit', $person)->with('status', __('entity.person_linked', ['name' => $person->name, 'user' => $user->name]));
    }

    public function unlinkUser(Person $person): RedirectResponse
    {
        $person->update(['user_id' => null]);

        return redirect()->route('people.edit', $person)->with('status', __('entity.person_unlinked', ['name' => $person->name]));
    }

    /**
     * Superadmin accounts are excluded — they're system-level operators, not the kind of
     * "person" a directory entry represents, per the user's explicit call. SQL's != is NULL-
     * unsafe (it silently drops NULL-role rows too, not just superadmin ones), so a plain
     * member's role === null is explicitly kept rather than accidentally filtered out.
     */
    private function linkableUsers()
    {
        return User::whereDoesntHave('person')
            ->where(fn ($query) => $query->whereNull('role')->orWhere('role', '!=', UserRole::SuperAdmin))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }
}

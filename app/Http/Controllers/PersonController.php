<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Controllers\Concerns\BuildsLeaderboards;
use App\Models\Conference;
use App\Models\Person;
use App\Models\User;
use App\Services\GeocodingService;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PersonController extends Controller
{
    use BuildsLeaderboards;

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

        $scoreHistory = $this->growthScoreHistory($person->socials);

        return view('people.show', [
            'person' => $person,
            'history' => $history,
            'scoreHistory' => $scoreHistory,
        ]);
    }

    public function create()
    {
        return view('people.form', ['person' => new Person, 'linkableUsers' => collect()]);
    }

    public function store(Request $request, GeocodingService $geocoding): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
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

        $data = array_merge($data, $this->resolveOrgScope($request, null, null));

        $person = Person::create($data);

        AuditLogger::log('person.created', $person, "Menambahkan Personal \"{$person->name}\".");

        return redirect()->route('people.show', $person)->with('status', "\"{$person->name}\" berhasil ditambahkan.");
    }

    public function edit(Request $request, Person $person)
    {
        return view('people.form', [
            'person' => $person,
            'linkableUsers' => Gate::allows('delete', $person) ? $this->linkableUsers() : collect(),
        ]);
    }

    public function update(Request $request, Person $person, GeocodingService $geocoding): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $data['latitude'] = $request->filled('latitude') ? (float) $data['latitude'] : null;
        $data['longitude'] = $request->filled('longitude') ? (float) $data['longitude'] : null;

        $coordsManuallyChanged = $data['latitude'] !== $person->latitude || $data['longitude'] !== $person->longitude;

        if ($coordsManuallyChanged) {
            $data['geocoded_at'] = null;
        } elseif ($data['city'] !== $person->city) {
            $this->applyGeocoding($data, $geocoding);
        }

        $data = array_merge($data, $this->resolveOrgScope($request, $person->union_id, $person->conference_id));

        // Skip logging when someone edits their own linked Person — that's a self-service
        // profile edit (Profil Saya's Info Personal tab), not the "admin acts on the system"
        // kind of thing this log is for, even when the actor happens to also hold a role
        // elsewhere. Editing anyone else's Person always implies a real non-read-only role
        // already (see PersonPolicy::update()), so no extra role check is needed here.
        if ($person->user_id !== $request->user()->id) {
            AuditLogger::log('person.updated', $person, "Memperbarui data Personal \"{$person->name}\".");
        }

        return redirect()->route('people.show', $person)->with('status', "\"{$person->name}\" berhasil diperbarui.");
    }

    /**
     * Never trust a client-submitted org assignment (mirrors ChurchController's
     * resolveConferenceId()). A Person may be independent (both null, decision #2), scoped
     * to a Union, or scoped to a Conference — never both at once. There's no scope-picker UI
     * yet (Phase 5), so today this only ever preserves the current values; it exists so that
     * UI can be added later without reopening this write path.
     */
    private function resolveOrgScope(Request $request, ?int $currentUnionId, ?int $currentConferenceId): array
    {
        $user = $request->user();

        // A self-registered member editing their own Person (PersonPolicy's self-ownership
        // carve-out) holds no role and can never assign an org scope — they can only ever
        // stay independent (decision #2).
        if ($user->role === null) {
            return ['union_id' => $currentUnionId, 'conference_id' => $currentConferenceId];
        }

        $submittedUnionId = $request->filled('union_id') ? (int) $request->input('union_id') : null;
        $submittedConferenceId = $request->filled('conference_id') ? (int) $request->input('conference_id') : null;

        return match ($user->role->level()) {
            'daerah' => ['union_id' => null, 'conference_id' => $user->conference_id],
            'uni' => match (true) {
                $submittedConferenceId && Conference::whereKey($submittedConferenceId)->where('union_id', $user->union_id)->exists()
                    => ['union_id' => null, 'conference_id' => $submittedConferenceId],
                $submittedUnionId === $user->union_id
                    => ['union_id' => $user->union_id, 'conference_id' => null],
                default => ['union_id' => $currentUnionId, 'conference_id' => $currentConferenceId],
            },
            default => $submittedUnionId || $submittedConferenceId
                ? ['union_id' => $submittedUnionId, 'conference_id' => $submittedConferenceId]
                : ['union_id' => $currentUnionId, 'conference_id' => $currentConferenceId],
        };
    }

    /**
     * Unlike churches, a person's name isn't a geocodable place, so there's no
     * name-based fallback — geocoding only runs when a city is actually given.
     */
    private function applyGeocoding(array &$data, GeocodingService $geocoding): void
    {
        if (empty($data['city'])) {
            return;
        }

        $result = $geocoding->geocode("{$data['city']}, Indonesia");

        if ($result) {
            $data['latitude'] = $result['lat'];
            $data['longitude'] = $result['lon'];
            $data['geocoded_at'] = now();
        }
    }

    public function toggleActive(Person $person): RedirectResponse
    {
        $person->update(['is_active' => ! $person->is_active]);
        $status = $person->is_active ? 'diaktifkan kembali' : 'dinonaktifkan';

        AuditLogger::log(
            $person->is_active ? 'person.activated' : 'person.deactivated',
            $person,
            ($person->is_active ? 'Mengaktifkan kembali' : 'Menonaktifkan')." Personal \"{$person->name}\"."
        );

        return redirect()->route('admin.accounts.index', ['tab' => 'personal'])->with('status', "\"{$person->name}\" telah {$status}.");
    }

    /**
     * A real, permanent delete — distinct from toggleActive() above. Unlike Union/Conference/
     * Church/Institution, nothing restricts on a Person's deletion (church_socials.person_id
     * cascades, people.user_id is nullOnDelete from the User side only), so there's no
     * dependents guard needed — the confirm prompt just needs to be honest that this also
     * wipes their tracked social accounts and history, which toggleActive() never touches.
     */
    public function destroy(Person $person): RedirectResponse
    {
        $name = $person->name;
        $person->delete();

        AuditLogger::log('person.deleted', $person, "Menghapus permanen Personal \"{$name}\".");

        return redirect()->route('admin.accounts.index', ['tab' => 'personal'])->with('status', "\"{$name}\" berhasil dihapus.");
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

        abort_if(! $user, 422, 'Pengguna tidak ditemukan atau sudah tertaut ke Personal lain.');

        $person->update(['user_id' => $user->id]);

        return redirect()->route('people.edit', $person)->with('status', "\"{$person->name}\" berhasil ditautkan ke akun \"{$user->name}\".");
    }

    public function unlinkUser(Person $person): RedirectResponse
    {
        $person->update(['user_id' => null]);

        return redirect()->route('people.edit', $person)->with('status', "Tautan akun login untuk \"{$person->name}\" telah dilepas.");
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

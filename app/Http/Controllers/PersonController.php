<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsLeaderboards;
use App\Models\Conference;
use App\Models\Person;
use App\Services\GeocodingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PersonController extends Controller
{
    use BuildsLeaderboards;

    /**
     * "Kelola Personal" — mirrors Kelola Organisasi's list-with-search-and-pagination shape,
     * scoped to the actor's region like the directory (unlike Kelola Organisasi, which is
     * nasional-only). Shows inactive people too (unlike the public directory), since this is
     * the only place an admin can reactivate one.
     */
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search'));

        $people = Person::with(['union', 'conference.union'])
            ->withCount('socials')
            ->visibleTo($request->user())
            ->when($search, fn ($q) => $q->where(fn ($q2) => $q2
                ->where('name', 'like', "%{$search}%")
                ->orWhere('city', 'like', "%{$search}%"),
            ))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.people.index', [
            'people' => $people,
            'search' => $search,
        ]);
    }

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
        return view('people.form', ['person' => new Person]);
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

        return redirect()->route('people.show', $person)->with('status', "\"{$person->name}\" berhasil ditambahkan.");
    }

    public function edit(Person $person)
    {
        return view('people.form', ['person' => $person]);
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

        $person->update($data);

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

        return redirect()->route('admin.people.index')->with('status', "\"{$person->name}\" telah {$status}.");
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

        return redirect()->route('admin.people.index')->with('status', "\"{$name}\" berhasil dihapus.");
    }
}

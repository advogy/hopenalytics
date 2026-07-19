<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsLeaderboards;
use App\Models\Person;
use App\Services\GeocodingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PersonController extends Controller
{
    use BuildsLeaderboards;

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

        $data['latitude'] = $data['latitude'] !== null ? (float) $data['latitude'] : null;
        $data['longitude'] = $data['longitude'] !== null ? (float) $data['longitude'] : null;

        if ($data['latitude'] !== null && $data['longitude'] !== null) {
            $data['geocoded_at'] = null;
        } else {
            $this->applyGeocoding($data, $geocoding);
        }

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

        $data['latitude'] = $data['latitude'] !== null ? (float) $data['latitude'] : null;
        $data['longitude'] = $data['longitude'] !== null ? (float) $data['longitude'] : null;

        $coordsManuallyChanged = $data['latitude'] !== $person->latitude || $data['longitude'] !== $person->longitude;

        if ($coordsManuallyChanged) {
            $data['geocoded_at'] = null;
        } elseif ($data['city'] !== $person->city) {
            $this->applyGeocoding($data, $geocoding);
        }

        $person->update($data);

        return redirect()->route('people.show', $person)->with('status', "\"{$person->name}\" berhasil diperbarui.");
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

    public function destroy(Person $person): RedirectResponse
    {
        $person->update(['is_active' => false]);

        return redirect()->route('churches.directory')->with('status', "\"{$person->name}\" dinonaktifkan.");
    }
}

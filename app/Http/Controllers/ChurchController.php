<?php

namespace App\Http\Controllers;

use App\Models\Church;
use App\Services\GeocodingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ChurchController extends Controller
{
    public function create()
    {
        return view('churches.form', ['church' => new Church]);
    }

    public function store(Request $request, GeocodingService $geocoding): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'logo_url' => ['nullable', 'url', 'max:2048'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $data['latitude'] = $data['latitude'] !== null ? (float) $data['latitude'] : null;
        $data['longitude'] = $data['longitude'] !== null ? (float) $data['longitude'] : null;

        $slug = Str::slug($data['name']);
        $original = $slug;
        $i = 1;
        while (Church::where('slug', $slug)->exists()) {
            $slug = "{$original}-{$i}";
            $i++;
        }
        $data['slug'] = $slug;

        if ($data['latitude'] !== null && $data['longitude'] !== null) {
            $data['geocoded_at'] = null;
        } else {
            $this->applyGeocoding($data, $geocoding);
        }

        $church = Church::create($data);

        return redirect()->route('churches.show', $church)->with('status', "Gereja \"{$church->name}\" berhasil ditambahkan.");
    }

    public function edit(Church $church)
    {
        return view('churches.form', ['church' => $church]);
    }

    public function update(Request $request, Church $church, GeocodingService $geocoding): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'logo_url' => ['nullable', 'url', 'max:2048'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $data['latitude'] = $data['latitude'] !== null ? (float) $data['latitude'] : null;
        $data['longitude'] = $data['longitude'] !== null ? (float) $data['longitude'] : null;

        $coordsManuallyChanged = $data['latitude'] !== $church->latitude || $data['longitude'] !== $church->longitude;

        if ($coordsManuallyChanged) {
            $data['geocoded_at'] = null;
        } elseif ($data['city'] !== $church->city) {
            $this->applyGeocoding($data, $geocoding);
        }

        $church->update($data);

        return redirect()->route('churches.show', $church)->with('status', "Gereja \"{$church->name}\" berhasil diperbarui.");
    }

    private function applyGeocoding(array &$data, GeocodingService $geocoding): void
    {
        $query = $geocoding->placeQueryFor($data['city'] ?? null, $data['name']);
        $result = $geocoding->geocode($query);

        if ($result) {
            $data['latitude'] = $result['lat'];
            $data['longitude'] = $result['lon'];
            $data['geocoded_at'] = now();
        }
    }

    public function destroy(Church $church): RedirectResponse
    {
        $church->update(['is_active' => false]);

        return redirect()->route('churches.index')->with('status', "Gereja \"{$church->name}\" dinonaktifkan.");
    }
}

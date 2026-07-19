<?php

namespace App\Http\Controllers;

use App\Enums\SocialPlatform;
use App\Models\Church;
use App\Models\ChurchSocial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ChurchSocialController extends Controller
{
    public function create(Church $church)
    {
        return view('churches.social-form', ['church' => $church, 'social' => new ChurchSocial]);
    }

    public function store(Request $request, Church $church): RedirectResponse
    {
        $data = $this->validated($request);

        $social = $church->socials()->create($data);

        return redirect()->route('churches.show', $church)->with('status', "Akun {$social->display_handle} berhasil ditambahkan.");
    }

    public function edit(ChurchSocial $social)
    {
        if ($social->person_id) {
            return view('people.social-form', ['person' => $social->person, 'social' => $social]);
        }

        return view('churches.social-form', ['church' => $social->church, 'social' => $social]);
    }

    public function update(Request $request, ChurchSocial $social): RedirectResponse
    {
        $personal = (bool) $social->person_id;
        $data = $this->validated($request, $personal);

        $social->update($data);

        return $personal
            ? redirect()->route('people.show', $social->person)->with('status', "Akun {$social->display_handle} berhasil diperbarui.")
            : redirect()->route('churches.show', $social->church)->with('status', "Akun {$social->display_handle} berhasil diperbarui.");
    }

    public function destroy(ChurchSocial $social): RedirectResponse
    {
        $church = $social->church;
        $person = $social->person;
        $displayHandle = $social->display_handle;
        $social->update(['is_active' => false]);

        return $church
            ? redirect()->route('churches.show', $church)->with('status', "Akun {$displayHandle} dihapus.")
            : redirect()->route('people.show', $person)->with('status', "Akun {$displayHandle} dihapus.");
    }

    private function validated(Request $request, bool $personal = false): array
    {
        $rules = [
            'platform' => ['required', 'string', 'in:'.implode(',', array_column(SocialPlatform::cases(), 'value'))],
            'handle' => ['required', 'string', 'max:255'],
            'profile_url' => ['nullable', 'url', 'max:2048'],
            'is_auto_fetch' => ['nullable', 'boolean'],
        ];

        if (! $personal) {
            $rules['category'] = ['required', 'string', 'in:gereja,umum'];
        }

        $data = $request->validate($rules);

        $data['handle'] = ltrim($data['handle'], '@');
        $data['is_auto_fetch'] = $request->boolean('is_auto_fetch');

        if ($personal) {
            $data['category'] = 'personal';
        }

        return $data;
    }
}

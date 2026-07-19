<?php

namespace App\Http\Controllers;

use App\Enums\SocialPlatform;
use App\Models\ChurchSocial;
use App\Models\Person;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PersonSocialController extends Controller
{
    public function create(Person $person)
    {
        return view('people.social-form', ['person' => $person, 'social' => new ChurchSocial]);
    }

    public function store(Request $request, Person $person): RedirectResponse
    {
        $data = $this->validated($request);

        $social = $person->socials()->create($data);

        return redirect()->route('people.show', $person)->with('status', "Akun {$social->display_handle} berhasil ditambahkan.");
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'platform' => ['required', 'string', 'in:'.implode(',', array_column(SocialPlatform::cases(), 'value'))],
            'handle' => ['required', 'string', 'max:255'],
            'profile_url' => ['nullable', 'url', 'max:2048'],
            'is_auto_fetch' => ['nullable', 'boolean'],
        ]);

        $data['handle'] = ltrim($data['handle'], '@');
        $data['is_auto_fetch'] = $request->boolean('is_auto_fetch');
        $data['category'] = 'personal';

        return $data;
    }
}

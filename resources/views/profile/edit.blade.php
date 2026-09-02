@extends('layouts.app')

@section('title', __('entity.profile_title') . ' — ' . config('app.name'))

@section('content')
    <div class="mb-6">
        <h1 class="mb-1 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('entity.profile_title') }}</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('entity.profile_subtitle') }}</p>
    </div>

    @if ($user->pending_email)
        <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-300">
            <p class="mb-2">
                {{ __('entity.profile_pending_email_notice', ['email' => $user->pending_email]) }}
            </p>
            <div class="flex items-center gap-3">
                <a href="{{ route('profile.verify-email') }}" class="font-medium underline">{{ __('entity.profile_verify_now') }}</a>
                <form method="POST" action="{{ route('profile.verify-email.cancel') }}">
                    @csrf
                    <button type="submit" class="font-medium underline">{{ __('entity.profile_cancel_email_change') }}</button>
                </form>
            </div>
        </div>
    @endif

    <x-tab-bar>
        <x-tab-button tab-key="username">{{ __('entity.profile_tab_username') }}</x-tab-button>
        <x-tab-button tab-key="password">{{ __('auth.password') }}</x-tab-button>
        @if ($person)
            <x-tab-button tab-key="personal">{{ __('entity.profile_tab_personal') }}</x-tab-button>
        @endif
        @if ($person)
            <x-tab-button tab-key="sosial">{{ __('entity.profile_tab_sosial') }}</x-tab-button>
        @endif
    </x-tab-bar>

    <div data-tab-panel="username" class="max-w-lg rounded-2xl border border-black/5 bg-white p-6 shadow-sm dark:border-white/5 dark:bg-slate-900">
        <form method="POST" action="{{ route('profile.update') }}" data-disable-on-submit>
            @csrf
            @method('PUT')

            <x-form-field name="name" :label="__('auth.name')" required :value="old('name', $user->name)" />
            <x-form-field name="email" type="email" :label="__('auth.email')" required :value="old('email', $user->email)" :hint="__('entity.profile_email_change_hint')" />
            <x-form-field name="current_password" type="password" :label="__('auth.current_password')" :hint="__('entity.current_password_conditional_hint')" />

            <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-70">
                {{ __('common.save_changes') }}
            </button>
        </form>
    </div>

    <div data-tab-panel="password" class="max-w-lg rounded-2xl border border-black/5 bg-white p-6 shadow-sm dark:border-white/5 dark:bg-slate-900">
        <form method="POST" action="{{ route('profile.password.update') }}">
            @csrf
            @method('PUT')

            <x-form-field name="current_password" type="password" :label="__('auth.current_password')" required />
            <x-form-field name="password" type="password" :label="__('auth.new_password')" required />
            <x-form-field name="password_confirmation" type="password" :label="__('auth.new_password_confirmation')" required />

            <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700">
                {{ __('entity.change_password_button') }}
            </button>
        </form>
    </div>

    @if ($person)
        <div data-tab-panel="personal" class="max-w-lg rounded-2xl border border-black/5 bg-white p-6 shadow-sm dark:border-white/5 dark:bg-slate-900">
            {{-- One form, one Simpan button — Lokasi and Wilayah both end up on this same Person
                 record now (see PersonController::update()/resolveOrgScope()), so there's no
                 reason left to make this two separate submits the way it briefly was when
                 Wilayah still wrote to the User row instead. --}}
            <form method="POST" action="{{ route('people.update', $person) }}" data-disable-on-submit>
                @csrf
                @method('PUT')
                <input type="hidden" name="name" value="{{ $person->name }}">

                <div class="{{ $canEditRegion ? 'mb-6 border-b border-black/5 pb-6 dark:border-white/5' : '' }}">
                    {{-- Sub-heading only shown alongside the Wilayah section below — on its own,
                         "Lokasi" under a tab already called "Info Personal" would just repeat
                         itself. --}}
                    @if ($canEditRegion)
                        <h2 class="mb-1 text-sm font-bold text-slate-900 dark:text-white">{{ __('entity.location_label') }}</h2>
                    @endif

                    <x-form-field id="person_city" name="city" :label="__('entity.city')" :hint="__('entity.city_optional_map')" :value="old('city', $person->city)" />
                    <x-form-field id="person_country" name="country" :label="__('entity.country')" :hint="__('entity.country_optional')" :value="old('country', $person->country)" :placeholder="__('entity.country_placeholder')" />
                    <x-coordinate-fields :latitude="$person->latitude" :longitude="$person->longitude" />
                </div>

                @if ($canEditRegion)
                    <div>
                        <h2 class="mb-1 text-sm font-bold text-slate-900 dark:text-white">{{ __('accounts.region') }}</h2>
                        <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">
                            {{ __('entity.profile_region_hint') }}
                        </p>

                        @include('partials.region-fields', [
                            'unions' => $unions,
                            'conferences' => $conferences,
                            'churches' => $churches,
                            // A Daerah-scoped Person always stores union_id as null (see
                            // PersonController::resolveSelfReportedScope()'s "never both at
                            // once" comment) — union_id alone would leave the Uni field blank
                            // every time this page reloads after such a save, even though a
                            // Daerah (and its Uni) is very much still selected. Falling back to
                            // the chosen conference's own union_id (already eager-loaded into
                            // $conferences above) re-derives it instead of storing it twice.
                            'selectedUnionId' => $person->union_id ?? $conferences->firstWhere('id', $person->conference_id)?->union_id,
                            'selectedConferenceId' => $person->conference_id,
                            'selectedChurchName' => $person->church?->name,
                        ])
                    </div>
                @endif

                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-70">
                    {{ __('common.save') }}
                </button>
            </form>
        </div>
    @endif

    @if ($person)
        <div data-tab-panel="sosial" class="max-w-lg rounded-2xl border border-black/5 bg-white p-6 shadow-sm dark:border-white/5 dark:bg-slate-900">
            {{-- This tab is where edit/delete of your own accounts actually happens now — data
                 display (stats, growth score, history) lives on people.show instead, reached
                 from Analitik & Grafik Personal, per the user's explicit call: one page for
                 viewing, one for managing, not the same list duplicated in two places. --}}
            <div class="mb-4 flex items-center justify-between gap-3">
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('entity.profile_own_socials_subtitle') }}</p>
                <a
                    href="{{ route('people.socials.create', $person) }}"
                    class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-blue-600 px-3 py-1.5 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700"
                >
                    {{ __('entity.add_account') }}
                </a>
            </div>

            @php $activeSocials = $person->socials->where('is_active', true); @endphp
            @if ($activeSocials->isEmpty())
                <x-empty-state variant="inline">{{ __('entity.no_socials') }}</x-empty-state>
            @else
                <ul class="-mx-6 divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($activeSocials as $social)
                        <x-social-account-row :social="$social" padding="px-6 py-3" />
                    @endforeach
                </ul>
            @endif
        </div>
    @endif

    @include('partials.tab-script', ['activeTab' => $activeTab])
@endsection

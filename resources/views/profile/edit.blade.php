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
        <x-tab-button tab-key="username">Username</x-tab-button>
        <x-tab-button tab-key="password">Kata Sandi</x-tab-button>
        @if ($person)
            <x-tab-button tab-key="personal">Info Personal</x-tab-button>
            <x-tab-button tab-key="sosial">Media Sosial</x-tab-button>
        @endif
        @if ($canEditRegion)
            <x-tab-button tab-key="wilayah">Wilayah</x-tab-button>
        @endif
    </x-tab-bar>

    <div data-tab-panel="username" class="max-w-lg rounded-2xl border border-black/5 bg-white p-6 shadow-sm dark:border-white/5 dark:bg-slate-900">
        <form method="POST" action="{{ route('profile.update') }}" data-disable-on-submit>
            @csrf
            @method('PUT')

            <x-form-field name="name" label="Nama Lengkap" required :value="old('name', $user->name)" />
            <x-form-field name="email" type="email" label="Email" required :value="old('email', $user->email)" hint="Mengubah email akan mengirim kode OTP ke alamat baru" />
            <x-form-field name="current_password" type="password" label="Password Saat Ini" hint="Wajib diisi hanya jika mengubah email" />

            <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-70">
                Simpan Perubahan
            </button>
        </form>
    </div>

    <div data-tab-panel="password" class="max-w-lg rounded-2xl border border-black/5 bg-white p-6 shadow-sm dark:border-white/5 dark:bg-slate-900">
        <form method="POST" action="{{ route('profile.password.update') }}">
            @csrf
            @method('PUT')

            <x-form-field name="current_password" type="password" label="Password Saat Ini" required />
            <x-form-field name="password" type="password" label="Kata Sandi Baru" required />
            <x-form-field name="password_confirmation" type="password" label="Konfirmasi Kata Sandi Baru" required />

            <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700">
                Ubah Kata Sandi
            </button>
        </form>
    </div>

    @if ($person)
        <div data-tab-panel="personal" class="max-w-lg rounded-2xl border border-black/5 bg-white p-6 shadow-sm dark:border-white/5 dark:bg-slate-900">
            <form method="POST" action="{{ route('people.update', $person) }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="name" value="{{ $person->name }}">

                <x-form-field id="person_city" name="city" label="Kota" hint="opsional, untuk peta" :value="old('city', $person->city)" />
                <x-coordinate-fields :latitude="$person->latitude" :longitude="$person->longitude" />

                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700">
                    Simpan
                </button>
            </form>
        </div>

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

    @if ($canEditRegion)
        <div data-tab-panel="wilayah" class="max-w-lg rounded-2xl border border-black/5 bg-white p-6 shadow-sm dark:border-white/5 dark:bg-slate-900">
            <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">
                Uni, Daerah, dan Gereja Anda saat ini — supaya admin di wilayah Anda bisa menemukan Anda.
            </p>

            <form method="POST" action="{{ route('profile.complete.store') }}" data-disable-on-submit>
                @csrf

                @include('partials.region-fields', [
                    'unions' => $unions,
                    'conferences' => $conferences,
                    'churches' => $churches,
                    'selectedUnionId' => $user->union_id,
                    'selectedConferenceId' => $user->conference_id,
                    'selectedChurchName' => $user->church?->name,
                ])

                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-70">
                    Simpan
                </button>
            </form>
        </div>
    @endif

    @include('partials.tab-script', ['activeTab' => $activeTab])
@endsection

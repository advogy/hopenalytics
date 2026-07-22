@extends('layouts.app')

@section('title', 'Profil Saya — ' . config('app.name'))

@section('content')
    <div class="mb-6">
        <h1 class="mb-1 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">Profil Saya</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">Kelola info personal, username, dan kata sandi akun Anda.</p>
    </div>

    @if ($user->pending_email)
        <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-300">
            <p class="mb-2">
                Menunggu verifikasi perubahan email ke <strong>{{ $user->pending_email }}</strong>.
            </p>
            <div class="flex items-center gap-3">
                <a href="{{ route('profile.verify-email') }}" class="font-medium underline">Verifikasi sekarang</a>
                <form method="POST" action="{{ route('profile.verify-email.cancel') }}">
                    @csrf
                    <button type="submit" class="font-medium underline">Batalkan</button>
                </form>
            </div>
        </div>
    @endif

    <div class="mb-6 flex gap-2 border-b border-black/5 dark:border-white/5">
        <button type="button" data-tab-button="username" class="border-b-2 px-4 py-2.5 text-sm font-medium transition">
            Username
        </button>
        <button type="button" data-tab-button="password" class="border-b-2 px-4 py-2.5 text-sm font-medium transition">
            Kata Sandi
        </button>
        @if ($person)
            <button type="button" data-tab-button="personal" class="border-b-2 px-4 py-2.5 text-sm font-medium transition">
                Info Personal
            </button>
            <button type="button" data-tab-button="sosial" class="border-b-2 px-4 py-2.5 text-sm font-medium transition">
                Media Sosial
            </button>
        @endif
        @if ($canEditRegion)
            <button type="button" data-tab-button="wilayah" class="border-b-2 px-4 py-2.5 text-sm font-medium transition">
                Wilayah
            </button>
        @endif
    </div>

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
                <p class="text-sm text-slate-500 dark:text-slate-400">Akun media sosial pribadi Anda.</p>
                <a
                    href="{{ route('people.socials.create', $person) }}"
                    class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-blue-600 px-3 py-1.5 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700"
                >
                    {{ __('entity.add_account') }}
                </a>
            </div>

            @if ($person->socials->isEmpty())
                <p class="py-6 text-center text-sm text-slate-400 dark:text-slate-500">{{ __('entity.no_socials') }}</p>
            @else
                <ul class="-mx-6 divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($person->socials as $social)
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

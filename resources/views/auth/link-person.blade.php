@php
    // A plain member sees this once, right after OTP verification, in the bare onboarding
    // layout (no nav yet — they aren't fully set up). An admin reaching this from their own
    // "Profil Saya" already has the full app around them, so it renders inside layouts.app
    // instead — dropping them into a blank guest page mid-session would be disorienting.
    $isAdmin = auth()->user()->role !== null;
@endphp

@extends($isAdmin ? 'layouts.app' : 'layouts.guest')

@section('title', __('auth.link_person_title') . ' — ' . config('app.name'))

@section('content')
    @if ($isAdmin)
        <h1 class="mb-1 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">
            {{ __('auth.link_person_title') }}
        </h1>
        <p class="mb-6 text-sm text-slate-500 dark:text-slate-400">
            {{ __('auth.link_person_subtitle') }}
        </p>
    @else
        <h1 class="mb-1 text-xl font-bold tracking-tight text-slate-900 dark:text-white">
            {{ __('auth.link_person_title') }}
        </h1>
        <p class="mb-6 text-sm text-slate-500 dark:text-slate-400">
            {{ __('auth.link_person_subtitle') }}
        </p>
    @endif

    <form
        method="POST"
        action="{{ route('link-person.store') }}"
        @class(['max-w-lg rounded-2xl border border-black/5 bg-white p-6 shadow-sm dark:border-white/5 dark:bg-slate-900' => $isAdmin])
    >
        @csrf

        <div class="mb-4 space-y-2">
            @foreach ($candidates as $candidate)
                <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-black/10 p-3 transition hover:bg-slate-50 has-[:checked]:border-blue-600 has-[:checked]:bg-blue-50 dark:border-white/10 dark:hover:bg-slate-800 dark:has-[:checked]:border-blue-400 dark:has-[:checked]:bg-blue-950/30">
                    <input type="radio" name="person_id" value="{{ $candidate->id }}" class="h-4 w-4 shrink-0 text-blue-600 focus:ring-blue-500" required>
                    <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#f7cd9a] text-sm font-bold text-blue-600 dark:bg-violet-950/60 dark:text-[#f7cd9a]">
                        {{ mb_substr($candidate->name, 0, 1) }}
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate font-medium text-slate-900 dark:text-white">{{ $candidate->name }}</span>
                        <span class="block truncate text-xs text-slate-500 dark:text-slate-400">
                            @if ($candidate->city){{ $candidate->city }} &middot; @endif
                            {{ __('auth.link_person_social_count', ['count' => $candidate->socials_count]) }}
                        </span>
                    </span>
                </label>
            @endforeach

            <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-black/10 p-3 transition hover:bg-slate-50 has-[:checked]:border-blue-600 has-[:checked]:bg-blue-50 dark:border-white/10 dark:hover:bg-slate-800 dark:has-[:checked]:border-blue-400 dark:has-[:checked]:bg-blue-950/30">
                <input type="radio" name="person_id" value="new" class="h-4 w-4 shrink-0 text-blue-600 focus:ring-blue-500" required>
                <span class="font-medium text-slate-900 dark:text-white">{{ __('auth.link_person_none') }}</span>
            </label>
        </div>

        <button type="submit" class="w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700">
            {{ __('auth.link_person_button') }}
        </button>
    </form>
@endsection

@php
    $platformLabels = ['youtube' => 'YouTube', 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'facebook' => 'Facebook'];
    $isYoutube = $social->platform->value === 'youtube';
    $isFacebook = $social->platform->value === 'facebook';
    $owner = $social->person ?? $social->church;
    $backRoute = $social->person_id ? route('people.show', $social->person) : route('churches.show', $social->church);
@endphp

@extends('layouts.app')

@section('title', __('entity.manual_stat_title') . ' — ' . $social->display_handle)

@section('content')
    <a href="{{ $backRoute }}" class="mb-4 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400">
        &larr; {{ __('entity.back_to', ['name' => $owner->name]) }}
    </a>

    <div class="mb-8 flex items-center gap-3">
        <x-platform-icon :platform="$social->platform" class="h-10 w-10 shrink-0 text-lg" />
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('entity.manual_stat_title') }}</h1>
            <p class="text-slate-500 dark:text-slate-400">{{ $platformLabels[$social->platform->value] }} &middot; {{ $social->display_handle }}</p>
        </div>
    </div>

    <form
        method="POST"
        action="{{ route('socials.stats.store', $social) }}"
        class="max-w-lg rounded-2xl border border-black/5 bg-white p-6 shadow-sm dark:border-white/5 dark:bg-slate-900"
    >
        @csrf

        <x-form-field
            name="recorded_at"
            :label="__('entity.stat_date')"
            type="date"
            required
            :value="old('recorded_at', now()->toDateString())"
        />

        <x-form-field
            name="followers_count"
            :label="$isYoutube ? __('entity.subscriber') : __('entity.followers')"
            type="number"
            min="0"
            required
            :value="old('followers_count', $isYoutube ? $latest?->subscribers_count : $latest?->followers_count)"
        />

        @unless ($isFacebook)
            <x-form-field
                name="following_count"
                label="Following"
                type="number"
                min="0"
                :value="old('following_count', $latest?->following_count)"
            />
        @endunless

        @if ($isYoutube)
            <x-form-field name="views_count" label="Views" type="number" min="0" :value="old('views_count', $latest?->views_count)" />
            <x-form-field name="videos_count" label="Videos" type="number" min="0" :value="old('videos_count', $latest?->videos_count)" />
        @elseif (! $isFacebook)
            <x-form-field name="posts_count" label="Posts" type="number" min="0" :value="old('posts_count', $latest?->posts_count)" />
        @endif

        @if ($social->platform->value === 'tiktok')
            <x-form-field name="likes_count" label="Likes" type="number" min="0" :value="old('likes_count', $latest?->likes_count)" />
        @endif

        <div class="flex items-center gap-3">
            <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700">
                {{ __('common.save') }}
            </button>
            <a href="{{ $backRoute }}" class="text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">
                {{ __('common.cancel') }}
            </a>
        </div>
    </form>
@endsection

@php
    $mailto = '<a href="mailto:info@hopenalytics.id" class="font-medium text-blue-600 hover:underline dark:text-blue-400">info@hopenalytics.id</a>';
@endphp

<h1 class="mb-1 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('legal.privacy_title') }}</h1>
<p class="mb-8 text-sm text-slate-500 dark:text-slate-400">{{ config('app.name') }} — {{ __('legal.updated_on') }}</p>

<div class="space-y-6 rounded-2xl border border-black/5 bg-white p-6 shadow-sm dark:border-white/5 dark:bg-slate-900">
    <div class="max-w-3xl space-y-3 text-sm text-slate-600 dark:text-slate-300">
        <p>{{ __('legal.privacy_intro', ['app' => config('app.name')]) }}</p>
    </div>

    <section>
        <h2 class="mb-2 text-lg font-bold text-slate-900 dark:text-white">{{ __('legal.privacy_s1_title') }}</h2>
        <div class="max-w-3xl space-y-2 text-sm text-slate-600 dark:text-slate-300">
            <p>{!! __('legal.privacy_s1_p1') !!}</p>
            <p>{!! __('legal.privacy_s1_p2') !!}</p>
            <p>{!! __('legal.privacy_s1_p3') !!}</p>
        </div>
    </section>

    <section>
        <h2 class="mb-2 text-lg font-bold text-slate-900 dark:text-white">{{ __('legal.privacy_s2_title') }}</h2>
        <div class="max-w-3xl space-y-2 text-sm text-slate-600 dark:text-slate-300">
            <p>{!! __('legal.privacy_s2_p1') !!}</p>
        </div>
    </section>

    <section>
        <h2 class="mb-2 text-lg font-bold text-slate-900 dark:text-white">{{ __('legal.privacy_s3_title') }}</h2>
        <ul class="max-w-3xl list-disc space-y-1 pl-5 text-sm text-slate-600 dark:text-slate-300">
            @foreach (__('legal.privacy_s3_items') as $item)
                <li>{{ $item }}</li>
            @endforeach
        </ul>
    </section>

    <section>
        <h2 class="mb-2 text-lg font-bold text-slate-900 dark:text-white">{{ __('legal.privacy_s4_title') }}</h2>
        <div class="max-w-3xl space-y-2 text-sm text-slate-600 dark:text-slate-300">
            <p>{{ __('legal.privacy_s4_p1') }}</p>
        </div>
    </section>

    <section>
        <h2 class="mb-2 text-lg font-bold text-slate-900 dark:text-white">{{ __('legal.privacy_s5_title') }}</h2>
        <ul class="max-w-3xl list-disc space-y-1 pl-5 text-sm text-slate-600 dark:text-slate-300">
            @foreach (__('legal.privacy_s5_items') as $item)
                <li>{!! $item !!}</li>
            @endforeach
        </ul>
    </section>

    <section>
        <h2 class="mb-2 text-lg font-bold text-slate-900 dark:text-white">{{ __('legal.privacy_s6_title') }}</h2>
        <div class="max-w-3xl space-y-2 text-sm text-slate-600 dark:text-slate-300">
            <p>{{ __('legal.privacy_s6_p1') }}</p>
        </div>
    </section>

    <section>
        <h2 class="mb-2 text-lg font-bold text-slate-900 dark:text-white">{{ __('legal.privacy_s7_title') }}</h2>
        <p class="max-w-3xl text-sm text-slate-600 dark:text-slate-300">{{ __('legal.privacy_s7_p1', ['app' => config('app.name')]) }}</p>
    </section>

    <section>
        <h2 class="mb-2 text-lg font-bold text-slate-900 dark:text-white">{{ __('legal.privacy_s8_title') }}</h2>
        <p class="max-w-3xl text-sm text-slate-600 dark:text-slate-300">{{ __('legal.privacy_s8_p1') }}</p>
    </section>

    <section>
        <h2 class="mb-2 text-lg font-bold text-slate-900 dark:text-white">{{ __('legal.privacy_s9_title') }}</h2>
        <p class="max-w-3xl text-sm text-slate-600 dark:text-slate-300">{!! __('legal.privacy_s9_p1', ['email' => $mailto]) !!}</p>
    </section>
</div>

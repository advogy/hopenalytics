<h1 class="mb-1 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('nav.about') }}</h1>
<p class="mb-8 text-sm text-slate-500 dark:text-slate-400">{{ __('about.subtitle', ['name' => config('app.name')]) }}</p>

<div class="rounded-2xl border border-black/5 bg-white p-6 shadow-sm dark:border-white/5 dark:bg-slate-900">
    <div class="mb-6 flex items-center gap-3">
        <x-logo-mark class="h-10 w-10 shrink-0 text-blue-600 dark:text-[#f3ead9]" />
        <p class="max-w-3xl text-sm text-slate-600 dark:text-slate-300">
            {{ __('about.description') }}
        </p>
    </div>

    <div class="mb-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        <div>
            <p class="font-medium text-slate-900 dark:text-white">{{ __('about.feature_monitoring_title') }}</p>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                {{ __('about.feature_monitoring_desc') }}
            </p>
        </div>
        <div>
            <p class="font-medium text-slate-900 dark:text-white">{{ __('about.feature_analytics_title') }}</p>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                {{ __('about.feature_analytics_desc') }}
            </p>
        </div>
        <div>
            <p class="font-medium text-slate-900 dark:text-white">{{ __('about.feature_comparison_title') }}</p>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                {{ __('about.feature_comparison_desc') }}
            </p>
        </div>
        <div>
            <p class="font-medium text-slate-900 dark:text-white">{{ __('about.feature_map_title') }}</p>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                {{ __('about.feature_map_desc') }}
            </p>
        </div>
        <div>
            <p class="font-medium text-slate-900 dark:text-white">{{ __('about.feature_directory_title') }}</p>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                {{ __('about.feature_directory_desc') }}
            </p>
        </div>
        <div>
            <p class="font-medium text-slate-900 dark:text-white">{{ __('about.feature_export_title') }}</p>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                {{ __('about.feature_export_desc') }}
            </p>
        </div>
        <div>
            <p class="font-medium text-slate-900 dark:text-white">{{ __('about.feature_queue_title') }}</p>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                {{ __('about.feature_queue_desc') }}
            </p>
        </div>
    </div>

    <div>
        <p class="mb-2 text-sm font-medium">{{ __('about.platforms_monitored') }}</p>
        <div class="flex flex-wrap gap-2">
            @foreach (['youtube' => 'YouTube', 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'facebook' => 'Facebook'] as $value => $label)
                <span class="inline-flex items-center gap-1.5 rounded-full border border-black/5 bg-slate-50 px-3 py-1 text-sm dark:border-white/5 dark:bg-slate-800">
                    <x-platform-icon :platform="$value" class="h-4 w-4" />
                    {{ $label }}
                </span>
            @endforeach
        </div>
    </div>
</div>

@if (auth()->user()?->role !== null)
    <div class="mt-6 rounded-2xl border border-black/5 bg-white p-6 shadow-sm dark:border-white/5 dark:bg-slate-900">
        <h2 class="mb-1 text-lg font-bold text-slate-900 dark:text-white">{{ __('about.score_title') }}</h2>
        <p class="mb-6 text-sm text-slate-500 dark:text-slate-400">{{ __('about.score_intro') }}</p>

        <div class="mb-6 grid gap-5 sm:grid-cols-2">
            <div>
                <p class="font-medium text-slate-900 dark:text-white">{{ __('about.score_step1_title') }}</p>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('about.score_step1_desc') }}</p>
            </div>
            <div>
                <p class="font-medium text-slate-900 dark:text-white">{{ __('about.score_step2_title') }}</p>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('about.score_step2_desc') }}</p>
            </div>
        </div>

        <div class="mb-6 rounded-xl bg-slate-50 p-4 dark:bg-slate-800/60">
            <p class="mb-1 text-sm font-medium text-slate-900 dark:text-white">{{ __('about.score_example_title') }}</p>
            <p class="text-sm text-slate-600 dark:text-slate-300">{{ __('about.score_example_desc') }}</p>
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <p class="font-medium text-slate-900 dark:text-white">{{ __('about.score_why_title') }}</p>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('about.score_why_desc') }}</p>
            </div>
            <div>
                <p class="font-medium text-slate-900 dark:text-white">{{ __('about.score_accounts_title') }}</p>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('about.score_accounts_desc') }}</p>
            </div>
        </div>
    </div>
@endif

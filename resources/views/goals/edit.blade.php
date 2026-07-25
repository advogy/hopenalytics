@extends('layouts.app')

@section('title', __('goals.title') . ' — ' . config('app.name'))

@section('content')
    <h1 class="mb-1 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('goals.title') }}</h1>
    <p class="mb-8 max-w-2xl text-sm text-slate-500 dark:text-slate-400">{{ __('goals.subtitle') }}</p>

    <form
        method="POST"
        action="{{ route('goals.update') }}"
        class="max-w-2xl rounded-2xl border border-black/5 bg-white p-6 shadow-sm dark:border-white/5 dark:bg-slate-900"
    >
        @csrf
        @method('PUT')

        <div class="divide-y divide-slate-100 dark:divide-slate-800">
            @foreach (\App\Models\Goal::METRICS as $metric)
                @php $goal = $goals[$metric]; @endphp
                <div class="grid grid-cols-1 gap-4 py-5 first:pt-0 last:pb-0 sm:grid-cols-3 sm:items-end">
                    <div>
                        <p class="text-sm font-bold text-slate-900 dark:text-white">{{ __('goals.metric_'.$metric) }}</p>
                    </div>

                    <div>
                        <label for="target_year_{{ $metric }}" class="mb-1.5 block text-sm font-medium">{{ __('goals.target_year') }}</label>
                        <input
                            type="number" id="target_year_{{ $metric }}" name="target_year[{{ $metric }}]"
                            value="{{ old('target_year.'.$metric, $goal->target_year) }}"
                            min="2000" max="2200"
                            class="w-full rounded-lg border border-black/10 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none dark:border-white/10 dark:bg-slate-800"
                        >
                        @error('target_year.'.$metric)
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="target_value_{{ $metric }}" class="mb-1.5 block text-sm font-medium">{{ __('goals.target_value') }}</label>
                        <input
                            type="number" id="target_value_{{ $metric }}" name="target_value[{{ $metric }}]"
                            value="{{ old('target_value.'.$metric, $goal->target_value) }}"
                            min="0"
                            class="w-full rounded-lg border border-black/10 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none dark:border-white/10 dark:bg-slate-800"
                        >
                        @error('target_value.'.$metric)
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            @endforeach
        </div>

        <button type="submit" class="mt-6 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700">
            {{ __('goals.save') }}
        </button>
    </form>
@endsection

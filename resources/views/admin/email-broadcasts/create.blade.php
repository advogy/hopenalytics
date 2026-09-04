@extends('layouts.app')

@section('title', __('email_broadcasts.create_title') . ' — ' . config('app.name'))

@section('content')
    <x-back-link :href="route('admin.email-broadcasts.index')">{{ __('common.back') }}</x-back-link>

    <div class="mb-6">
        <h1 class="mb-1 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('email_broadcasts.create_title') }}</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('email_broadcasts.create_subtitle') }}</p>
    </div>

    {{-- Always visible, not tucked behind a tooltip — per the user's explicit call to explain
         the throttling up front rather than leaving an admin wondering why sending isn't instant. --}}
    <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
        <p class="mb-1 font-bold">{{ __('email_broadcasts.limitations_title') }}</p>
        <p>{{ __('email_broadcasts.limitations_body', ['delay' => $delaySeconds]) }}</p>
    </div>

    <form method="POST" action="{{ route('admin.email-broadcasts.store') }}" data-disable-on-submit id="broadcast-form">
        @csrf

        <div class="mb-6 rounded-2xl border border-black/5 bg-white p-6 shadow-sm dark:border-white/5 dark:bg-slate-900">
            <h2 class="mb-1 font-bold text-slate-900 dark:text-white">{{ __('email_broadcasts.recipient_group_title') }}</h2>
            <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">{{ __('email_broadcasts.recipient_group_subtitle') }}</p>

            <div class="mb-4 grid gap-2 sm:grid-cols-2">
                @foreach ($groupLabels as $group => $label)
                    <label class="flex items-center justify-between gap-3 rounded-lg border border-black/10 px-3.5 py-2.5 text-sm dark:border-white/10">
                        <span class="flex items-center gap-2">
                            <input
                                type="checkbox"
                                name="groups[]"
                                value="{{ $group }}"
                                data-group-checkbox
                                data-count="{{ $groupCounts[$group] }}"
                                checked
                                class="h-4 w-4 rounded border-black/20 text-blue-600 focus:ring-blue-500"
                            >
                            {{ $label }}
                        </span>
                        <span class="text-xs text-slate-400 dark:text-slate-500">{{ $groupCounts[$group] }}</span>
                    </label>
                @endforeach
            </div>
            @error('groups')
                <p class="mb-4 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror

            <p data-recipient-count class="rounded-lg border border-blue-100 bg-blue-50 px-4 py-2.5 text-sm text-blue-800 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-300">
                {{-- Filled in by JS on load (see script below) — starts from every group checked,
                     matching the checkboxes' own default state. --}}
            </p>
        </div>

        <div class="mb-6 rounded-2xl border border-black/5 bg-white p-6 shadow-sm dark:border-white/5 dark:bg-slate-900">
            <div class="mb-4">
                <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-200">{{ __('email_broadcasts.field_subject') }}</label>
                <input
                    type="text"
                    name="subject"
                    value="{{ old('subject') }}"
                    maxlength="255"
                    required
                    class="w-full rounded-lg border border-black/10 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none dark:border-white/10 dark:bg-slate-800"
                >
                @error('subject')
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-6">
                <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-200">{{ __('email_broadcasts.field_body') }}</label>
                <textarea
                    name="body"
                    rows="8"
                    maxlength="5000"
                    required
                    class="w-full rounded-lg border border-black/10 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none dark:border-white/10 dark:bg-slate-800"
                >{{ old('body') }}</textarea>
                <p class="mt-1 text-xs text-slate-400">{{ __('email_broadcasts.field_body_hint') }}</p>
                @error('body')
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <button
                type="submit"
                data-send-button
                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
            >
                {{ __('email_broadcasts.send_button') }}
            </button>
        </div>
    </form>

    <script>
        (function () {
            var form = document.getElementById('broadcast-form');
            var checkboxes = Array.from(form.querySelectorAll('[data-group-checkbox]'));
            var countLabel = form.querySelector('[data-recipient-count]');
            var sendButton = form.querySelector('[data-send-button]');
            var delaySeconds = {{ (int) $delaySeconds }};

            var messages = {
                count: @json(__('email_broadcasts.recipient_count', ['count' => ':count'])),
                zero: @json(__('email_broadcasts.recipient_count_zero')),
                estimated: @json(__('email_broadcasts.estimated_time', ['count' => ':count', 'duration' => ':duration'])),
                seconds: @json(__('email_broadcasts.duration_seconds', ['count' => ':count'])),
                minutes: @json(__('email_broadcasts.duration_minutes', ['count' => ':count'])),
                hours: @json(__('email_broadcasts.duration_hours', ['count' => ':count'])),
                confirm: @json(__('email_broadcasts.send_confirm', ['count' => ':count'])),
            };

            function formatDuration(totalSeconds) {
                if (totalSeconds < 60) return messages.seconds.replace(':count', Math.ceil(totalSeconds));
                if (totalSeconds < 3600) return messages.minutes.replace(':count', Math.ceil(totalSeconds / 60));
                return messages.hours.replace(':count', (totalSeconds / 3600).toFixed(1));
            }

            function update() {
                var total = checkboxes.filter(function (cb) { return cb.checked; })
                    .reduce(function (sum, cb) { return sum + parseInt(cb.dataset.count, 10); }, 0);

                if (total === 0) {
                    countLabel.textContent = messages.zero;
                    sendButton.disabled = true;
                    form.removeAttribute('data-confirm');
                } else {
                    var estimate = messages.estimated
                        .replace(':count', total)
                        .replace(':duration', formatDuration(total * delaySeconds));
                    countLabel.textContent = messages.count.replace(':count', total) + ' ' + estimate;
                    sendButton.disabled = false;
                    form.setAttribute('data-confirm', messages.confirm.replace(':count', total));
                }
            }

            checkboxes.forEach(function (cb) { cb.addEventListener('change', update); });
            update();
        })();
    </script>
@endsection

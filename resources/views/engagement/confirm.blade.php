{{-- Union admins confirm or reject uploaded membership figures (MembershipConfirmationController). --}}
@extends('layouts.app')

@section('title', __('engagement.confirm_title') . ' — ' . config('app.name'))

@section('content')
    <x-hub-header hub="goals" />

    <div class="mb-6">
        <h2 class="mb-1 text-xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('engagement.confirm_title') }}</h2>
        <p class="max-w-2xl text-sm text-slate-500 dark:text-slate-400">{{ __('engagement.confirm_subtitle') }}</p>
    </div>

    @if ($pendingCount > 0)
        <div class="mb-4 flex justify-end">
            <x-export-button :url="route('export.membership-pending.preview')" />
        </div>
    @endif

    @if ($pendingCount === 0)
        <x-empty-state>{{ __('engagement.confirm_empty') }}</x-empty-state>
    @else
        <form method="POST" action="{{ route('engagement.confirm.update') }}" id="confirm-membership-form">
            @csrf
            @method('PUT')

            @error('ids')
                <p class="mb-3 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror

            <div class="mb-4 flex flex-wrap items-center gap-3">
                <label class="mr-auto inline-flex items-center gap-2 text-sm font-medium">
                    <input type="checkbox" data-select-all class="h-4 w-4 rounded border-black/20 text-blue-600 focus:ring-blue-500">
                    {{ __('engagement.confirm_select_all') }} ({{ number_format($pendingCount) }})
                </label>
                <button type="submit" name="action" value="reject" data-confirm-reject class="rounded-lg border border-red-200 bg-white px-4 py-2 text-sm font-medium text-red-600 shadow-sm transition hover:bg-red-50 dark:border-red-900 dark:bg-slate-900 dark:text-red-400 dark:hover:bg-red-950/40">
                    {{ __('engagement.reject_button') }}
                </button>
                <button type="submit" name="action" value="confirm" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700">
                    {{ __('engagement.confirm_button') }}
                </button>
            </div>

            <div class="space-y-6">
                @foreach ($groups as $conferenceName => $memberships)
                    <div class="overflow-x-auto rounded-2xl border border-black/5 dark:border-white/5">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 dark:bg-slate-800/60">
                                <tr>
                                    <th class="w-10 px-4 py-3"></th>
                                    <th class="px-4 py-3 font-bold text-slate-900 dark:text-white">{{ $conferenceName }}</th>
                                    <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('engagement.col_as_of') }}</th>
                                    <th class="px-4 py-3 text-right font-medium text-slate-500 dark:text-slate-400">{{ __('engagement.col_new') }}</th>
                                    <th class="px-4 py-3 text-right font-medium text-slate-500 dark:text-slate-400">{{ __('engagement.col_previous') }}</th>
                                    <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('engagement.col_uploaded_by') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-800 dark:bg-slate-900">
                                @foreach ($memberships as $membership)
                                    @php $before = $previous->get($membership->id); @endphp
                                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                        <td class="px-4 py-3">
                                            <input type="checkbox" name="ids[]" value="{{ $membership->id }}" id="membership_{{ $membership->id }}" data-membership class="h-4 w-4 rounded border-black/20 text-blue-600 focus:ring-blue-500">
                                        </td>
                                        <td class="px-4 py-3 font-medium"><label for="membership_{{ $membership->id }}">{{ $membership->church->name }}</label></td>
                                        <td class="whitespace-nowrap px-4 py-3 text-slate-500 dark:text-slate-400">{{ $membership->as_of->translatedFormat('d M Y') }}</td>
                                        <td class="px-4 py-3 text-right font-semibold tabular-nums">{{ number_format($membership->members_count) }}</td>
                                        <td class="px-4 py-3 text-right tabular-nums text-slate-500 dark:text-slate-400">
                                            @if ($before)
                                                {{ number_format($before->members_count) }}
                                                <span class="block text-xs text-slate-400">{{ $before->as_of->translatedFormat('d M Y') }}</span>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $membership->uploader?->name ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endforeach
            </div>
        </form>

        <script>
            (function () {
                var form = document.getElementById('confirm-membership-form');
                var all = form.querySelector('[data-select-all]');
                var boxes = form.querySelectorAll('[data-membership]');

                all.addEventListener('change', function () {
                    boxes.forEach(function (box) { box.checked = all.checked; });
                });

                // Rejecting asks first; confirming doesn't (it's the usual action).
                form.querySelector('[data-confirm-reject]').addEventListener('click', function (event) {
                    if (! window.confirm(@js(__('engagement.reject_confirm')))) {
                        event.preventDefault();
                    }
                });
            })();
        </script>
    @endif
@endsection

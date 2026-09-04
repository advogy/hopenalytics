{{--
    "Saran Admin" tab — one row per pending AdminSuggestion (a member typed a not-yet-existing
    Gereja name during Lengkapi Profil/Profil Saya and is claiming to be its admin). Expects
    $pendingSuggestions (a paginator, each row's user/person/conference.union eager-loaded, plus
    a ->similarChurches collection UserAssignmentController::index() attaches per row — see its
    own doc comment) and $canReviewSuggestions.
--}}
<x-admin-list-card
    :items="$pendingSuggestions"
    :title="__('admin_suggestions.title')"
    :subtitle="__('admin_suggestions.subtitle')"
    :empty-message="__('admin_suggestions.none_pending')"
>
    <thead>
        <tr class="text-slate-500 dark:text-slate-400">
            <th class="py-2 pr-2 font-medium">#</th>
            <th class="py-2 pr-2 font-medium">{{ __('admin_suggestions.col_requester') }}</th>
            <th class="py-2 pr-2 font-medium">{{ __('admin_suggestions.col_church_name') }}</th>
            <th class="py-2 pr-2 font-medium">{{ __('common.conference') }}</th>
            <th class="py-2 pr-2 font-medium">{{ __('admin_suggestions.col_submitted_at') }}</th>
            <th class="py-2 text-right font-medium">{{ __('common.action') }}</th>
        </tr>
    </thead>
    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
        @foreach ($pendingSuggestions as $suggestion)
            <tr class="align-top">
                <td class="py-2 pr-2 text-slate-400 dark:text-slate-500">{{ $loop->iteration }}</td>
                <td class="py-2 pr-2">
                    @include('admin.users.partials.name-email', ['user' => $suggestion->user])
                </td>
                <td class="py-2 pr-2">
                    <div class="font-medium text-slate-900 dark:text-white">{{ $suggestion->church_name }}</div>
                </td>
                <td class="py-2 pr-2 text-slate-500 dark:text-slate-400">
                    {{ $suggestion->conference->name }} ({{ $suggestion->conference->union->name }})
                </td>
                <td class="py-2 pr-2 whitespace-nowrap text-slate-500 dark:text-slate-400">
                    {{ $suggestion->created_at->translatedFormat('d M Y H:i') }}
                </td>
                <td class="py-2">
                    <div class="flex flex-nowrap items-center justify-end gap-3">
                        <form
                            method="POST"
                            action="{{ route('admin.admin-suggestions.approve', $suggestion) }}"
                            data-confirm="{{ __('admin_suggestions.approve_confirm', ['name' => $suggestion->user->name, 'church' => $suggestion->church_name]) }}"
                        >
                            @csrf
                            <button
                                type="submit"
                                title="{{ __('admin_suggestions.approve') }}"
                                aria-label="{{ __('admin_suggestions.approve') }}"
                                class="shrink-0 text-slate-500 hover:text-emerald-600 dark:text-slate-400 dark:hover:text-emerald-400"
                            >
                                <x-icon name="check-circle" class="h-5 w-5" />
                            </button>
                        </form>
                        <form
                            method="POST"
                            action="{{ route('admin.admin-suggestions.reject', $suggestion) }}"
                            data-confirm="{{ __('admin_suggestions.reject_confirm', ['name' => $suggestion->user->name]) }}"
                        >
                            @csrf
                            <button
                                type="submit"
                                title="{{ __('admin_suggestions.reject') }}"
                                aria-label="{{ __('admin_suggestions.reject') }}"
                                class="shrink-0 text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
                            >
                                <x-icon name="x-circle" class="h-5 w-5" />
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            @if ($suggestion->similarChurches->isNotEmpty())
                {{-- A second, full-width row rather than squeezing this into the narrow "Nama
                     Gereja" column (it used to be a max-w-xs box there, forcing every match onto
                     its own line and pushing the row tall) — collapsed by default via <details>
                     (no JS needed) so a long match list costs one line until the reviewer
                     actually wants to see it, and when open it can wrap across the row's full
                     width instead of stacking narrow-and-tall. --}}
                <tr>
                    <td></td>
                    <td colspan="4" class="pb-2 pr-2">
                        <details class="rounded-lg border border-amber-200 bg-amber-50 open:pb-2 dark:border-amber-900 dark:bg-amber-950">
                            <summary class="cursor-pointer select-none px-2 py-1.5 text-xs font-medium text-amber-800 dark:text-amber-300">
                                {{ __('admin_suggestions.similar_warning', ['count' => $suggestion->similarChurches->count()]) }}
                            </summary>
                            <div class="flex flex-wrap gap-x-3 gap-y-1 px-2 text-xs text-amber-800 dark:text-amber-300">
                                @foreach ($suggestion->similarChurches as $match)
                                    <a href="{{ route('churches.edit', $match['model']) }}" class="underline hover:no-underline" target="_blank" rel="noopener">
                                        {{ $match['model']->name }}
                                        <span class="text-amber-600 dark:text-amber-400">({{ $match['model']->conference?->name ?? '—' }})</span>
                                    </a>
                                @endforeach
                            </div>
                            <p class="mt-1.5 px-2 text-[11px] italic text-amber-600 dark:text-amber-400">
                                {{ __('admin_suggestions.similar_disclaimer') }}
                            </p>
                        </details>
                    </td>
                </tr>
            @endif
        @endforeach
    </tbody>
</x-admin-list-card>

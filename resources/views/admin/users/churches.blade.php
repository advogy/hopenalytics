{{-- "Gereja yang dikelola" (UserAssignmentController::editChurches()): the churches an
     Admin/Pimpinan Gereja manages — the main one, plus extra ones for someone serving at more
     than one church. Opened in Kelola Pengguna's shared modal (row-actions links here with
     modal=1), same contract as change-region.blade.php. --}}
@extends(($modal ?? false) ? 'layouts.blank' : 'layouts.app')

@section('title', __('users.churches_title') . ' — ' . config('app.name'))

@section('content')
    @unless ($modal ?? false)
        <x-back-link :href="route('admin.users.index', ['tab' => $tab])">{{ __('common.back') }}</x-back-link>

        <h1 class="mb-1 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('users.churches_title') }}</h1>
    @endunless

    <div class="max-w-lg rounded-2xl border border-black/5 bg-white p-6 shadow-sm dark:border-white/5 dark:bg-slate-900">
        <p class="mb-1 text-sm text-slate-500 dark:text-slate-400">{{ $target->name }} — {{ $target->email }} · {{ $target->role->label() }}</p>
        <p class="mb-5 text-sm text-slate-500 dark:text-slate-400">{{ __('users.churches_intro') }}</p>

        <ul class="mb-6 divide-y divide-slate-100 rounded-xl border border-black/5 dark:divide-slate-800 dark:border-white/5">
            @foreach ($churches as $church)
                <li class="flex items-center justify-between gap-3 px-4 py-3">
                    <div class="min-w-0">
                        <p class="truncate font-medium text-slate-900 dark:text-white">{{ $church->name }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            {{ $church->conference?->name ?? '—' }}
                            @if ($church->id === $target->church_id)
                                · <span class="font-medium text-blue-600 dark:text-blue-400">{{ __('users.churches_main') }}</span>
                            @endif
                        </p>
                    </div>
                    @if ($church->id !== $target->church_id)
                        <form
                            method="POST"
                            action="{{ route('admin.users.churches.remove', [$target, $church]) }}"
                            data-confirm="{{ __('users.churches_remove_confirm', ['church' => $church->name, 'name' => $target->name]) }}"
                            @if ($modal ?? false) data-modal-ajax-form @endif
                        >
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="tab" value="{{ $tab }}">
                            @if ($modal ?? false)
                                <input type="hidden" name="modal" value="1">
                            @endif
                            <button type="submit" class="shrink-0 cursor-pointer text-sm font-medium text-red-600 hover:underline dark:text-red-400">{{ __('users.churches_remove') }}</button>
                        </form>
                    @else
                        <span class="shrink-0 text-xs text-slate-400" title="{{ __('users.churches_main_hint') }}">{{ __('users.churches_main_hint_short') }}</span>
                    @endif
                </li>
            @endforeach
        </ul>

        @if ($scopeOptions === [])
            <p class="text-sm text-slate-400">{{ __('users.churches_none_left') }}</p>
        @else
            <form
                method="POST"
                action="{{ route('admin.users.churches.add', $target) }}"
                data-disable-on-submit
                @if ($modal ?? false) data-modal-ajax-form data-modal-title="{{ __('users.churches_title') }}" @endif
            >
                @csrf
                <input type="hidden" name="tab" value="{{ $tab }}">
                @if ($modal ?? false)
                    <input type="hidden" name="modal" value="1">
                @endif

                <label class="mb-4 block">
                    <span class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('users.churches_add_label') }}</span>
                    <div id="add-church-wrapper" class="relative" data-searchable-select>
                        <input type="hidden" name="church_id" data-searchable-select-value required>
                        <input
                            type="text"
                            data-searchable-select-search
                            autocomplete="off"
                            placeholder="{{ __('users.search_scope_for', ['level' => __('common.church')]) }}"
                            class="w-full rounded-lg border border-black/10 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none dark:border-white/10 dark:bg-slate-800"
                        >
                        <ul
                            data-searchable-select-list
                            class="absolute left-0 top-full z-20 mt-1 hidden max-h-52 w-full overflow-y-auto rounded-lg border border-black/10 bg-white p-1 text-sm shadow-lg dark:border-white/10 dark:bg-slate-800"
                        ></ul>
                    </div>
                </label>

                <script>
                    (function () {
                        var ctl = window.initSearchableSelect(document.getElementById('add-church-wrapper'));
                        ctl.setOptions(@json($scopeOptions), @json(__('users.search_scope_for', ['level' => __('common.church')])));
                    })();
                </script>

                <div class="flex items-center gap-3">
                    <button type="submit" class="cursor-pointer rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700">
                        {{ __('users.churches_add') }}
                    </button>
                    @if ($modal ?? false)
                        <button type="button" data-app-modal-close class="cursor-pointer text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">
                            {{ __('common.cancel') }}
                        </button>
                    @endif
                </div>
            </form>
        @endif
    </div>
@endsection

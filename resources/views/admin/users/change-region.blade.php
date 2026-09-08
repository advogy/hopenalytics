{{-- "Ganti Wilayah" — replaces releaseRegion()'s old plain clear-only action for an already
     role-assigned Admin/Pimpinan Divisi/Uni/Daerah/Gereja (see UserAssignmentController::editRegion()'s
     own doc comment): a single searchable-select for the new region, same level as $target's
     current role, with a "Tidak ada" pseudo-option at the top of that same list for whoever still
     wants the old release-only behavior. $modal follows the exact same contract as
     admin/users/edit.blade.php's own (see that file's doc comment) — this is always opened via
     the modal in practice (row-actions.blade.php only ever links here with modal=1), but the
     non-modal fallback is kept for consistency with every other admin/users/*.blade.php form. --}}
@extends(($modal ?? false) ? 'layouts.blank' : 'layouts.app')

@section('title', __('users.change_region_title') . ' — ' . config('app.name'))

@section('content')
    @unless ($modal ?? false)
        <x-back-link :href="route('admin.users.index', ['tab' => $tab])">{{ __('common.back') }}</x-back-link>

        <h1 class="mb-1 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('users.change_region_title') }}</h1>
        <p class="mb-8 text-sm text-slate-500 dark:text-slate-400">{{ $target->name }} — {{ $target->email }}</p>
    @endunless

    <form
        method="POST"
        action="{{ route('admin.users.change-region.update', $target) }}"
        data-disable-on-submit
        @if ($modal ?? false) data-modal-ajax-form data-modal-title="{{ __('users.change_region_title') }}" @endif
        class="max-w-lg rounded-2xl border border-black/5 bg-white p-6 shadow-sm dark:border-white/5 dark:bg-slate-900"
    >
        @csrf
        <input type="hidden" name="tab" value="{{ $tab }}">
        @if ($modal ?? false)
            <input type="hidden" name="modal" value="1">
            <p class="mb-5 text-sm text-slate-500 dark:text-slate-400">{{ $target->name }} — {{ $target->email }}</p>
        @endif

        <div class="mb-4 grid grid-cols-2 gap-3 text-sm">
            <div>
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('users.col_role') }}</p>
                <p class="font-medium text-slate-900 dark:text-white">{{ $target->role->label() }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('users.change_region_current_label') }}</p>
                <p class="font-medium text-slate-900 dark:text-white">{{ $currentRegionName ?? __('users.change_region_none_current') }}</p>
            </div>
        </div>

        <label class="mb-5 block">
            <span class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('users.change_region_new_label') }}</span>
            <div id="change-region-scope-wrapper" class="relative" data-searchable-select>
                <input type="hidden" name="scope_id" data-searchable-select-value value="{{ $currentScopeId }}">
                <input
                    type="text"
                    data-searchable-select-search
                    autocomplete="off"
                    placeholder="{{ __('users.search_scope_for', ['level' => $levelLabel]) }}"
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
                var wrapper = document.getElementById('change-region-scope-wrapper');
                var ctl = window.initSearchableSelect(wrapper);
                var options = @json(array_merge([['id' => '', 'label' => __('users.change_region_none_option')]], $scopeOptions));

                ctl.setOptions(options, @json(__('users.search_scope_for', ['level' => $levelLabel])));

                @if ($currentScopeId)
                    ctl.preset({{ $currentScopeId }}, @json($currentRegionName));
                @endif
            })();
        </script>

        <div class="flex items-center gap-3">
            <button type="submit" class="cursor-pointer rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700">
                {{ __('common.save_changes') }}
            </button>
            @if ($modal ?? false)
                <button type="button" data-app-modal-close class="cursor-pointer text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">
                    {{ __('common.cancel') }}
                </button>
            @else
                <a href="{{ route('admin.users.index', ['tab' => $tab]) }}" class="text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">
                    {{ __('common.cancel') }}
                </a>
            @endif
        </div>
    </form>
@endsection

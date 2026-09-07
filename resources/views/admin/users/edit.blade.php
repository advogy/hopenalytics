{{-- $modal: true only when this is fetched by Kelola Pengguna's edit modal (see
     admin/users/index.blade.php's edit-modal JS and UserAssignmentController::edit()) — same
     contract components/entity-crud-form.blade.php's own :modal prop follows (see that
     component's doc comment): skip layouts.app/the back-link/the <h1> so this renders as a bare
     fragment, and stamp data-modal-ajax-form/data-modal-title/a hidden modal=1 field onto the
     form so a resubmit is recognized as modal-originated too. --}}
@extends(($modal ?? false) ? 'layouts.blank' : 'layouts.app')

@section('title', __('users.edit_title') . ' — ' . config('app.name'))

@section('content')
    @unless ($modal ?? false)
        <x-back-link :href="route('admin.users.index', ['tab' => $tab])">{{ __('common.back') }}</x-back-link>

        <h1 class="mb-1 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('users.edit_title') }}</h1>
        <p class="mb-8 text-sm text-slate-500 dark:text-slate-400">{{ $target->email }}</p>
    @endunless

    <form
        method="POST"
        action="{{ route('admin.users.update', $target) }}"
        data-disable-on-submit
        @if ($modal ?? false) data-modal-ajax-form data-modal-title="{{ __('users.edit_title') }}" @endif
        class="max-w-lg rounded-2xl border border-black/5 bg-white p-6 shadow-sm dark:border-white/5 dark:bg-slate-900"
    >
        @csrf
        @method('PUT')
        <input type="hidden" name="tab" value="{{ $tab }}">
        @if ($modal ?? false)
            <input type="hidden" name="modal" value="1">
            <p class="mb-5 text-sm text-slate-500 dark:text-slate-400">{{ $target->email }}</p>
        @endif

        <x-form-field name="name" :label="__('users.edit_name_label')" :hint="__('users.edit_name_hint')" required :value="$target->name" />

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

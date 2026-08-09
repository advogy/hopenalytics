@extends('layouts.app')

@section('title', ($church->exists ? __('entity.title_edit_church') : __('entity.title_add_church')) . ' — ' . config('app.name'))

@section('content')
    <a
        href="{{ route('admin.accounts.index', ['tab' => 'gereja']) }}"
        class="mb-4 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400"
    >
        &larr; {{ __('common.back') }}
    </a>

    <h1 class="mb-8 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">
        {{ $church->exists ? __('entity.title_edit_church') : __('entity.title_add_church') }}
    </h1>

    <form
        method="POST"
        action="{{ $church->exists ? route('churches.update', $church) : route('churches.store') }}"
        class="max-w-lg rounded-2xl border border-black/5 bg-white p-6 shadow-sm dark:border-white/5 dark:bg-slate-900"
    >
        @csrf
        @if ($church->exists)
            @method('PUT')
        @endif

        <x-form-field name="name" :label="__('entity.church_name')" required :value="$church->name" :placeholder="__('entity.name_placeholder')" />
        <div id="name-similar-results" class="hidden mb-5 -mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm dark:border-amber-900 dark:bg-amber-950"></div>
        <script>
            window.initSimilarNameCheck(document.getElementById('name'), document.getElementById('name-similar-results'), {
                url: '{{ route('churches.similar') }}',
                excludeId: {{ $church->exists ? $church->id : 'null' }},
            });
        </script>

        <x-form-field name="city" :label="__('entity.city')" :hint="__('entity.city_optional')" :value="$church->city" :placeholder="__('entity.city_placeholder')" />

        <x-form-field name="logo_url" :label="__('entity.logo_url')" :hint="__('entity.city_optional')" type="url" :value="$church->logo_url" placeholder="https://..." />

        <x-coordinate-fields :latitude="$church->latitude" :longitude="$church->longitude" />

        <div class="mb-5">
            <label class="mb-1.5 block text-sm font-medium">{{ __('entity.conference') }}</label>
            @if ($canPickConference)
                @if ($unions->isNotEmpty())
                    {{-- Nasional-level: full Uni → Daerah cascade. Only conference_id is ever
                         actually submitted (name="conference_id" below) — this hidden input's
                         value is pre-filled purely so initUnionConferenceCascade() can preselect
                         the right Uni on page load without a round-trip; it derives that from
                         the church's own conference since a church itself never independently
                         "belongs" to a Uni the way an Institution can. --}}
                    <div class="mb-2 relative" data-searchable-select data-church-union>
                        <input type="hidden" data-searchable-select-value value="{{ $church->conference?->union_id }}">
                        <input
                            type="text"
                            data-searchable-select-search
                            autocomplete="off"
                            placeholder="{{ __('entity.search_uni_placeholder') }}"
                            class="w-full rounded-lg border border-black/10 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none dark:border-white/10 dark:bg-slate-800"
                        >
                        <ul data-searchable-select-list class="absolute left-0 top-full z-20 mt-1 hidden max-h-52 w-full overflow-y-auto rounded-lg border border-black/10 bg-white p-1 text-sm shadow-lg dark:border-white/10 dark:bg-slate-800"></ul>
                    </div>
                @endif
                <div class="relative" data-searchable-select data-church-conference>
                    <input type="hidden" name="conference_id" data-searchable-select-value value="{{ old('conference_id', $church->conference_id) }}">
                    <input
                        type="text"
                        data-searchable-select-search
                        autocomplete="off"
                        placeholder="{{ __('entity.search_daerah_placeholder') }}"
                        class="w-full rounded-lg border border-black/10 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none dark:border-white/10 dark:bg-slate-800"
                    >
                    <ul data-searchable-select-list class="absolute left-0 top-full z-20 mt-1 hidden max-h-52 w-full overflow-y-auto rounded-lg border border-black/10 bg-white p-1 text-sm shadow-lg dark:border-white/10 dark:bg-slate-800"></ul>
                </div>
                @error('conference_id')
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror

                <script>
                    window.initUnionConferenceCascade({
                        unionSelector: '[data-church-union]',
                        conferenceSelector: '[data-church-conference]',
                        unions: @json($unions->map(fn ($u) => ['id' => $u->id, 'label' => $u->name])),
                        conferences: @json($conferences->map(fn ($c) => ['id' => $c->id, 'union_id' => $c->union_id, 'label' => $c->name])),
                        unionPlaceholder: @json(__('entity.search_uni_placeholder')),
                        conferencePlaceholder: @json(__('entity.search_daerah_placeholder')),
                        conferenceWaitingPlaceholder: @json(__('accounts.waiting_for_uni')),
                    });
                </script>
            @else
                <p class="rounded-lg border border-black/10 bg-slate-50 px-3 py-2 text-sm text-slate-500 dark:border-white/10 dark:bg-slate-800 dark:text-slate-400">
                    {{ $church->conference ? "{$church->conference->name} ({$church->conference->union->name})" : __('entity.conference_unassigned') }}
                </p>
            @endif
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700">
                {{ $church->exists ? __('common.save_changes') : __('entity.title_add_church') }}
            </button>
            <a href="{{ route('admin.accounts.index', ['tab' => 'gereja']) }}" class="text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">
                {{ __('common.cancel') }}
            </a>
        </div>
    </form>

    @if ($church->exists)
        <form
            method="POST"
            action="{{ route('churches.toggle-active', $church) }}"
            class="mt-6 max-w-lg"
            @if ($church->is_active) data-confirm="{{ __('entity.deactivate_church_confirm') }}" @endif
        >
            @csrf
            @method('PATCH')
            <button type="submit" class="text-sm text-red-600 hover:underline dark:text-red-400">
                {{ $church->is_active ? __('entity.deactivate_church') : __('entity.activate_church') }}
            </button>
        </form>
    @endif
@endsection

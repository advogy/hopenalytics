@extends('layouts.app')

@section('title', __('accounts.no_socials_title') . ' — ' . config('app.name'))

@section('content')
    <a href="{{ route('admin.accounts.index') }}" class="mb-4 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400">
        &larr; {{ __('nav.manage_accounts') }}
    </a>

    <div class="mb-6">
        <h1 class="mb-1 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('accounts.no_socials_title') }}</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('accounts.no_socials_subtitle') }}</p>
    </div>

    @if ($churches->isEmpty() && $institutions->isEmpty() && $people->isEmpty())
        <div class="rounded-2xl border border-dashed border-slate-300 p-12 text-center dark:border-slate-700">
            <p class="text-slate-500 dark:text-slate-400">{{ __('accounts.no_socials_empty') }}</p>
        </div>
    @else
        <div class="space-y-8">
            <section>
                <h2 class="mb-3 text-lg font-bold text-slate-900 dark:text-white">
                    {{ __('common.church') }} <span class="font-normal text-slate-400">({{ $churches->count() }})</span>
                </h2>

                @if ($churches->isEmpty())
                    <p class="text-sm text-slate-400 dark:text-slate-500">{{ __('accounts.no_socials_section_empty', ['entity' => __('common.church')]) }}</p>
                @else
                    <div class="overflow-x-auto rounded-2xl border border-black/5 dark:border-white/5">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 dark:bg-slate-800/60">
                                <tr>
                                    <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('common.name') }}</th>
                                    <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('entity.city') }}</th>
                                    <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('common.conference') }}</th>
                                    <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-800 dark:bg-slate-900">
                                @foreach ($churches as $church)
                                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                        <td class="px-4 py-3 font-medium">{{ $church->name }}</td>
                                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $church->city ?? '—' }}</td>
                                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">
                                            @if ($church->conference)
                                                {{ $church->conference->name }}
                                                <span class="text-slate-400">({{ $church->conference->union->name }})</span>
                                            @else
                                                <span class="text-amber-600 dark:text-amber-400">{{ __('common.not_assigned') }}</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            @can('update', $church)
                                                <a href="{{ route('churches.socials.index', $church) }}" class="text-xs text-slate-400 hover:text-blue-600 dark:text-slate-500 dark:hover:text-blue-400">
                                                    {{ __('accounts.no_socials_add_link') }}
                                                </a>
                                            @endcan
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <section>
                <h2 class="mb-3 text-lg font-bold text-slate-900 dark:text-white">
                    {{ __('common.institution') }} <span class="font-normal text-slate-400">({{ $institutions->count() }})</span>
                </h2>

                @if ($institutions->isEmpty())
                    <p class="text-sm text-slate-400 dark:text-slate-500">{{ __('accounts.no_socials_section_empty', ['entity' => __('common.institution')]) }}</p>
                @else
                    <div class="overflow-x-auto rounded-2xl border border-black/5 dark:border-white/5">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 dark:bg-slate-800/60">
                                <tr>
                                    <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('common.name') }}</th>
                                    <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('accounts.region') }}</th>
                                    <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-800 dark:bg-slate-900">
                                @foreach ($institutions as $institution)
                                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                        <td class="px-4 py-3 font-medium">{{ $institution->name }}</td>
                                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">
                                            @if ($institution->conference)
                                                {{ $institution->conference->name }}
                                                <span class="text-slate-400">({{ $institution->conference->union->name }})</span>
                                            @elseif ($institution->union)
                                                {{ $institution->union->name }}
                                            @else
                                                <span class="text-slate-400">{{ __('common.national') }}</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            @can('update', $institution)
                                                <a href="{{ route('admin.institutions.socials.index', $institution) }}" class="text-xs text-slate-400 hover:text-blue-600 dark:text-slate-500 dark:hover:text-blue-400">
                                                    {{ __('accounts.no_socials_add_link') }}
                                                </a>
                                            @endcan
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <section>
                <h2 class="mb-3 text-lg font-bold text-slate-900 dark:text-white">
                    {{ __('common.personal') }} <span class="font-normal text-slate-400">({{ $people->count() }})</span>
                </h2>

                @if ($people->isEmpty())
                    <p class="text-sm text-slate-400 dark:text-slate-500">{{ __('accounts.no_socials_section_empty', ['entity' => __('common.personal')]) }}</p>
                @else
                    <div class="overflow-x-auto rounded-2xl border border-black/5 dark:border-white/5">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 dark:bg-slate-800/60">
                                <tr>
                                    <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('common.name') }}</th>
                                    <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('entity.city') }}</th>
                                    <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{{ __('accounts.scope') }}</th>
                                    <th class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-800 dark:bg-slate-900">
                                @foreach ($people as $person)
                                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                        <td class="px-4 py-3 font-medium">{{ $person->name }}</td>
                                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $person->city ?? '—' }}</td>
                                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">
                                            @if ($person->conference)
                                                {{ $person->conference->name }}
                                                <span class="text-slate-400">({{ $person->conference->union->name }})</span>
                                            @elseif ($person->union)
                                                {{ $person->union->name }}
                                            @else
                                                <span class="text-slate-400">{{ __('common.independent') }}</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            @can('update', $person)
                                                <a href="{{ route('people.socials.index', $person) }}" class="text-xs text-slate-400 hover:text-blue-600 dark:text-slate-500 dark:hover:text-blue-400">
                                                    {{ __('accounts.no_socials_add_link') }}
                                                </a>
                                            @endcan
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>
    @endif
@endsection

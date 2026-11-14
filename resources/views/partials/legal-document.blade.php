{{--
    The body of a legal page (Kebijakan Privasi / Syarat Layanan): title, last-updated date,
    intro, then each section from lang/{locale}/legal.php's $sectionsKey array. Each section has
    an anchor id (e.g. #hapus-data, linked as Meta's data deletion instructions URL), and its
    text may use :app and :email.
--}}
@php
    $mailto = '<a href="mailto:info@hopenalytics.id" class="font-medium text-blue-600 hover:underline dark:text-blue-400">info@hopenalytics.id</a>';
    $fill = fn (string $text) => strtr($text, [':app' => e(config('app.name')), ':email' => $mailto]);
@endphp

<h1 class="mb-1 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __($titleKey) }}</h1>
<p class="mb-8 text-sm text-slate-500 dark:text-slate-400">{{ config('app.name') }} — {{ __('legal.updated_on') }}</p>

<div class="space-y-6 rounded-2xl border border-black/5 bg-white p-6 shadow-sm dark:border-white/5 dark:bg-slate-900">
    <div class="max-w-3xl space-y-3 text-sm text-slate-600 dark:text-slate-300">
        <p>{{ __($introKey, ['app' => config('app.name')]) }}</p>
    </div>

    @foreach (__($sectionsKey) as $section)
        <section id="{{ $section['id'] }}" class="scroll-mt-20">
            <h2 class="mb-2 text-lg font-bold text-slate-900 dark:text-white">{{ $section['title'] }}</h2>
            @if (! empty($section['paragraphs']))
                <div class="max-w-3xl space-y-2 text-sm text-slate-600 dark:text-slate-300">
                    @foreach ($section['paragraphs'] as $paragraph)
                        <p>{!! $fill($paragraph) !!}</p>
                    @endforeach
                </div>
            @endif
            @if (! empty($section['items']))
                <ul @class(['max-w-3xl list-disc space-y-1 pl-5 text-sm text-slate-600 dark:text-slate-300', 'mt-2' => ! empty($section['paragraphs'])])>
                    @foreach ($section['items'] as $item)
                        <li>{!! $fill($item) !!}</li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endforeach
</div>

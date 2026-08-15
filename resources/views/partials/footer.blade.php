@php $stacked = $stacked ?? false; @endphp

<footer class="{{ $stacked ? 'mt-6' : 'border-t border-black/5 dark:border-white/5' }}">
    <div class="{{ $stacked ? 'flex flex-col items-center gap-1 text-center' : 'mx-auto flex max-w-6xl flex-col items-center gap-1 px-6 py-6 sm:flex-row sm:justify-between sm:text-left' }} text-xs text-slate-400 dark:text-slate-500">
        <p>
            &copy; {{ now()->year }} {{ config('app.name') }} {{ __('nav.footer_by') }} <a href="https://adventistasia.studio" target="_blank" rel="noopener noreferrer" class="hover:text-blue-600 dark:hover:text-blue-400"><b>Adventist Media Center Southern Asia-Pacific Division</b></a> {{ __('nav.footer_and') }} <a href="https://www.hopenchannel.id" target="_blank" rel="noopener noreferrer" class="hover:text-blue-600 dark:hover:text-blue-400"><b>Hope Channel Indonesia</b></a>. <br>{{ __('nav.footer_copyright') }}
            {{ __('nav.footer_developer') }}
            <a href="{{ config('app.developer_url') }}" target="_blank" rel="noopener noreferrer" class="hover:text-blue-600 dark:hover:text-blue-400"><b>{{ config('app.developer') }}</b></a>.
        </p>
        <p>{{ __('nav.footer_version', ['version' => config('app.version')]) }}</p>
    </div>
</footer>

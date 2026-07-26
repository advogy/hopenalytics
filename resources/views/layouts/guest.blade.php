<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <script>
            (function () {
                // Light is the default for everyone until they explicitly switch — no falling
                // back to the OS/browser's prefers-color-scheme like before.
                if (localStorage.getItem('theme') === 'dark') {
                    document.documentElement.classList.add('dark');
                }
            })();
        </script>

        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>@yield('title', config('app.name'))</title>

        <link rel="icon" type="image/svg+xml" href="{{ asset('images/hopenalytics-mark.svg') }}">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @include('partials.searchable-select')
    </head>
    <body class="flex min-h-screen flex-col items-center justify-center bg-[#f8f4ec] px-4 py-8 font-sans text-slate-900 antialiased dark:bg-[#16130f] dark:text-slate-100">
        @php $wide = $wide ?? false; @endphp
        <div class="w-full {{ $wide ? 'max-w-4xl' : 'max-w-sm' }}">
            <a href="{{ route('churches.index') }}" class="mb-8 flex items-center justify-center gap-3 whitespace-nowrap">
                <x-logo-mark class="h-16 w-16 shrink-0 text-blue-600 dark:text-[#f3ead9]" />
                <span class="text-3xl font-semibold tracking-tight">Hopenalytics</span>
            </a>

            @if (session('status'))
                <div class="mb-4 flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-400">
                    {{ session('status') }}
                </div>
            @endif

            @if (session('error'))
                <div class="mb-4 flex items-center gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-400">
                    {{ session('error') }}
                </div>
            @endif

            @if ($wide)
                @yield('content')
            @else
                <div class="rounded-2xl border border-black/5 bg-white p-6 shadow-sm dark:border-white/5 dark:bg-slate-900">
                    @yield('content')
                </div>
            @endif

            @include('partials.footer', ['stacked' => true])
        </div>

        @include('partials.confirm-dialog')
        @include('partials.disable-on-submit')
        @include('partials.password-toggle')
        @include('partials.floating-widgets')
    </body>
</html>

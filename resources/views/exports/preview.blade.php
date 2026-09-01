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
        <title>{{ __('nav.export_preview_title') }} — {{ $dataset['title'] }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-[#f8f4ec] font-sans text-slate-900 antialiased dark:bg-[#16130f] dark:text-slate-100">
        <main class="mx-auto w-full max-w-4xl px-6 py-10">
            <x-back-link :href="url()->previous()">{{ __('common.back') }}</x-back-link>

            @include('exports._content')
        </main>
    </body>
</html>

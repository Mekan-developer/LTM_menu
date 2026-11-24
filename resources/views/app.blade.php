<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="theme-color" content="#1F4A3A">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <link rel="manifest" href="/manifest.webmanifest">
        <title inertia>{{ config('app.name') }}</title>
        @php
            // Тёмная тема персонала (useTheme.js) — ставим класс до загрузки JS, чтобы не мигал светлый фон.
            // Гостевые страницы, кухня и печатные формы тему не меняют.
            $component = $page['component'] ?? '';
            $themed = ! str_starts_with($component, 'Guest/') && ! str_starts_with($component, 'Kitchen/')
                && ! in_array($component, ['Tables/Print', 'Cashier/Receipt'], true);
        @endphp
        @if ($themed)
            <script>
                try {
                    var s = localStorage.getItem('staff-theme'), o = localStorage.getItem('waiter-theme');
                    if (!s && o) s = o === 'day' ? 'light' : 'dark';
                    var d = s ? s === 'dark' : (@js(str_starts_with($component, 'Waiter/')) || matchMedia('(prefers-color-scheme: dark)').matches);
                    if (d) document.documentElement.classList.add('theme-night');
                } catch (e) {}
            </script>
        @endif
        @routes
        @vite(['resources/js/app.ts', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body>
        @inertia
    </body>
</html>

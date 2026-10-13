<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="PAL Market connects buyers of construction materials in Tanzania with approved sellers. List what you need, compare price offers and track your order. Kiswahili and English.">
        <meta name="theme-color" content="#FFFFFF">
        <meta name="color-scheme" content="light dark">
        <link rel="icon" type="image/svg+xml" href="/favicon.svg">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        {{-- Sets the saved light/dark theme before first paint (external file: the CSP forbids inline scripts). --}}
        <script src="/theme-init.js"></script>

        <title>PAL Market</title>
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.jsx'])
    </head>

    <body>
        <noscript>PAL Market needs JavaScript to run. / PAL Market inahitaji JavaScript ili kufanya kazi.</noscript>
        <div id="app"></div>
    </body>
</html>

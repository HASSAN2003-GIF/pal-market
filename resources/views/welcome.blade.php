<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="PAL Market connects buyers of construction materials in Tanzania with approved suppliers. Post a materials list, compare quotations, and raise purchase orders.">
        <meta name="theme-color" content="#1A2430">
        <link rel="icon" type="image/svg+xml" href="/favicon.svg">

        <title>PAL Market — construction materials, quoted</title>
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.jsx'])
    </head>

    <body>
        <noscript>PAL Market needs JavaScript enabled to run.</noscript>
        <div id="app"></div>
    </body>
</html>

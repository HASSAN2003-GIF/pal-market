<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>PAL Market</title>

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.jsx'])
    </head>

    <body class="bg-white text-slate-900 antialiased">
        <div id="app"></div>
    </body>
</html>
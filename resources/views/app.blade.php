<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <x-inertia::head>
            <title>{{ config('app.name') }}</title>
        </x-inertia::head>
    </head>
    <body>
        <x-inertia::app />
        <noscript>This app requires JavaScript to display its Vue pages.</noscript>
    </body>
</html>

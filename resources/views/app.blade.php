<!DOCTYPE html>
{{-- The console host carries the console theme tokens on <html> so portalled
     shadcn overlays (dialog, tooltip, sheet) inherit them too. --}}
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark', 'console-theme' => request()->getHost() === config('tenancy.console_domain')])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts


        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', $pageVitePath])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>

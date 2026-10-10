<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full overflow-y-auto scrollbar-thin scrollbar-thumb-gray-500 scrollbar-track-gray-700">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <link rel="shortcut icon" href="{{ asset('img/favicon.ico') }}" type="image/x-icon">

        <title>{{ __($title) . ' | ' . config('app.name', 'Laravel') }}</title>

        <style>
            [x-cloak] {
                display: none !important;
            }
        </style>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @stack('styles')
    </head>
    <body class="font-sans antialiased dark-mode flex flex-col h-full" x-data="{sidebar: false}">
        <x-layouts.backend.navigation />
        <x-layouts.messages />
        <x-layouts.backend.sidebar />

        <livewire:message-alerts />

        <!-- Page Content -->
        <main class="p-2">
            {{ $slot }}
        </main>

        <footer class="text-center mt-auto p-4">
            <div class="text-sm">{{ config('app.name', 'Laravel') }} @ {{ now()->year }}</div>
            @if ( !app()->isProduction() )
                <div class="text-xs">Laravel v{{ Illuminate\Foundation\Application::VERSION }} (PHP v{{ PHP_VERSION }})</div>
            @endif
        </footer>

        <div>
            @livewire('blockpc-sidebar-notifications', [], key('blockpc-sidebar-notifications'))
            @livewire('custom-modal')
        </div>
        @livewireScripts
        @stack('scripts')
    </body>
</html>

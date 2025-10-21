<!DOCTYPE html>
<html lang="ru" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    {{-- ДИНАМИЧЕСКИЙ TITLE --}}
    <title>
        @hasSection('title')
            @yield('title')
        @else
            {{ config('app.name', 'High VPN') }}
        @endif
    </title>

    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>[x-cloak]{display:none!important}</style>
    @stack('head')
    @stack('styles')
</head>
<body class="min-h-screen flex flex-col bg-gray-50">
<header class="bg-white shadow-sm">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
        <div class="flex justify-between items-center">
            <div class="flex items-center">
                {{-- БЕЗ ССЫЛКИ/ПЕРЕХОДА В АДМИНКУ --}}
                <span class="text-sm sm:text-base font-semibold text-gray-800">
                    {{ config('app.name', 'High VPN') }}
                </span>
                @hasSection('header-subtitle')
                    <span class="ml-3 text-gray-400">•</span>
                    <span class="ml-3 text-xs sm:text-sm text-gray-500">@yield('header-subtitle')</span>
                @endif
            </div>
            @yield('header-right')
        </div>
    </div>
</header>

<main class="flex-1 bg-gray-100">
    @yield('content')
</main>

<footer class="bg-white shadow-lg">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
        <div class="text-center text-sm text-gray-500">
            &copy; {{ date('Y') }} {{ config('app.name', 'High VPN') }}. Все права защищены.
        </div>
    </div>
</footer>

@stack('scripts')
</body>
</html>

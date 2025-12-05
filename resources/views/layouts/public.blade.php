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
    <script src="https://cdn.tailwindcss.com" onerror="this.onerror=null; document.getElementById('tailwind-fallback').disabled=false;"></script>
    <style id="tailwind-fallback" disabled>
        /* Fallback стили для работы без Tailwind CDN */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: system-ui, -apple-system, sans-serif; line-height: 1.5; }
        .max-w-6xl { max-width: 72rem; margin: 0 auto; }
        .px-4 { padding-left: 1rem; padding-right: 1rem; }
        .py-8 { padding-top: 2rem; padding-bottom: 2rem; }
        .mb-8 { margin-bottom: 2rem; }
        .text-center { text-align: center; }
        .text-4xl { font-size: 2.25rem; }
        .font-bold { font-weight: 700; }
        .text-gray-900 { color: #111827; }
        .bg-white { background-color: #fff; }
        .rounded-2xl { border-radius: 1rem; }
        .shadow-lg { box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
        .p-6 { padding: 1.5rem; }
        .mb-6 { margin-bottom: 1.5rem; }
        .grid { display: grid; }
        .gap-4 { gap: 1rem; }
        .gap-6 { gap: 1.5rem; }
        .grid-cols-1 { grid-template-columns: repeat(1, minmax(0, 1fr)); }
        @media (min-width: 768px) { .md\:grid-cols-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (min-width: 1024px) { .lg\:grid-cols-4 { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
        button { cursor: pointer; border: none; padding: 0.75rem 1.5rem; border-radius: 0.5rem; font-weight: 600; transition: all 0.2s; }
        .bg-blue-600 { background-color: #2563eb; }
        .text-white { color: #fff; }
        .hover\:bg-blue-700:hover { background-color: #1d4ed8; }
        .hidden { display: none; }
        .flex { display: flex; }
        .items-center { align-items: center; }
        .justify-between { justify-content: space-between; }
        .w-full { width: 100%; }
        .h-3 { height: 0.75rem; }
        .bg-gray-200 { background-color: #e5e7eb; }
        .rounded-full { border-radius: 9999px; }
        .transition-all { transition-property: all; transition-timing-function: cubic-bezier(0.4,0,0.2,1); transition-duration: 150ms; }
        .text-sm { font-size: 0.875rem; }
        .font-medium { font-weight: 500; }
        .text-gray-600 { color: #4b5563; }
        .text-gray-500 { color: #6b7280; }
        .py-4 { padding-top: 1rem; padding-bottom: 1rem; }
        .px-6 { padding-left: 1.5rem; padding-right: 1.5rem; }
        .border-b { border-bottom-width: 1px; border-bottom-color: #e5e7eb; }
        .space-y-3 > * + * { margin-top: 0.75rem; }
        .space-y-4 > * + * { margin-top: 1rem; }
        .mt-8 { margin-top: 2rem; }
        .mt-6 { margin-top: 1.5rem; }
        .pt-6 { padding-top: 1.5rem; }
        .border-t { border-top-width: 1px; border-top-color: #e5e7eb; }
        .bg-green-600 { background-color: #16a34a; }
        .hover\:bg-green-700:hover { background-color: #15803d; }
        .text-2xl { font-size: 1.5rem; }
        .text-xl { font-size: 1.25rem; }
        .mb-4 { margin-bottom: 1rem; }
        .mb-2 { margin-bottom: 0.5rem; }
        .mb-3 { margin-bottom: 0.75rem; }
        .p-4 { padding: 1rem; }
        .bg-gray-50 { background-color: #f9fafb; }
        .rounded-lg { border-radius: 0.5rem; }
        .text-lg { font-size: 1.125rem; }
        .font-semibold { font-weight: 600; }
        .bg-yellow-50 { background-color: #fefce8; }
        .bg-blue-50 { background-color: #eff6ff; }
        .border-l-4 { border-left-width: 4px; }
        .border-yellow-400 { border-color: #facc15; }
        .border-blue-400 { border-color: #60a5fa; }
        .text-yellow-800 { color: #854d0e; }
        .text-blue-800 { color: #1e40af; }
        .text-yellow-700 { color: #a16207; }
        .text-blue-700 { color: #1d4ed8; }
        .items-start { align-items: flex-start; }
        .flex-shrink-0 { flex-shrink: 0; }
        .ml-3 { margin-left: 0.75rem; }
        .flex-1 { flex: 1 1 0%; }
        .gap-2 { gap: 0.5rem; }
        .bg-yellow-600 { background-color: #ca8a04; }
        .hover\:bg-yellow-700:hover { background-color: #a16207; }
        .bg-gray-200 { background-color: #e5e7eb; }
        .hover\:bg-gray-300:hover { background-color: #d1d5db; }
        .text-gray-700 { color: #374151; }
        .disabled { opacity: 0.5; cursor: not-allowed; }
        .opacity-50 { opacity: 0.5; }
    </style>
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

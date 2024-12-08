<!DOCTYPE html>
<html lang="ru" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VPN Configuration</title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Custom Styles -->
    <style>
        [x-cloak] { display: none !important; }
    </style>

    @stack('styles')
</head>
<body class="min-h-screen flex flex-col bg-gray-50">
    <!-- Header -->
    <header class="bg-white shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center">
{{--                    <a href="/" class="text-xl font-bold text-gray-900">--}}
{{--                        VPN Configuration--}}
{{--                    </a>--}}
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-1 bg-gray-100">
        @yield('content')
    </main>

    <!-- Footer -->
    <footer class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <div class="text-center text-sm text-gray-500">
                &copy; {{ date('Y') }} VPN Configuration. Все права защищены.
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    @stack('scripts')
</body>
</html>

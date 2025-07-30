<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Личный кабинет | High VPN Bot-t</title>
    <link rel="icon"
          href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🔒</text></svg>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --dark: #1e293b;
            --light: #f8fafc;
            --gray: #94a3b8;
        }

        .sidebar {
            width: 250px;
            transition: all 0.3s;
        }

        .sidebar-collapsed {
            width: 80px;
        }

        .content {
            margin-left: 250px;
            transition: all 0.3s;
        }

        .content-collapsed {
            margin-left: 80px;
        }

        .nav-link {
            transition: all 0.2s;
        }

        .nav-link:hover {
            background-color: rgba(99, 102, 241, 0.1);
        }

        .nav-link.active {
            background-color: rgba(99, 102, 241, 0.2);
            border-left: 3px solid var(--primary);
        }

        .stat-card {
            transition: all 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body class="bg-gray-50">
<div class="flex h-screen">
    <!-- Боковое меню -->
    <div class="sidebar bg-white shadow-md fixed h-full">
        <div class="p-4 border-b border-gray-200 flex items-center justify-center h-16">
            <div class="flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-indigo-500" fill="none" viewBox="0 0 24 24"
                     stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
                <span class="ml-2 text-xl font-bold text-indigo-600">High VPN</span>
            </div>
        </div>

        <div class="p-4">
            <ul class="space-y-2">
                <li>
                    <a href="{{ route('personal.dashboard') }}"
                       class="nav-link flex items-center p-3 rounded-lg {{ request()->routeIs('personal.dashboard') ? 'active' : '' }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-600" fill="none"
                             viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                        </svg>
                        <span class="ml-3">Главная</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('personal.keys') }}"
                       class="nav-link flex items-center p-3 rounded-lg {{ request()->routeIs('personal.keys') ? 'active' : '' }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-600" fill="none"
                             viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                        </svg>
                        <span class="ml-3">Управление ключами</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('personal.stats') }}"
                       class="nav-link flex items-center p-3 rounded-lg {{ request()->routeIs('personal.stats') ? 'active' : '' }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-600" fill="none"
                             viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                        <span class="ml-3">Статистика продаж</span>
                    </a>
                </li>
{{--                <li>--}}
{{--                    <a href="{{ route('personal.packages') }}"--}}
{{--                       class="nav-link flex items-center p-3 rounded-lg {{ request()->routeIs('personal.packages') ? 'active' : '' }}">--}}
{{--                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-600" fill="none"--}}
{{--                             viewBox="0 0 24 24" stroke="currentColor">--}}
{{--                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"--}}
{{--                                  d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>--}}
{{--                        </svg>--}}
{{--                        <span class="ml-3">Пакеты ключей</span>--}}
{{--                    </a>--}}
{{--                </li>--}}
                <li>
                    <a href="{{ route('personal.faq') }}"
                       class="nav-link flex items-center p-3 rounded-lg {{ request()->routeIs('personal.settings') ? 'active' : '' }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-600" fill="none"
                             viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        <span class="ml-3">База знаний FAQ</span>
                    </a>
                </li>
            </ul>
        </div>

        <div class="absolute bottom-0 w-full p-4 border-t border-gray-200">
            <form action="{{ route('personal.logout') }}" method="POST">
                @csrf
                <button type="submit"
                        class="w-full flex items-center justify-center p-2 rounded-lg text-gray-600 hover:bg-gray-100">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                         stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                    <span class="ml-3">Выйти</span>
                </button>
            </form>
        </div>
    </div>

    <!-- Основное содержимое -->
    <div class="content flex-1 overflow-y-auto">
        <!-- Шапка -->
        <header class="bg-white shadow-sm">
            <div class="max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8 flex justify-between items-center">
                <h1 class="text-lg font-semibold text-gray-900">
                    @yield('title')
                </h1>

                <div class="flex items-center space-x-4">
                    <div class="flex items-center">
                        <div class="h-8 w-8 rounded-full bg-indigo-100 flex items-center justify-center">
                    <span class="text-indigo-600 font-medium">
                        {{ strtoupper(substr($salesman->name ?? $salesman->username, 0, 1)) }}
                    </span>
                        </div>
                        <span class="ml-2 text-sm font-medium text-gray-700">
                    {{ $salesman->name ?? $salesman->username }}
                </span>
                    </div>
                </div>
            </div>
        </header>

        <!-- Контент -->
        <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
            @yield('content')
        </main>
    </div>
</div>

</body>
</html>

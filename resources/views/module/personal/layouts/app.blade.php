<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
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
            transition: all 0.3s ease;
            transform: translateX(0);
            z-index: 50;
        }

        .prose blockquote {
            border-left: 3px solid #e5e7eb;
            padding-left: 1rem;
            margin: 1rem 0;
            color: #1f2937;
        }
        .prose a {
            color: #4f46e5;
            text-decoration: underline;
        }
        .prose strong {
            font-weight: 600;
        }

        .sidebar-collapsed {
            width: 80px;
        }

        .sidebar-hidden {
            transform: translateX(-100%);
        }

        .content {
            margin-left: 250px;
            transition: all 0.3s ease;
        }

        .content-collapsed {
            margin-left: 80px;
        }

        .content-full {
            margin-left: 0;
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

        .sidebar-toggle {
            display: none;
        }

        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                height: 100vh;
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .sidebar-visible {
                transform: translateX(0);
            }

            .content {
                margin-left: 0;
                width: 100%;
            }

            .content-collapsed {
                margin-left: 0;
            }

            .sidebar-toggle {
                display: block;
            }

            .sidebar-collapsed {
                width: 250px;
            }

            .overlay {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: rgba(0, 0, 0, 0.5);
                z-index: 40;
                display: none;
            }

            .overlay-visible {
                display: block;
            }
        }

        .tooltip {
            position: relative;
        }

        .tooltip .tooltip-text {
            visibility: hidden;
            width: 120px;
            background-color: #333;
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 5px;
            position: absolute;
            z-index: 1;
            left: 100%;
            top: 50%;
            transform: translateY(-50%);
            margin-left: 15px;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .tooltip:hover .tooltip-text {
            visibility: visible;
            opacity: 1;
        }

        .vpn-instructions-preview {
            white-space: pre-line;
            line-height: 1.6;
        }
        .vpn-instructions-preview blockquote {
            border-left: 3px solid #e5e7eb;
            padding-left: 1rem;
            margin: 1rem 0;
            color: #1f2937;
            font-weight: 500;
        }
        .vpn-instructions-preview a {
            color: #4f46e5;
            text-decoration: underline;
        }
        .vpn-instructions-preview strong {
            font-weight: 600;
        }
        .vpn-instructions-preview ul {
            list-style-type: disc;
            padding-left: 1.5rem;
            margin: 0.5rem 0;
        }
        .vpn-instructions-preview li {
            margin-bottom: 0.25rem;
        }
    </style>
</head>
<body class="bg-gray-50">
<div class="flex h-screen">
    <!-- Overlay для мобильных -->
    <div class="overlay" id="overlay"></div>

    <!-- Боковое меню -->
    <div class="sidebar bg-white shadow-md fixed h-full" id="sidebar">
        <div class="p-4 border-b border-gray-200 flex items-center justify-between h-16">
            <div class="flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-indigo-500" fill="none" viewBox="0 0 24 24"
                     stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
                <span class="ml-2 text-xl font-bold text-indigo-600" id="logo-text">High VPN</span>
            </div>
            <button id="collapse-btn" class="p-1 rounded-md hover:bg-gray-100 hidden md:block">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-500" fill="none" viewBox="0 0 24 24"
                     stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </button>
        </div>

        <div class="p-4">
            <ul class="space-y-2">
                <li>
                    <a href="{{ route('personal.dashboard') }}"
                       class="nav-link flex items-center p-3 rounded-lg {{ request()->routeIs('personal.dashboard') ? 'active' : '' }} tooltip">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-600" fill="none"
                             viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                        </svg>
                        <span class="ml-3" id="dashboard-text">Главная</span>
                        <span class="tooltip-text">Главная</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('personal.keys') }}"
                       class="nav-link flex items-center p-3 rounded-lg {{ request()->routeIs('personal.keys') ? 'active' : '' }} tooltip">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-600" fill="none"
                             viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                        </svg>
                        <span class="ml-3" id="keys-text">Управление ключами</span>
                        <span class="tooltip-text">Управление ключами</span>
                    </a>
                </li>
{{--                <li>--}}
{{--                    <a href="{{ route('personal.stats') }}"--}}
{{--                       class="nav-link flex items-center p-3 rounded-lg {{ request()->routeIs('personal.stats') ? 'active' : '' }} tooltip">--}}
{{--                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-600" fill="none"--}}
{{--                             viewBox="0 0 24 24" stroke="currentColor">--}}
{{--                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"--}}
{{--                                  d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>--}}
{{--                        </svg>--}}
{{--                        <span class="ml-3" id="stats-text">Статистика продаж</span>--}}
{{--                        <span class="tooltip-text">Статистика продаж</span>--}}
{{--                    </a>--}}
{{--                </li>--}}
                <li>
                    <a href="{{ route('personal.network.index') }}"
                       class="nav-link flex items-center p-3 rounded-lg {{ request()->routeIs('personal.network.*') ? 'active' : '' }} tooltip">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M3 12h3m12 0h3M7 12a5 5 0 1010 0 5 5 0 00-10 0z"/>
                        </svg>
                        <span class="ml-3" id="network-text">Проверка сети</span>
                        <span class="tooltip-text">Проверка сети</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('personal.faq') }}"
                       class="nav-link flex items-center p-3 rounded-lg {{ request()->routeIs('personal.faq') ? 'active' : '' }} tooltip">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-600" fill="none"
                             viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        <span class="ml-3" id="faq-text">Редактор FAQ</span>
                        <span class="tooltip-text">Редактор FAQ</span>
                    </a>
                </li>
            </ul>
        </div>

        <div class="absolute bottom-0 w-full p-4 border-t border-gray-200">
            <form action="{{ route('personal.logout') }}" method="POST">
                @csrf
                <button type="submit"
                        class="w-full flex items-center justify-center p-2 rounded-lg text-gray-600 hover:bg-gray-100 tooltip">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                         stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                    <span class="ml-3" id="logout-text">Выйти</span>
                    <span class="tooltip-text">Выйти</span>
                </button>
            </form>
        </div>
    </div>

    <!-- Основное содержимое -->
    <div class="content flex-1 overflow-y-auto" id="main-content">
        <!-- Шапка -->
        <header class="bg-white shadow-sm">
            <div class="max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8 flex justify-between items-center">
                <div class="flex items-center">
                    <button id="mobile-menu-btn" class="sidebar-toggle mr-4 p-1 rounded-md hover:bg-gray-100 md:hidden">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-500" fill="none"
                             viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>
                    <h1 class="text-lg font-semibold text-gray-900">
                        @yield('title')
                    </h1>
                </div>

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

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('main-content');
        const collapseBtn = document.getElementById('collapse-btn');
        const mobileMenuBtn = document.getElementById('mobile-menu-btn');
        const overlay = document.getElementById('overlay');
        const logoText = document.getElementById('logo-text');
        const navTexts = [
            document.getElementById('dashboard-text'),
            document.getElementById('keys-text'),
            document.getElementById('stats-text'),
            document.getElementById('faq-text'),
            document.getElementById('logout-text')
        ];

        // Проверяем состояние в localStorage
        const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';

        // Применяем начальное состояние
        if (isCollapsed) {
            collapseSidebar();
        }

        // Обработчик для кнопки сворачивания (десктоп)
        collapseBtn.addEventListener('click', function () {
            if (sidebar.classList.contains('sidebar-collapsed')) {
                expandSidebar();
                localStorage.setItem('sidebarCollapsed', 'false');
            } else {
                collapseSidebar();
                localStorage.setItem('sidebarCollapsed', 'true');
            }
        });

        // Обработчик для кнопки меню (мобильные)
        mobileMenuBtn.addEventListener('click', function () {
            sidebar.classList.add('sidebar-visible');
            overlay.classList.add('overlay-visible');
        });

        // Закрываем меню при клике на overlay
        overlay.addEventListener('click', function () {
            sidebar.classList.remove('sidebar-visible');
            overlay.classList.remove('overlay-visible');
        });

        function collapseSidebar() {
            sidebar.classList.add('sidebar-collapsed');
            mainContent.classList.add('content-collapsed');
            mainContent.classList.remove('content-full');

            // Скрываем текст в навигации
            navTexts.forEach(text => {
                if (text) text.style.display = 'none';
            });
            if (logoText) logoText.style.display = 'none';

            // Меняем иконку кнопки
            collapseBtn.innerHTML = `
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            `;
        }

        function expandSidebar() {
            sidebar.classList.remove('sidebar-collapsed');
            mainContent.classList.remove('content-collapsed');
            mainContent.classList.remove('content-full');

            // Показываем текст в навигации
            navTexts.forEach(text => {
                if (text) text.style.display = 'inline';
            });
            if (logoText) logoText.style.display = 'inline';

            // Меняем иконку кнопки
            collapseBtn.innerHTML = `
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            `;
        }
    });
</script>
</body>
</html>

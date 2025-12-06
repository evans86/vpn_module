<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <title>
        @hasSection('title')
            @yield('title') - {{ config('app.name', 'VPN Admin') }}
        @else
            {{ config('app.name', 'VPN Admin') }}
        @endif
    </title>

    <!-- Favicon -->
    <link rel="icon" href="{{ \App\Helpers\AssetHelper::url('img/favicon.ico') }}">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
          integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA=="
          crossorigin="anonymous" referrerpolicy="no-referrer"/>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Alpine.js для интерактивности -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <!-- jQuery и Toastr -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    
    <!-- Custom Admin Styles -->
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
    
    <style>
        [x-cloak] { display: none !important; }
    </style>

    @stack('css')
    @stack('styles')
</head>
<body class="h-full bg-gray-50 overflow-hidden" 
      x-data="{ 
        sidebarOpen: false, 
        sidebarCollapsed: false,
        isDesktop: window.innerWidth >= 1024,
        init() {
          this.isDesktop = window.innerWidth >= 1024;
          this.sidebarOpen = this.isDesktop;
          const handleResize = () => {
            const wasDesktop = this.isDesktop;
            this.isDesktop = window.innerWidth >= 1024;
            if (this.isDesktop) {
              this.sidebarOpen = true;
            } else if (wasDesktop && !this.isDesktop) {
              // Закрываем sidebar только при переходе с десктопа на мобильный
              this.sidebarOpen = false;
            }
          };
          window.addEventListener('resize', handleResize);
        },
        toggleSidebar() {
          if (!this.isDesktop) {
            this.sidebarOpen = !this.sidebarOpen;
          }
        }
      }">
    <!-- Mobile sidebar overlay -->
    <div x-show="sidebarOpen && !isDesktop" 
         x-cloak
         @click.self="sidebarOpen = false"
         class="fixed inset-0 bg-gray-900 bg-opacity-50 z-40 lg:hidden"></div>

    <div class="flex h-full">
        <!-- Sidebar -->
        <aside :class="[
          sidebarCollapsed ? 'w-20' : 'w-64',
          (sidebarOpen || isDesktop) ? 'translate-x-0' : '-translate-x-full'
        ]"
               x-show="sidebarOpen || isDesktop"
               x-transition:enter="transition ease-out duration-300"
               x-transition:enter-start="-translate-x-full"
               x-transition:enter-end="translate-x-0"
               x-transition:leave="transition ease-in duration-300"
               x-transition:leave-start="translate-x-0"
               x-transition:leave-end="-translate-x-full"
               class="fixed lg:static inset-y-0 left-0 z-50 bg-white border-r border-gray-200 transition-transform duration-300 flex flex-col">
        <!-- Logo -->
        <div class="flex items-center justify-between h-16 px-4 border-b border-gray-200">
            <div class="flex items-center space-x-3" :class="sidebarCollapsed ? 'justify-center w-full' : ''">
                <div class="flex-shrink-0">
                    <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                    </svg>
                </div>
                <span x-show="!sidebarCollapsed" class="text-xl font-bold text-gray-900">VPN Admin</span>
            </div>
            <button @click="sidebarCollapsed = !sidebarCollapsed" 
                    class="hidden lg:block p-1 rounded-md text-gray-400 hover:text-gray-600 hover:bg-gray-100">
                <i class="fas fa-chevron-left" :class="sidebarCollapsed ? 'fa-chevron-right' : ''"></i>
            </button>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 overflow-y-auto py-4 px-2">
            <ul class="space-y-1">
                @include('layouts.admin.nav-item', [
                    'route' => 'admin.module.server.index',
                    'icon' => 'fa-server',
                    'label' => 'Серверы',
                    'routes' => ['admin.module.server.*']
                ])
                @include('layouts.admin.nav-item', [
                    'route' => 'admin.module.panel.index',
                    'icon' => 'fa-desktop',
                    'label' => 'Панели',
                    'routes' => ['admin.module.panel.*']
                ])
                @include('layouts.admin.nav-item', [
                    'route' => 'admin.module.panel-settings.index',
                    'icon' => 'fa-cog',
                    'label' => 'Настройки распределения',
                    'routes' => ['admin.module.panel-settings.*']
                ])
                @include('layouts.admin.nav-item', [
                    'route' => 'admin.module.panel-statistics.index',
                    'icon' => 'fa-chart-bar',
                    'label' => 'Статистика панелей',
                    'routes' => ['admin.module.panel-statistics.*']
                ])
                @include('layouts.admin.nav-item', [
                    'route' => 'admin.module.server-users.index',
                    'icon' => 'fa-users',
                    'label' => 'Пользователи сервера',
                    'routes' => ['admin.module.server-users.*']
                ])
                @include('layouts.admin.nav-item', [
                    'route' => 'admin.module.salesman.index',
                    'icon' => 'fa-user-tie',
                    'label' => 'Продавцы',
                    'routes' => ['admin.module.salesman.*']
                ])
                @include('layouts.admin.nav-item', [
                    'route' => 'admin.module.pack.index',
                    'icon' => 'fa-box',
                    'label' => 'Пакеты',
                    'routes' => ['admin.module.pack.*']
                ])
                @include('layouts.admin.nav-item', [
                    'route' => 'admin.module.pack-salesman.index',
                    'icon' => 'fa-file-invoice',
                    'label' => 'Пакеты продавцов',
                    'routes' => ['admin.module.pack-salesman.*']
                ])
                @include('layouts.admin.nav-item', [
                    'route' => 'admin.module.key-activate.index',
                    'icon' => 'fa-key',
                    'label' => 'Активация ключей',
                    'routes' => ['admin.module.key-activate.*']
                ])
                @include('layouts.admin.nav-item', [
                    'route' => 'admin.module.bot.index',
                    'icon' => 'fa-robot',
                    'label' => 'Настройки бота',
                    'routes' => ['admin.module.bot.*']
                ])
                @include('layouts.admin.nav-item', [
                    'route' => 'admin.module.telegram-users.index',
                    'icon' => 'fa-user-friends',
                    'label' => 'Пользователи',
                    'routes' => ['admin.module.telegram-users.*']
                ])
                @include('layouts.admin.nav-item', [
                    'route' => 'admin.module.server-monitoring.index',
                    'icon' => 'fa-chart-line',
                    'label' => 'Статистика',
                    'routes' => ['admin.module.server-monitoring.*']
                ])
                @include('layouts.admin.nav-item', [
                    'route' => 'admin.module.connection-limit-violations.index',
                    'icon' => 'fa-shield-alt',
                    'label' => 'Лимиты подключений',
                    'routes' => ['admin.module.connection-limit-violations.*']
                ])
                @include('layouts.admin.nav-item', [
                    'route' => 'admin.logs.index',
                    'icon' => 'fa-file-alt',
                    'label' => 'Логи',
                    'routes' => ['admin.logs.*']
                ])
            </ul>
        </nav>
        </aside>

        <!-- Main content -->
        <div class="flex-1 flex flex-col overflow-hidden transition-all duration-300">
        <!-- Header -->
        <header class="bg-white border-b border-gray-200 h-16 flex items-center justify-between px-4 lg:px-6 flex-shrink-0">
            <div class="flex items-center space-x-4">
                <button @click="toggleSidebar()" 
                        class="lg:hidden p-2 rounded-md text-gray-400 hover:text-gray-600 hover:bg-gray-100">
                    <i class="fas fa-bars text-xl"></i>
                </button>
                <h1 class="text-xl font-semibold text-gray-900">
                    @hasSection('page-title')
                        @yield('page-title')
                    @else
                        @yield('title', 'Панель управления')
                    @endif
                </h1>
            </div>

            <div class="flex items-center space-x-4">
                @auth
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" 
                                class="flex items-center space-x-2 px-3 py-2 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-100 focus:outline-none">
                            <div class="h-8 w-8 rounded-full bg-indigo-100 flex items-center justify-center">
                                <span class="text-indigo-600 font-medium text-sm">
                                    {{ strtoupper(substr(Auth::user()->name ?? 'A', 0, 1)) }}
                                </span>
                            </div>
                            <span class="hidden sm:block">{{ Auth::user()->name }}</span>
                            <i class="fas fa-chevron-down text-xs"></i>
                        </button>
                        
                        <div x-show="open" 
                             @click.away="open = false"
                             x-cloak
                             x-transition
                             class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50 border border-gray-200">
                            <form id="logout-form" action="{{ route('logout') }}" method="POST" class="hidden">
                                @csrf
                            </form>
                            <a href="javascript:void(0);"
                               onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
                               class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-sign-out-alt mr-2 text-red-500"></i>
                                Выход
                            </a>
                        </div>
                    </div>
                @endauth
            </div>
        </header>

        <!-- Page content -->
        <main class="flex-1 overflow-y-auto bg-gray-50 p-4 lg:p-6">
            <!-- Flash messages -->
            @if(session('success'))
                <div class="mb-4 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg flex items-center justify-between" role="alert">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle mr-2"></i>
                        <span>{{ session('success') }}</span>
                    </div>
                    <button type="button" class="text-green-600 hover:text-green-800" onclick="this.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            @endif

            @if(session('error'))
                <div class="mb-4 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg flex items-center justify-between" role="alert">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <span>{{ session('error') }}</span>
                    </div>
                    <button type="button" class="text-red-600 hover:text-red-800" onclick="this.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-4 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg" role="alert">
                    <div class="flex items-center mb-2">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <strong>Ошибки:</strong>
                    </div>
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>
        </div>
    </div>

    <script>
        // Toastr настройки
        toastr.options = {
            "closeButton": true,
            "progressBar": true,
            "positionClass": "toast-top-right",
            "timeOut": "3000",
            "extendedTimeOut": "1000"
        };
    </script>

    @stack('js')
    @stack('scripts')
    
    <!-- Универсальные функции для выпадающих меню -->
    <script>
        // Универсальная функция для позиционирования выпадающих меню
        if (typeof window.positionDropdownMenu === 'undefined') {
            window.positionDropdownMenu = function(menu) {
                const parent = menu.parentElement;
                if (!parent) return;
                
                const buttons = parent.querySelectorAll('button');
                const button = buttons[0];
                if (!button) return;
                
                const buttonRect = button.getBoundingClientRect();
                const menuRect = menu.getBoundingClientRect();
                const viewportHeight = window.innerHeight;
                
                const spaceBelow = viewportHeight - buttonRect.bottom;
                const spaceAbove = buttonRect.top;
                const menuHeight = menuRect.height || 250;
                
                if (spaceBelow < menuHeight + 20 && spaceAbove >= menuHeight + 20) {
                    menu.style.top = 'auto';
                    menu.style.bottom = '100%';
                    menu.style.marginTop = '0';
                    menu.style.marginBottom = '0.5rem';
                    menu.classList.remove('mt-2', 'origin-top-right');
                    menu.classList.add('mb-2', 'origin-bottom-right');
                } else {
                    menu.style.top = '100%';
                    menu.style.bottom = 'auto';
                    menu.style.marginTop = '0.5rem';
                    menu.style.marginBottom = '0';
                    menu.classList.remove('mb-2', 'origin-bottom-right');
                    menu.classList.add('mt-2', 'origin-top-right');
                }
            };

            // Функция для сброса позиции меню
            window.resetDropdownMenu = function(menu) {
                menu.style.top = '';
                menu.style.bottom = '';
                menu.style.marginTop = '';
                menu.style.marginBottom = '';
                menu.classList.remove('mb-2', 'origin-bottom-right');
                menu.classList.add('mt-2', 'origin-top-right');
            };
        }
    </script>
</body>
</html>

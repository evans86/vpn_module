@php
    // Получаем переменные из include
    $route = $route ?? null;
    $icon = $icon ?? 'fa-circle';
    $label = $label ?? '';
    $routes = $routes ?? [];
    $nested = $nested ?? false;

    $isActive = false;
    foreach ($routes as $routePattern) {
        if (request()->routeIs($routePattern)) {
            $isActive = true;
            break;
        }
    }
    if (!$isActive && $route) {
        $isActive = request()->routeIs($route);
    }

    $linkClasses = $nested
        ? 'flex items-center px-2 py-1.5 text-sm rounded-md transition-colors duration-150 ' . ($isActive ? 'bg-indigo-50 text-indigo-700 font-medium' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900')
        : 'flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-150 ' . ($isActive ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900');
@endphp

<li>
    <a href="{{ route($route) }}"
       title="{{ $label }}"
       class="{{ $linkClasses }}"
       x-bind:class="sidebarCollapsed ? 'justify-center' : ''"
       @click="if (window.innerWidth < 1024) { setTimeout(() => { sidebarOpen = false; }, 100); }">
        <i class="fas {{ $icon }} w-5 text-center flex-shrink-0"></i>
        <span x-show="!sidebarCollapsed" x-cloak class="ml-3 truncate">{{ $label }}</span>
    </a>
</li>



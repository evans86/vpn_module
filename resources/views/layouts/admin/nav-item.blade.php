@php
    // Получаем переменные из include
    $route = $route ?? null;
    $icon = $icon ?? 'fa-circle';
    $label = $label ?? '';
    $routes = $routes ?? [];
    
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
@endphp

<li>
    <a href="{{ route($route) }}" 
       class="flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-150 {{ $isActive ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}"
       x-bind:class="sidebarCollapsed ? 'justify-center' : ''"
       @click="if (window.innerWidth < 1024) { setTimeout(() => { sidebarOpen = false; }, 100); }">
        <i class="fas {{ $icon }} w-5 text-center"></i>
        <span x-show="!sidebarCollapsed" x-cloak class="ml-3">{{ $label }}</span>
    </a>
</li>



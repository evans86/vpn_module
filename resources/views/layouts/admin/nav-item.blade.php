@php
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

    if ($nested) {
        $linkClasses = 'admin-nav-nested-link group flex items-center gap-2 pl-2 pr-2 py-1.5 text-xs rounded-md border-l-2 -ml-px transition-colors duration-200 ease-out '
            . ($isActive
                ? 'border-indigo-500 bg-white text-indigo-800 font-medium shadow-sm'
                : 'border-transparent text-gray-600 hover:bg-white hover:text-gray-900 hover:border-gray-200');
    } else {
        $linkClasses = 'flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 '
            . ($isActive ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900');
    }
@endphp

<li>
    <a href="{{ route($route) }}"
       title="{{ $label }}"
       class="{{ $linkClasses }}"
       x-bind:class="sidebarCollapsed ? 'justify-center' : ''"
       @click="if (window.innerWidth < 1024) { setTimeout(() => { sidebarOpen = false; }, 100); }">
        <i class="fas {{ $icon }} w-4 text-center flex-shrink-0 {{ $nested ? ($isActive ? 'text-indigo-600' : 'text-indigo-400 group-hover:text-indigo-600') : '' }}"></i>
        <span x-show="!sidebarCollapsed"
              x-transition:enter="transition ease-out duration-150"
              x-transition:enter-start="opacity-0"
              x-transition:enter-end="opacity-100"
              x-transition:leave="transition ease-in duration-100"
              x-transition:leave-start="opacity-100"
              x-transition:leave-end="opacity-0"
              x-cloak
              class="truncate {{ $nested ? 'leading-snug' : '' }}">{{ $label }}</span>
    </a>
</li>

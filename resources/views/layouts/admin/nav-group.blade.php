@php
    $activeRoutes = $activeRoutes ?? [];
    $isGroupActive = false;
    foreach ($activeRoutes as $pattern) {
        if (request()->routeIs($pattern)) {
            $isGroupActive = true;
            break;
        }
    }
    $icon = $icon ?? 'fa-folder';
    $label = $label ?? '';
    $items = $items ?? [];
@endphp

<li class="relative" x-data="{ open: {{ $isGroupActive ? 'true' : 'false' }} }">
    <button type="button"
            title="{{ $label }}"
            @click="if (sidebarCollapsed) { sidebarCollapsed = false; open = true; } else { open = !open; }"
            class="w-full flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-150 {{ $isGroupActive ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}"
            x-bind:class="sidebarCollapsed ? 'justify-center' : ''"
            :aria-expanded="(!sidebarCollapsed && open) ? 'true' : 'false'">
        <i class="fas {{ $icon }} w-5 text-center flex-shrink-0"></i>
        <span x-show="!sidebarCollapsed" x-cloak class="ml-3 flex-1 text-left">{{ $label }}</span>
        <i x-show="!sidebarCollapsed" x-cloak
           class="fas fa-chevron-down text-xs text-gray-400 transition-transform duration-200 flex-shrink-0"
           :class="open ? 'rotate-180' : ''"></i>
    </button>
    <ul x-show="open && !sidebarCollapsed"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0 -translate-y-0.5"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-cloak
        class="mt-0.5 space-y-0.5 pl-1">
        @foreach ($items as $item)
            @include('layouts.admin.nav-item', array_merge($item, ['nested' => true]))
        @endforeach
    </ul>
</li>

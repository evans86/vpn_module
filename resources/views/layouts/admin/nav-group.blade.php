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
            class="admin-nav-group-btn w-full flex items-center gap-2 px-3 py-2.5 text-sm font-semibold rounded-lg border border-transparent transition-all duration-200 ease-out {{ $isGroupActive ? 'bg-indigo-50 text-indigo-800 border-indigo-100 shadow-sm' : 'text-gray-800 hover:bg-gray-50 hover:border-gray-200' }}"
            x-bind:class="sidebarCollapsed ? 'justify-center px-2' : ''"
            :aria-expanded="(!sidebarCollapsed && open) ? 'true' : 'false'">
        <i class="fas {{ $icon }} w-5 text-center flex-shrink-0 text-indigo-600/90"></i>
        <span x-show="!sidebarCollapsed"
              x-transition:enter="transition ease-out duration-200"
              x-transition:enter-start="opacity-0 -translate-x-1"
              x-transition:enter-end="opacity-100 translate-x-0"
              x-transition:leave="transition ease-in duration-150"
              x-transition:leave-start="opacity-100"
              x-transition:leave-end="opacity-0"
              x-cloak
              class="admin-sidebar-label flex-1 text-left tracking-tight">{{ $label }}</span>
        <i x-show="!sidebarCollapsed"
           x-transition
           x-cloak
           class="fas fa-chevron-down text-[10px] text-indigo-400 transition-transform duration-300 ease-out flex-shrink-0"
           :class="open ? 'rotate-180' : ''"></i>
    </button>
    {{-- Плавное раскрытие: grid 0fr → 1fr --}}
    <div class="grid transition-[grid-template-rows] duration-300 ease-[cubic-bezier(0.4,0,0.2,1)]"
         :class="(open && !sidebarCollapsed) ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'">
        <div class="min-h-0 overflow-hidden">
            <ul class="admin-nav-nested mt-1.5 mb-0.5 ml-1 pl-2 border-l-2 border-indigo-100/90 space-y-0.5 rounded-r-md bg-slate-50/70 py-1.5">
                @foreach ($items as $item)
                    @include('layouts.admin.nav-item', array_merge($item, ['nested' => true]))
                @endforeach
            </ul>
        </div>
    </div>
</li>

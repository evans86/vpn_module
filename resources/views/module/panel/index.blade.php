@extends('layouts.admin')

@section('title', 'Панели управления')
@section('page-title', 'Панели управления')

@php
    use App\Models\Panel\Panel;
@endphp

@section('content')
    <div class="space-y-6">
        <x-admin.card title="Список панелей">
            <x-slot name="tools">
                <div class="flex items-center space-x-3">
                    @php
                        $currentParams = request()->except(['page', 'show_deleted']);
                        $showDeletedParam = isset($showDeleted) && $showDeleted;
                    @endphp
                    @if($showDeletedParam)
                        <a href="{{ route('admin.module.panel.index', $currentParams) }}" 
                           class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            <i class="fas fa-eye-slash mr-2"></i>
                            Скрыть удаленные
                        </a>
                    @else
                        <a href="{{ route('admin.module.panel.index', array_merge($currentParams, ['show_deleted' => 1])) }}" 
                           class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            <i class="fas fa-eye mr-2"></i>
                            Показать скрытые
                        </a>
                    @endif
                    <button type="button" 
                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                            onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: { id: 'createPanelModal' } }))">
                        <i class="fas fa-plus mr-2"></i>
                        Добавить панель
                    </button>
                </div>
            </x-slot>

            <!-- Filters -->
            <x-admin.filter-form action="{{ route('admin.module.panel.index') }}">
                <x-admin.filter-input 
                    name="server" 
                    label="Сервер" 
                    value="{{ request('server') }}" 
                    placeholder="Поиск по имени или IP сервера" />
                
                <x-admin.filter-input 
                    name="panel_adress" 
                    label="Адрес панели" 
                    value="{{ request('panel_adress') }}" 
                    placeholder="Поиск по адресу панели" />
            </x-admin.filter-form>

            <!-- Table -->
            @if($panels->isEmpty())
                <x-admin.empty-state 
                    icon="fa-desktop" 
                    title="Панели не найдены"
                    description="Попробуйте изменить параметры фильтрации или создать новую панель">
                    <x-slot name="action">
                        <button type="button" 
                                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: { id: 'createPanelModal' } }))">
                            <i class="fas fa-plus mr-2"></i>
                            Добавить панель
                        </button>
                    </x-slot>
                </x-admin.empty-state>
            @else
                <!-- Cards Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($panels as $panel)
                        <div class="bg-white rounded-lg shadow-sm border border-gray-200 hover:shadow-md transition-shadow {{ $panel->panel_status === Panel::PANEL_DELETED ? 'opacity-60 bg-gray-50' : '' }}">
                            <!-- Card Header -->
                            <div class="px-6 py-4 border-b border-gray-200">
                                <div class="flex items-center space-x-3">
                                    <div class="flex-shrink-0">
                                        <div class="w-12 h-12 rounded-lg bg-gradient-to-br from-purple-500 to-pink-600 flex items-center justify-center">
                                            <i class="fas fa-desktop text-white text-xl"></i>
                                        </div>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <h3 class="text-lg font-semibold text-gray-900 truncate" title="{{ $panel->formatted_address }}">
                                            Панель #{{ $panel->id }}
                                        </h3>
                                        <a href="{{ $panel->panel_adress }}" target="_blank" class="text-sm text-indigo-600 hover:text-indigo-800 flex items-center truncate">
                                            <span class="truncate">{{ $panel->formatted_address }}</span>
                                            <i class="fas fa-external-link-alt ml-1 text-xs flex-shrink-0"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <!-- Card Body -->
                            <div class="px-6 py-4 space-y-4">
                                <!-- Status Badge -->
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-medium text-gray-700">Статус:</span>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        {{ $panel->status_badge_class === 'success' ? 'bg-green-100 text-green-800' : '' }}
                                        {{ $panel->status_badge_class === 'danger' ? 'bg-red-100 text-red-800' : '' }}
                                        {{ $panel->status_badge_class === 'warning' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                        {{ $panel->status_badge_class === 'info' ? 'bg-blue-100 text-blue-800' : '' }}
                                        {{ $panel->status_badge_class === 'secondary' ? 'bg-gray-100 text-gray-800' : '' }}">
                                        {{ $panel->status_label }}
                                    </span>
                                </div>

                                <!-- Panel Type -->
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-medium text-gray-700">Тип:</span>
                                    <span class="text-sm text-gray-900 font-medium">{{ $panel->panel_type_label }}</span>
                                </div>

                                <!-- Config Type -->
                                @if($panel->config_type)
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-medium text-gray-700">Конфиг:</span>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        {{ $panel->config_type_badge_class === 'success' ? 'bg-green-100 text-green-800' : '' }}
                                        {{ $panel->config_type_badge_class === 'info' ? 'bg-blue-100 text-blue-800' : '' }}
                                        {{ $panel->config_type_badge_class === 'secondary' ? 'bg-gray-100 text-gray-800' : '' }}
                                        {{ $panel->config_type_badge_class === 'primary' ? 'bg-indigo-100 text-indigo-800' : '' }}
                                        {{ $panel->config_type_badge_class === 'warning' ? 'bg-amber-100 text-amber-800' : '' }}
                                        {{ $panel->config_type_badge_class === 'purple' ? 'bg-violet-100 text-violet-800' : '' }}">
                                        {{ $panel->config_type_label }}
                                    </span>
                                </div>
                                @if($panel->config_updated_at)
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-medium text-gray-700">Обновлен:</span>
                                    <span class="text-xs text-gray-500">{{ $panel->config_updated_at->format('d.m.Y H:i') }}</span>
                                </div>
                                @endif
                                @endif

                                <!-- TLS Status -->
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-medium text-gray-700">TLS:</span>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        {{ $panel->use_tls ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                        {{ $panel->use_tls ? 'Включен' : 'Выключен' }}
                                    </span>
                                </div>

                                <!-- Rotation Status -->
                                @if($panel->excluded_from_rotation)
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-medium text-gray-700">Ротация:</span>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                        <i class="fas fa-ban mr-1"></i> Исключена
                                    </span>
                                </div>
                                @endif

                                <!-- Server -->
                                <div class="flex items-start justify-between">
                                    <span class="text-sm font-medium text-gray-700">Сервер:</span>
                                    <div class="text-right">
                                        @if($panel->server && isset($panel->server->id))
                                            <a href="{{ route('admin.module.server.index', ['id' => $panel->server_id]) }}"
                                               class="text-sm text-indigo-600 hover:text-indigo-800 font-medium"
                                               title="Перейти к серверу">
                                                {{ $panel->server->name ?? 'N/A' }}
                                            </a>
                                            @if(isset($panel->server->host) && $panel->server->host)
                                                <div class="text-xs text-gray-500 mt-1 font-mono">
                                                    {{ $panel->server->host }}
                                                </div>
                                            @endif
                                        @else
                                            <span class="text-sm text-red-600">Сервер удален</span>
                                        @endif
                                    </div>
                                </div>

                                <!-- Credentials -->
                                <div class="pt-2 border-t border-gray-200">
                                    <div class="space-y-2">
                                        <div class="flex items-center justify-between">
                                            <span class="text-sm font-medium text-gray-700">Логин:</span>
                                            <div class="flex items-center space-x-2">
                                                <span class="text-sm text-gray-900 font-mono">{{ $panel->panel_login }}</span>
                                                <button onclick="copyToClipboard('{{ $panel->panel_login }}', 'Логин')" 
                                                        class="text-gray-400 hover:text-indigo-600 transition-colors p-1 rounded hover:bg-gray-100"
                                                        title="Копировать логин">
                                                    <i class="fas fa-copy text-xs"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="flex items-center justify-between">
                                            <span class="text-sm font-medium text-gray-700">Пароль:</span>
                                            <div class="flex items-center space-x-2">
                                                <span class="text-sm text-gray-900 font-mono text-xs break-all max-w-[120px] truncate" title="{{ $panel->panel_password }}">{{ $panel->panel_password }}</span>
                                                <button onclick="copyToClipboard('{{ $panel->panel_password }}', 'Пароль')" 
                                                        class="text-gray-400 hover:text-indigo-600 transition-colors p-1 rounded hover:bg-gray-100 flex-shrink-0"
                                                        title="Копировать пароль">
                                                    <i class="fas fa-copy text-xs"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Card Footer -->
                            @if($panel->panel_status !== Panel::PANEL_DELETED)
                                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 rounded-b-lg">
                                    <div class="space-y-3">
                                        <!-- Navigation Actions -->
                                        <div class="grid grid-cols-2 gap-2">
                                            <a href="{{ route('admin.module.server-users.index', ['panel_id' => $panel->id]) }}"
                                               class="inline-flex items-center justify-center px-3 py-2 text-sm font-medium rounded-md text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 transition-colors">
                                                <i class="fas fa-users mr-2"></i>
                                                <span>Пользователи</span>
                                            </a>
                                            @if($panel->panel_status === Panel::PANEL_CONFIGURED)
                                                <a href="{{ route('admin.module.server-monitoring.index', ['panel_id' => $panel->id]) }}"
                                                   class="inline-flex items-center justify-center px-3 py-2 text-sm font-medium rounded-md text-indigo-700 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 transition-colors">
                                                    <i class="fas fa-chart-line mr-2"></i>
                                                    <span>Статистика</span>
                                                </a>
                                            @else
                                                <div></div>
                                            @endif
                                        </div>

                                        <!-- WARP: один шаг по умолчанию; пресеты и ручные действия — ниже в «Дополнительно» -->
                                        @if($panel->panel === \App\Models\Panel\Panel::MARZBAN)
                                            @if($panel->server_id && $panel->panel_status === \App\Models\Panel\Panel::PANEL_CONFIGURED)
                                                @if($panel->config_type === \App\Models\Panel\Panel::CONFIG_TYPE_MIXED_WARP && $panel->warp_routing_enabled)
                                                    {{-- После успешного one-click конфиг уже mixed_warp + коридор WARP включён — крупную кнопку убираем, чтобы не гонять повторно без необходимости. --}}
                                                    <div class="rounded-lg border border-emerald-200 bg-emerald-50/70 px-3 py-2.5 flex items-start gap-2.5 shadow-sm">
                                                        <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-emerald-600 text-white text-sm mt-px">
                                                            <i class="fas fa-check"></i>
                                                        </span>
                                                        <div class="min-w-0 space-y-1">
                                                            <p class="text-xs font-semibold text-emerald-950">Одношаговый WARP уже активен</p>
                                                            <p class="text-[10px] text-emerald-900 leading-snug">
                                                                Конфиг: <strong class="font-medium">{{ $panel->config_type_label }}</strong>@if($panel->config_updated_at), обновлён {{ $panel->config_updated_at->format('d.m.Y H:i') }}@endif.
                                                                SOCKS: порт {{ (int) ($panel->warp_socks_port ?? config('panel.warp_default_socks_port', 40000)) }}.
                                                                Повторный запуск кнопкой обычно не нужен; переустановка по SSH или смена пресета — в «Дополнительно».
                                                            </p>
                                                        </div>
                                                    </div>
                                                @else
                                                <div class="rounded-xl border-2 border-emerald-300 bg-gradient-to-b from-emerald-50 to-white p-4 shadow-sm space-y-2">
                                                    <div class="flex items-center gap-2">
                                                        <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-emerald-600 text-white">
                                                            <i class="fas fa-magic"></i>
                                                        </span>
                                                        <div>
                                                            <p class="text-sm font-semibold text-emerald-950">WARP за один шаг</p>
                                                            <p class="text-[11px] text-emerald-900 leading-snug">По SSH (root): sing-box/wgcf → импорт ключей в панель → пресет <strong>Смешанный + REALITY с WARP</strong> для рабочих клиентских линков. Длительность обычно 2–5 минут.</p>
                                                        </div>
                                                    </div>
                                                    <form action="{{ route('admin.module.panel.warp-one-click', $panel) }}" method="POST"
                                                          onsubmit="return confirm('Запустить полную автонастройку WARP на {{ optional($panel->server)->ip ?? '—' }}? На сервере нужен доступ root по SSH. Продолжить?');">
                                                        @csrf
                                                        <input type="hidden" name="warp_socks_port" value="{{ (int) ($panel->warp_socks_port ?? config('panel.warp_default_socks_port', 40000)) }}">
                                                        <button type="submit" class="w-full py-3 px-3 text-sm font-semibold rounded-lg text-white bg-emerald-600 hover:bg-emerald-700 border border-emerald-700 shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 transition-colors">
                                                            Настроить WARP (всё автоматически)
                                                        </button>
                                                    </form>
                                                    <p class="text-[10px] text-emerald-800/90 leading-snug">После отправки можно закрыть страницу и подождать. Порт SOCKS по умолчанию: {{ (int) config('panel.warp_default_socks_port', 40000) }}. Другой порт — «Дополнительно» → блок WARP (ручная диагностика) → поля SOCKS в свёрнутом подразделе, там же SSH‑установка при необходимости.</p>
                                                </div>
                                                @endif
                                            @elseif($panel->server_id === null)
                                                <p class="text-xs text-amber-800 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2">
                                                    Автонастройка WARP: привяжите к панели сервер в карточке панели.
                                                </p>
                                            @elseif(in_array((int) $panel->panel_status, [\App\Models\Panel\Panel::PANEL_CREATED, \App\Models\Panel\Panel::PANEL_ERROR], true))
                                                {{-- Иначе тупик: статус «Настроена» ставится только после applyConfiguration через API, а пресеты были скрыты при «Создана». --}}
                                                <div class="rounded-lg border border-indigo-200 bg-gradient-to-b from-indigo-50 to-white p-4 space-y-2 shadow-sm">
                                                    <div class="flex items-start gap-2">
                                                        <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-indigo-600 text-white text-sm">
                                                            <i class="fas fa-bolt"></i>
                                                        </span>
                                                        <div class="space-y-1">
                                                            <p class="text-sm font-semibold text-indigo-950">Первое применение конфига в Marzban</p>
                                                            <p class="text-[11px] text-indigo-900 leading-snug">
                                                                Пока статус не «Настроена», зелёная кнопка WARP недоступна.
                                                                Выберите пресет в блоке <strong class="font-semibold">«Дополнительно»</strong> ниже (он открыт по умолчанию) или выполните быстрый вариант — передача конфига <strong class="font-semibold">REALITY</strong> через действие legacy.
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <form action="{{ route('admin.module.panel.configure', $panel) }}" method="POST" class="pt-1">
                                                        @csrf
                                                        <button type="submit" class="w-full py-2.5 px-3 text-xs font-semibold rounded-md text-white bg-indigo-600 hover:bg-indigo-700 border border-indigo-700 shadow-sm">
                                                            Быстро: отправить конфиг REALITY → статус «Настроена»
                                                        </button>
                                                    </form>
                                                    <p class="text-[10px] text-indigo-800/90 leading-snug">
                                                        Если нужен именно стабильный без REALITY — в «Дополнительно» нажмите «Стабильный». После успешного запроса к API Marzban статус станет «Настроена», затем можно «Настроить WARP».
                                                    </p>
                                                </div>
                                            @elseif($panel->panel_status !== \App\Models\Panel\Panel::PANEL_CONFIGURED)
                                                <p class="text-xs text-amber-800 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2">
                                                    Завершите настройку панели или примените пресет в «Дополнительно», чтобы статус стал «Настроена» и открылась автонастройка WARP.
                                                </p>
                                            @endif

                                            @if(in_array((int) $panel->panel_status, [
                                                \App\Models\Panel\Panel::PANEL_CREATED,
                                                \App\Models\Panel\Panel::PANEL_ERROR,
                                                \App\Models\Panel\Panel::PANEL_CONFIGURED,
                                            ], true))
                                                <details class="rounded-lg border border-gray-200 bg-gray-50/80"
                                                    @if(in_array((int) $panel->panel_status, [\App\Models\Panel\Panel::PANEL_CREATED, \App\Models\Panel\Panel::PANEL_ERROR], true)) open @endif>
                                                    <summary class="px-3 py-2.5 text-xs font-semibold text-gray-800 cursor-pointer select-none">
                                                        Дополнительно:
                                                        @if((int) $panel->panel_status === \App\Models\Panel\Panel::PANEL_CONFIGURED)
                                                            пресеты без WARP, только конфиг «+ WARP», ручная настройка SOCKS
                                                        @else
                                                            выберите пресет → статус «Настроена», затем WARP
                                                        @endif
                                                    </summary>
                                                    <div class="px-3 pb-4 pt-0 space-y-4 border-t border-gray-100">
                                                        @if((int) $panel->panel_status !== \App\Models\Panel\Panel::PANEL_CONFIGURED)
                                                            <p class="text-[11px] text-amber-900 bg-amber-50 border border-amber-200 rounded-md px-2.5 py-2 leading-snug">
                                                                Статус ещё не «Настроена»: любой успешный пресет отправит конфиг через API и обновит статус.
                                                                Подпись «Конфиг» на карточке может быть из шаблона — решает то, что реально записано после ответа Marzban.
                                                            </p>
                                                        @endif
                                                        <div>
                                                            <p class="text-[11px] text-gray-600 mb-2">Пресеты входящих (без авто‑установки WARP по SSH).</p>
                                                            <div class="grid grid-cols-2 gap-2">
                                                                <form action="{{ route('admin.module.panel.update-config-stable', $panel) }}" method="POST" class="min-w-0">
                                                                    @csrf
                                                                    <button type="submit"
                                                                            class="w-full min-h-[3.25rem] flex flex-col items-center justify-center gap-1 px-2 py-2 text-xs font-medium rounded-md text-blue-700 bg-blue-50 hover:bg-blue-100 border border-blue-200 transition-colors
                                                                        {{ $panel->config_type === 'stable' ? 'ring-2 ring-blue-500 shadow-sm' : '' }}"
                                                                            title="Стабильный конфиг (без REALITY)">
                                                                        <i class="fas fa-shield-alt text-sm shrink-0"></i>
                                                                        <span class="leading-tight text-center break-words">Стабильный</span>
                                                                    </button>
                                                                </form>
                                                                <form action="{{ route('admin.module.panel.update-config-reality', $panel) }}" method="POST" class="min-w-0">
                                                                    @csrf
                                                                    <button type="submit"
                                                                            class="w-full min-h-[3.25rem] flex flex-col items-center justify-center gap-1 px-2 py-2 text-xs font-medium rounded-md text-green-700 bg-green-50 hover:bg-green-100 border border-green-200 transition-colors
                                                                        {{ $panel->config_type === 'reality' ? 'ring-2 ring-green-500 shadow-sm' : '' }}"
                                                                            title="Конфиг с REALITY - все протоколы">
                                                                        <i class="fas fa-rocket text-sm shrink-0"></i>
                                                                        <span class="leading-tight text-center break-words">REALITY</span>
                                                                    </button>
                                                                </form>
                                                                <form action="{{ route('admin.module.panel.update-config-mixed', $panel) }}" method="POST" class="min-w-0 col-span-2">
                                                                    @csrf
                                                                    <button type="submit"
                                                                            class="w-full min-h-[3.25rem] flex flex-col items-center justify-center gap-1 px-2 py-2 text-xs font-medium rounded-md text-amber-700 bg-amber-50 hover:bg-amber-100 border border-amber-200 transition-colors
                                                                        {{ $panel->config_type === 'mixed' ? 'ring-2 ring-amber-500 shadow-sm' : '' }}"
                                                                            title="SS + Trojan + 3 VLESS REALITY">
                                                                        <i class="fas fa-layer-group text-sm shrink-0"></i>
                                                                        <span class="leading-tight text-center break-words">Смешанный (без WARP)</span>
                                                                    </button>
                                                                </form>
                                                            </div>
                                                        </div>
                                                        <div class="rounded-lg border border-violet-200 bg-violet-50/50 p-3">
                                                            <p class="text-[11px] font-medium text-violet-900 mb-2">Если sing-box / WARP на сервере уже стоит — только применить пресет Marzban (+ WARP):</p>
                                                            <form action="{{ route('admin.module.panel.update-config-mixed-warp', $panel) }}" method="POST">
                                                                @csrf
                                                                <button type="submit"
                                                                        class="w-full py-2 text-xs font-medium rounded-md text-violet-900 bg-white border border-violet-300 hover:bg-violet-100">
                                                                    Только конфигурация «+ WARP» (без SSH‑установки)
                                                                </button>
                                                            </form>
                                                        </div>
                                                        <div class="border border-cyan-200 bg-cyan-50/80 rounded-lg p-3 space-y-2">
                                                            <div class="flex flex-wrap items-center gap-2">
                                                                <span class="text-xs font-semibold text-cyan-900">WARP (ручная диагностика)</span>
                                                                @if($panel->warp_routing_enabled)
                                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-emerald-100 text-emerald-900 border border-emerald-200">Включён</span>
                                                                @else
                                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-slate-100 text-slate-700 border border-slate-200">Выключен</span>
                                                                @endif
                                                            </div>
                                                            <p class="text-[11px] text-cyan-800 leading-snug">
                                                                Раздел для отладки.
                                                                @if((int) $panel->panel_status === \App\Models\Panel\Panel::PANEL_CONFIGURED)
                                                                    Для типового сценария используйте зелёную кнопку выше «Настроить WARP».
                                                                @else
                                                                    Сначала доведите панель до статуса «Настроена», затем появится зелёная кнопка WARP на карточке.
                                                                @endif
                                                            </p>
                                                            <div class="flex flex-wrap gap-2">
                                                                @if($panel->warp_routing_enabled)
                                                                    <form action="{{ route('admin.module.panel.toggle-warp-routing', $panel) }}" method="POST" class="inline">
                                                                        @csrf
                                                                        <input type="hidden" name="warp_on" value="0">
                                                                        <button type="submit" class="inline-flex items-center justify-center px-2.5 py-1.5 text-[11px] font-medium rounded-md text-slate-800 bg-white border border-slate-300 hover:bg-slate-50">Отключить WARP</button>
                                                                    </form>
                                                                @else
                                                                    <form action="{{ route('admin.module.panel.toggle-warp-routing', $panel) }}" method="POST" class="inline">
                                                                        @csrf
                                                                        <input type="hidden" name="warp_on" value="1">
                                                                        <button type="submit" class="inline-flex items-center justify-center px-2.5 py-1.5 text-[11px] font-medium rounded-md text-white bg-cyan-600 hover:bg-cyan-700">Включить WARP</button>
                                                                    </form>
                                                                @endif
                                                                @if($panel->server_id)
                                                                    <form action="{{ route('admin.module.panel.check-warp-socks', $panel) }}" method="POST" class="inline">
                                                                        @csrf
                                                                        <button type="submit" class="inline-flex items-center justify-center px-2.5 py-1.5 text-[11px] font-medium rounded-md text-cyan-900 bg-cyan-100 border border-cyan-300 hover:bg-cyan-200">Проверить</button>
                                                                    </form>
                                                                    <form action="{{ route('admin.module.panel.import-warp-wireguard-snapshot', $panel) }}" method="POST" class="inline" onsubmit="return confirm('Считать wgcf с ноды в панель?');">
                                                                        @csrf
                                                                        <button type="submit" class="inline-flex items-center justify-center px-2.5 py-1.5 text-[11px] font-medium rounded-md text-slate-800 bg-white border border-slate-300 hover:bg-slate-50">wgcf → панель</button>
                                                                    </form>
                                                                @endif
                                                            </div>
                                                            <details class="mt-1 rounded border border-cyan-200/80 bg-white/60" id="warpDetails{{ (int) $panel->id }}">
                                                                <summary class="px-2 py-2 text-[11px] font-medium text-cyan-900 cursor-pointer select-none list-none">
                                                                    <span class="inline-flex items-center gap-1"><i class="fas fa-sliders-h text-cyan-600 text-[10px]"></i> Адрес SOCKS, узкий режим, установка по SSH</span>
                                                                </summary>
                                                                <div class="px-2 pb-3 pt-0 space-y-2 border-t border-cyan-100">
                                                                    <p class="text-[10px] text-cyan-800 leading-snug pt-2">
                                                                        Уже редко нужно после «Настроить WARP». <a href="https://marzban-docs.sm1ky.com/tutorials/cloudflare-warp/" class="text-cyan-700 underline" target="_blank" rel="noopener">Доку Marzban (WARP)</a>.
                                                                    </p>
                                                                    <form action="{{ route('admin.module.panel.update-warp-routing', $panel) }}" method="POST" class="space-y-2">
                                                                        @csrf
                                                                        <input type="hidden" name="warp_routing_touched" value="1">
                                                                        <input type="hidden" name="warp_routing_enabled" value="{{ $panel->warp_routing_enabled ? 1 : 0 }}">
                                                                        <label class="flex items-start gap-2 text-[11px] text-cyan-900 cursor-pointer">
                                                                            <input type="checkbox" name="warp_selective" value="1" class="rounded mt-0.5"
                                                                                   @checked((bool) old('warp_selective', ! (bool) ($panel->warp_routing_all ?? true)))>
                                                                            <span>Узкий маршрут вместо полного через WARP (списки в <code class="text-[10px] bg-cyan-50 px-0.5 rounded">config/vpn.php</code>)</span>
                                                                        </label>
                                                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                                                            <div>
                                                                                <label class="block text-[10px] text-cyan-800 mb-0.5">SOCKS хост</label>
                                                                                <input type="text" name="warp_socks_host" value="{{ old('warp_socks_host', $panel->warp_socks_host ?? config('panel.warp_default_socks_host', '172.17.0.1')) }}"
                                                                                       class="w-full text-xs border border-cyan-200 rounded px-2 py-1.5" placeholder="{{ config('panel.warp_default_socks_host', '172.17.0.1') }}">
                                                                            </div>
                                                                            <div>
                                                                                <label class="block text-[10px] text-cyan-800 mb-0.5">Порт (пусто = {{ config('panel.warp_default_socks_port', 40000) }})</label>
                                                                                <input type="number" name="warp_socks_port" id="marzban-warp-socks-port-{{ (int) $panel->id }}" min="1" max="65535"
                                                                                       value="{{ old('warp_socks_port', $panel->warp_socks_port) }}"
                                                                                       class="w-full text-xs border border-cyan-200 rounded px-2 py-1.5" placeholder="{{ config('panel.warp_default_socks_port', 40000) }}">
                                                                            </div>
                                                                        </div>
                                                                        <button type="submit" class="w-full text-xs font-medium py-2 rounded-md text-white bg-cyan-600 hover:bg-cyan-700">Сохранить и переприменить</button>
                                                                    </form>
                                                                    @if($panel->server_id)
                                                                        <form action="{{ route('admin.module.panel.install-warp-socks', $panel) }}" method="POST" class="space-y-2 pt-1 border-t border-cyan-100"
                                                                              onsubmit="(function(){var p=document.getElementById('marzban-warp-socks-port-{{ (int) $panel->id }}');var d={{ (int) config('panel.warp_default_socks_port', 40000) }};var t=p&&p.value.trim()!==''?p.value.trim():d;document.getElementById('warp-install-socks-port-{{ (int) $panel->id }}').value=t;return true;})(); return confirm('Установка на {{ optional($panel->server)->ip ?? "—" }} по SSH (нужен root). Продолжить?');">
                                                                            @csrf
                                                                            <input type="hidden" name="warp_socks_port" id="warp-install-socks-port-{{ (int) $panel->id }}" value="{{ (int) ($panel->warp_socks_port ?? config('panel.warp_default_socks_port', 40000)) }}">
                                                                            <input type="hidden" name="enable_warp_routing" value="1">
                                                                            <button type="submit" class="w-full text-xs font-medium py-2 rounded-md text-cyan-900 bg-cyan-100 hover:bg-cyan-200 border border-cyan-300">Только установка на сервер (без пресета +WARP)</button>
                                                                            <p class="text-[10px] text-cyan-700">Не нажимайте, если уже есть зелёная автонастройка — возможен конфликт портов.</p>
                                                                        </form>
                                                                    @else
                                                                        <p class="text-[10px] text-amber-800 pt-1">Для автоустановки привяжите к панели сервер с SSH.</p>
                                                                    @endif
                                                                </div>
                                                            </details>
                                                        </div>
                                                    </div>
                                                </details>
                                            @endif
                                        @endif

                                        <!-- TLS Actions -->
                                        <div class="grid grid-cols-2 gap-2">
                                            @if($panel->tls_certificate_path && $panel->tls_key_path)
                                                <button type="button" 
                                                        onclick="toggleTls({{ $panel->id }}, {{ $panel->use_tls ? 'true' : 'false' }})"
                                                        class="inline-flex items-center justify-center px-3 py-2 text-sm font-medium rounded-md {{ $panel->use_tls ? 'text-green-700 bg-green-50 hover:bg-green-100 border-green-200' : 'text-gray-700 bg-gray-50 hover:bg-gray-100 border-gray-200' }} border transition-colors
                                                        {{ $panel->use_tls ? 'ring-2 ring-green-500 shadow-sm' : '' }}"
                                                        title="{{ $panel->use_tls ? 'Выключить TLS' : 'Включить TLS' }}">
                                                    <i class="fas {{ $panel->use_tls ? 'fa-lock' : 'fa-unlock' }} mr-2"></i>
                                                    <span>TLS {{ $panel->use_tls ? 'ON' : 'OFF' }}</span>
                                                </button>
                                                <button type="button" 
                                                        onclick="openCertificatesModal({{ $panel->id }}, 'yes', {{ $panel->use_tls ? 'true' : 'false' }}, '{{ $panel->panel_adress }}')"
                                                        class="inline-flex items-center justify-center px-3 py-2 text-sm font-medium rounded-md text-purple-700 bg-purple-50 hover:bg-purple-100 border border-purple-200 transition-colors"
                                                        title="Настроить TLS сертификаты">
                                                    <i class="fas fa-cog mr-2"></i>
                                                    <span>Настройки</span>
                                                </button>
                                            @else
                                                <button type="button" 
                                                        onclick="openCertificatesModal({{ $panel->id }}, 'no', false, '{{ $panel->panel_adress }}')"
                                                        class="inline-flex items-center justify-center px-3 py-2 text-sm font-medium rounded-md text-purple-700 bg-purple-50 hover:bg-purple-100 border border-purple-200 transition-colors col-span-2"
                                                        title="Получить TLS сертификат Let's Encrypt">
                                                    <i class="fas fa-certificate mr-2"></i>
                                                    <span>Получить TLS сертификат</span>
                                                </button>
                                            @endif
                                        </div>

                                        <!-- Management Actions -->
                                        <div class="grid grid-cols-2 gap-2">
                                            <button type="button" 
                                                    onclick="toggleRotationExclusion({{ $panel->id }}, {{ $panel->excluded_from_rotation ? 'true' : 'false' }})"
                                                    class="inline-flex items-center justify-center px-3 py-2 text-sm font-medium rounded-md {{ $panel->excluded_from_rotation ? 'text-yellow-700 bg-yellow-50 hover:bg-yellow-100 border-yellow-200' : 'text-gray-700 bg-gray-50 hover:bg-gray-100 border-gray-200' }} border transition-colors
                                                    {{ $panel->excluded_from_rotation ? 'ring-2 ring-yellow-500 shadow-sm' : '' }}"
                                                    title="{{ $panel->excluded_from_rotation ? 'Включить в ротацию' : 'Исключить из ротации (для тестирования)' }}">
                                                <i class="fas {{ $panel->excluded_from_rotation ? 'fa-check-circle' : 'fa-ban' }} mr-2"></i>
                                                <span>{{ $panel->excluded_from_rotation ? 'В ротации' : 'Исключить' }}</span>
                                            </button>
                                            <button type="button" 
                                                    onclick="deletePanel({{ $panel->id }})"
                                                    class="inline-flex items-center justify-center px-3 py-2 text-sm font-medium rounded-md text-red-700 bg-red-50 hover:bg-red-100 border border-red-200 transition-colors">
                                                <i class="fas fa-trash mr-2"></i>
                                                <span>Удалить</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>

                <!-- Pagination -->
                <div class="mt-6">
                    <x-admin.pagination-wrapper :paginator="$panels" />
                </div>
            @endif
        </x-admin.card>
    </div>

    <!-- Modal: Create Panel -->
    <x-admin.modal id="createPanelModal" title="Добавить панель">
        <form id="createPanelForm" action="{{ route('admin.module.panel.store') }}" method="POST">
            @csrf
            <div class="mb-4">
                <label for="server_id" class="block text-sm font-medium text-gray-700 mb-1">
                    Выберите сервер
                </label>
                <select id="server_id" name="server_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 sm:text-sm" required>
                    <option value="">Выберите сервер...</option>
                    @foreach($servers as $serverId => $serverName)
                        <option value="{{ $serverId }}">{{ $serverName }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-sm text-gray-500">Будет создана панель Marzban на выбранном сервере</p>
            </div>
        </form>
        <x-slot name="footer">
            <button type="submit" form="createPanelForm" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                <i class="fas fa-plus mr-2"></i> Создать
            </button>
            <button type="button" 
                    class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" 
                    onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: { id: 'createPanelModal' } }))">
                Отмена
            </button>
        </x-slot>
    </x-admin.modal>

    <!-- Modal: Get Let's Encrypt Certificate -->
    <x-admin.modal id="certificatesModal" title="Получение TLS сертификата Let's Encrypt">
        <form id="certificatesForm" method="POST">
            @csrf
            <div id="certificatesStatus" class="mb-4 p-3 rounded-md bg-gray-50 border border-gray-200">
                <p class="text-sm text-gray-600">
                    <i class="fas fa-info-circle mr-2"></i>
                    <span id="statusText">Проверка статуса...</span>
                </p>
            </div>

            <div class="mb-4 p-3 rounded-md bg-green-50 border border-green-200">
                <p class="text-sm text-green-800">
                    <i class="fas fa-magic mr-2"></i>
                    <strong>Автоматическое получение:</strong> Система автоматически получит валидный сертификат Let's Encrypt для вашего домена.
                </p>
            </div>

            <div class="mb-4">
                <label for="domain" class="block text-sm font-medium text-gray-700 mb-1">
                    Домен
                </label>
                <input type="text" 
                       id="domain" 
                       name="domain" 
                       readonly
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm bg-gray-100 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                <p class="mt-1 text-xs text-gray-500">
                    Домен автоматически определяется из адреса панели. Если нужно изменить, отредактируйте адрес панели.
                </p>
            </div>

            <div class="mb-4">
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                    Email (опционально)
                </label>
                <input type="email" 
                       id="email" 
                       name="email" 
                       placeholder="admin@домен"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                <p class="mt-1 text-xs text-gray-500">Email для уведомлений от Let's Encrypt. По умолчанию: admin@домен</p>
            </div>

            <div class="mb-4" id="useTlsSection">
                <label class="flex items-center">
                    <input type="checkbox" 
                           name="use_tls" 
                           value="1"
                           id="useTlsCheckbox"
                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                    <span class="ml-2 text-sm text-gray-700">Включить TLS шифрование для этой панели</span>
                </label>
                <p class="mt-1 text-xs text-gray-500">По умолчанию TLS выключен для обратной совместимости</p>
                <p class="mt-2 text-xs text-blue-600">
                    <i class="fas fa-info-circle mr-1"></i>
                    <strong>Совет:</strong> Вы можете включить/выключить TLS кнопкой "TLS ON/OFF" на карточке панели без перезагрузки сертификатов
                </p>
            </div>

            <div class="mb-4 p-3 rounded-md bg-yellow-50 border border-yellow-200">
                <p class="text-sm text-yellow-800">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <strong>Важно:</strong> Убедитесь, что домен указывает на IP сервера с панелью Marzban, а не на сервер Laravel! Порт 80 должен быть открыт для HTTP-валидации.
                </p>
            </div>
        </form>
        <x-slot name="footer">
            <button type="button" 
                    id="removeCertificatesBtn"
                    onclick="removeCertificates()"
                    class="inline-flex items-center px-4 py-2 border border-red-300 text-sm font-medium rounded-md shadow-sm text-red-700 bg-red-50 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                <i class="fas fa-trash mr-2"></i> Удалить сертификаты
            </button>
            <button type="submit" 
                    form="certificatesForm" 
                    id="submitCertificatesBtn"
                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                <i class="fas fa-upload mr-2"></i> <span id="submitBtnText">Загрузить</span>
            </button>
            <button type="button" 
                    class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" 
                    onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: { id: 'certificatesModal' } }))">
                Отмена
            </button>
        </x-slot>
    </x-admin.modal>
@endsection

@push('js')
    <script>
        let currentPanelId = null;

        function openCertificatesModal(panelId, hasCertificates, useTls, panelAddress) {
            currentPanelId = panelId;
            const form = document.getElementById('certificatesForm');
            form.action = '{{ route('admin.module.panel.get-letsencrypt-certificate', ['panel' => ':id']) }}'.replace(':id', panelId);
            
            const statusDiv = document.getElementById('certificatesStatus');
            const statusText = document.getElementById('statusText');
            const removeBtn = document.getElementById('removeCertificatesBtn');
            const useTlsCheckbox = form.querySelector('input[name="use_tls"]');
            const domainInput = form.querySelector('input[name="domain"]');
            
            // Автоматически определяем домен из адреса панели
            let domain = '';
            try {
                const url = new URL(panelAddress);
                domain = url.hostname;
            } catch (e) {
                // Если не удалось распарсить URL, пробуем извлечь домен другим способом
                const match = panelAddress.match(/(?:https?:\/\/)?([^\/]+)/);
                if (match) {
                    domain = match[1].split(':')[0]; // Убираем порт если есть
                }
            }
            
            if (domainInput) {
                domainInput.value = domain;
            }
            
            // Сбрасываем форму перед открытием
            form.reset();
            
            // Восстанавливаем домен и use_tls
            if (domainInput) {
                domainInput.value = domain;
            }
            
            if (hasCertificates === 'yes') {
                statusDiv.className = 'mb-4 p-3 rounded-md bg-green-50 border border-green-200';
                statusText.innerHTML = '<i class="fas fa-check-circle mr-2 text-green-600"></i>Сертификаты настроены для этой панели';
                removeBtn.style.display = 'inline-flex';
            } else {
                statusDiv.className = 'mb-4 p-3 rounded-md bg-yellow-50 border border-yellow-200';
                statusText.innerHTML = '<i class="fas fa-exclamation-triangle mr-2 text-yellow-600"></i>Сертификаты не настроены, используются настройки по умолчанию';
                removeBtn.style.display = 'none';
            }
            
            // Устанавливаем значение use_tls
            if (useTlsCheckbox) {
                useTlsCheckbox.checked = useTls === true || useTls === 'true';
            }
            
            // Обновляем текст кнопки
            const submitBtn = document.getElementById('submitCertificatesBtn');
            const submitBtnText = document.getElementById('submitBtnText');
            if (submitBtn && submitBtnText) {
                submitBtnText.textContent = hasCertificates === 'yes' ? 'Обновить сертификат' : 'Получить сертификат';
                submitBtn.querySelector('i').className = 'fas fa-certificate mr-2';
            }
            
            window.dispatchEvent(new CustomEvent('open-modal', { detail: { id: 'certificatesModal' } }));
        }

        function toggleTls(panelId, isEnabled) {
            $.ajax({
                url: '{{ route('admin.module.panel.toggle-tls', ['panel' => ':id']) }}'.replace(':id', panelId),
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}'
                },
                success: function (response) {
                    toastr.success(response.message || (isEnabled ? 'TLS выключен' : 'TLS включен'));
                    if (response.use_tls) {
                        toastr.info('Не забудьте обновить конфигурацию панели (кнопка "Стабильный" или "REALITY")');
                    }
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                },
                error: function (xhr) {
                    let errorMessage = 'Произошла ошибка';
                    if (xhr.responseJSON) {
                        errorMessage = xhr.responseJSON.message || errorMessage;
                    }
                    toastr.error(errorMessage);
                }
            });
        }

        function toggleRotationExclusion(panelId, isExcluded) {
            $.ajax({
                url: '{{ route('admin.module.panel.toggle-rotation-exclusion', ['panel' => ':id']) }}'.replace(':id', panelId),
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}'
                },
                success: function (response) {
                    toastr.success(response.message || (isExcluded ? 'Панель включена в ротацию' : 'Панель исключена из ротации'));
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                },
                error: function (xhr) {
                    let errorMessage = 'Произошла ошибка';
                    if (xhr.responseJSON) {
                        errorMessage = xhr.responseJSON.message || errorMessage;
                    }
                    toastr.error(errorMessage);
                }
            });
        }

        function removeCertificates() {
            if (!confirm('Вы уверены, что хотите удалить сертификаты для этой панели? Будут использоваться настройки по умолчанию.')) {
                return;
            }

            $.ajax({
                url: '{{ route('admin.module.panel.remove-certificates', ['panel' => ':id']) }}'.replace(':id', currentPanelId),
                method: 'DELETE',
                data: {
                    _token: '{{ csrf_token() }}'
                },
                success: function (response) {
                    toastr.success('Сертификаты успешно удалены');
                    window.dispatchEvent(new CustomEvent('close-modal', { detail: { id: 'certificatesModal' } }));
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                },
                error: function (xhr) {
                    let errorMessage = 'Произошла ошибка при удалении сертификатов';
                    if (xhr.responseJSON) {
                        errorMessage = xhr.responseJSON.message || errorMessage;
                    }
                    toastr.error(errorMessage);
                }
            });
        }

        // Обработка отправки формы
        $(document).ready(function () {
            $('#certificatesForm').on('submit', function(e) {
                e.preventDefault();
                
                const form = $(this);
                const formData = {
                    _token: form.find('input[name="_token"]').val(),
                    domain: form.find('input[name="domain"]').val(),
                    email: form.find('input[name="email"]').val() || '',
                    use_tls: form.find('input[name="use_tls"]').is(':checked') ? '1' : '0'
                };
                
                if (!formData.domain) {
                    toastr.error('Домен не определен. Проверьте адрес панели.');
                    return;
                }
                
                // Показываем индикатор загрузки
                const submitBtn = $('#submitCertificatesBtn');
                const originalText = submitBtn.find('span').text();
                submitBtn.prop('disabled', true).find('span').text('Получение сертификата...');
                
                $.ajax({
                    url: form.attr('action'),
                    method: 'POST',
                    data: formData,
                    success: function(response) {
                        toastr.success(response.message || 'Сертификат успешно получен!');
                        window.dispatchEvent(new CustomEvent('close-modal', { detail: { id: 'certificatesModal' } }));
                        setTimeout(() => {
                            window.location.reload();
                        }, 2000);
                    },
                    error: function(xhr) {
                        let errorMessage = 'Произошла ошибка при получении сертификата';
                        if (xhr.responseJSON) {
                            errorMessage = xhr.responseJSON.message || errorMessage;
                            if (xhr.responseJSON.errors) {
                                const errors = Object.values(xhr.responseJSON.errors).flat();
                                errorMessage = errors.join(', ');
                            }
                        }
                        toastr.error(errorMessage);
                        submitBtn.prop('disabled', false).find('span').text(originalText);
                    }
                });
            });
        });
    </script>
    <script>
        // Функция копирования в буфер обмена
        function copyToClipboard(text, label) {
            navigator.clipboard.writeText(text).then(function() {
                toastr.success(label + ' скопирован в буфер обмена', '', {
                    timeOut: 2000,
                    positionClass: 'toast-top-right'
                });
            }).catch(function(err) {
                // Fallback для старых браузеров
                const textArea = document.createElement('textarea');
                textArea.value = text;
                textArea.style.position = 'fixed';
                textArea.style.left = '-999999px';
                document.body.appendChild(textArea);
                textArea.select();
                try {
                    document.execCommand('copy');
                    toastr.success(label + ' скопирован в буфер обмена', '', {
                        timeOut: 2000,
                        positionClass: 'toast-top-right'
                    });
                } catch (err) {
                    toastr.error('Не удалось скопировать ' + label, '', {
                        timeOut: 3000
                    });
                }
                document.body.removeChild(textArea);
            });
        }

        $(document).ready(function () {
            function deletePanel(id) {
                if (confirm('Вы уверены, что хотите удалить эту панель?')) {
                    $.ajax({
                        url: '{{ route('admin.module.panel.destroy', ['panel' => ':id']) }}'.replace(':id', id),
                        method: 'DELETE',
                        data: {
                            _token: '{{ csrf_token() }}'
                        },
                        success: function (response) {
                            toastr.success('Панель успешно удалена');
                            setTimeout(() => {
                                window.location.reload();
                            }, 1000);
                        },
                        error: function (xhr) {
                            let errorMessage = 'Произошла ошибка при удалении панели';
                            if (xhr.responseJSON) {
                                errorMessage = xhr.responseJSON.message || errorMessage;
                            }
                            toastr.error(errorMessage);
                        }
                    });
                }
            }
            window.deletePanel = deletePanel;
        });
    </script>
@endpush

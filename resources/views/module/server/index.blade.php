@extends('layouts.admin')

@section('title', 'Серверы')
@section('page-title', 'Управление серверами')

@php
    use App\Models\Server\Server;
    use App\Constants\TariffTier;
@endphp

@section('content')
    <div class="space-y-6">
        <x-admin.card title="Список серверов">
            <x-slot name="tools">
                @php
                    $currentParams = request()->except(['page', 'show_deleted']);
                    $showDeletedParam = isset($showDeleted) && $showDeleted;
                @endphp
                @if($showDeletedParam)
                    <a href="{{ route('admin.module.server.index', $currentParams) }}" 
                       class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        <i class="fas fa-eye-slash mr-2"></i>
                        Скрыть удаленные
                    </a>
                @else
                    <a href="{{ route('admin.module.server.index', array_merge($currentParams, ['show_deleted' => 1])) }}" 
                       class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        <i class="fas fa-eye mr-2"></i>
                        Показать скрытые
                    </a>
                @endif
            </x-slot>

            <!-- Filters -->
            <x-admin.filter-form action="{{ route('admin.module.server.index') }}">
                <x-admin.filter-input 
                    name="name" 
                    label="Имя сервера" 
                    value="{{ request('name') }}" 
                    placeholder="Поиск по имени" />
                
                <x-admin.filter-input 
                    name="ip" 
                    label="IP адрес" 
                    value="{{ request('ip') }}" 
                    placeholder="Поиск по IP" />
                
                <x-admin.filter-select 
                    name="status" 
                    label="Статус"
                    :options="[
                        Server::SERVER_CREATED => 'Создан',
                        Server::SERVER_CONFIGURED => 'Настроен',
                        Server::SERVER_ERROR => 'Ошибка',
                        Server::SERVER_DELETED => 'Удален'
                    ]"
                    value="{{ request('status') }}" />

                <x-admin.filter-select
                    name="provider"
                    label="Провайдер"
                    placeholder="Все провайдеры"
                    :options="$providerFilterOptions ?? []"
                    value="{{ request('provider') }}" />

                <x-admin.filter-select
                    name="sort"
                    label="Сортировка"
                    placeholder="По умолчанию"
                    :options="[
                        'id_desc' => 'Сначала новые (по ID)',
                        'provider_asc' => 'По провайдеру (А → Я)',
                        'provider_desc' => 'По провайдеру (Я → А)',
                    ]"
                    value="{{ request('sort', 'id_desc') }}" />
            </x-admin.filter-form>

            <!-- Table -->
            @if($servers->isEmpty())
                <x-admin.empty-state 
                    icon="fa-server" 
                    title="Серверы не найдены"
                    description="Попробуйте изменить параметры фильтрации или создать новый сервер">
                    <x-slot name="action">
                        <div class="flex flex-wrap gap-2 justify-center">
                            <button type="button" 
                                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                    onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: { id: 'createServerModal' } }))">
                                <i class="fas fa-plus mr-2"></i>
                                Добавить сервер (API)
                            </button>
                            <button type="button" 
                                    class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                    onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: { id: 'createManualServerModal' } }))">
                                <i class="fas fa-server mr-2"></i>
                                Добавить вручную (без API)
                            </button>
                        </div>
                    </x-slot>
                </x-admin.empty-state>
            @else
                <div class="flex flex-wrap items-center gap-2 mb-4 p-3 bg-gray-50 rounded-lg border border-gray-200">
                    <span class="text-sm text-gray-600 font-medium">Действия:</span>
                    <button type="button" 
                            class="inline-flex items-center px-3 py-1.5 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                            onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: { id: 'createServerModal' } }))">
                        <i class="fas fa-plus mr-2"></i>
                        Добавить сервер (API)
                    </button>
                    <button type="button" 
                            class="inline-flex items-center px-3 py-1.5 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                            onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: { id: 'createManualServerModal' } }))">
                        <i class="fas fa-server mr-2"></i>
                        Добавить вручную (без API)
                    </button>
                </div>
                <!-- Cards Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($servers as $server)
                        <div class="bg-white rounded-lg shadow-sm border border-gray-200 hover:shadow-md transition-shadow {{ $server->server_status === Server::SERVER_DELETED ? 'opacity-60 bg-gray-50' : '' }}">
                            <!-- Card Header -->
                            <div class="px-6 py-4 border-b border-gray-200">
                                <div class="flex items-center space-x-3">
                                    <div class="flex-shrink-0">
                                        <div class="w-12 h-12 rounded-lg bg-gradient-to-br from-indigo-500 to-blue-600 flex items-center justify-center">
                                            <i class="fas fa-server text-white text-xl"></i>
                                        </div>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <h3 class="text-lg font-semibold text-gray-900 truncate" title="{{ $server->name }}">
                                            {{ $server->name ?: 'Сервер #' . $server->id }}
                                        </h3>
                                        <p class="text-xs text-gray-500">ID: {{ $server->id }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Card Body -->
                            <div class="px-6 py-4 space-y-4">
                                <!-- Status Badge -->
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-medium text-gray-700">Статус:</span>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        {{ $server->status_badge_class === 'success' ? 'bg-green-100 text-green-800' : '' }}
                                        {{ $server->status_badge_class === 'danger' ? 'bg-red-100 text-red-800' : '' }}
                                        {{ $server->status_badge_class === 'warning' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                        {{ $server->status_badge_class === 'info' ? 'bg-blue-100 text-blue-800' : '' }}
                                        {{ $server->status_badge_class === 'secondary' ? 'bg-gray-100 text-gray-800' : '' }}">
                                        {{ $server->status_label }}
                                    </span>
                                </div>

                                <!-- Provider -->
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-medium text-gray-700">Провайдер:</span>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        {{ $server->getProviderLabel() }}
                                    </span>
                                </div>

                                {{-- Тариф активации: переменная ниже — из-за ограничения разбора Blade нельзя писать сложное условие внутри @selected(...) в одну строку --}}
                                @php
                                    $currentActivationTier = $server->tariff_tier ?? TariffTier::FULL;
                                @endphp
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-sm font-medium text-gray-700 shrink-0">Тариф активации:</span>
                                    <select
                                        class="text-xs rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 max-w-[10rem]"
                                        title="Какой пул серверов использовать при выборе панели для ключей"
                                        onchange="updateServerTariffTier({{ $server->id }}, this.value)">
                                        @foreach (TariffTier::all() as $tier)
                                            <option value="{{ $tier }}" title="Код: {{ $tier }}" @selected($currentActivationTier === $tier)>{{ TariffTier::label($tier) }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- Location -->
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-medium text-gray-700">Локация:</span>
                                    <div class="flex items-center space-x-2">
                                        <img src="https://flagcdn.com/w40/{{ strtolower($server->location->code) }}.png"
                                             class="w-5 h-4 rounded object-cover"
                                             alt="{{ strtoupper($server->location->code) }}"
                                             title="{{ strtoupper($server->location->code) }}">
                                        <span class="text-sm text-gray-900 font-medium">{{ strtoupper($server->location->code) }}</span>
                                    </div>
                                </div>

                                <!-- IP Address -->
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-medium text-gray-700">IP адрес:</span>
                                    <div class="flex items-center space-x-2">
                                        <code class="bg-gray-100 px-2 py-1 rounded text-xs font-mono text-gray-800">{{ $server->ip }}</code>
                                        <button onclick="copyToClipboard('{{ $server->ip }}', 'IP адрес')" 
                                                class="text-gray-400 hover:text-indigo-600 transition-colors p-1 rounded hover:bg-gray-100"
                                                title="Копировать IP адрес">
                                            <i class="fas fa-copy text-xs"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- Host -->
                                <div class="flex items-start justify-between">
                                    <span class="text-sm font-medium text-gray-700">Хост:</span>
                                    <span class="text-sm text-gray-900 text-right font-mono break-all ml-2">{{ $server->host }}</span>
                                </div>

                                <!-- Credentials -->
                                <div class="pt-2 border-t border-gray-200">
                                    <div class="space-y-2">
                                        <div class="flex items-center justify-between">
                                            <span class="text-sm font-medium text-gray-700">Логин:</span>
                                            <div class="flex items-center space-x-2">
                                                <span class="text-sm text-gray-900 font-mono">{{ $server->login }}</span>
                                                <button onclick="copyToClipboard('{{ $server->login }}', 'Логин')" 
                                                        class="text-gray-400 hover:text-indigo-600 transition-colors p-1 rounded hover:bg-gray-100"
                                                        title="Копировать логин">
                                                    <i class="fas fa-copy text-xs"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="flex items-center justify-between">
                                            <span class="text-sm font-medium text-gray-700">Пароль:</span>
                                            <div class="flex items-center space-x-2">
                                                <span class="text-sm text-gray-900 font-mono text-xs break-all max-w-[120px] truncate" title="{{ $server->password }}">{{ $server->password }}</span>
                                                <button onclick="copyToClipboard('{{ $server->password }}', 'Пароль')" 
                                                        class="text-gray-400 hover:text-indigo-600 transition-colors p-1 rounded hover:bg-gray-100 flex-shrink-0"
                                                        title="Копировать пароль">
                                                    <i class="fas fa-copy text-xs"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Logs Upload Status -->
                                <div class="pt-2 border-t border-gray-200">
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm font-medium text-gray-700">Выгрузка логов:</span>
                                        @if($server->logs_upload_enabled)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800" title="Выгрузка логов активна">
                                                <i class="fas fa-check-circle mr-1"></i> Включена
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800" title="Выгрузка логов не настроена">
                                                <i class="fas fa-times-circle mr-1"></i> Выключена
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <!-- Card Footer -->
                            @if($server->server_status !== Server::SERVER_DELETED)
                                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 rounded-b-lg">
                                    @if($server->usesManualStrategy() && (int)$server->server_status === (int)Server::SERVER_CREATED)
                                        <div class="flex flex-col gap-2 mb-3 p-3 bg-amber-50 border border-amber-200 rounded-md">
                                            <p class="text-sm text-amber-800 font-medium">Настройте DNS и проверьте доступность:</p>
                                            <div class="flex flex-wrap items-end gap-2">
                                                <label class="flex flex-col text-xs text-amber-900">
                                                    <span class="mb-1">Порт SSH (для проверки TCP)</span>
                                                    <input type="number" min="1" max="65535" id="manualSshPort-{{ $server->id }}"
                                                           class="w-28 rounded-md border border-amber-300 px-2 py-1.5 text-sm text-gray-900 shadow-sm focus:border-amber-500 focus:ring-amber-500"
                                                           value="{{ $server->ssh_port ?? '' }}"
                                                           placeholder="22">
                                                </label>
                                            </div>
                                            <div class="flex flex-wrap gap-2">
                                                <button type="button" onclick="setupDnsManual({{ $server->id }})"
                                                        class="inline-flex items-center justify-center px-3 py-2 text-sm font-medium rounded-md text-amber-800 bg-amber-100 hover:bg-amber-200 border border-amber-300 transition-colors">
                                                    <i class="fas fa-globe mr-2"></i>
                                                    <span>Настроить DNS</span>
                                                </button>
                                                <button type="button" onclick="pingAndConfigureManual({{ $server->id }})"
                                                        class="inline-flex items-center justify-center px-3 py-2 text-sm font-medium rounded-md text-green-800 bg-green-100 hover:bg-green-200 border border-green-300 transition-colors">
                                                    <i class="fas fa-network-wired mr-2"></i>
                                                    <span>Пинг и отметить настроенным</span>
                                                </button>
                                            </div>
                                        </div>
                                    @endif
                                    {{-- Одна строка кнопок: по 2 в ряд (flex + basis), «Удалить» на всю ширину --}}
                                    <div class="flex flex-wrap gap-2">
                                        @if($server->panel)
                                            <a href="{{ route('admin.module.panel.index', ['panel_id' => $server->panel->id]) }}"
                                               class="inline-flex flex-1 min-w-[min(100%,10rem)] basis-[calc(50%-0.25rem)] max-w-full items-center justify-center px-3 py-2 text-sm font-medium rounded-md text-indigo-700 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 transition-colors">
                                                <i class="fas fa-desktop mr-2"></i>
                                                <span>Панель</span>
                                            </a>
                                        @endif
                                        <a href="{{ route('admin.module.server-users.index', ['server_id' => $server->id]) }}"
                                           class="inline-flex flex-1 min-w-[min(100%,10rem)] basis-[calc(50%-0.25rem)] max-w-full items-center justify-center px-3 py-2 text-sm font-medium rounded-md text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 transition-colors">
                                            <i class="fas fa-users mr-2"></i>
                                            <span>Пользователи</span>
                                        </a>
                                        @if($server->usesManualStrategy())
                                            <button type="button" onclick='openEditProviderModal({{ $server->id }}, @json($server->provider))'
                                                    class="inline-flex flex-1 min-w-[min(100%,10rem)] basis-[calc(50%-0.25rem)] max-w-full items-center justify-center px-3 py-2 text-sm font-medium rounded-md text-slate-700 bg-slate-50 hover:bg-slate-100 border border-slate-200 transition-colors">
                                                <i class="fas fa-tag mr-2"></i>
                                                <span>Код провайдера</span>
                                            </button>
                                        @endif
                                        @if(!$server->logs_upload_enabled)
                                            <button type="button" onclick="enableLogUpload({{ $server->id }})"
                                                    class="inline-flex flex-1 min-w-[min(100%,10rem)] basis-[calc(50%-0.25rem)] max-w-full items-center justify-center px-3 py-2 text-sm font-medium rounded-md text-blue-700 bg-blue-50 hover:bg-blue-100 border border-blue-200 transition-colors">
                                                <i class="fas fa-upload mr-2"></i>
                                                <span>Включить логи</span>
                                            </button>
                                        @endif
                                        <button type="button" onclick="checkLogUploadStatus({{ $server->id }})"
                                                class="inline-flex flex-1 min-w-[min(100%,10rem)] basis-[calc(50%-0.25rem)] max-w-full items-center justify-center px-3 py-2 text-sm font-medium rounded-md text-green-700 bg-green-50 hover:bg-green-100 border border-green-200 transition-colors">
                                            <i class="fas fa-check-circle mr-2"></i>
                                            <span>Проверить логи</span>
                                        </button>
                                        <button type="button" onclick="rebootServer({{ $server->id }})"
                                                class="inline-flex flex-1 min-w-[min(100%,10rem)] basis-[calc(50%-0.25rem)] max-w-full items-center justify-center px-3 py-2 text-sm font-medium rounded-md text-amber-800 bg-amber-50 hover:bg-amber-100 border border-amber-300 transition-colors">
                                            <i class="fas fa-power-off mr-2"></i>
                                            <span>Перезагрузить сервер</span>
                                        </button>
                                        <button type="button" onclick="deleteServer({{ $server->id }})"
                                                class="inline-flex w-full items-center justify-center px-3 py-2 text-sm font-medium rounded-md text-red-700 bg-red-50 hover:bg-red-100 border border-red-200 transition-colors">
                                            <i class="fas fa-trash mr-2"></i>
                                            <span>Удалить</span>
                                        </button>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>

                <!-- Pagination -->
                <div class="mt-6">
                    <x-admin.pagination-wrapper :paginator="$servers" />
                </div>
            @endif
        </x-admin.card>
    </div>

    <!-- Modal: Create Server -->
    <x-admin.modal id="createServerModal" title="Добавить сервер">
        <form id="createServerForm">
            @csrf
            <div class="mb-4">
                <label for="createServerProvider" class="block text-sm font-medium text-gray-700 mb-1">
                    Провайдер
                </label>
                <select id="createServerProvider" name="provider" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 sm:text-sm" required>
                    <option value="">Выберите провайдера...</option>
                    <option value="{{ Server::VDSINA }}">VDSina</option>
                    <option value="{{ Server::TIMEWEB }}">Timeweb Cloud</option>
                </select>
            </div>
            <div class="mb-4">
                <label for="createServerLocation" class="block text-sm font-medium text-gray-700 mb-1">
                    Локация
                </label>
                <select id="createServerLocation" name="location_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 sm:text-sm" required>
                    <option value="">Выберите локацию...</option>
                    @foreach($locations as $location)
                        <option value="{{ $location->id }}">{{ $location->code }} {{ $location->emoji }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-4">
                <label for="createServerTariffTier" class="block text-sm font-medium text-gray-700 mb-1">Тариф для выдачи ключей</label>
                <select id="createServerTariffTier" name="tariff_tier" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 sm:text-sm">
                    @foreach (TariffTier::all() as $tier)
                        <option value="{{ $tier }}" title="Код: {{ $tier }}" @selected($tier === TariffTier::FULL)>{{ TariffTier::label($tier) }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-500">Тот же пул, что и у ручных серверов. Подсказка при наведении на пункт — внутренний код.</p>
            </div>
        </form>
        <x-slot name="footer">
            <button type="button" 
                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 create-server" 
                    id="createServerBtn">
                Создать сервер
            </button>
            <button type="button" 
                    class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" 
                    onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: { id: 'createServerModal' } }))">
                Отмена
            </button>
        </x-slot>
    </x-admin.modal>

    <!-- Modal: Add manual server (no API) -->
    <x-admin.modal id="createManualServerModal" title="Добавить сервер вручную (провайдер без API)">
        <form id="createManualServerForm">
            @csrf
            <div class="mb-4">
                <label for="manualServerLocation" class="block text-sm font-medium text-gray-700 mb-1">Локация <span class="text-red-500">*</span></label>
                <select id="manualServerLocation" name="location_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 sm:text-sm" required>
                    <option value="">Выберите локацию...</option>
                    @foreach($locations as $location)
                        <option value="{{ $location->id }}">{{ $location->code }} {{ $location->emoji }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-4">
                <label for="manualProviderName" class="block text-sm font-medium text-gray-700 mb-1">Название провайдера <span class="text-red-500">*</span></label>
                <input type="text" id="manualProviderName" name="provider_name" list="manualProviderNameSuggestions" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 sm:text-sm" placeholder="Например: Selectel, Hetzner, свой VPS" required autocomplete="off">
                <datalist id="manualProviderNameSuggestions">
                    @foreach($manualProviderSuggestions ?? [] as $code)
                        <option value="{{ $code }}"></option>
                    @endforeach
                </datalist>
                <p class="mt-1 text-xs text-gray-500">Один и тот же код для нескольких серверов одного хостера — ротация объединит панели. В конфиге слотов укажите латинский код (см. подсказку после сохранения).</p>
            </div>
            <div class="mb-4">
                <label for="manualServerName" class="block text-sm font-medium text-gray-700 mb-1">Название сервера <span class="text-red-500">*</span></label>
                <input type="text" id="manualServerName" name="name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 sm:text-sm" placeholder="Например: VPS Finland" required>
            </div>
            <div class="mb-4">
                <label for="manualServerIp" class="block text-sm font-medium text-gray-700 mb-1">IP-адрес <span class="text-red-500">*</span></label>
                <input type="text" id="manualServerIp" name="ip" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 sm:text-sm" placeholder="1.2.3.4" required>
            </div>
            <div class="mb-4">
                <label for="manualServerHost" class="block text-sm font-medium text-gray-700 mb-1">Хост (опционально)</label>
                <input type="text" id="manualServerHost" name="host" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 sm:text-sm" placeholder="host.example.com или оставьте пустым">
            </div>
            <div class="mb-4">
                <label for="manualServerLogin" class="block text-sm font-medium text-gray-700 mb-1">Логин (опционально)</label>
                <input type="text" id="manualServerLogin" name="login" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 sm:text-sm" placeholder="root">
            </div>
            <div class="mb-4">
                <label for="manualServerPassword" class="block text-sm font-medium text-gray-700 mb-1">Пароль (опционально)</label>
                <input type="password" id="manualServerPassword" name="password" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 sm:text-sm" placeholder="Пароль доступа к серверу">
                <p class="mt-3 text-sm text-gray-600 mb-1">Порт SSH (для проверки «Пинг»)</p>
                <input type="number" min="1" max="65535" id="manualServerSshPort" name="ssh_port" class="mt-1 block w-full max-w-xs rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 sm:text-sm" placeholder="22 — по умолчанию; у части хостеров — 3333 и др.">
            </div>
            <div class="mb-4">
                <label for="manualServerTariffTier" class="block text-sm font-medium text-gray-700 mb-1">Тариф для выдачи ключей</label>
                <select id="manualServerTariffTier" name="tariff_tier" class="mt-1 block w-full max-w-md rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 sm:text-sm">
                    @foreach (TariffTier::all() as $tier)
                        <option value="{{ $tier }}" title="Код: {{ $tier }}" @selected($tier === TariffTier::FULL)>{{ TariffTier::label($tier) }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-500">Кому выдавать ключи с этого сервера при ротации. Подсказка при наведении — внутренний код.</p>
            </div>
        </form>
        <x-slot name="footer">
            <button type="button" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" id="createManualServerBtn">
                Добавить сервер
            </button>
            <button type="button" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: { id: 'createManualServerModal' } }))">
                Отмена
            </button>
        </x-slot>
    </x-admin.modal>

    <x-admin.modal id="editProviderModal" title="Код провайдера (без API)">
        <input type="hidden" id="editProviderServerId" value="">
        <p class="text-sm text-gray-600 mb-3">Введите название — в БД сохранится нормализованный код (латиница), как при добавлении сервера. Используйте тот же код для всех машин одного хостера, чтобы ротация работала по провайдеру.</p>
        <label for="editProviderNameInput" class="block text-sm font-medium text-gray-700 mb-1">Название провайдера</label>
        <input type="text" id="editProviderNameInput" maxlength="80" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 sm:text-sm" placeholder="Например: my-hosting">
        <x-slot name="footer">
            <button type="button" id="saveEditProviderBtn" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                Сохранить
            </button>
            <button type="button" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: { id: 'editProviderModal' } }))">
                Отмена
            </button>
        </x-slot>
    </x-admin.modal>

    @push('js')
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

            function updateServerTariffTier(serverId, tariffTier) {
                $.ajax({
                    url: '{{ url('/admin/module/server') }}/' + serverId,
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    data: {
                        _token: '{{ csrf_token() }}',
                        _method: 'PUT',
                        tariff_tier: tariffTier
                    },
                    success: function (response) {
                        if (response.success) {
                            toastr.success(response.message || 'Тариф сохранён');
                        } else {
                            toastr.error(response.message || 'Ошибка');
                        }
                    },
                    error: function (xhr) {
                        var msg = 'Ошибка сохранения';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            msg = xhr.responseJSON.message;
                        }
                        toastr.error(msg);
                    }
                });
            }

            // Настроить DNS для ручного сервера (Cloudflare)
            function setupDnsManual(id) {
                $.ajax({
                    url: '{{ route('admin.module.server.setup-dns', ['server' => ':id']) }}'.replace(':id', id),
                    method: 'POST',
                    data: { _token: '{{ csrf_token() }}' },
                    beforeSend: function () {
                        toastr.info('Создание DNS-записи...', '', { timeOut: 2000 });
                    },
                    success: function (response) {
                        if (response.success) {
                            toastr.success(response.message || 'DNS настроен');
                            setTimeout(function () { window.location.reload(); }, 1500);
                        } else {
                            toastr.error(response.message || 'Ошибка настройки DNS');
                        }
                    },
                    error: function (xhr) {
                        var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Ошибка настройки DNS';
                        toastr.error(msg);
                    }
                });
            }

                // Пинг и переход в статус «Настроен» для ручного сервера
            function pingAndConfigureManual(id) {
                var rawPort = $('#manualSshPort-' + id).val();
                rawPort = (rawPort != null && String(rawPort).trim() !== '') ? String(rawPort).trim() : '';
                var portNum = rawPort === '' ? 22 : parseInt(rawPort, 10);
                if (rawPort !== '' && (isNaN(portNum) || portNum < 1 || portNum > 65535)) {
                    toastr.error('Укажите порт SSH от 1 до 65535 или оставьте пустым (22)');
                    return;
                }
                var postData = { _token: '{{ csrf_token() }}' };
                if (rawPort !== '') {
                    postData.ssh_port = portNum;
                }
                $.ajax({
                    url: '{{ route('admin.module.server.ping-and-configure', ['server' => ':id']) }}'.replace(':id', id),
                    method: 'POST',
                    data: postData,
                    beforeSend: function () {
                        toastr.info('Проверка TCP к порту SSH ' + (rawPort !== '' ? portNum : '22 (по умолчанию)') + '...', '', { timeOut: 2000 });
                    },
                    success: function (response) {
                        if (response.success) {
                            toastr.success(response.message || 'Сервер отмечен как настроенный');
                            setTimeout(function () { window.location.reload(); }, 1500);
                        } else {
                            toastr.error(response.message || 'Сервер недоступен');
                        }
                    },
                    error: function (xhr) {
                        var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Сервер недоступен или ошибка запроса';
                        toastr.error(msg);
                    }
                });
            }

            function rebootServer(id) {
                if (!confirm('Перезагрузить сервер? Сервисы на нём (в т.ч. VPN) будут недоступны 1–3 минуты.')) {
                    return;
                }
                $.ajax({
                    url: '{{ url('/admin/module/server') }}/' + id + '/reboot',
                    method: 'POST',
                    data: { _token: '{{ csrf_token() }}' },
                    success: function (response) {
                        if (response.success) {
                            toastr.success(response.message || 'Перезагрузка запланирована');
                        } else {
                            toastr.error(response.message || 'Ошибка');
                        }
                    },
                    error: function (xhr) {
                        var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Ошибка перезагрузки';
                        toastr.error(msg);
                    }
                });
            }

            // Глобальная функция удаления сервера
            function deleteServer(id) {
                if (confirm('Вы уверены, что хотите удалить этот сервер?')) {
                    $.ajax({
                        url: '{{ route('admin.module.server.destroy', ['server' => ':id']) }}'.replace(':id', id),
                        method: 'DELETE',
                        data: {
                            _token: '{{ csrf_token() }}'
                        },
                        success: function (response) {
                            toastr.success('Сервер успешно удален');
                            setTimeout(() => {
                                window.location.reload();
                            }, 1000);
                        },
                        error: function (xhr) {
                            let errorMessage = 'Произошла ошибка при удалении сервера';
                            if (xhr.responseJSON) {
                                errorMessage = xhr.responseJSON.message || errorMessage;
                            }
                            toastr.error(errorMessage);
                        }
                    });
                }
            }

            $(document).ready(function () {
                // Настройка toastr
                toastr.options = {
                    "closeButton": true,
                    "progressBar": true,
                    "positionClass": "toast-top-right",
                    "timeOut": "3000"
                };

                console.log('Document ready');

                window.openEditProviderModal = function (serverId, currentCode) {
                    $('#editProviderServerId').val(serverId);
                    var readable = (currentCode === 'manual' || !currentCode) ? '' : String(currentCode).split('-').join(' ');
                    $('#editProviderNameInput').val(readable);
                    window.dispatchEvent(new CustomEvent('open-modal', { detail: { id: 'editProviderModal' } }));
                };

                $('#saveEditProviderBtn').on('click', function () {
                    var id = $('#editProviderServerId').val();
                    var provider_name = $('#editProviderNameInput').val().trim();
                    if (!id || !provider_name) {
                        toastr.error('Укажите название провайдера');
                        return;
                    }
                    var btn = $(this);
                    btn.prop('disabled', true);
                    $.ajax({
                        url: '{{ url('/admin/module/server') }}/' + id,
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                        data: {
                            _token: '{{ csrf_token() }}',
                            _method: 'PUT',
                            provider_name: provider_name
                        },
                        success: function (response) {
                            if (response.success) {
                                toastr.success(response.message || 'Сохранено');
                                window.dispatchEvent(new CustomEvent('close-modal', { detail: { id: 'editProviderModal' } }));
                                setTimeout(function () { window.location.reload(); }, 800);
                            } else {
                                toastr.error(response.message || 'Ошибка');
                            }
                        },
                        error: function (xhr) {
                            var msg = 'Ошибка сохранения';
                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                msg = xhr.responseJSON.message;
                            }
                            toastr.error(msg);
                        },
                        complete: function () {
                            btn.prop('disabled', false);
                        }
                    });
                });

                // Обработчик создания сервера
                $('.create-server').on('click', function () {
                    const btn = $(this);
                    const provider = $('#createServerProvider').val();
                    const location_id = $('#createServerLocation').val();
                    const tariff_tier = $('#createServerTariffTier').val();

                    if (!provider || !location_id) {
                        toastr.error('Пожалуйста, выберите провайдера и локацию');
                        return;
                    }

                    // Отключаем кнопку
                    btn.prop('disabled', true);

                    // Показываем индикатор загрузки
                    const loadingHtml = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Создание...';
                    const originalHtml = btn.html();
                    btn.html(loadingHtml);

                    $.ajax({
                        url: '{{ route('admin.module.server.store') }}',
                        method: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}',
                            provider: provider,
                            location_id: location_id,
                            tariff_tier: tariff_tier || 'full'
                        },
                        success: function (response) {
                            if (response.success) {
                                toastr.success('Сервер успешно создан');
                                setTimeout(() => {
                                    window.location.reload();
                                }, 1000);
                            } else {
                                toastr.error(response.message || 'Произошла ошибка при создании сервера');
                            }
                        },
                        error: function (xhr) {
                            let errorMessage = 'Произошла ошибка при создании сервера';
                            if (xhr.responseJSON) {
                                if (xhr.responseJSON.errors) {
                                    errorMessage = Object.values(xhr.responseJSON.errors).join('<br>');
                                } else {
                                    errorMessage = xhr.responseJSON.message || errorMessage;
                                }
                            }
                            toastr.error(errorMessage);
                        },
                        complete: function () {
                            // Возвращаем кнопку в исходное состояние
                            btn.prop('disabled', false);
                            btn.html(originalHtml);
                        }
                    });
                });

                // Добавить сервер вручную (без API)
                $('#createManualServerBtn').on('click', function () {
                    const btn = $(this);
                    const location_id = $('#manualServerLocation').val();
                    const provider_name = $('#manualProviderName').val().trim();
                    const name = $('#manualServerName').val().trim();
                    const ip = $('#manualServerIp').val().trim();
                    const host = $('#manualServerHost').val().trim();
                    const login = $('#manualServerLogin').val().trim();
                    const password = $('#manualServerPassword').val();
                    const tariff_tier = $('#manualServerTariffTier').val();
                    const sshPortRaw = $('#manualServerSshPort').val();
                    const sshPortTrim = (sshPortRaw != null && String(sshPortRaw).trim() !== '') ? String(sshPortRaw).trim() : '';
                    let ssh_port = null;
                    if (sshPortTrim !== '') {
                        const p = parseInt(sshPortTrim, 10);
                        if (isNaN(p) || p < 1 || p > 65535) {
                            toastr.error('Порт SSH: число от 1 до 65535 или оставьте пустым');
                            return;
                        }
                        ssh_port = p;
                    }

                    if (!location_id || !provider_name || !name || !ip) {
                        toastr.error('Заполните обязательные поля: Локация, название провайдера, название сервера, IP-адрес');
                        return;
                    }

                    btn.prop('disabled', true);
                    const originalHtml = btn.html();
                    btn.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Добавление...');

                    var manualServerPost = {
                        _token: '{{ csrf_token() }}',
                        location_id: location_id,
                        provider_name: provider_name,
                        name: name,
                        ip: ip,
                        host: host || null,
                        login: login || null,
                        password: password || null,
                        tariff_tier: tariff_tier || 'full'
                    };
                    if (ssh_port !== null) {
                        manualServerPost.ssh_port = ssh_port;
                    }
                    $.ajax({
                        url: '{{ route('admin.module.server.store-manual') }}',
                        method: 'POST',
                        data: manualServerPost,
                        success: function (response) {
                            if (response.success) {
                                toastr.success('Сервер добавлен');
                                window.dispatchEvent(new CustomEvent('close-modal', { detail: { id: 'createManualServerModal' } }));
                                setTimeout(function () { window.location.reload(); }, 1000);
                            } else {
                                toastr.error(response.message || 'Ошибка при добавлении сервера');
                            }
                        },
                        error: function (xhr) {
                            let errorMessage = 'Ошибка при добавлении сервера';
                            if (xhr.responseJSON) {
                                if (xhr.responseJSON.errors) {
                                    errorMessage = Object.values(xhr.responseJSON.errors).flat().join(' ');
                                } else {
                                    errorMessage = xhr.responseJSON.message || errorMessage;
                                }
                            }
                            toastr.error(errorMessage);
                        },
                        complete: function () {
                            btn.prop('disabled', false);
                            btn.html(originalHtml);
                        }
                    });
                });
            });

            // Включить выгрузку логов
            function enableLogUpload(id) {
                if (!confirm('Включить выгрузку логов на этом сервере? Это может занять некоторое время.')) {
                    return;
                }

                $.ajax({
                    url: '{{ route('admin.module.server.enable-log-upload', ['server' => ':id']) }}'.replace(':id', id),
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}'
                    },
                    beforeSend: function() {
                        toastr.info('Настройка выгрузки логов...', 'Пожалуйста, подождите');
                    },
                    success: function (response) {
                        if (response.success) {
                            toastr.success(response.message || 'Выгрузка логов успешно включена');
                            setTimeout(() => {
                                window.location.reload();
                            }, 1500);
                        } else {
                            toastr.error(response.message || 'Ошибка при включении выгрузки логов');
                        }
                    },
                    error: function (xhr) {
                        let errorMessage = 'Произошла ошибка при включении выгрузки логов';
                        if (xhr.responseJSON) {
                            errorMessage = xhr.responseJSON.message || errorMessage;
                        }
                        toastr.error(errorMessage);
                    }
                });
            }

            // Проверить статус выгрузки логов
            function checkLogUploadStatus(id) {
                $.ajax({
                    url: '{{ route('admin.module.server.check-log-upload-status', ['server' => ':id']) }}'.replace(':id', id),
                    method: 'GET',
                    beforeSend: function() {
                        toastr.info('Проверка статуса выгрузки логов...', 'Пожалуйста, подождите');
                    },
                    success: function (response) {
                        if (response.success) {
                            let status = response.status;
                            let message = response.message || 'Статус проверен';
                            
                            let details = [];
                            details.push('Установлен: ' + (status.installed ? '✓ Да' : '✗ Нет'));
                            details.push('Cron настроен: ' + (status.cron_configured ? '✓ Да' : '✗ Нет'));
                            details.push('Включено в БД: ' + (status.enabled_in_db ? '✓ Да' : '✗ Нет'));
                            details.push('Активен: ' + (status.active ? '✓ Да' : '✗ Нет'));
                            
                            if (status.active) {
                                toastr.success(message + '\n\n' + details.join('\n'), 'Статус выгрузки логов', {
                                    timeOut: 10000,
                                    extendedTimeOut: 10000
                                });
                            } else {
                                toastr.warning(message + '\n\n' + details.join('\n'), 'Статус выгрузки логов', {
                                    timeOut: 10000,
                                    extendedTimeOut: 10000
                                });
                            }
                            
                            // Обновляем страницу через 2 секунды, чтобы показать обновленный статус
                            setTimeout(() => {
                                window.location.reload();
                            }, 2000);
                        } else {
                            toastr.error(response.message || 'Ошибка при проверке статуса');
                        }
                    },
                    error: function (xhr) {
                        let errorMessage = 'Произошла ошибка при проверке статуса';
                        if (xhr.responseJSON) {
                            errorMessage = xhr.responseJSON.message || errorMessage;
                        }
                        toastr.error(errorMessage);
                    }
                });
            }
        </script>
    @endpush

@endsection

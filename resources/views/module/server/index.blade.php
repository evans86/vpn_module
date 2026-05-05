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
                        'tariff_asc' => 'По тарифу активации (возр.)',
                        'tariff_desc' => 'По тарифу активации (убыв.)',
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
                <div class="flex flex-wrap items-center gap-x-2 gap-y-2 mb-4 p-3 bg-gray-50 rounded-lg border border-gray-200">
                    <span class="text-sm text-gray-600 font-medium shrink-0">Действия:</span>
                    <button type="button"
                            class="shrink-0 whitespace-nowrap inline-flex items-center px-3 py-1.5 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                            onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: { id: 'createServerModal' } }))">
                        <i class="fas fa-plus mr-2"></i>
                        Добавить сервер (API)
                    </button>
                    <button type="button"
                            class="shrink-0 whitespace-nowrap inline-flex items-center px-3 py-1.5 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                            onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: { id: 'createManualServerModal' } }))">
                        <i class="fas fa-server mr-2"></i>
                        Добавить вручную (без API)
                    </button>
                    <button type="button" id="bulkInstallSpeedtestCliBtn"
                            class="shrink-0 whitespace-nowrap inline-flex items-center px-3 py-1.5 border border-amber-400 text-sm font-medium rounded-md shadow-sm text-amber-900 bg-amber-50 hover:bg-amber-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-400"
                            title="По SSH ставит пакет speedtest-cli на серверы со статусом «Настроен» (нужен sudo или root)">
                        <i class="fas fa-tachometer-alt mr-2"></i>
                        speedtest-cli
                    </button>
                    <button type="button" id="bulkApplyDecoyStubBtn"
                            class="shrink-0 whitespace-nowrap inline-flex items-center px-3 py-1.5 border border-emerald-500 text-sm font-medium rounded-md shadow-sm text-emerald-900 bg-emerald-50 hover:bg-emerald-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500"
                            title="По SSH обновить Nginx-заглушку (assets, /test-speed, конфиг) на всех «Настроен», где заглушка уже применялась хотя бы раз. Опция 123.rar — из БД каждой карточки.">
                        <i class="fas fa-mask mr-2"></i>
                        Обновить заглушку
                    </button>
                    <label class="shrink-0 inline-flex items-center gap-1.5 text-xs text-gray-600 whitespace-nowrap cursor-pointer" title="Общая настройка для массового «Обновить заглушку» (как чекбокс в карточке)">
                        <input type="checkbox" id="bulkApplyDecoyStubInstallHostNginx" class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500" checked>
                        <span>Ставить nginx на хост при отсутствии</span>
                    </label>
                    <button type="button" id="bulkRunLogUploadNowBtn"
                            class="shrink-0 whitespace-nowrap inline-flex items-center px-3 py-1.5 border border-teal-500 text-sm font-medium rounded-md shadow-sm text-teal-900 bg-teal-50 hover:bg-teal-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-teal-500"
                            title="На всех серверах с включённой выгрузкой логов по SSH выполнить /root/upload-logs.sh (как cron: сжатие, запись в S3, удаление исходников, при наличии контейнера — docker restart marzban).">
                        <i class="fas fa-cloud-upload-alt mr-2"></i>
                        Выгрузить логи сейчас
                    </button>
                    <button type="button" id="bulkReinstallLogUploadScriptBtn"
                            class="shrink-0 whitespace-nowrap inline-flex items-center px-3 py-1.5 border border-sky-500 text-sm font-medium rounded-md shadow-sm text-sky-900 bg-sky-50 hover:bg-sky-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500"
                            title="На всех «Настроен» с включённой выгрузкой заново выполнить установку скрипта из панели: /tmp/upload-logs-install.sh → /root/upload-logs.sh, ~/.s3cfg, cron (apt/s3cmd, проверка s3cmd ls). Долго, по очереди.">
                        <i class="fas fa-sync-alt mr-2"></i>
                        Обновить скрипт выгрузки
                    </button>
                </div>
                <pre id="bulkInstallSpeedtestCliOut"
                     class="hidden mb-4 w-full text-xs font-mono bg-slate-900 text-green-100 rounded-md p-3 max-h-72 overflow-auto border border-slate-700"></pre>
                <pre id="bulkApplyDecoyStubOut"
                     class="hidden mb-4 w-full text-xs font-mono bg-slate-900 text-green-100 rounded-md p-3 max-h-72 overflow-auto border border-slate-700"></pre>
                <pre id="bulkRunLogUploadNowOut"
                     class="hidden mb-4 w-full text-xs font-mono bg-slate-900 text-green-100 rounded-md p-3 max-h-96 overflow-auto border border-slate-700"></pre>
                <div id="logUploadInstallPanel" class="hidden mb-4 rounded-md border border-sky-700 overflow-hidden shadow-sm">
                    <div class="px-3 py-1.5 text-xs font-medium text-sky-950 bg-sky-100 border-b border-sky-300">
                        Результат: установка / обновление скрипта выгрузки (одна нода или массово) — см. строки ниже
                    </div>
                    <pre id="logUploadInstallResultOut"
                         class="w-full text-xs font-mono bg-slate-900 text-green-100 p-3 max-h-96 overflow-auto border-t border-slate-700 min-h-[2rem]"></pre>
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

                                {{-- Тариф активации: TariffTier::normalize — пустая строка в БД не заменяется через ?? и ломала selected (показывался первый option = free) --}}
                                @php
                                    $currentActivationTier = TariffTier::normalize($server->tariff_tier);
                                @endphp
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-sm font-medium text-gray-700 shrink-0">Тариф активации:</span>
                                    <select
                                        class="text-xs rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 max-w-[10rem]"
                                        title="Какой пул серверов использовать при выборе панели для ключей"
                                        onchange="updateServerTariffTier({{ $server->id }}, this.value)">
                                        @foreach (TariffTier::forAdminSelect() as $tier)
                                            <option value="{{ $tier }}" title="Код: {{ $tier }}" @if($currentActivationTier === $tier) selected @endif>{{ TariffTier::label($tier) }}</option>
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
                                    @php
                                        $decoyStubActive = $server->decoy_stub_last_applied_at
                                            && !\Illuminate\Support\Str::startsWith((string) ($server->decoy_stub_last_message ?? ''), 'Ошибка:');
                                    @endphp
                                    <div class="mb-3 p-3 bg-slate-50 border border-slate-200 rounded-md space-y-2">
                                        <p class="text-sm text-slate-800 font-medium">Nginx-заглушка по IP (80/443)</p>
                                        @if(! $decoyStubActive)
                                            <p class="text-xs text-slate-600">Копирует <code class="text-xs">deploy/stub-assets</code> и конфиг default_server. Нужен root SSH; при включённом чекбоксе ниже — при необходимости ставится nginx в ОС (apt/yum/dnf), иначе — ветки nginx/caddy/docker.</p>
                                        @endif
                                        @if($decoyStubActive)
                                            <div class="flex flex-wrap items-center gap-2">
                                                <button type="button"
                                                        onclick="applyDecoyStub({{ $server->id }}, true)"
                                                        class="group inline-flex items-center gap-2 pl-1 pr-2 py-1 rounded-md text-sm font-medium text-emerald-900 bg-emerald-50 border border-emerald-200 hover:bg-emerald-100 hover:border-emerald-300 transition-colors focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-emerald-500"
                                                        title="Нажмите, чтобы снова применить заглушку (те же настройки по SSH)">
                                                    <span class="inline-flex items-center justify-center w-7 h-7 rounded bg-emerald-200/80 text-emerald-800 group-hover:bg-emerald-200">
                                                        <i class="fas fa-check text-sm"></i>
                                                    </span>
                                                    <span class="pr-0.5">Включена</span>
                                                    <span class="text-xs font-normal text-emerald-800/80 border-l border-emerald-300 pl-2 ml-0.5">
                                                        {{ optional($server->decoy_stub_last_applied_at)->format('d.m.Y H:i') }}
                                                    </span>
                                                    <i class="fas fa-sync-alt text-xs text-emerald-700/80 ml-0.5" aria-hidden="true"></i>
                                                </button>
                                            </div>
                                            @if($server->decoy_stub_last_message)
                                                <p class="text-xs text-slate-600" title="{{ $server->decoy_stub_last_message }}">
                                                    {{ \Illuminate\Support\Str::limit((string) $server->decoy_stub_last_message, 140) }}
                                                </p>
                                            @endif
                                        @else
                                            @if($server->decoy_stub_last_message)
                                                <p class="text-xs text-slate-700">
                                                    <span class="font-medium">После последнего запуска:</span>
                                                    @if($server->decoy_stub_last_applied_at)
                                                        {{ $server->decoy_stub_last_applied_at->format('d.m.Y H:i') }} —
                                                    @endif
                                                    {{ \Illuminate\Support\Str::limit((string) $server->decoy_stub_last_message, 200) }}
                                                </p>
                                            @endif
                                        @endif
                                        <label class="inline-flex items-center gap-2 text-sm text-slate-800 cursor-pointer">
                                            <input type="checkbox" id="decoyStubHostNginx-{{ $server->id }}"
                                                   class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                                   checked>
                                            <span>Установить nginx на хост, если нет</span>
                                        </label>
                                        <label class="inline-flex items-center gap-2 text-sm text-slate-800 cursor-pointer">
                                            <input type="checkbox" id="decoyStub123-{{ $server->id }}"
                                                   class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                                   @if($server->decoy_stub_include_123_rar) checked @endif>
                                            <span>Добавить «приманку» <code class="text-xs">123.rar</code> (~15 МиБ в каталоге заглушки)</span>
                                        </label>
                                        @if(! $decoyStubActive)
                                            <div>
                                                <button type="button" onclick="applyDecoyStub({{ $server->id }}, false)"
                                                        class="inline-flex items-center justify-center px-3 py-2 text-sm font-medium rounded-md text-slate-800 bg-white hover:bg-slate-100 border border-slate-300 transition-colors">
                                                    <i class="fas fa-mask mr-2"></i>
                                                    <span>Применить заглушку</span>
                                                </button>
                                            </div>
                                        @endif
                                    </div>
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
                                            <button type="button" onclick="enableLogUpload({{ $server->id }}, false, @json($server->name ?: 'Сервер #'.$server->id))"
                                                    class="inline-flex flex-1 min-w-[min(100%,10rem)] basis-[calc(50%-0.25rem)] max-w-full items-center justify-center px-3 py-2 text-sm font-medium rounded-md text-blue-700 bg-blue-50 hover:bg-blue-100 border border-blue-200 transition-colors">
                                                <i class="fas fa-upload mr-2"></i>
                                                <span>Включить логи</span>
                                            </button>
                                        @else
                                            <button type="button" onclick="enableLogUpload({{ $server->id }}, true, @json($server->name ?: 'Сервер #'.$server->id))"
                                                    title="Заново записать /root/upload-logs.sh, ключи в ~/.s3cfg и строку cron (как при первом включении, с актуальным бакетом из .env)."
                                                    class="inline-flex flex-1 min-w-[min(100%,10rem)] basis-[calc(50%-0.25rem)] max-w-full items-center justify-center px-3 py-2 text-sm font-medium rounded-md text-sky-800 bg-sky-50 hover:bg-sky-100 border border-sky-300 transition-colors">
                                                <i class="fas fa-sync-alt mr-2"></i>
                                                <span>Обновить скрипт выгрузки</span>
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
                    @foreach (TariffTier::forAdminSelect() as $tier)
                        <option value="{{ $tier }}" title="Код: {{ $tier }}" @if($tier === TariffTier::FULL) selected @endif>{{ TariffTier::label($tier) }}</option>
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
                    @foreach (TariffTier::forAdminSelect() as $tier)
                        <option value="{{ $tier }}" title="Код: {{ $tier }}" @if($tier === TariffTier::FULL) selected @endif>{{ TariffTier::label($tier) }}</option>
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

            function applyDecoyStub(id, isUpdate) {
                var msg = isUpdate
                    ? 'Обновить Nginx-заглушку на этом сервере (тот же SSH, nginx/caddy, каталог /var/www/panel-stub)?'
                    : 'Развернуть Nginx-заглушку на этом сервере (SSH, /etc/nginx, /var/www)?';
                if (!confirm(msg)) {
                    return;
                }
                const includeRar = document.getElementById('decoyStub123-' + id) ? document.getElementById('decoyStub123-' + id).checked : false;
                const elHostNginx = document.getElementById('decoyStubHostNginx-' + id);
                const installHostNginx = elHostNginx ? elHostNginx.checked : true;
                $.ajax({
                    url: '{{ route('admin.module.server.apply-decoy-stub', ['server' => ':id']) }}'.replace(':id', id),
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        include_123_rar: includeRar ? 1 : 0,
                        install_host_nginx: installHostNginx ? 1 : 0
                    },
                    beforeSend: function() {
                        toastr.info('Применяем заглушку…', 'Подождите');
                    },
                    success: function (response) {
                        if (response.success) {
                            toastr.success(response.message || 'Готово');
                            setTimeout(function () { window.location.reload(); }, 1500);
                        } else {
                            toastr.error(response.message || 'Ошибка');
                        }
                    },
                    error: function (xhr) {
                        var errorMessage = 'Ошибка при применении заглушки';
                        if (xhr.responseJSON) {
                            errorMessage = xhr.responseJSON.message || errorMessage;
                        }
                        toastr.error(errorMessage);
                    }
                });
            }

            /** Показать блок результата над списком серверов — установка / обновление скрипта выгрузки */
            function showLogUploadInstallPanel(text) {
                var wrap = document.getElementById('logUploadInstallPanel');
                var out = document.getElementById('logUploadInstallResultOut');
                if (!out || !wrap) {
                    return;
                }
                wrap.classList.remove('hidden');
                out.textContent = text;
                wrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }

            // Включить или переустановить выгрузку логов (тот же эндпоинт)
            function enableLogUpload(id, reinstall, serverLabel) {
                reinstall = !!reinstall;
                serverLabel = serverLabel || ('#' + id);
                var confirmText = reinstall
                    ? 'Переустановить скрипт выгрузки на этом сервере?\n\nБудут заново записаны /root/upload-logs.sh, ~/.s3cfg и задача cron с бакетом и ключами из конфигурации панели. Может занять минуту (apt и s3cmd).'
                    : 'Включить выгрузку логов на этом сервере? Это может занять некоторое время.';
                if (!confirm(confirmText)) {
                    return;
                }

                $.ajax({
                    url: '{{ route('admin.module.server.enable-log-upload', ['server' => ':id']) }}'.replace(':id', id),
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}'
                    },
                    beforeSend: function() {
                        toastr.info(reinstall ? 'Обновление скрипта выгрузки…' : 'Настройка выгрузки логов...', 'Пожалуйста, подождите');
                        showLogUploadInstallPanel(
                            (reinstall ? 'Обновление скрипта выгрузки' : 'Включение выгрузки логов') +
                                '\n' + serverLabel + ' (id ' + id + ')\n\nВыполняется по SSH…'
                        );
                    },
                    success: function (response) {
                        var headline = reinstall ? 'Обновление скрипта выгрузки' : 'Включение выгрузки логов';
                        var statusLine = (response.success ? 'OK' : 'Ошибка') + ': ' + (response.message || '');
                        showLogUploadInstallPanel(
                            headline + '\n' + serverLabel + ' (id ' + id + ')\n' + statusLine
                        );
                        if (response.success) {
                            toastr.success(response.message || 'Выгрузка логов успешно включена');
                            setTimeout(() => window.location.reload(), 6000);
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
                        showLogUploadInstallPanel(
                            (reinstall ? 'Обновление скрипта' : 'Включение выгрузки') +
                                '\n' + serverLabel + ' (id ' + id + ')\nОшибка запроса: ' + errorMessage
                        );
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

            $('#bulkInstallSpeedtestCliBtn').on('click', function () {
                if (!confirm('Установить speedtest-cli по SSH на все серверы со статусом «Настроен»?\n\nНа каждый узел по очереди: apt/dnf/yum/apk (как в ОС). Нужны логин/пароль SSH в карточке и root или sudo без запроса пароля. Может занять несколько минут.')) {
                    return;
                }
                var btn = $(this);
                var out = document.getElementById('bulkInstallSpeedtestCliOut');
                btn.prop('disabled', true);
                if (out) {
                    out.classList.remove('hidden');
                    out.textContent = 'Выполняется…';
                }
                $.ajax({
                    url: '{{ route('admin.module.server.bulk-install-speedtest-cli') }}',
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        only_configured: 1
                    },
                    success: function (response) {
                        var lines = [(response.message || '')];
                        (response.results || []).forEach(function (r) {
                            var prefix = '#' + r.id + ' ' + (r.name || '');
                            if (r.skipped) {
                                lines.push(prefix + ' — пропуск: ' + (r.message || ''));
                            } else {
                                lines.push(prefix + ' — ' + (r.success ? 'OK' : 'ошибка') + ': ' + (r.message || ''));
                            }
                        });
                        if (out) {
                            out.textContent = lines.join('\n');
                        }
                        if (response.success) {
                            toastr.success(response.message || 'Готово');
                        } else {
                            toastr.warning(response.message || 'Есть ошибки — см. лог ниже страницы');
                        }
                    },
                    error: function (xhr) {
                        var msg = 'Запрос не выполнен';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            msg = xhr.responseJSON.message;
                        }
                        toastr.error(msg);
                        if (out) {
                            out.textContent = msg;
                        }
                    },
                    complete: function () {
                        btn.prop('disabled', false);
                    }
                });
            });

            $('#bulkApplyDecoyStubBtn').on('click', function () {
                var installHost = document.getElementById('bulkApplyDecoyStubInstallHostNginx');
                var installHostVal = !installHost || installHost.checked;
                if (!confirm('Обновить Nginx-заглушку по SSH на всех серверах со статусом «Настроен», у которых заглушка уже применялась хотя бы раз?\n\n'
                    + 'На каждый узел по очереди: те же шаги, что кнопка «Применить/обновить» в карточке (файлы в /var/www/panel-stub, сниппет /test-speed, конфиг).\n'
                    + 'Приманка /123.rar: из галочки в карточке каждого сервера (сохранена в БД).\n'
                    + 'Ставить nginx на хост при отсутствии: ' + (installHostVal ? 'да' : 'нет') + '.\n\n'
                    + 'Первое развёртывание на новой машине делайте из карточки. Может занять много времени.')) {
                    return;
                }
                var btn = $(this);
                var out = document.getElementById('bulkApplyDecoyStubOut');
                btn.prop('disabled', true);
                if (out) {
                    out.classList.remove('hidden');
                    out.textContent = 'Выполняется…';
                }
                $.ajax({
                    url: '{{ route('admin.module.server.bulk-apply-decoy-stub') }}',
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        only_configured: 1,
                        only_stub_deployed_before: 1,
                        install_host_nginx: installHostVal ? 1 : 0
                    },
                    success: function (response) {
                        var lines = [(response.message || '')];
                        (response.results || []).forEach(function (r) {
                            var prefix = '#' + r.id + ' ' + (r.name || '');
                            if (r.skipped) {
                                lines.push(prefix + ' — пропуск: ' + (r.message || ''));
                            } else {
                                lines.push(prefix + ' — ' + (r.success ? 'OK' : 'ошибка') + ': ' + (r.message || ''));
                            }
                        });
                        if (out) {
                            out.textContent = lines.join('\n');
                        }
                        if (response.success) {
                            toastr.success(response.message || 'Готово');
                            setTimeout(function () { window.location.reload(); }, 2500);
                        } else {
                            toastr.warning(response.message || 'Есть ошибки — см. лог ниже страницы');
                        }
                    },
                    error: function (xhr) {
                        var msg = 'Запрос не выполнен';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            msg = xhr.responseJSON.message;
                        }
                        toastr.error(msg);
                        if (out) {
                            out.textContent = msg;
                        }
                    },
                    complete: function () {
                        btn.prop('disabled', false);
                    }
                });
            });

            $('#bulkRunLogUploadNowBtn').on('click', function () {
                if (!confirm('Запустить выгрузку логов на всех серверах, у которых в БД включена выгрузка?\n\nНа каждом узле по SSH выполнится /root/upload-logs.sh (архивация, S3, удаление файлов при успехе; при наличии контейнера — перезапуск marzban). Запросы к панели идут пакетами (по 1 серверу за короткий HTTP — иначе прокси/Cloudflare режет один долгий ответ).\nНужны логин и пароль SSH на карточке.')) {
                    return;
                }
                var btn = $(this);
                var out = document.getElementById('bulkRunLogUploadNowOut');
                btn.prop('disabled', true);
                if (out) {
                    out.classList.remove('hidden');
                    out.textContent = 'Выполняется пакетами (одна нода за запрос к панели; обход Cloudflare/nginx)…';
                }

                function firstLines(txt, maxLines) {
                    if (!txt) {
                        return '';
                    }
                    return txt.replace(/\r\n/g, '\n').split('\n').slice(0, maxLines).join('\n');
                }

                var lines = [
                    'Ручная выгрузка логов: несколько коротких HTTP вместо одного долгого (иначе браузер видит ошибку при том, что ноды на origin уже успели выполниться).',
                    ''
                ];
                var totals = { attempted: 0, ok: 0, fail: 0, skipped: 0 };

                function renderOut() {
                    var tail = '\n────────────────────\nИтого: попыток ' + totals.attempted +
                        ', успех ' + totals.ok + ', ошибок ' + totals.fail + ', пропуск ' + totals.skipped;
                    if (out) {
                        out.textContent = lines.join('\n') + tail;
                    }
                }

                function appendBatch(response) {
                    lines.push(response.message || '');
                    (response.results || []).forEach(function (r) {
                        var prefix = '#' + r.id + ' ' + (r.name || '');
                        if (r.skipped) {
                            lines.push(prefix + ' — пропуск: ' + (r.message || ''));
                            return;
                        }
                        lines.push(prefix + ' — ' + (r.success ? 'OK' : 'ошибка') + ': ' + (r.message || ''));
                        var excerpt = firstLines(r.output || '', 12);
                        if (excerpt) {
                            lines.push('  └─ вывод:\n    ' + excerpt.split('\n').join('\n    '));
                        }
                    });
                    lines.push('');
                    var s = response.summary || {};
                    totals.attempted += (s.attempted || 0);
                    totals.ok += (s.ok || 0);
                    totals.fail += (s.fail || 0);
                    totals.skipped += (s.skipped || 0);
                }

                function runBatch(afterId, perBatch) {
                    perBatch = perBatch == null ? 1 : perBatch;
                    $.ajax({
                        url: '{{ route('admin.module.server.bulk-run-log-upload') }}',
                        method: 'POST',
                        timeout: 420000,
                        data: {
                            _token: '{{ csrf_token() }}',
                            after_id: afterId,
                            per_batch: perBatch
                        },
                        success: function (response) {
                            appendBatch(response);
                            renderOut();
                            var b = response.batch || {};
                            if (b.has_more) {
                                lines.push('Следующий пакет…');
                                renderOut();
                                runBatch(b.next_after_id || 0, perBatch);

                                return;
                            }
                            lines.push('===== Готово');
                            renderOut();
                            if (totals.fail === 0 && totals.attempted > 0) {
                                toastr.success('Выгрузка выполнена по всем узлам выборки');
                            } else if (totals.attempted === 0 && totals.skipped === 0) {
                                toastr.info(response.message || 'Нет серверов для обработки');
                            } else if (totals.fail > 0) {
                                toastr.warning('Есть ошибки — см. текст ниже');
                            } else {
                                toastr.success(response.message || 'Готово');
                            }
                            btn.prop('disabled', false);
                        },
                        error: function (xhr, textStatus) {
                            var msg = 'Запрос не выполнен';
                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                msg = xhr.responseJSON.message;
                            } else if (xhr.status === 524) {
                                msg = 'HTTP 524 (Cloudflare): ответ до браузера не успел.';
                            } else if (textStatus === 'timeout') {
                                msg = 'Таймаут запроса (7 мин)';
                            }
                            toastr.error(msg);
                            lines.push('');
                            lines.push('!!! Ошибка при пакете после id ' + afterId + ' (per_batch ' + perBatch + '): ' + msg);
                            if (xhr.status) {
                                lines.push('HTTP-код: ' + xhr.status);
                            }
                            renderOut();
                            if ((xhr.status === 524 || xhr.status === 504) && perBatch > 1) {
                                lines.push('Повтор этого же пакета с per_batch=1…');
                                renderOut();
                                setTimeout(function () {
                                    runBatch(afterId, 1);
                                }, 2500);

                                return;
                            }
                            btn.prop('disabled', false);
                        }
                    });
                }

                renderOut();
                runBatch(0, 1);
            });

            @php
                $bulkAsyncSrvMarker = '__SRVTPL__';
                /**
                 * Не используем route('…log-upload-async-reinstall…'): на части окружений маршруты ещё не задеплоены
                 * или висит старый route:cache без этих имён — тогда компиляция Blade падает. Собираем URL так же надёжно,
                 * как для enable-log-upload с плейсхолдером :id ниже по файлу.
                 */
                $bulkAsyncTplBaseUrl = route('admin.module.server.enable-log-upload', ['server' => $bulkAsyncSrvMarker]);
                $bulkAsyncStartTpl = preg_replace('#/enable-log-upload/?(\?.*)?$#', '/log-upload-async-reinstall/start$1', $bulkAsyncTplBaseUrl);
                $bulkAsyncPollTpl = preg_replace('#/enable-log-upload/?(\?.*)?$#', '/log-upload-async-reinstall/status$1', $bulkAsyncTplBaseUrl);
                $bulkAsyncTargetIdsUrl = rtrim(route('admin.module.server.index'), '/').'/log-upload-async-reinstall-target-ids';
            @endphp

            /**
             * Без async/await: в части WebView/старых движков парсер падает на «async function»,
             * и весь код после этой строки не выполняется — кнопка остаётся без обработчика.
             * Логика та же, через Promise + fetch.
             */
            $('#bulkReinstallLogUploadScriptBtn').on('click', function () {
                if (!confirm('Переустановить скрипт выгрузки на всех «Настроен» серверах с включённой выгрузкой?\n\nУстановка (apt, s3cfg, cron) запускается на ноде в фоне (nohup). Браузер только короткими запросами опрашивает статус — так не действует лимит Cloudflare ~100 с на один долгий HTTP.')) {
                    return;
                }

                var csrf = '{{ csrf_token() }}';
                var bulkStartTpl = @json($bulkAsyncStartTpl);
                var bulkPollTpl = @json($bulkAsyncPollTpl);
                var bulkMarker = @json($bulkAsyncSrvMarker);

                function sleepPromise(ms) {
                    return new Promise(function (resolve) {
                        setTimeout(resolve, ms);
                    });
                }

                function srvUrl(tpl, serverId) {
                    return tpl.split(bulkMarker).join(String(serverId));
                }

                function fetchJsonPromise(url, options) {
                    return fetch(url, options || {}).then(function (res) {
                        return res.json().catch(function () {
                            return {};
                        }).then(function (data) {
                            if (!res.ok && !data.message) {
                                data.message = res.status === 524
                                    ? 'HTTP 524 (Cloudflare)'
                                    : ('HTTP ' + res.status);
                            }
                            return { res: res, data: data };
                        });
                    });
                }

                var btn = $(this);
                btn.prop('disabled', true);

                var lines = [
                    'Массовое обновление скрипта выгрузки (фон на ноде + короткие опросы)',
                    'Каждый запрос к origin короткий (обход Cloudflare 524 на одной длинной операции).',
                    ''
                ];
                var totals = { handled: 0, ok: 0, fail: 0, startFail: 0 };

                function renderOut() {
                    var tail = '\n────────────────────\nИтого: обработано ' + totals.handled + ', успех ' + totals.ok +
                        ', ошибок ' + totals.fail + ', не удалось запустить фон ' + totals.startFail;
                    showLogUploadInstallPanel(lines.join('\n') + tail);
                }

                renderOut();

                var listUrl = @json($bulkAsyncTargetIdsUrl) + '?only_configured=1';

                function finishAll(targetsLength) {
                    lines.push('===== Завершено');
                    renderOut();
                    if (totals.fail === 0 && totals.startFail === 0 && totals.handled > 0) {
                        toastr.success('Массовое обновление скриптов завершено');
                    } else if (targetsLength === 0 && totals.handled === 0 && totals.startFail === 0) {
                        toastr.info('Нет серверов в списке');
                    } else {
                        toastr.warning('Есть ошибки — см. блок выше');
                    }
                    btn.prop('disabled', false);
                }

                function processServerAt(i, targets) {
                    if (i >= targets.length) {
                        finishAll(targets.length);
                        return;
                    }

                    var t = targets[i];
                    var sid = t.id;
                    var label = '#' + sid + ' ' + (t.name || '');

                    lines.push('— ' + label);
                    lines.push('  запуск фоновой установки на ноде…');
                    renderOut();

                    var startUrl = srvUrl(bulkStartTpl, sid);
                    var pollUrl = srvUrl(bulkPollTpl, sid) + '?only_configured=1';

                    fetchJsonPromise(startUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                            'X-CSRF-TOKEN': csrf,
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: '_token=' + encodeURIComponent(csrf) + '&only_configured=1'
                    }).then(function (rStart) {
                        if (!rStart.res.ok || !rStart.data.success) {
                            totals.startFail++;
                            lines[lines.length - 1] = '  не удалось запустить: ' + (rStart.data.message || rStart.res.status);
                            lines.push('');
                            renderOut();
                            processServerAt(i + 1, targets);
                            return;
                        }

                        function pollStep(n) {
                            if (n >= 600) {
                                totals.handled++;
                                totals.fail++;
                                lines[lines.length - 1] = '  таймаут: нет файла статуса (см. /tmp/log-upload-async-install.log на сервере).';
                                lines.push('');
                                renderOut();
                                processServerAt(i + 1, targets);
                                return;
                            }

                            sleepPromise(n === 0 ? 2500 : 6500).then(function () {
                                return fetchJsonPromise(pollUrl, {
                                    credentials: 'same-origin',
                                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                                });
                            }).then(function (rPoll) {
                                if (!rPoll.res.ok) {
                                    totals.handled++;
                                    totals.fail++;
                                    lines[lines.length - 1] = '  ошибка опроса: ' + (rPoll.data.message || rPoll.res.status);
                                    lines.push('');
                                    renderOut();
                                    processServerAt(i + 1, targets);
                                    return;
                                }

                                if (rPoll.data.pending) {
                                    lines[lines.length - 1] = '  ожидание ноды (опрос ' + (n + 1) + ')…';
                                    renderOut();
                                    pollStep(n + 1);
                                    return;
                                }

                                totals.handled++;
                                if (rPoll.data.success) {
                                    totals.ok++;
                                    lines[lines.length - 1] = '  OK: ' + (rPoll.data.message || '');
                                } else {
                                    totals.fail++;
                                    lines[lines.length - 1] = '  ошибка: ' + (rPoll.data.message || '');
                                }
                                lines.push('');
                                renderOut();
                                processServerAt(i + 1, targets);
                            }).catch(function (e) {
                                totals.handled++;
                                totals.fail++;
                                lines[lines.length - 1] = '  ошибка сети при опросе: ' + (e && e.message ? e.message : String(e));
                                lines.push('');
                                renderOut();
                                processServerAt(i + 1, targets);
                            });
                        }

                        pollStep(0);
                    }).catch(function (e) {
                        lines[lines.length - 1] = '  ошибка сети при старте: ' + (e && e.message ? e.message : String(e));
                        lines.push('');
                        renderOut();
                        processServerAt(i + 1, targets);
                    });
                }

                fetchJsonPromise(listUrl, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                }).then(function (rList) {
                    if (!rList.res.ok || !Array.isArray(rList.data.targets)) {
                        lines.push('Не удалось получить список: ' + (rList.data.message || rList.res.status));
                        renderOut();
                        toastr.error('Не удалось получить список серверов');
                        btn.prop('disabled', false);
                        return;
                    }

                    var targets = rList.data.targets;
                    lines.push('К обработке: ' + targets.length + ' сервера(ов).');
                    lines.push('');
                    renderOut();

                    processServerAt(0, targets);
                }).catch(function (e) {
                    lines.push('');
                    lines.push('Ошибка в браузере: ' + (e && e.message ? e.message : String(e)));
                    renderOut();
                    toastr.error('Сбой сценария в браузере');
                    btn.prop('disabled', false);
                });
            });
        </script>
    @endpush

@endsection

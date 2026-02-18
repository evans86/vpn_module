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
                                        {{ $panel->config_type_badge_class === 'secondary' ? 'bg-gray-100 text-gray-800' : '' }}">
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
                                    <div class="grid grid-cols-2 gap-4">
                                        <!-- Left Side: Navigation Actions -->
                                        <div class="flex flex-col gap-2">
                                            <a href="{{ route('admin.module.server-users.index', ['panel_id' => $panel->id]) }}"
                                               class="inline-flex items-center justify-center px-3 py-2 text-sm font-medium rounded-md text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 transition-colors w-full">
                                                <i class="fas fa-users mr-2"></i>
                                                <span>Пользователи</span>
                                            </a>
                                            @if($panel->panel_status === Panel::PANEL_CONFIGURED)
                                                <a href="{{ route('admin.module.server-monitoring.index', ['panel_id' => $panel->id]) }}"
                                                   class="inline-flex items-center justify-center px-3 py-2 text-sm font-medium rounded-md text-indigo-700 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 transition-colors w-full">
                                                    <i class="fas fa-chart-line mr-2"></i>
                                                    <span>Статистика</span>
                                                </a>
                                            @endif
                                        </div>
                                        
                                        <!-- Right Side: Service Actions -->
                                        <div class="flex flex-col gap-2">
                                            <!-- Stable Config Button -->
                                            <form action="{{ route('admin.module.panel.update-config-stable', $panel) }}" method="POST" class="w-full">
                                                @csrf
                                                <button type="submit" 
                                                        class="inline-flex items-center justify-center px-3 py-2 text-sm font-medium rounded-md text-blue-700 bg-blue-50 hover:bg-blue-100 border border-blue-200 transition-colors w-full
                                                        {{ $panel->config_type === 'stable' ? 'ring-2 ring-blue-500' : '' }}"
                                                        title="Стабильный конфиг (без REALITY) - максимальная надежность">
                                                    <i class="fas fa-shield-alt mr-2"></i>
                                                    <span>Стабильный</span>
                                                </button>
                                            </form>
                                            
                                            <!-- REALITY Config Button -->
                                            <form action="{{ route('admin.module.panel.update-config-reality', $panel) }}" method="POST" class="w-full">
                                                @csrf
                                                <button type="submit" 
                                                        class="inline-flex items-center justify-center px-3 py-2 text-sm font-medium rounded-md text-green-700 bg-green-50 hover:bg-green-100 border border-green-200 transition-colors w-full
                                                        {{ $panel->config_type === 'reality' ? 'ring-2 ring-green-500' : '' }}"
                                                        title="Конфиг с REALITY - лучший обход блокировок">
                                                    <i class="fas fa-rocket mr-2"></i>
                                                    <span>REALITY</span>
                                                </button>
                                            </form>
                                            
                                            <!-- TLS Toggle Button -->
                                            @if($panel->tls_certificate_path && $panel->tls_key_path)
                                                <button type="button" 
                                                        onclick="toggleTls({{ $panel->id }}, {{ $panel->use_tls ? 'true' : 'false' }})"
                                                        class="inline-flex items-center justify-center px-3 py-2 text-sm font-medium rounded-md {{ $panel->use_tls ? 'text-green-700 bg-green-50 hover:bg-green-100 border-green-200' : 'text-gray-700 bg-gray-50 hover:bg-gray-100 border-gray-200' }} border transition-colors w-full
                                                        {{ $panel->use_tls ? 'ring-2 ring-green-500' : '' }}"
                                                        title="{{ $panel->use_tls ? 'Выключить TLS' : 'Включить TLS' }}">
                                                    <i class="fas {{ $panel->use_tls ? 'fa-lock' : 'fa-unlock' }} mr-2"></i>
                                                    <span>TLS {{ $panel->use_tls ? 'ON' : 'OFF' }}</span>
                                                </button>
                                            @else
                                                <button type="button" 
                                                        onclick="openCertificatesModal({{ $panel->id }}, 'no', false)"
                                                        class="inline-flex items-center justify-center px-3 py-2 text-sm font-medium rounded-md text-purple-700 bg-purple-50 hover:bg-purple-100 border border-purple-200 transition-colors w-full"
                                                        title="Загрузить TLS сертификаты">
                                                    <i class="fas fa-certificate mr-2"></i>
                                                    <span>TLS</span>
                                                </button>
                                            @endif
                                            
                                            <!-- TLS Settings Button (если сертификаты есть) -->
                                            @if($panel->tls_certificate_path && $panel->tls_key_path)
                                                <button type="button" 
                                                        onclick="openCertificatesModal({{ $panel->id }}, 'yes', {{ $panel->use_tls ? 'true' : 'false' }})"
                                                        class="inline-flex items-center justify-center px-3 py-2 text-sm font-medium rounded-md text-purple-700 bg-purple-50 hover:bg-purple-100 border border-purple-200 transition-colors w-full"
                                                        title="Настроить TLS сертификаты">
                                                    <i class="fas fa-cog mr-2"></i>
                                                    <span>Настройки</span>
                                                </button>
                                            @endif
                                            
                                            <!-- Exclude from Rotation Button -->
                                            <button type="button" 
                                                    onclick="toggleRotationExclusion({{ $panel->id }}, {{ $panel->excluded_from_rotation ? 'true' : 'false' }})"
                                                    class="inline-flex items-center justify-center px-3 py-2 text-sm font-medium rounded-md {{ $panel->excluded_from_rotation ? 'text-yellow-700 bg-yellow-50 hover:bg-yellow-100 border-yellow-200' : 'text-gray-700 bg-gray-50 hover:bg-gray-100 border-gray-200' }} border transition-colors w-full"
                                                    title="{{ $panel->excluded_from_rotation ? 'Включить в ротацию' : 'Исключить из ротации (для тестирования)' }}">
                                                <i class="fas {{ $panel->excluded_from_rotation ? 'fa-check-circle' : 'fa-ban' }} mr-2"></i>
                                                <span>{{ $panel->excluded_from_rotation ? 'В ротации' : 'Исключить' }}</span>
                                            </button>
                                            
                                            <button type="button" 
                                                    onclick="deletePanel({{ $panel->id }})"
                                                    class="inline-flex items-center justify-center px-3 py-2 text-sm font-medium rounded-md text-red-700 bg-red-50 hover:bg-red-100 border border-red-200 transition-colors w-full">
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

    <!-- Modal: Upload TLS Certificates -->
    <x-admin.modal id="certificatesModal" title="Настройка TLS сертификатов">
        <form id="certificatesForm" method="POST" enctype="multipart/form-data">
            @csrf
            <div id="certificatesStatus" class="mb-4 p-3 rounded-md bg-gray-50 border border-gray-200">
                <p class="text-sm text-gray-600">
                    <i class="fas fa-info-circle mr-2"></i>
                    <span id="statusText">Проверка статуса...</span>
                </p>
            </div>

            <div class="mb-4" id="certificateUploadSection">
                <label for="certificate" class="block text-sm font-medium text-gray-700 mb-1">
                    Сертификат (cert.pem или cert.crt)
                </label>
                <input type="file" 
                       id="certificate" 
                       name="certificate" 
                       accept=".pem,.crt"
                       class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                <p class="mt-1 text-xs text-gray-500">Формат: PEM или CRT, максимум 10MB</p>
            </div>

            <div class="mb-4" id="keyUploadSection">
                <label for="key" class="block text-sm font-medium text-gray-700 mb-1">
                    Приватный ключ (key.pem или key.key)
                </label>
                <input type="file" 
                       id="key" 
                       name="key" 
                       accept=".pem,.key"
                       class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                <p class="mt-1 text-xs text-gray-500">Формат: PEM или KEY, максимум 10MB</p>
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

            <div class="mb-4 p-3 rounded-md bg-blue-50 border border-blue-200">
                <p class="text-sm text-blue-800">
                    <i class="fas fa-lightbulb mr-2"></i>
                    <strong>Совет:</strong> Если не загрузить сертификаты, будут использоваться настройки по умолчанию из конфигурации.
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

        function openCertificatesModal(panelId, hasCertificates, useTls) {
            currentPanelId = panelId;
            const form = document.getElementById('certificatesForm');
            form.action = '{{ route('admin.module.panel.upload-certificates', ['panel' => ':id']) }}'.replace(':id', panelId);
            
            const statusDiv = document.getElementById('certificatesStatus');
            const statusText = document.getElementById('statusText');
            const removeBtn = document.getElementById('removeCertificatesBtn');
            const useTlsCheckbox = form.querySelector('input[name="use_tls"]');
            
            const useTlsSection = document.getElementById('useTlsSection');
            
            const certificateUploadSection = document.getElementById('certificateUploadSection');
            const keyUploadSection = document.getElementById('keyUploadSection');
            
            // Сбрасываем форму перед открытием
            form.reset();
            
            if (hasCertificates === 'yes') {
                statusDiv.className = 'mb-4 p-3 rounded-md bg-green-50 border border-green-200';
                statusText.innerHTML = '<i class="fas fa-check-circle mr-2 text-green-600"></i>Сертификаты настроены для этой панели';
                removeBtn.style.display = 'inline-flex';
                if (useTlsSection) {
                    useTlsSection.style.display = 'block';
                }
                // Скрываем поля загрузки, если сертификаты уже есть
                if (certificateUploadSection) {
                    certificateUploadSection.style.display = 'none';
                }
                if (keyUploadSection) {
                    keyUploadSection.style.display = 'none';
                }
                // Меняем текст кнопки
                const submitBtn = document.getElementById('submitCertificatesBtn');
                const submitBtnText = document.getElementById('submitBtnText');
                if (submitBtn && submitBtnText) {
                    submitBtnText.textContent = 'Обновить настройки';
                    submitBtn.querySelector('i').className = 'fas fa-save mr-2';
                }
            } else {
                statusDiv.className = 'mb-4 p-3 rounded-md bg-yellow-50 border border-yellow-200';
                statusText.innerHTML = '<i class="fas fa-exclamation-triangle mr-2 text-yellow-600"></i>Сертификаты не настроены, используются настройки по умолчанию';
                removeBtn.style.display = 'none';
                // Показываем чекбокс даже при первой загрузке
                if (useTlsSection) {
                    useTlsSection.style.display = 'block';
                }
                // Показываем поля загрузки, если сертификатов нет
                if (certificateUploadSection) {
                    certificateUploadSection.style.display = 'block';
                }
                if (keyUploadSection) {
                    keyUploadSection.style.display = 'block';
                }
                // Меняем текст кнопки
                const submitBtn = document.getElementById('submitCertificatesBtn');
                const submitBtnText = document.getElementById('submitBtnText');
                if (submitBtn && submitBtnText) {
                    submitBtnText.textContent = 'Загрузить';
                    submitBtn.querySelector('i').className = 'fas fa-upload mr-2';
                }
            }
            
            // Устанавливаем значение use_tls
            if (useTlsCheckbox) {
                useTlsCheckbox.checked = useTls === true || useTls === 'true';
            }
            
            // Сброс формы (кроме use_tls)
            const useTlsValue = useTlsCheckbox ? useTlsCheckbox.checked : false;
            form.reset();
            if (useTlsCheckbox) {
                useTlsCheckbox.checked = useTlsValue;
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
                const formData = new FormData(this);
                
                $.ajax({
                    url: form.attr('action'),
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        toastr.success(response.message || 'Сертификаты успешно загружены');
                        // Закрываем модальное окно
                        window.dispatchEvent(new CustomEvent('close-modal', { detail: { id: 'certificatesModal' } }));
                        // Перезагружаем страницу для обновления статуса
                        setTimeout(() => {
                            window.location.reload();
                        }, 500);
                    },
                    error: function(xhr) {
                        let errorMessage = 'Произошла ошибка при загрузке сертификатов';
                        if (xhr.responseJSON) {
                            errorMessage = xhr.responseJSON.message || errorMessage;
                            if (xhr.responseJSON.errors) {
                                const errors = Object.values(xhr.responseJSON.errors).flat();
                                errorMessage = errors.join(', ');
                            }
                        }
                        toastr.error(errorMessage);
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

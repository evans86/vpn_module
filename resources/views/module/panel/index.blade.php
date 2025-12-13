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
@endsection

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

@extends('layouts.admin')

@section('title', 'Серверы')
@section('page-title', 'Управление серверами')

@php
    use App\Models\Server\Server;
@endphp

@section('content')
    <div class="space-y-6">
        <x-admin.card title="Список серверов">
            <x-slot name="tools">
                <div class="flex items-center space-x-3">
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
                    <button type="button" 
                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                            onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: { id: 'createServerModal' } }))">
                        <i class="fas fa-plus mr-2"></i>
                        Добавить сервер
                    </button>
                </div>
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
            </x-admin.filter-form>

            <!-- Table -->
            @if($servers->isEmpty())
                <x-admin.empty-state 
                    icon="fa-server" 
                    title="Серверы не найдены"
                    description="Попробуйте изменить параметры фильтрации или создать новый сервер">
                    <x-slot name="action">
                        <button type="button" 
                                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: { id: 'createServerModal' } }))">
                            <i class="fas fa-plus mr-2"></i>
                            Добавить сервер
                        </button>
                    </x-slot>
                </x-admin.empty-state>
            @else
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
                                    <div class="grid grid-cols-2 gap-4">
                                        <!-- Left Side: Navigation Actions -->
                                        <div class="flex flex-col gap-2">
                                            @if($server->panel)
                                                <a href="{{ route('admin.module.panel.index', ['panel_id' => $server->panel->id]) }}" 
                                                   class="inline-flex items-center justify-center px-3 py-2 text-sm font-medium rounded-md text-indigo-700 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 transition-colors w-full">
                                                    <i class="fas fa-desktop mr-2"></i>
                                                    <span>Панель</span>
                                                </a>
                                            @endif
                                            <a href="{{ route('admin.module.server-users.index', ['server_id' => $server->id]) }}" 
                                               class="inline-flex items-center justify-center px-3 py-2 text-sm font-medium rounded-md text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 transition-colors w-full">
                                                <i class="fas fa-users mr-2"></i>
                                                <span>Пользователи</span>
                                            </a>
                                        </div>
                                        
                                        <!-- Right Side: Service Actions -->
                                        <div class="flex flex-col gap-2">
                                            <button onclick="enableLogUpload({{ $server->id }})"
                                                    class="inline-flex items-center justify-center px-3 py-2 text-sm font-medium rounded-md text-blue-700 bg-blue-50 hover:bg-blue-100 border border-blue-200 transition-colors w-full">
                                                <i class="fas fa-upload mr-2"></i>
                                                <span>Включить логи</span>
                                            </button>
                                            <button onclick="checkLogUploadStatus({{ $server->id }})"
                                                    class="inline-flex items-center justify-center px-3 py-2 text-sm font-medium rounded-md text-green-700 bg-green-50 hover:bg-green-100 border border-green-200 transition-colors w-full">
                                                <i class="fas fa-check-circle mr-2"></i>
                                                <span>Проверить логи</span>
                                            </button>
                                            <button onclick="deleteServer({{ $server->id }})"
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
                <select id="createServerProvider" name="provider" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 sm:text-sm">
                    <option value="{{ Server::VDSINA }}">VDSina</option>
                </select>
            </div>
        </form>
        <x-slot name="footer">
            <button type="button" 
                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 create-server" 
                    id="createServerBtn" 
                    data-provider="vdsina"
                    data-location="1">
                Создать сервер
            </button>
            <button type="button" 
                    class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" 
                    onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: { id: 'createServerModal' } }))">
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

                // Обработчик создания сервера
                $('.create-server').on('click', function () {
                    const btn = $(this);
                    const provider = btn.data('provider');
                    const location_id = btn.data('location');

                    if (!provider || !location_id) {
                        toastr.error('Не указан провайдер или локация');
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
                            location_id: location_id
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

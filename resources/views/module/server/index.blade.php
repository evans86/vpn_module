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
                <x-admin.table :headers="['#', 'Название', 'IP', 'Логин', 'Пароль', 'Хост', 'Локация', 'Статус', 'Выгрузка логов', 'Действия']">
                    @php
                        $totalServers = $servers->count();
                        $currentIndex = 0;
                    @endphp
                    @foreach($servers as $server)
                        @php
                            $currentIndex++;
                            // Если записей 3 или меньше, все меню открываются сверху
                            // Если записей больше 3, последние 3 открываются сверху
                            $isLastRows = $totalServers <= 3 || $currentIndex > ($totalServers - 3);
                        @endphp
                        <tr class="hover:bg-gray-50 {{ $server->server_status === Server::SERVER_DELETED ? 'opacity-60 bg-gray-100' : '' }}">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                {{ $server->id }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $server->name }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <code class="bg-gray-100 px-2 py-1 rounded text-xs">{{ $server->ip }}</code>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $server->login }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <span class="font-mono text-xs">{{ $server->password }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $server->host }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <div class="flex items-center">
                                    <img src="https://flagcdn.com/w40/{{ strtolower($server->location->code) }}.png"
                                         class="w-6 h-4 mr-2 rounded object-cover"
                                         alt="{{ strtoupper($server->location->code) }}"
                                         title="{{ strtoupper($server->location->code) }}">
                                    <span>{{ strtoupper($server->location->code) }}</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    {{ $server->status_badge_class === 'success' ? 'bg-green-100 text-green-800' : '' }}
                                    {{ $server->status_badge_class === 'danger' ? 'bg-red-100 text-red-800' : '' }}
                                    {{ $server->status_badge_class === 'warning' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                    {{ $server->status_badge_class === 'info' ? 'bg-blue-100 text-blue-800' : '' }}
                                    {{ $server->status_badge_class === 'secondary' ? 'bg-gray-100 text-gray-800' : '' }}">
                                    {{ $server->status_label }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($server->logs_upload_enabled)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800" title="Выгрузка логов активна">
                                        <i class="fas fa-check-circle mr-1"></i> Включена
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800" title="Выгрузка логов не настроена">
                                        <i class="fas fa-times-circle mr-1"></i> Выключена
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                @if($server->server_status !== Server::SERVER_DELETED)
                                    <div class="relative inline-block text-left" x-data="{ open: false }">
                                        <button @click="open = !open" 
                                                class="text-gray-400 hover:text-gray-600 focus:outline-none">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        
                                        <div x-show="open" 
                                             @click.away="open = false"
                                             x-cloak
                                             x-transition
                                             class="absolute right-0 w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50 {{ $isLastRows ? 'origin-bottom-right bottom-full mb-2' : 'origin-top-right top-full mt-2' }}">
                                            <div class="py-1">
                                                @if($server->panel)
                                                    <a href="{{ route('admin.module.panel.index', ['panel_id' => $server->panel->id]) }}" 
                                                       class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                        <i class="fas fa-desktop mr-2"></i> Панель
                                                    </a>
                                                @endif
                                                <a href="{{ route('admin.module.server-users.index', ['server_id' => $server->id]) }}" 
                                                   class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                    <i class="fas fa-users mr-2"></i> Пользователи
                                                </a>
                                                <div class="border-t border-gray-100 my-1"></div>
                                                <button onclick="enableLogUpload({{ $server->id }})"
                                                        class="block w-full text-left px-4 py-2 text-sm text-blue-700 hover:bg-blue-50">
                                                    <i class="fas fa-upload mr-2"></i> Включить выгрузку логов
                                                </button>
                                                <button onclick="checkLogUploadStatus({{ $server->id }})"
                                                        class="block w-full text-left px-4 py-2 text-sm text-green-700 hover:bg-green-50">
                                                    <i class="fas fa-check-circle mr-2"></i> Проверить выгрузку логов
                                                </button>
                                                <div class="border-t border-gray-100 my-1"></div>
                                                <button onclick="deleteServer({{ $server->id }})"
                                                        class="block w-full text-left px-4 py-2 text-sm text-red-700 hover:bg-red-50">
                                                    <i class="fas fa-trash mr-2"></i> Удалить
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </x-admin.table>

                <!-- Pagination -->
                <x-admin.pagination-wrapper :paginator="$servers" />
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

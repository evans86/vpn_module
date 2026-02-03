@extends('layouts.admin')

@section('title', 'Информация о продавце')
@section('page-title', 'Информация о продавце #' . $salesman->id)

@section('content')
    <div class="space-y-6">
        <x-admin.card>
            <x-slot name="title">
                Информация о продавце #{{ $salesman->id }}
            </x-slot>
            <x-slot name="tools">
                <a href="{{ route('admin.module.salesman.index') }}" 
                   class="inline-flex items-center px-3 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <i class="fas fa-arrow-left mr-2"></i> Назад к списку
                </a>
            </x-slot>

            <!-- Основная информация -->
            <div class="mb-6">
                <h5 class="text-lg font-semibold text-gray-900 mb-4">Основная информация</h5>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ID</label>
                        <input type="text" 
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm bg-gray-50 text-gray-500 sm:text-sm" 
                               value="{{ $salesman->id }}" 
                               readonly>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Telegram ID</label>
                        <input type="text" 
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm bg-gray-50 text-gray-500 sm:text-sm" 
                               value="{{ $salesman->telegram_id }}" 
                               readonly>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                        <input type="text" 
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm bg-gray-50 text-gray-500 sm:text-sm" 
                               value="{{ $salesman->username ?? 'Не указан' }}" 
                               readonly>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Статус</label>
                        <div>
                            <button class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md shadow-sm text-white status-toggle {{ $salesman->status ? 'bg-green-600 hover:bg-green-700' : 'bg-red-600 hover:bg-red-700' }} focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                    data-id="{{ $salesman->id }}">
                                {{ $salesman->status ? 'Активен' : 'Неактивен' }}
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Дата регистрации</label>
                        <input type="text" 
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm bg-gray-50 text-gray-500 sm:text-sm" 
                               value="{{ $salesman->created_at->format('d.m.Y H:i') }}" 
                               readonly>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Последнее обновление</label>
                        <input type="text" 
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm bg-gray-50 text-gray-500 sm:text-sm" 
                               value="{{ $salesman->updated_at->format('d.m.Y H:i') }}" 
                               readonly>
                    </div>
                </div>
            </div>

            <!-- Токен и ссылка на бота -->
            <div class="mb-6">
                <h5 class="text-lg font-semibold text-gray-900 mb-4">Данные бота</h5>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Токен бота</label>
                        <div class="flex rounded-md shadow-sm">
                            <input type="text" 
                                   class="flex-1 rounded-l-md border-gray-300 shadow-sm bg-gray-50 text-gray-500 sm:text-sm" 
                                   value="{{ $salesman->token }}"
                                   id="salesmanToken" 
                                   readonly>
                            <button class="inline-flex items-center px-3 py-2 border border-l-0 border-gray-300 rounded-r-md bg-white text-gray-500 hover:bg-gray-50 focus:z-10 focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500"
                                    data-clipboard-target="#salesmanToken">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                        <div class="mt-2">
                            <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 update-token-btn"
                                    data-salesman-id="{{ $salesman->id }}">
                                <i class="fas fa-sync-alt mr-2"></i> Обновить токен
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Ссылка на бота</label>
                        <div class="flex rounded-md shadow-sm">
                            <input type="text" 
                                   class="flex-1 rounded-l-md border-gray-300 shadow-sm bg-gray-50 text-gray-500 sm:text-sm" 
                                   value="{{ $salesman->bot_link }}"
                                   id="botLink" 
                                   readonly>
                            <button class="inline-flex items-center px-3 py-2 border border-l-0 border-gray-300 bg-white text-gray-500 hover:bg-gray-50 focus:z-10 focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500"
                                    data-clipboard-target="#botLink">
                                <i class="fas fa-copy"></i>
                            </button>
                            <a href="{{ $salesman->bot_link }}"
                               target="_blank"
                               class="inline-flex items-center px-3 py-2 border border-l-0 border-gray-300 rounded-r-md bg-white text-indigo-600 hover:bg-indigo-50 focus:z-10 focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500">
                                <i class="fas fa-external-link-alt"></i>
                            </a>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Модуль VPN</label>
                        <div class="flex rounded-md shadow-sm">
                            @if($salesman->botModule !== null)
                                <input type="text" 
                                       class="flex-1 rounded-l-md border-gray-300 shadow-sm bg-gray-50 text-gray-500 sm:text-sm" 
                                       value="{{ $salesman->botModule->public_key }}"
                                       id="salesmanPublicKey" 
                                       readonly>
                            @else
                                <input type="text" 
                                       class="flex-1 rounded-l-md border-gray-300 shadow-sm bg-gray-50 text-gray-500 sm:text-sm" 
                                       value="Модуль не установлен"
                                       id="salesmanPublicKey" 
                                       readonly>
                            @endif
                            <button class="inline-flex items-center px-3 py-2 border border-l-0 border-gray-300 rounded-r-md bg-white text-gray-500 hover:bg-gray-50 focus:z-10 focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500"
                                    data-clipboard-target="#salesmanPublicKey">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Привязанная панель -->
            <div class="mb-6">
                <h5 class="text-lg font-semibold text-gray-900 mb-4">Панель управления</h5>
                @if($salesman->panel)
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Привязанная панель</label>
                        <div class="flex rounded-md shadow-sm">
                            <input type="text" 
                                   class="flex-1 rounded-l-md border-gray-300 shadow-sm bg-gray-50 text-gray-500 sm:text-sm" 
                                   value="{{ $salesman->panel->panel_adress }}"
                                   id="panelAddress" 
                                   readonly>
                            <a href="{{ route('admin.module.panel.index', ['panel_id' => $salesman->panel->id]) }}"
                               class="inline-flex items-center px-3 py-2 border border-l-0 border-gray-300 bg-white text-indigo-600 hover:bg-indigo-50 focus:z-10 focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500">
                                <i class="fas fa-external-link-alt mr-1"></i> Перейти
                            </a>
                            <button class="inline-flex items-center px-3 py-2 border border-l-0 border-gray-300 rounded-r-md bg-white text-red-600 hover:bg-red-50 reset-panel-btn focus:z-10 focus:outline-none focus:ring-1 focus:ring-red-500 focus:border-red-500"
                                    data-salesman-id="{{ $salesman->id }}">
                                <i class="fas fa-times"></i> Отвязать
                            </button>
                        </div>
                    </div>
                @else
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                        <p class="text-yellow-800">Панель не привязана</p>
                    </div>
                    <button class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 assign-panel-btn"
                            data-salesman-id="{{ $salesman->id }}">
                        <i class="fas fa-link mr-2"></i> Привязать панель
                    </button>
                @endif
            </div>

            <div class="mb-6">
                <div class="flex justify-between items-center mb-4">
                    <h5 class="text-lg font-semibold text-gray-900">Модуль VPN</h5>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ID</label>
                        <input type="text" 
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm bg-gray-50 text-gray-500 sm:text-sm" 
                               value="{{ $salesman->module_bot_id ?? 'Не указан' }}" 
                               readonly>
                    </div>
                </div>

                <!-- Пакеты продавца -->
                <div class="mb-6">
                    <div class="flex justify-between items-center mb-4">
                        <h5 class="text-lg font-semibold text-gray-900">Пакеты продавца</h5>
                        <button class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 assign-pack-btn"
                                data-salesman-id="{{ $salesman->id }}">
                            <i class="fas fa-plus mr-2"></i> Добавить пакет
                        </button>
                    </div>

                    @if($salesman->packs->isEmpty())
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <p class="text-blue-800">У продавца нет назначенных пакетов</p>
                        </div>
                    @else
                        <x-admin.table :headers="['ID', 'Название', 'Цена', 'Ключи', 'Статус', 'Дата добавления', 'Действия']">
                            @foreach($salesman->packs as $pack)
                                @php
                                    $packSalesman = \App\Models\PackSalesman\PackSalesman::where('pack_id', $pack->id)->where('salesman_id', $salesman->id)->first();
                                @endphp
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        {{ $pack->id }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ $pack->title }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ $pack->price }} ₽
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <a href="{{ route('admin.module.key-activate.index', ['pack_salesman_id' => $packSalesman->id ?? 0]) }}"
                                           class="text-indigo-600 hover:text-indigo-800"
                                           title="Просмотреть ключи пакета">
                                            {{ $pack->count }} ключей
                                        </a>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($packSalesman)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $packSalesman->isPaid() ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                                                {{ $packSalesman->isPaid() ? 'Оплачен' : 'Ожидает оплаты' }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">Не назначен</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $pack->pivot->created_at->format('d.m.Y H:i') }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        @if($packSalesman && !$packSalesman->isPaid())
                                            <button class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 mark-as-paid-btn focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500"
                                                    data-pack-salesman-id="{{ $packSalesman->id }}">
                                                Отметить оплаченным
                                            </button>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </x-admin.table>
                    @endif
                </div>
            </div>
        </x-admin.card>
    </div>

    <!-- Модальное окно для выбора пакета -->
    <x-admin.modal id="packModal" title="Добавить пакет">
        <form id="assignPackForm">
            <input type="hidden" id="salesmanId" name="salesman_id">
            <div class="mb-4">
                <label for="packId" class="block text-sm font-medium text-gray-700 mb-1">Выберите пакет</label>
                <select class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 sm:text-sm" id="packId" name="pack_id" required>
                    @foreach($packs as $pack)
                        <option value="{{ $pack->id }}">{{ $pack->title }}: {{ $pack->price }}₽</option>
                    @endforeach
                </select>
            </div>
        </form>
        <x-slot name="footer">
            <button type="button" 
                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" 
                    id="assignPackButton">Добавить</button>
            <button type="button" 
                    class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" 
                    onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: { id: 'packModal' } }))">Отмена</button>
        </x-slot>
    </x-admin.modal>

    <!-- Модальное окно для выбора панели -->
    <x-admin.modal id="panelModal" title="Привязать панель">
        <form id="assignPanelForm">
            <input type="hidden" id="salesmanIdForPanel" name="salesman_id">
            <div class="mb-4">
                <label for="panelId" class="block text-sm font-medium text-gray-700 mb-1">Выберите панель</label>
                <select class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 sm:text-sm" id="panelId" name="panel_id" required>
                    @foreach($panels as $panel)
                        <option value="{{ $panel->id }}">{{ $panel->panel_adress }}</option>
                    @endforeach
                </select>
            </div>
        </form>
        <x-slot name="footer">
            <button type="button" 
                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" 
                    id="assignPanelButton">Привязать</button>
            <button type="button" 
                    class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" 
                    onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: { id: 'panelModal' } }))">Отмена</button>
        </x-slot>
    </x-admin.modal>
@endsection

@push('js')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/clipboard.js/2.0.8/clipboard.min.js"></script>
    <script>
        $(document).ready(function () {
            // Настройка CSRF-токена для всех AJAX-запросов
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });

            // Инициализация ClipboardJS
            new ClipboardJS('[data-clipboard-target]');

            // Уведомление о копировании
            $('[data-clipboard-target]').on('click', function () {
                toastr.success('Скопировано в буфер обмена');
            });

            // Обработчик клика по кнопке статуса
            $('.status-toggle').on('click', function () {
                const id = $(this).data('id');
                const btn = $(this);
                $.ajax({
                    url: `/admin/module/salesman/${id}/toggle-status`,
                    type: 'POST',
                    success: function (response) {
                        if (response.success) {
                            if (response.status) {
                                btn.removeClass('bg-red-600 hover:bg-red-700').addClass('bg-green-600 hover:bg-green-700');
                                btn.text('Активен');
                            } else {
                                btn.removeClass('bg-green-600 hover:bg-green-700').addClass('bg-red-600 hover:bg-red-700');
                                btn.text('Неактивен');
                            }
                            toastr.success(response.message);
                        } else {
                            toastr.error(response.message);
                        }
                    },
                    error: function (xhr) {
                        console.error('Error:', xhr);
                        toastr.error('Произошла ошибка при изменении статуса');
                    }
                });
            });

            // Обработчик клика по кнопке "Отвязать панель"
            $('.reset-panel-btn').on('click', function () {
                const salesmanId = $(this).data('salesman-id');

                if (confirm('Вы уверены, что хотите отвязать панель?')) {
                    $.ajax({
                        url: `/admin/module/salesman/${salesmanId}/reset-panel`,
                        method: 'POST',
                        success: function (response) {
                            if (response.success) {
                                toastr.success(response.message);
                                location.reload();
                            } else {
                                toastr.error(response.message);
                            }
                        },
                        error: function (xhr) {
                            toastr.error(xhr.responseJSON?.message || 'Произошла ошибка при отвязке панели');
                        }
                    });
                }
            });

            // Обработчик клика по кнопке "Добавить пакет"
            $('.assign-pack-btn').on('click', function () {
                const salesmanId = $(this).data('salesman-id');
                $('#salesmanId').val(salesmanId);
                window.dispatchEvent(new CustomEvent('open-modal', { detail: { id: 'packModal' } }));
            });

            // Обработчик клика по кнопке "Добавить" в модальном окне пакета
            $('#assignPackButton').on('click', function () {
                const salesmanId = $('#salesmanId').val();
                const packId = $('#packId').val();

                $.ajax({
                    url: `/admin/module/salesman/${salesmanId}/assign-pack`,
                    method: 'POST',
                    data: {
                        pack_id: packId
                    },
                    success: function (response) {
                        if (response.success) {
                            toastr.success('Пакет успешно добавлен');
                            window.dispatchEvent(new CustomEvent('close-modal', { detail: { id: 'packModal' } }));
                            location.reload();
                        } else {
                            toastr.error(response.message || 'Произошла ошибка');
                        }
                    },
                    error: function (xhr) {
                        toastr.error(xhr.responseJSON?.message || 'Произошла ошибка при назначении пакета');
                    }
                });
            });

            // Обработчик клика по кнопке "Отметить оплаченным"
            $('.mark-as-paid-btn').on('click', function () {
                const packSalesmanId = $(this).data('pack-salesman-id');

                if (confirm('Вы уверены, что хотите отметить пакет как оплаченный?')) {
                    $.ajax({
                        url: `/admin/module/pack-salesman/${packSalesmanId}/mark-as-paid`,
                        method: 'POST',
                        success: function (response) {
                            if (response.success) {
                                toastr.success('Пакет успешно отмечен как оплаченный');
                                location.reload();
                            } else {
                                toastr.error(response.message || 'Произошла ошибка');
                            }
                        },
                        error: function (xhr) {
                            toastr.error(xhr.responseJSON?.message || 'Произошла ошибка при обновлении статуса');
                        }
                    });
                }
            });

            // Обработчик клика по кнопке "Привязать панель"
            $('.assign-panel-btn').on('click', function () {
                const salesmanId = $(this).data('salesman-id');
                $('#salesmanIdForPanel').val(salesmanId);
                window.dispatchEvent(new CustomEvent('open-modal', { detail: { id: 'panelModal' } }));
            });

            // Обработчик клика по кнопке "Привязать" в модальном окне панели
            $('#assignPanelButton').on('click', function () {
                const salesmanId = $('#salesmanIdForPanel').val();
                const panelId = $('#panelId').val();

                $.ajax({
                    url: `/admin/module/salesman/${salesmanId}/assign-panel`,
                    method: 'POST',
                    data: {
                        panel_id: panelId
                    },
                    success: function (response) {
                        if (response.success) {
                            toastr.success('Панель успешно привязана');
                            window.dispatchEvent(new CustomEvent('close-modal', { detail: { id: 'panelModal' } }));
                            location.reload();
                        } else {
                            toastr.error(response.message || 'Произошла ошибка');
                        }
                    },
                    error: function (xhr) {
                        toastr.error(xhr.responseJSON?.message || 'Произошла ошибка при привязке панели');
                    }
                });
            });

            // Обновление токена бота
            $('.update-token-btn').on('click', function() {
                const salesmanId = $(this).data('salesman-id');
                const newToken = prompt('Введите новый токен бота:');
                
                if (!newToken || newToken.trim() === '') {
                    return;
                }

                if (!confirm('Вы уверены, что хотите обновить токен бота? Это может повлиять на работу бота.')) {
                    return;
                }

                $.ajax({
                    url: `/admin/module/salesman/${salesmanId}/update-bot-token`,
                    method: 'POST',
                    data: {
                        token: newToken.trim(),
                        _token: $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Токен бота успешно обновлен! Страница будет перезагружена.');
                            location.reload();
                        } else {
                            alert('Ошибка: ' + response.message);
                        }
                    },
                    error: function(xhr) {
                        const error = xhr.responseJSON?.message || 'Произошла ошибка при обновлении токена';
                        alert('Ошибка: ' + error);
                    }
                });
            });
        });
    </script>
@endpush

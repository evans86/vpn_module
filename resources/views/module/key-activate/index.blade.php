@extends('layouts.admin')

@section('title', 'Ключи активации')
@section('page-title', 'Ключи активации')

@section('content')
    <div class="space-y-6">
        <x-admin.card title="Ключи активации">
            <!-- Filters -->
            <x-admin.filter-form action="{{ route('admin.module.key-activate.index') }}">
                <x-admin.filter-input 
                    name="id" 
                    label="ID ключа" 
                    value="{{ request('id') }}" 
                    placeholder="Введите ID" />
                
                <x-admin.filter-input 
                    name="pack_id" 
                    label="ID пакета" 
                    value="{{ request('pack_id') }}" 
                    placeholder="Введите ID пакета" />
                
                <x-admin.filter-select 
                    name="status" 
                    label="Статус"
                    :options="$statuses"
                    value="{{ request('status') }}" />
                
                <x-admin.filter-input 
                    name="user_tg_id" 
                    label="Telegram ID покупателя" 
                    value="{{ request('user_tg_id') }}" 
                    placeholder="Введите Telegram ID"
                    type="number" />
                
                <x-admin.filter-input 
                    name="telegram_id" 
                    label="Telegram ID продавца" 
                    value="{{ request('telegram_id') }}" 
                    placeholder="Введите Telegram ID" />
            </x-admin.filter-form>

            <!-- Table -->
            @if($activate_keys->isEmpty())
                <x-admin.empty-state 
                    icon="fa-key" 
                    title="Ключи не найдены"
                    description="Попробуйте изменить параметры фильтрации" />
            @else
                <x-admin.table :headers="['ID', 'Трафик', 'Пакет продавца', 'Пакет модуля', 'Продавец', 'Дата окончания', 'Telegram ID', 'Пользователь сервера', 'Статус', 'Действия']">
                    @foreach($activate_keys as $key)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <div class="flex items-center">
                                    <span class="font-mono text-xs">{{ substr($key->id, 0, 8) }}...</span>
                                    <button class="ml-2 text-gray-400 hover:text-gray-600"
                                            data-clipboard-text="{{ $key->id }}"
                                            title="Копировать ID">
                                        <i class="fas fa-copy text-xs"></i>
                                    </button>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ number_format($key->traffic_limit / (1024*1024*1024), 1) }} GB
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                @if($key->pack_salesman_id)
                                    <a href="{{ route('admin.module.pack-salesman.index', ['id' => $key->pack_salesman_id]) }}"
                                       class="text-indigo-600 hover:text-indigo-800"
                                       title="Перейти к пакету">
                                        {{ $key->pack_salesman_id }}
                                    </a>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($key->packSalesman && $key->packSalesman->pack)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $key->packSalesman->pack->module_key ? 'bg-yellow-100 text-yellow-800' : 'bg-blue-100 text-blue-800' }}">
                                        {{ $key->packSalesman->pack->module_key ? 'Модуль' : 'Бот' }}
                                    </span>
                                @else
                                    <span class="text-gray-500 text-xs">Не задан (бесплатный)</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                @if($key->packSalesman && $key->packSalesman->salesman)
                                    <a href="{{ url('/admin/module/salesman?telegram_id=' . $key->packSalesman->salesman->telegram_id) }}"
                                       class="text-indigo-600 hover:text-indigo-800">
                                        {{ $key->packSalesman->salesman->telegram_id }}
                                    </a>
                                @else
                                    <span class="text-gray-400">Не указан</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <div class="flex items-center">
                                    @if($key->finish_at)
                                        {{ date('d.m.Y H:i', $key->finish_at) }}
                                        <button class="ml-2 text-indigo-600 hover:text-indigo-800 edit-date"
                                                data-id="{{ $key->id }}"
                                                data-type="finish_at"
                                                data-value="{{ date('d.m.Y H:i', $key->finish_at) }}"
                                                title="Изменить дату">
                                            <i class="fas fa-edit text-xs"></i>
                                        </button>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                @if($key->user_tg_id)
                                    <a href="https://t.me/{{ $key->user_tg_id }}" 
                                       target="_blank"
                                       class="text-indigo-600 hover:text-indigo-800">
                                        {{ $key->user_tg_id }}
                                    </a>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                @if($key->keyActivateUser && $key->keyActivateUser->serverUser)
                                    <a href="{{ route('admin.module.server-users.index', ['id' => $key->keyActivateUser->server_user_id]) }}"
                                       class="btn btn-sm btn-primary">
                                        <i class="fas fa-user"></i>
                                    </a>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    {{ $key->getStatusBadgeClass() === 'success' ? 'bg-green-100 text-green-800' : '' }}
                                    {{ $key->getStatusBadgeClass() === 'danger' ? 'bg-red-100 text-red-800' : '' }}
                                    {{ $key->getStatusBadgeClass() === 'warning' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                    {{ $key->getStatusBadgeClass() === 'info' ? 'bg-blue-100 text-blue-800' : '' }}
                                    {{ $key->getStatusBadgeClass() === 'secondary' ? 'bg-gray-100 text-gray-800' : '' }}">
                                    {{ $key->getStatusText() }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="relative inline-block text-left" x-data="{ open: false }">
                                    <button @click="open = !open" 
                                            class="text-gray-400 hover:text-gray-600 focus:outline-none">
                                        <i class="fas fa-cog"></i>
                                    </button>
                                    
                                    <div x-show="open" 
                                         @click.away="open = false"
                                         x-cloak
                                         x-transition
                                         class="origin-top-right absolute right-0 mt-2 w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-10">
                                        <div class="py-1">
                                            @if($key->user_tg_id)
                                                <button type="button"
                                                        class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 btn-transfer-key"
                                                        data-key-id="{{ $key->id }}"
                                                        data-key-traffic="{{ $key->traffic_limit }}"
                                                        data-key-finish="{{ $key->finish_at }}">
                                                    <i class="fas fa-exchange-alt mr-2"></i> Перенести на другой сервер
                                                </button>
                                            @endif
                                            <button type="button"
                                                    class="block w-full text-left px-4 py-2 text-sm text-red-700 hover:bg-red-50 delete-key"
                                                    data-id="{{ $key->id }}">
                                                <i class="fas fa-trash mr-2"></i> Удалить
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </x-admin.table>

                <!-- Pagination -->
                <x-admin.pagination-wrapper :paginator="$activate_keys" />
            @endif
        </x-admin.card>
    </div>

    <!-- Modal: Transfer Key -->
    <x-admin.modal id="transferKeyModal" title="Перенос ключа на другой сервер" size="md">
        <form id="transfer-key-form">
            <input type="hidden" id="transfer-key-id" name="key_id">
            <div class="mb-4">
                <label for="target-panel-select" class="block text-sm font-medium text-gray-700 mb-1">
                    Выберите сервер для переноса
                </label>
                <select class="form-control" id="target-panel-select" name="target_panel_id" required>
                    <option value="">Выберите сервер</option>
                </select>
                <p class="mt-1 text-sm text-gray-500">
                    Выберите сервер, на который нужно перенести ключ. Все настройки и ограничения будут сохранены.
                </p>
            </div>
        </form>
        <x-slot name="footer">
            <button type="submit" form="transfer-key-form" class="btn btn-primary">
                <span class="spinner spinner-border-sm d-none" role="status"></span>
                Перенести
            </button>
            <button type="button" 
                    class="btn btn-secondary" 
                    onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: { id: 'transferKeyModal' } }))">
                Отмена
            </button>
        </x-slot>
    </x-admin.modal>
@endsection

@push('css')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" type="text/css" href="https://npmcdn.com/flatpickr/dist/themes/material_blue.css">
@endpush

@push('js')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/clipboard.js/2.0.8/clipboard.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/ru.js"></script>
    <script>
        $(document).ready(function () {
            // Инициализация Clipboard.js
            var clipboard = new ClipboardJS('[data-clipboard-text]');
            clipboard.on('success', function (e) {
                toastr.success('Скопировано в буфер обмена');
                e.clearSelection();
            });

            // Инициализация Flatpickr
            flatpickr.localize(flatpickr.l10ns.ru);

            // Обработчик клика по кнопке редактирования даты
            $(document).on('click', '.edit-date', function (e) {
                e.preventDefault();
                const button = $(this);
                const id = button.data('id');
                const type = button.data('type');
                const currentValue = button.data('value');

                const input = $('<input type="text" class="form-control form-control-sm d-inline-block" style="width: 150px;">');
                input.val(currentValue);

                const container = button.parent();
                const originalText = container.contents().filter(function () {
                    return this.nodeType === 3;
                }).first();
                originalText.replaceWith(input);

                const fp = flatpickr(input[0], {
                    enableTime: true,
                    dateFormat: "d.m.Y H:i",
                    defaultDate: currentValue,
                    onClose: function (selectedDates, dateStr) {
                        if (selectedDates.length > 0) {
                            $.ajax({
                                url: '/admin/module/key-activate/update-date',
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                                },
                                data: {
                                    id: id,
                                    type: type,
                                    value: Math.floor(selectedDates[0].getTime() / 1000)
                                },
                                success: function (response) {
                                    if (response.success) {
                                        input.replaceWith(dateStr);
                                        button.data('value', dateStr);
                                        toastr.success('Дата обновлена');
                                    } else {
                                        toastr.error('Ошибка при обновлении даты');
                                        input.replaceWith(currentValue);
                                    }
                                },
                                error: function () {
                                    toastr.error('Ошибка при обновлении даты');
                                    input.replaceWith(currentValue);
                                }
                            });
                        } else {
                            input.replaceWith(currentValue);
                        }
                    }
                });

                fp.open();
            });

            // Обработчик клика по кнопке переноса
            $(document).on('click', '.btn-transfer-key', function () {
                const keyId = $(this).data('key-id');
                $('#transfer-key-id').val(keyId);

                $.ajax({
                    url: '{{ route('admin.module.server-user-transfer.panels') }}',
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: {
                        key_id: keyId
                    },
                    success: function (response) {
                        const panels = response.panels || [];
                        const select = $('#target-panel-select');

                        select.empty();
                        select.append('<option value="">Выберите сервер</option>');

                        if (panels.length > 0) {
                            panels.forEach(function (panel) {
                                const serverName = panel.server_name || 'Неизвестный сервер';
                                const address = panel.address || '';
                                const displayName = address ? `${serverName} (${address})` : serverName;
                                select.append(`<option value="${panel.id}">${displayName}</option>`);
                            });

                            window.dispatchEvent(new CustomEvent('open-modal', { detail: { id: 'transferKeyModal' } }));
                        } else {
                            toastr.warning('Нет доступных серверов для переноса');
                        }
                    },
                    error: function (xhr) {
                        toastr.error('Ошибка при загрузке списка серверов: ' + (xhr.responseJSON?.message || 'Неизвестная ошибка'));
                    }
                });
            });

            // Обработчик отправки формы переноса
            $('#transfer-key-form').on('submit', function (e) {
                e.preventDefault();

                const keyId = $('#transfer-key-id').val();
                const targetPanelId = $('#target-panel-select').val();

                if (!targetPanelId) {
                    toastr.warning('Пожалуйста, выберите сервер для переноса');
                    return;
                }

                const submitBtn = $(this).find('button[type="submit"]');
                const spinner = submitBtn.find('.spinner');

                submitBtn.prop('disabled', true);
                spinner.removeClass('d-none');

                $.ajax({
                    url: '{{ route('admin.module.server-user-transfer.transfer') }}',
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: {
                        key_id: keyId,
                        target_panel_id: targetPanelId
                    },
                    success: function (response) {
                        window.dispatchEvent(new CustomEvent('close-modal', { detail: { id: 'transferKeyModal' } }));
                        toastr.success('Ключ успешно перенесен на новый сервер');
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    },
                    error: function (xhr) {
                        toastr.error('Ошибка при переносе ключа: ' + (xhr.responseJSON?.message || 'Неизвестная ошибка'));
                    },
                    complete: function () {
                        submitBtn.prop('disabled', false);
                        spinner.addClass('d-none');
                    }
                });
            });

            // Обработчик удаления ключа
            $(document).on('click', '.delete-key', function (e) {
                e.preventDefault();
                const id = $(this).data('id');

                if (confirm('Вы уверены, что хотите удалить этот ключ?')) {
                    $.ajax({
                        url: '/admin/module/key-activate/' + id,
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        success: function () {
                            toastr.success('Ключ успешно удален');
                            setTimeout(() => {
                                window.location.reload();
                            }, 1000);
                        },
                        error: function (xhr) {
                            toastr.error('Ошибка при удалении ключа');
                        }
                    });
                }
            });
        });
    </script>
@endpush

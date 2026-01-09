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
                    @php
                        $totalKeys = $activate_keys->count();
                        $currentIndex = 0;
                    @endphp
                    @foreach($activate_keys as $key)
                        @php
                            $currentIndex++;
                            // Если записей 3 или меньше, все меню открываются сверху
                            // Если записей больше 3, последние 3 открываются сверху
                            $isLastRows = $totalKeys <= 3 || $currentIndex > ($totalKeys - 3);
                        @endphp
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
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $key->getStatusBadgeClassSalesman() }}">
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
                                         class="absolute right-0 w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50 @if($isLastRows)origin-bottom-right bottom-full mb-2 @elseorigin-top-right top-full mt-2 @endif">
                                        <div class="py-1">
                                            @if($key->status === \App\Models\KeyActivate\KeyActivate::EXPIRED && $key->user_tg_id)
                                                <button type="button"
                                                        class="block w-full text-left px-4 py-2 text-sm text-blue-700 hover:bg-blue-50 btn-renew-key"
                                                        data-key-id="{{ $key->id }}"
                                                        data-key-traffic="{{ $key->traffic_limit }}"
                                                        data-key-finish="{{ $key->finish_at }}"
                                                        data-key-user-tg-id="{{ $key->user_tg_id }}">
                                                    <i class="fas fa-redo mr-2"></i> Перевыпустить
                                                </button>
                                            @endif
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

    <!-- Modal: Renew Key -->
    <x-admin.modal id="renewKeyModal" title="Перевыпуск ключа" size="md">
        <div class="mb-4">
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-yellow-600"></i>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-yellow-800">
                            Внимание!
                        </h3>
                        <div class="mt-2 text-sm text-yellow-700">
                            <p>Вы собираетесь перевыпустить просроченный ключ. Будет создан новый пользователь сервера с сохранением всех параметров:</p>
                            <ul class="list-disc list-inside mt-2 space-y-1">
                                <li>Остатки трафика: <span id="renew-traffic-info" class="font-semibold"></span></li>
                                <li>Дата окончания: <span id="renew-finish-info" class="font-semibold"></span></li>
                                <li>Telegram ID: <span id="renew-user-tg-id" class="font-semibold"></span></li>
                            </ul>
                            <p class="mt-2 font-semibold">Ключ будет возвращен в статус "Активирован".</p>
                        </div>
                    </div>
                </div>
            </div>
            <p class="text-sm text-gray-600">Вы уверены, что хотите продолжить?</p>
        </div>
        <x-slot name="footer">
            <button type="button" 
                    class="btn btn-primary" 
                    id="confirm-renew-key">
                <span class="spinner spinner-border-sm d-none" role="status"></span>
                Да, перевыпустить
            </button>
            <button type="button" 
                    class="btn btn-secondary" 
                    onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: { id: 'renewKeyModal' } }))">
                Отмена
            </button>
        </x-slot>
    </x-admin.modal>

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

            // Обработчик клика по кнопке перевыпуска
            $(document).on('click', '.btn-renew-key', function () {
                const keyId = $(this).data('key-id');
                const trafficLimit = $(this).data('key-traffic');
                const finishAt = $(this).data('key-finish');
                const userTgId = $(this).data('key-user-tg-id');

                // Форматируем трафик
                const trafficGB = (trafficLimit / (1024 * 1024 * 1024)).toFixed(1);
                $('#renew-traffic-info').text(trafficGB + ' GB');

                // Форматируем дату
                if (finishAt) {
                    const finishDate = new Date(finishAt * 1000);
                    const formattedDate = finishDate.toLocaleDateString('ru-RU', {
                        day: '2-digit',
                        month: '2-digit',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                    $('#renew-finish-info').text(formattedDate);
                } else {
                    $('#renew-finish-info').text('Не указана');
                }

                $('#renew-user-tg-id').text(userTgId);

                // Сохраняем ID ключа в data-атрибут кнопки подтверждения
                $('#confirm-renew-key').data('key-id', keyId);

                window.dispatchEvent(new CustomEvent('open-modal', { detail: { id: 'renewKeyModal' } }));
            });

            // Обработчик подтверждения перевыпуска
            $('#confirm-renew-key').on('click', function () {
                const keyId = $(this).data('key-id');
                const submitBtn = $(this);
                const spinner = submitBtn.find('.spinner');

                if (!keyId) {
                    toastr.error('Ошибка: не указан ID ключа');
                    return;
                }

                submitBtn.prop('disabled', true);
                spinner.removeClass('d-none');

                $.ajax({
                    url: '{{ route('admin.module.key-activate.renew') }}',
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: {
                        key_id: keyId
                    },
                    success: function (response) {
                        window.dispatchEvent(new CustomEvent('close-modal', { detail: { id: 'renewKeyModal' } }));
                        toastr.success('Ключ успешно перевыпущен');
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    },
                    error: function (xhr) {
                        console.error('Renew key error:', xhr);
                        
                        let errorMessage = 'Неизвестная ошибка';
                        
                        if (xhr.responseJSON) {
                            // Есть JSON ответ
                            errorMessage = xhr.responseJSON.message || errorMessage;
                            
                            // Добавляем debug info если есть
                            if (xhr.responseJSON.debug) {
                                console.error('Debug info:', xhr.responseJSON.debug);
                                errorMessage += ' (см. консоль для деталей)';
                            }
                        } else if (xhr.responseText) {
                            // Есть текстовый ответ (возможно HTML ошибка)
                            console.error('Response text:', xhr.responseText);
                            errorMessage = 'Ошибка сервера (HTTP ' + xhr.status + '). См. консоль для деталей.';
                        } else if (xhr.status === 0) {
                            errorMessage = 'Ошибка сети. Проверьте подключение к интернету.';
                        } else {
                            errorMessage = 'HTTP ошибка ' + xhr.status + ': ' + xhr.statusText;
                        }
                        
                        toastr.error('Ошибка при перевыпуске ключа: ' + errorMessage);
                    },
                    complete: function () {
                        submitBtn.prop('disabled', false);
                        spinner.addClass('d-none');
                    }
                });
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

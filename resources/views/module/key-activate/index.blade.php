@extends('layouts.admin')

@section('title', 'Ключи активации')
@section('page-title', 'Ключи активации')

@push('styles')
<style>
    /* Исправление для выпадающего меню действий */
    /* Используем position: fixed для dropdown, поэтому overflow не проблема */
    
    /* Dropdown menu должен быть выше всего */
    .dropdown-menu-actions {
        position: fixed !important;
        z-index: 9999 !important;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
    }
    
    /* Анимация появления */
    .dropdown-menu-actions[x-cloak] {
        display: none;
    }
</style>
@endpush

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
                            <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-xs sm:text-sm">
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
                                    <a href="{{ route('admin.module.server-users.index', ['key_activate_id' => $key->id]) }}"
                                       class="btn btn-sm btn-primary"
                                       title="Пользователи сервера по этому ключу (все слоты)">
                                        <i class="fas fa-user"></i>
                                        @if(isset($key->key_activate_users_count) && $key->key_activate_users_count > 1)
                                            <span class="ml-0.5">({{ $key->key_activate_users_count }})</span>
                                        @endif
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
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium" style="overflow: visible !important; position: relative;">
                                <div class="relative inline-block text-left" x-data="{ open: false, buttonRect: null }" x-init="$watch('open', value => {
                                    if (value) {
                                        buttonRect = $refs.button.getBoundingClientRect();
                                    }
                                })">
                                    <button @click="open = !open" 
                                            x-ref="button"
                                            class="text-gray-400 hover:text-gray-600 focus:outline-none"
                                            title="Действия">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    
                                    <div x-show="open" 
                                         @click.away="open = false"
                                         x-cloak
                                         x-transition
                                         class="dropdown-menu-actions fixed w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5"
                                         style="z-index: 9999 !important;"
                                         x-bind:style="{
                                             @if($isLastRows)
                                             top: (buttonRect ? (buttonRect.top - 200) + 'px' : 'auto'),
                                             @else
                                             top: (buttonRect ? (buttonRect.bottom + 8) + 'px' : 'auto'),
                                             @endif
                                             right: (buttonRect ? (window.innerWidth - buttonRect.right) + 'px' : '0')
                                         }">
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
        <input type="hidden" id="renew-key-id" value="">
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
            <button type="button" class="btn btn-primary" id="confirm-renew-key">
                Да, перевыпустить
            </button>
            <button type="button" class="btn btn-secondary"
                    onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: { id: 'renewKeyModal' } }))">
                Отмена
            </button>
        </x-slot>
    </x-admin.modal>

    <!-- Modal: Transfer Key -->
    <x-admin.modal id="transferKeyModal" title="Перенос ключа на другой сервер" size="md">
        <form id="transfer-key-form">
            <input type="hidden" id="transfer-key-id" name="key_id">
            <input type="hidden" id="transfer-source-panel-id" name="source_panel_id" value="">
            <div id="transfer-slot-block" class="mb-4 d-none">
                <label for="source-slot-select" class="block text-sm font-medium text-gray-700 mb-1">
                    Какой слот переносить
                </label>
                <select class="form-control" id="source-slot-select">
                    <option value="">Выберите слот (провайдер/сервер)</option>
                </select>
                <p class="mt-1 text-sm text-gray-500">У ключа несколько слотов на разных панелях — выберите, какой перенести.</p>
            </div>
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
            <button type="submit" form="transfer-key-form" id="transfer-key-submit-btn" class="btn btn-primary">
                <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                <span class="btn-text">Перенести</span>
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

                $('#renew-key-id').val(keyId || '');

                window.dispatchEvent(new CustomEvent('open-modal', { detail: { id: 'renewKeyModal' } }));
            });

            // Обработчик подтверждения перевыпуска
            $('#confirm-renew-key').on('click', function () {
                const keyId = $('#renew-key-id').val();
                const btn = $(this);
                const originalText = 'Да, перевыпустить';

                if (!keyId) {
                    toastr.error('Ошибка: не указан ID ключа');
                    return;
                }

                btn.prop('disabled', true).text('Выполняется…');

                $.ajax({
                    url: '{{ route('admin.module.key-activate.renew') }}',
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                        'Accept': 'application/json'
                    },
                    data: { key_id: keyId },
                    success: function (data) {
                        window.dispatchEvent(new CustomEvent('close-modal', { detail: { id: 'renewKeyModal' } }));
                        toastr.success(data.message || 'Перевыпуск запущен. Обновите страницу через минуту.');
                        setTimeout(function () { window.location.reload(); }, 2500);
                    },
                    error: function (xhr) {
                        var raw = xhr.responseJSON || (function () {
                            try { return xhr.responseText ? JSON.parse(xhr.responseText) : null; } catch (e) { return null; }
                        })();
                        var msg = (raw && raw.message) ? raw.message : '';
                        if (raw && raw.errors && raw.errors.key_id && raw.errors.key_id[0]) {
                            msg = raw.errors.key_id[0];
                        }
                        if (xhr.status === 419) {
                            msg = 'Сессия истекла. Обновите страницу и попробуйте снова.';
                        } else if (xhr.status === 422) {
                            msg = msg || 'Неверные данные (ключ не найден или форма изменилась).';
                        } else if (xhr.status === 500) {
                            msg = msg || 'Ошибка сервера. Проверьте раздел «Логи» в админке.';
                        } else if (xhr.status === 0) {
                            msg = 'Ошибка сети или таймаут. Перевыпуск может занять минуту — попробуйте ещё раз.';
                        } else if (xhr.status === 502 || xhr.status === 503 || xhr.status === 504) {
                            msg = 'Сервер не успел ответить (таймаут). Попробуйте снова через минуту.';
                        } else if (!msg) {
                            msg = 'Ошибка (HTTP ' + xhr.status + '). Проверьте раздел «Логи» в админке.';
                        }
                        // В консоль всегда выводим тело ответа (для отладки 500)
                        var body = typeof xhr.responseText === 'string' ? xhr.responseText : '';
                        console.error('Перевыпуск ключа: HTTP ' + xhr.status + '. Ответ сервера:', body ? (body.length > 500 ? body.substring(0, 500) + '...' : body) : '(пусто)', raw);
                        toastr.error(msg);
                    },
                    complete: function () {
                        btn.prop('disabled', false).text(originalText);
                    }
                });
            });

            // Обработчик клика по кнопке переноса — один запрос: слоты и панели (без панелей, где ключ уже есть)
            $(document).on('click', '.btn-transfer-key', function () {
                const keyId = $(this).data('key-id');
                $('#transfer-key-id').val(keyId);
                $('#transfer-source-panel-id').val('');
                $('#source-slot-select').empty().off('change');

                $.ajax({
                    url: '{{ route('admin.module.server-user-transfer.transfer-data') }}',
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: { key_id: keyId },
                    success: function (response) {
                        const slots = response.slots || [];
                        const panels = response.panels || [];
                        if (slots.length === 0) {
                            toastr.error('У ключа нет слотов для переноса');
                            return;
                        }
                        var sourcePanelId = slots[0].panel_id;
                        if (slots.length > 1) {
                            $('#transfer-slot-block').removeClass('d-none');
                            var slotSelect = $('#source-slot-select');
                            slotSelect.append('<option value="">Выберите слот</option>');
                            slots.forEach(function (s) {
                                var name = (s.server_name || 'Сервер') + ' (' + s.panel_id + ')';
                                var label = s.provider ? name + ', ' + s.provider : name;
                                slotSelect.append('<option value="' + s.panel_id + '">' + label + '</option>');
                            });
                            slotSelect.val(sourcePanelId);
                            slotSelect.off('change').on('change', function () {
                                var v = $(this).val();
                                if (v) $('#transfer-source-panel-id').val(v);
                            });
                            $('#transfer-source-panel-id').val(sourcePanelId);
                        } else {
                            $('#transfer-slot-block').addClass('d-none');
                            $('#transfer-source-panel-id').val(sourcePanelId);
                        }
                        var select = $('#target-panel-select');
                        select.empty();
                        select.append('<option value="">Выберите сервер</option>');
                        if (panels.length > 0) {
                            panels.forEach(function (panel) {
                                var serverName = panel.server_name || 'Неизвестный сервер';
                                var short = serverName + ' (' + panel.id + ')';
                                var displayName = panel.provider ? short + ', ' + panel.provider : short;
                                select.append('<option value="' + panel.id + '">' + displayName + '</option>');
                            });
                        }
                        window.dispatchEvent(new CustomEvent('open-modal', { detail: { id: 'transferKeyModal' } }));
                        if (panels.length === 0) {
                            toastr.warning('Нет доступных серверов для переноса (ключ уже есть на всех панелях)');
                        }
                    },
                    error: function (xhr) {
                        toastr.error('Ошибка загрузки: ' + (xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Неизвестная ошибка'));
                    }
                });
            });

            // Обработчик отправки формы переноса
            $('#transfer-key-form').on('submit', function (e) {
                e.preventDefault();

                var keyId = $('#transfer-key-id').val();
                var targetPanelId = $('#target-panel-select').val();
                var sourcePanelId = $('#transfer-source-panel-id').val();

                if (!targetPanelId) {
                    toastr.warning('Пожалуйста, выберите сервер для переноса');
                    return;
                }

                var submitBtn = $('#transfer-key-submit-btn');
                var spinner = submitBtn.find('.spinner-border');
                var btnText = submitBtn.find('.btn-text');

                submitBtn.prop('disabled', true);
                spinner.removeClass('d-none');
                btnText.addClass('d-none');

                var payload = {
                    key_id: keyId,
                    target_panel_id: targetPanelId
                };
                if (sourcePanelId) payload.source_panel_id = sourcePanelId;

                $.ajax({
                    url: '{{ route('admin.module.server-user-transfer.transfer') }}',
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: payload,
                    success: function (response) {
                        window.dispatchEvent(new CustomEvent('close-modal', { detail: { id: 'transferKeyModal' } }));
                        toastr.success('Ключ успешно перенесен на новый сервер');
                        setTimeout(function () {
                            window.location.reload();
                        }, 1000);
                    },
                    error: function (xhr) {
                        toastr.error('Ошибка при переносе ключа: ' + (xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Неизвестная ошибка'));
                    },
                    complete: function () {
                        submitBtn.prop('disabled', false);
                        spinner.addClass('d-none');
                        btnText.removeClass('d-none');
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

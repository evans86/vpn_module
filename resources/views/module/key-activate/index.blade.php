@extends('layouts.app', ['page' => __('Ключи активации'), 'pageSlug' => 'activate_keys'])

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Ключи активации</h4>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="{{ url('/admin/module/key-activate') }}" class="mb-4">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="id">ID ключа</label>
                                        <input type="text" class="form-control" id="id" name="id"
                                               value="{{ request('id') }}" placeholder="Введите ID">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="pack_id">ID пакета</label>
                                        <input type="text" class="form-control" id="pack_id" name="pack_id"
                                               value="{{ request('pack_id') }}" placeholder="Введите ID пакета">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="status">Статус</label>
                                        <select class="form-control select2" id="status" name="status">
                                            <option value="">Все статусы</option>
                                            @foreach($statuses as $value => $label)
                                                <option
                                                    value="{{ $value }}" {{ request('status') == $value ? 'selected' : '' }}>
                                                    {{ $label }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="user_tg_id">Telegram ID покупателя</label>
                                        <input type="number" class="form-control" id="user_tg_id" name="user_tg_id"
                                               value="{{ request('user_tg_id') }}" placeholder="Введите Telegram ID">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="telegram_id">Telegram ID продавца</label>
                                        <input type="text" class="form-control" id="telegram_id" name="telegram_id"
                                               value="{{ request('telegram_id') }}" placeholder="Введите Telegram ID">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label>&nbsp;</label>
                                        <div class="btn-group btn-block">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-search"></i> Поиск
                                            </button>
                                            <a href="{{ route('admin.module.key-activate.index') }}"
                                               class="btn btn-secondary">
                                                <i class="fas fa-times"></i> Сбросить
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>

                        <div class="table-responsive">
                            <table class="table table-responsive-md">
                                <thead>
                                <tr>
                                    <th><strong>ID</strong></th>
                                    <th><strong>Трафик</strong></th>
                                    <th><strong>Пакет продавца</strong></th>
                                    <th><strong>Пакет модуля</strong></th>
                                    <th><strong>Продавец</strong></th>
                                    <th><strong>Дата окончания</strong></th>
                                    <th><strong>Telegram ID</strong></th>
                                    {{--                                    <th><strong>Активировать до</strong></th>--}}
                                    <th><strong>Пользователь сервера</strong></th>
                                    <th><strong>Статус</strong></th>
                                    <th></th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($activate_keys as $key)
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <span>{{ substr($key->id, 0, 8) }}...</span>
                                                <button class="btn btn-sm btn-link ml-2"
                                                        data-clipboard-text="{{ $key->id }}"
                                                        title="Копировать ID">
                                                    <i class="fas fa-copy"></i>
                                                </button>
                                            </div>
                                        </td>
                                        <td>{{ number_format($key->traffic_limit / (1024*1024*1024), 1) }} GB</td>
                                        <td>
                                            @if($key->pack_salesman_id)
                                                <a href="{{ route('admin.module.pack-salesman.index', ['id' => $key->pack_salesman_id]) }}"
                                                   title="Перейти к пакету">
                                                    {{ $key->pack_salesman_id }}
                                                </a>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>
                                            <span
                                                class="badge badge-{{ $key->packSalesman->pack->module_key ? 'warning' : 'info' }}">
                                                {{ $key->packSalesman->pack->module_key ? 'Модуль' : 'Бот' }}
                                            </span>

                                        </td>
                                        <td>
                                            @if($key->packSalesman && $key->packSalesman->salesman)
                                                <a href="{{ url('/admin/module/salesman?telegram_id=' . $key->packSalesman->salesman->telegram_id) }}"
                                                   class="text-primary">
                                                    {{ $key->packSalesman->salesman->telegram_id }}
                                                </a>
                                            @else
                                                Не указан
                                            @endif
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                {{ $key->finish_at ? date('d.m.Y H:i', $key->finish_at) : '' }}
                                                @if($key->finish_at)
                                                    {{--                                                {{ date('d.m.Y H:i', $key->finish_at) }}--}}
                                                    <button class="btn btn-sm btn-link edit-date"
                                                            data-id="{{ $key->id }}"
                                                            data-type="finish_at"
                                                            data-value="{{ date('d.m.Y H:i', $key->finish_at) }}"
                                                            title="Изменить дату">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                @endif
                                            </div>
                                        </td>
                                        <td>
                                            @if($key->user_tg_id)
                                                <a href="https://t.me/{{ $key->user_tg_id }}" target="_blank">
                                                    {{ $key->user_tg_id }}
                                                </a>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        {{--                                        <td>--}}
                                        {{--                                            <div class="d-flex align-items-center">--}}
                                        {{--                                                {{ $key->deleted_at ? date('d.m.Y H:i', $key->deleted_at) : '' }}--}}
                                        {{--                                                @if($key->deleted_at)--}}
                                        {{--                                                    <button class="btn btn-sm btn-link edit-date"--}}
                                        {{--                                                            data-id="{{ $key->id }}"--}}
                                        {{--                                                            data-type="deleted_at"--}}
                                        {{--                                                            data-value="{{ date('d.m.Y H:i', $key->deleted_at) }}"--}}
                                        {{--                                                            title="Изменить дату">--}}
                                        {{--                                                        <i class="fas fa-edit"></i>--}}
                                        {{--                                                    </button>--}}
                                        {{--                                                @endif--}}
                                        {{--                                            </div>--}}
                                        {{--                                        </td>--}}
                                        <td>
                                            @if($key->keyActivateUser && $key->keyActivateUser->serverUser)
                                                <a href="{{ route('admin.module.server-users.index', ['id' => $key->keyActivateUser->server_user_id]) }}"
                                                   class="btn btn-sm btn-primary">
                                                    <i class="fas fa-user"></i>
                                                </a>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge {{ $key->getStatusBadgeClass() }}">
                                                {{ $key->getStatusText() }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <button class="btn btn-primary btn-sm dropdown-toggle" type="button"
                                                        data-toggle="dropdown">
                                                    <i class="fas fa-cog"></i>
                                                </button>
                                                <div class="dropdown-menu">
                                                    @if($key->user_tg_id)
                                                        <button type="button"
                                                                class="dropdown-item btn-transfer-key"
                                                                data-key-id="{{ $key->id }}"
                                                                data-key-traffic="{{ $key->traffic_limit }}"
                                                                data-key-finish="{{ $key->finish_at }}">
                                                            <i class="fas fa-exchange-alt"></i> Перенести на другой
                                                            сервер
                                                        </button>
                                                    @endif
                                                    <button type="button"
                                                            class="dropdown-item delete-key"
                                                            data-id="{{ $key->id }}">
                                                        <i class="fas fa-trash"></i> Удалить
                                                    </button>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex">
                            {{ $activate_keys->appends(request()->query())->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Модальное окно для переноса ключа -->
    <div class="modal" id="transferKeyModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Перенос ключа на другой сервер</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="transfer-key-form">
                    <div class="modal-body">
                        <input type="hidden" id="transfer-key-id" name="key_id">
                        <div class="form-group">
                            <label for="target-panel-select">Выберите сервер для переноса</label>
                            <select class="form-control" id="target-panel-select" name="target_panel_id" required>
                                <option value="">Выберите сервер</option>
                            </select>
                            <small class="form-text text-muted">
                                Выберите сервер, на который нужно перенести ключ.
                                Все настройки и ограничения будут сохранены.
                            </small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Отмена</button>
                        <button type="submit" class="btn btn-primary">
                            <span class="spinner-border spinner-border-sm d-none" role="status"></span>
                            Перенести
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('css')
        <style>
            .btn-link {
                padding: 0 5px;
            }

            .btn-link:hover {
                text-decoration: none;
            }

            .edit-date {
                margin-left: 5px;
                color: #3498db;
            }

            .edit-date:hover {
                color: #2980b9;
            }

            .dropdown-item.text-danger:hover {
                background-color: #fee2e2;
            }

            .flatpickr-input {
                background-color: #fff !important;
            }
        </style>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
        <link rel="stylesheet" type="text/css" href="https://npmcdn.com/flatpickr/dist/themes/material_blue.css">
    @endpush

    @push('scripts')
        <script src="https://cdnjs.cloudflare.com/ajax/libs/clipboard.js/2.0.8/clipboard.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
        <script src="https://npmcdn.com/flatpickr/dist/l10n/ru.js"></script>
        <script>
            $(document).ready(function () {
                // Инициализируем bootstrap-select для всех select на странице
                $('.form-control').selectpicker({
                    style: 'btn-light',
                    size: 7,
                    liveSearch: true,
                    width: '100%'
                });

                // Инициализация Clipboard.js
                var clipboard = new ClipboardJS('[data-clipboard-text]');
                clipboard.on('success', function (e) {
                    e.clearSelection();
                });

                // Инициализация Flatpickr для всех полей с датами
                flatpickr.localize(flatpickr.l10ns.ru);

                // Обработчик клика по кнопке редактирования даты
                $(document).on('click', '.edit-date', function (e) {
                    e.preventDefault();
                    const button = $(this);
                    const id = button.data('id');
                    const type = button.data('type');
                    const currentValue = button.data('value');

                    // Создаем временное поле ввода
                    const input = $('<input type="text" class="form-control form-control-sm d-inline-block" style="width: 150px;">');
                    input.val(currentValue);

                    // Заменяем текст даты на поле ввода
                    const container = button.parent();
                    const originalText = container.contents().filter(function () {
                        return this.nodeType === 3;
                    }).first();
                    originalText.replaceWith(input);

                    // Инициализируем Flatpickr для поля ввода
                    const fp = flatpickr(input[0], {
                        enableTime: true,
                        dateFormat: "d.m.Y H:i",
                        defaultDate: currentValue,
                        onClose: function (selectedDates, dateStr) {
                            if (selectedDates.length > 0) {
                                // Отправляем запрос на обновление даты
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
                                            // Обновляем отображаемую дату
                                            input.replaceWith(dateStr);
                                            // Обновляем значение в кнопке
                                            button.data('value', dateStr);
                                        } else {
                                            alert('Ошибка при обновлении даты');
                                            input.replaceWith(currentValue);
                                        }
                                    },
                                    error: function () {
                                        alert('Ошибка при обновлении даты');
                                        input.replaceWith(currentValue);
                                    }
                                });
                            } else {
                                input.replaceWith(currentValue);
                            }
                        }
                    });

                    // Открываем календарь
                    fp.open();
                });

                // Обработчик клика по кнопке переноса
                $(document).on('click', '.btn-transfer-key', function () {
                    const keyId = $(this).data('key-id');
                    console.log('Key ID:', keyId);

                    // Сохраняем ID ключа в скрытое поле
                    $('#transfer-key-id').val(keyId);

                    // Загружаем список доступных панелей
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
                            console.log('Response:', response);
                            const panels = response.panels || [];

                            // Очищаем и заполняем select опциями
                            const select = $('#target-panel-select');
                            console.log('Select element:', select.length ? 'Found' : 'Not found');
                            console.log('Panels to add:', panels);

                            select.empty();
                            select.append('<option value="">Выберите сервер</option>');

                            if (panels.length > 0) {
                                panels.forEach(function (panel) {
                                    console.log('Adding panel:', panel);
                                    const serverName = panel.server_name || 'Неизвестный сервер';
                                    const address = panel.address || '';
                                    const displayName = address ? `${serverName} (${address})` : serverName;
                                    const optionHtml = `<option value="${panel.id}">${displayName}</option>`;
                                    console.log('Option HTML:', optionHtml);
                                    select.append(optionHtml);
                                });

                                // Проверяем количество опций после добавления
                                console.log('Total options after adding:', select.find('option').length);

                                // Инициализируем bootstrap-select
                                select.selectpicker('destroy');
                                select.selectpicker({
                                    style: 'btn-light',
                                    size: 7,
                                    liveSearch: true,
                                    width: '100%'
                                });

                                // Показываем модальное окно
                                $('#transferKeyModal').modal('show');

                                // Проверяем HTML после показа модального окна
                                setTimeout(() => {
                                    console.log('Modal HTML after show:', $('#transferKeyModal').html());
                                    console.log('Select HTML after show:', $('#target-panel-select').html());
                                }, 500);
                            } else {
                                alert('Нет доступных серверов для переноса');
                            }
                        },
                        error: function (xhr) {
                            console.error('Error:', xhr);
                            alert('Ошибка при загрузке списка серверов: ' + (xhr.responseJSON?.message || 'Неизвестная ошибка'));
                        }
                    });
                });

                // Обработчик отправки формы переноса
                $('#transfer-key-form').on('submit', function (e) {
                    e.preventDefault();

                    const keyId = $('#transfer-key-id').val();
                    const targetPanelId = $('#target-panel-select').val();

                    if (!targetPanelId) {
                        alert('Пожалуйста, выберите сервер для переноса');
                        return;
                    }

                    const spinner = $(this).find('.spinner-border');
                    const submitBtn = $(this).find('button[type="submit"]');

                    // Блокируем кнопку и показываем спиннер
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
                            $('#transferKeyModal').modal('hide');
                            alert('Ключ успешно перенесен на новый сервер');
                            location.reload();
                        },
                        error: function (xhr) {
                            console.error('Transfer error:', xhr);
                            alert('Ошибка при переносе ключа: ' + (xhr.responseJSON?.message || 'Неизвестная ошибка'));
                        },
                        complete: function () {
                            // Разблокируем кнопку и скрываем спиннер
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
                                location.reload();
                            },
                            error: function (xhr) {
                                alert('Ошибка при удалении ключа');
                            }
                        });
                    }
                });
            });
        </script>
    @endpush
@endsection

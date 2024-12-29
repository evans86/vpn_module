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
                                    <th><strong>Продавец</strong></th>
                                    <th><strong>Дата окончания</strong></th>
                                    <th><strong>Telegram ID</strong></th>
                                    <th><strong>Активировать до</strong></th>
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
                                                {{ date('d.m.Y H:i', $key->finish_at) }}
                                                <button class="btn btn-sm btn-link edit-date"
                                                        data-id="{{ $key->id }}"
                                                        data-type="finish_at"
                                                        data-value="{{ date('d.m.Y H:i', $key->finish_at) }}"
                                                        title="Изменить дату">
                                                    <i class="fas fa-edit"></i>
                                                </button>
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
                                        <td>
                                            <div class="d-flex align-items-center">
                                                {{ date('d.m.Y H:i', $key->deleted_at) }}
                                                <button class="btn btn-sm btn-link edit-date"
                                                        data-id="{{ $key->id }}"
                                                        data-type="deleted_at"
                                                        data-value="{{ date('d.m.Y H:i', $key->deleted_at) }}"
                                                        title="Изменить дату">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            </div>
                                        </td>
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
                                                <button class="btn btn-primary btn-sm dropdown-toggle" type="button" data-toggle="dropdown">
                                                    <i class="fas fa-cog"></i>
                                                </button>
                                                <div class="dropdown-menu">
                                                    @if($key->user_tg_id)
                                                        <button type="button"
                                                                class="dropdown-item btn-transfer-key"
                                                                data-key-id="{{ $key->id }}"
                                                                data-key-traffic="{{ $key->traffic_limit }}"
                                                                data-key-finish="{{ $key->finish_at }}">
                                                            <i class="fas fa-exchange-alt"></i> Перенести на другой сервер
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
    <div class="modal fade" id="transferKeyModal" tabindex="-1" role="dialog">
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

    @push('js')
        <script src="https://cdnjs.cloudflare.com/ajax/libs/clipboard.js/2.0.8/clipboard.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
        <script src="https://npmcdn.com/flatpickr/dist/l10n/ru.js"></script>
        <script>
            $(document).ready(function() {
                // Инициализация Clipboard.js
                var clipboard = new ClipboardJS('[data-clipboard-text]');
                clipboard.on('success', function(e) {
                    e.clearSelection();
                    alert('ID скопирован в буфер обмена');
                });

                // Обработчик клика по кнопке переноса
                $(document).on('click', '.btn-transfer-key', function() {
                    const keyId = $(this).data('key-id');
                    console.log('Key ID:', keyId); // Отладочный вывод

                    // Сохраняем ID ключа в скрытое поле
                    $('#transfer-key-id').val(keyId);

                    // Загружаем список доступных серверов
                    $.ajax({
                        url: '/admin/module/server-user-transfer/panels',
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        success: function(response) {
                            console.log('Panels response:', response); // Отладочный вывод

                            // Формируем HTML для модального окна
                            let modalHtml = `
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Перенос ключа на другой сервер</h5>
                                            <button type="button" class="close" data-dismiss="modal">
                                                <span>&times;</span>
                                            </button>
                                        </div>
                                        <form id="transfer-key-form">
                                            <div class="modal-body">
                                                <input type="hidden" id="transfer-key-id">
                                                <div class="form-group">
                                                    <label for="target-panel-select">Выберите сервер для переноса</label>
                                                    <select class="form-control" id="target-panel-select" required>`;

                            const panels = response.panels || [];
                            if (Array.isArray(panels) && panels.length > 0) {
                                modalHtml += '<option value="">Выберите сервер</option>';
                                panels.forEach(function(panel) {
                                    console.log('Panel:', panel);
                                    const serverName = panel.server_name || 'Неизвестный сервер';
                                    const address = panel.address || 'Адрес не указан';
                                    modalHtml += `<option value="${panel.id}">${serverName} (${address})</option>`;
                                });
                            } else {
                                modalHtml += '<option value="">Нет доступных серверов</option>';
                            }

                            modalHtml += `
                                                    </select>
                                                    <small class="form-text text-muted">
                                                        Выберите сервер, на который нужно перенести ключ.
                                                        Все настройки и ограничения будут сохранены.
                                                    </small>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Отмена</button>
                                                <button type="submit" class="btn btn-primary" id="transfer-submit-btn">
                                                    <span class="spinner-border spinner-border-sm d-none" role="status"></span>
                                                    Перенести
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>`;

                            // Обновляем содержимое модального окна
                            $('#transferKeyModal').html(modalHtml);

                            // Устанавливаем ID ключа
                            $('#transfer-key-id').val(keyId);

                            // Показываем модальное окно
                            $('#transferKeyModal').modal('show');
                        },
                        error: function(xhr) {
                            console.error('Ajax error:', xhr); // Отладочный вывод
                            const message = xhr.responseJSON?.message || 'Произошла ошибка при загрузке серверов';
                            alert(message);
                        }
                    });
                });

                // Обработчик отправки формы переноса
                $('#transferKeyModal').on('submit', '#transfer-key-form', function(e) {
                    e.preventDefault();
                    
                    const keyId = $('#transfer-key-id').val();
                    const targetPanelId = $('#target-panel-select').val();
                    
                    console.log('Form submitted:', {
                        keyId: keyId,
                        targetPanelId: targetPanelId
                    });
                    
                    if (!targetPanelId) {
                        alert('Выберите сервер для переноса');
                        return;
                    }

                    // Показываем спиннер
                    const submitBtn = $('#transfer-submit-btn');
                    submitBtn.prop('disabled', true);
                    submitBtn.find('.spinner-border').removeClass('d-none');

                    // Отправляем запрос на перенос
                    $.ajax({
                        url: '/admin/module/server-user-transfer/transfer',
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        data: {
                            key_id: keyId,
                            target_panel_id: targetPanelId
                        },
                        success: function(response) {
                            console.log('Transfer response:', response);
                            // Скрываем модальное окно
                            $('#transferKeyModal').modal('hide');
                            
                            if (response.success) {
                                // Показываем сообщение об успехе
                                alert('Ключ успешно перенесен на новый сервер');
                                // Перезагружаем страницу для обновления данных
                                window.location.reload();
                            } else {
                                // Если сервер вернул ошибку в response
                                const message = response.message || 'Произошла ошибка при переносе ключа';
                                alert(message);
                            }
                        },
                        error: function(xhr) {
                            console.error('Transfer error:', xhr);
                            // Скрываем модальное окно
                            $('#transferKeyModal').modal('hide');
                            
                            let message = 'Произошла ошибка при переносе ключа';
                            
                            // Пытаемся получить сообщение об ошибке из ответа сервера
                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                message = xhr.responseJSON.message;
                            } else if (xhr.status === 404) {
                                message = 'Ключ или сервер не найден';
                            } else if (xhr.status === 403) {
                                message = 'Нет прав для выполнения операции';
                            }
                            
                            alert(message);
                        },
                        complete: function() {
                            // Скрываем спиннер
                            submitBtn.prop('disabled', false);
                            submitBtn.find('.spinner-border').addClass('d-none');
                        }
                    });
                });

                // Обработчик удаления ключа
                $(document).on('click', '.delete-key', function(e) {
                    e.preventDefault();
                    const id = $(this).data('id');

                    if (confirm('Вы уверены, что хотите удалить этот ключ?')) {
                        $.ajax({
                            url: '/admin/module/key-activate/' + id,
                            method: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                            },
                            success: function() {
                                location.reload();
                            },
                            error: function(xhr) {
                                alert('Ошибка при удалении ключа');
                            }
                        });
                    }
                });
            });
        </script>
    @endpush
@endsection

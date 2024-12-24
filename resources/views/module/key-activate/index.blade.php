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
                                        <input type="text" class="form-control" id="id" name="id" value="{{ request('id') }}" placeholder="Введите ID">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="pack_id">ID пакета</label>
                                        <input type="text" class="form-control" id="pack_id" name="pack_id" value="{{ request('pack_id') }}" placeholder="Введите ID пакета">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="status">Статус</label>
                                        <select class="form-control select2" id="status" name="status">
                                            <option value="">Все статусы</option>
                                            @foreach($statuses as $value => $label)
                                                <option value="{{ $value }}" {{ request('status') == $value ? 'selected' : '' }}>
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
                                <div class="col-md-3 d-flex align-items-end">
                                    <div class="form-group">
                                        <button type="submit" class="btn btn-primary">Фильтровать</button>
                                        @if(request('pack_id') || request('status') || request('user_tg_id') || request('id') || request('telegram_id'))
                                            <a href="{{ url('/admin/module/key-activate') }}" class="btn btn-secondary">Сбросить</a>
                                        @endif
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
                                    <th><strong>Пакет</strong></th>
                                    <th><strong>Продавец</strong></th>
                                    <th><strong>Дата окончания</strong></th>
                                    <th><strong>Telegram ID</strong></th>
                                    <th><strong>Активировать до</strong></th>
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
                                            @if($key->packSalesman)
                                                <a href="{{ url('/admin/module/pack?id=' . $key->packSalesman->pack_id) }}" class="text-primary">
                                                    {{ $key->packSalesman->pack_id }}
                                                </a>
                                            @else
                                                Не указан
                                            @endif
                                        </td>
                                        <td>
                                            @if($key->packSalesman && $key->packSalesman->salesman)
                                                <a href="{{ url('/admin/module/salesman?telegram_id=' . $key->packSalesman->salesman->telegram_id) }}" class="text-primary">
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
                                            <span class="badge {{ $key->getStatusBadgeClass() }}">
                                                {{ $key->getStatusText() }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="dropdown">
                                                <button class="btn btn-link" type="button" data-toggle="dropdown">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                </button>
                                                <div class="dropdown-menu">
                                                    <a class="dropdown-item text-danger delete-key" href="#" data-id="{{ $key->id }}">
                                                        <i class="fas fa-trash mr-2"></i>Удалить
                                                    </a>
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
            $(document).ready(function () {
                // Инициализация Clipboard.js
                var clipboard = new ClipboardJS('[data-clipboard-text]');

                clipboard.on('success', function (e) {
                    toastr.success('Скопировано в буфер обмена');
                    e.clearSelection();
                });

                clipboard.on('error', function (e) {
                    toastr.error('Ошибка копирования');
                });

                // Обработка редактирования дат
                $('.edit-date').on('click', function() {
                    const id = $(this).data('id');
                    const type = $(this).data('type');
                    const currentValue = $(this).data('value');
                    
                    // Создаем модальное окно
                    const modal = $(`
                        <div class="modal fade" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Изменить дату</h5>
                                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                                    </div>
                                    <div class="modal-body">
                                        <input type="text" class="form-control datepicker" value="${currentValue}">
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Отмена</button>
                                        <button type="button" class="btn btn-primary save-date">Сохранить</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `);

                    // Инициализируем календарь
                    const picker = modal.find('.datepicker').flatpickr({
                        enableTime: true,
                        dateFormat: "Y-m-d H:i",
                        time_24hr: true,
                        locale: "ru",
                        defaultDate: currentValue,
                        minuteIncrement: 1
                    });

                    // Обработка сохранения
                    modal.find('.save-date').on('click', function() {
                        const newValue = picker.selectedDates[0];
                        if (!newValue) {
                            toastr.error('Выберите дату');
                            return;
                        }

                        const data = {};
                        data[type] = flatpickr.formatDate(newValue, "Y-m-d H:i");

                        $.ajax({
                            url: `/admin/module/key-activate/${id}/update-dates`,
                            type: 'POST',
                            data: data,
                            headers: {
                                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                            },
                            success: function(response) {
                                if (response.success) {
                                    toastr.success(response.message);
                                    location.reload();
                                } else {
                                    toastr.error(response.message);
                                }
                                modal.modal('hide');
                            },
                            error: function(xhr) {
                                const response = xhr.responseJSON;
                                toastr.error(response.message || 'Ошибка при обновлении даты');
                                modal.modal('hide');
                            }
                        });
                    });

                    // Показываем модальное окно
                    modal.modal('show');
                });

                // Обработка удаления
                $('.delete-key').on('click', function(e) {
                    e.preventDefault();
                    const id = $(this).data('id');
                    
                    if (confirm('Вы уверены, что хотите удалить этот ключ?')) {
                        $.ajax({
                            url: `/admin/module/key-activate/${id}`,
                            type: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                            },
                            success: function(response) {
                                if (response.message) {
                                    toastr.success(response.message);
                                    setTimeout(() => location.reload(), 1000);
                                }
                            },
                            error: function() {
                                toastr.error('Ошибка при удалении ключа');
                            }
                        });
                    }
                });
            });
        </script>
    @endpush
@endsection

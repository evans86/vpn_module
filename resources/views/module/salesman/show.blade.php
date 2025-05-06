@extends('layouts.app', ['page' => __('Продавец'), 'pageSlug' => 'salesmans'])

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header">
                        <div class="row">
                            <div class="col-md-6">
                                <h4 class="card-title">Информация о продавце #{{ $salesman->id }}</h4>
                            </div>
                            <div class="col-md-6 text-right">
                                <a href="{{ route('admin.module.salesman.index') }}" class="btn btn-sm btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Назад к списку
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Основная информация -->
                        <div class="mb-5">
                            <h5 class="mb-4">Основная информация</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>ID</label>
                                        <input type="text" class="form-control" value="{{ $salesman->id }}" readonly>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Telegram ID</label>
                                        <input type="text" class="form-control" value="{{ $salesman->telegram_id }}" readonly>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Username</label>
                                        <input type="text" class="form-control" value="{{ $salesman->username ?? 'Не указан' }}" readonly>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Статус</label>
                                        <div>
                                            <button class="btn btn-sm status-toggle {{ $salesman->status ? 'btn-success' : 'btn-danger' }}"
                                                    data-id="{{ $salesman->id }}">
                                                {{ $salesman->status ? 'Активен' : 'Неактивен' }}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Дата регистрации</label>
                                        <input type="text" class="form-control"
                                               value="{{ $salesman->created_at->format('d.m.Y H:i') }}" readonly>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Последнее обновление</label>
                                        <input type="text" class="form-control"
                                               value="{{ $salesman->updated_at->format('d.m.Y H:i') }}" readonly>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Токен и ссылка на бота -->
                        <div class="mb-5">
                            <h5 class="mb-4">Данные бота</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Токен бота</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control"
                                                   value="{{ $salesman->token }}"
                                                   id="salesmanToken" readonly>
                                            <div class="input-group-append">
                                                <button class="btn btn-outline-secondary"
                                                        data-clipboard-target="#salesmanToken">
                                                    <i class="fas fa-copy"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Ссылка на бота</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control"
                                                   value="{{ $salesman->bot_link }}"
                                                   id="botLink" readonly>
                                            <div class="input-group-append">
                                                <button class="btn btn-outline-secondary"
                                                        data-clipboard-target="#botLink">
                                                    <i class="fas fa-copy"></i>
                                                </button>
                                                <a href="{{ $salesman->bot_link }}"
                                                   target="_blank"
                                                   class="btn btn-outline-primary">
                                                    <i class="fas fa-external-link-alt"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Привязанная панель -->
                        <div class="mb-5">
                            <h5 class="mb-4">Панель управления</h5>
                            <div class="row">
                                <div class="col-md-12">
                                    @if($salesman->panel)
                                        <div class="form-group">
                                            <label>Привязанная панель</label>
                                            <div class="input-group">
                                                <input type="text" class="form-control"
                                                       value="{{ $salesman->panel->panel_adress }}"
                                                       id="panelAddress" readonly>
                                                <div class="input-group-append">
                                                    <a href="{{ route('admin.module.panel.index', ['panel_id' => $salesman->panel->id]) }}"
                                                       class="btn btn-outline-primary">
                                                        <i class="fas fa-external-link-alt"></i> Перейти
                                                    </a>
                                                    <button class="btn btn-outline-danger reset-panel-btn"
                                                            data-salesman-id="{{ $salesman->id }}">
                                                        <i class="fas fa-times"></i> Отвязать
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    @else
                                        <div class="alert alert-warning">
                                            Панель не привязана
                                        </div>
                                        <button class="btn btn-info assign-panel-btn"
                                                data-salesman-id="{{ $salesman->id }}">
                                            <i class="fas fa-link"></i> Привязать панель
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Статистика и активность -->
{{--                        <div class="mb-5">--}}
{{--                            <h5 class="mb-4">Статистика и активность</h5>--}}
{{--                            <div class="row">--}}
{{--                                <div class="col-md-4">--}}
{{--                                    <div class="card card-stats">--}}
{{--                                        <div class="card-body">--}}
{{--                                            <div class="row">--}}
{{--                                                <div class="col-5">--}}
{{--                                                    <div class="info-icon text-center icon-primary">--}}
{{--                                                        <i class="fas fa-users"></i>--}}
{{--                                                    </div>--}}
{{--                                                </div>--}}
{{--                                                <div class="col-7">--}}
{{--                                                    <div class="numbers">--}}
{{--                                                        <p class="card-category">Клиентов</p>--}}
{{--                                                        <h3 class="card-title">{{ $stats['total_clients'] ?? 0 }}</h3>--}}
{{--                                                    </div>--}}
{{--                                                </div>--}}
{{--                                            </div>--}}
{{--                                        </div>--}}
{{--                                        <div class="card-footer">--}}
{{--                                            <hr>--}}
{{--                                            <div class="stats">--}}
{{--                                                <i class="fas fa-sync-alt"></i> Обновлено сейчас--}}
{{--                                            </div>--}}
{{--                                        </div>--}}
{{--                                    </div>--}}
{{--                                </div>--}}
{{--                                <div class="col-md-4">--}}
{{--                                    <div class="card card-stats">--}}
{{--                                        <div class="card-body">--}}
{{--                                            <div class="row">--}}
{{--                                                <div class="col-5">--}}
{{--                                                    <div class="info-icon text-center icon-success">--}}
{{--                                                        <i class="fas fa-money-bill-wave"></i>--}}
{{--                                                    </div>--}}
{{--                                                </div>--}}
{{--                                                <div class="col-7">--}}
{{--                                                    <div class="numbers">--}}
{{--                                                        <p class="card-category">Общий доход</p>--}}
{{--                                                        <h3 class="card-title">{{ $stats['total_income'] ?? 0 }} ₽</h3>--}}
{{--                                                    </div>--}}
{{--                                                </div>--}}
{{--                                            </div>--}}
{{--                                        </div>--}}
{{--                                        <div class="card-footer">--}}
{{--                                            <hr>--}}
{{--                                            <div class="stats">--}}
{{--                                                <i class="fas fa-calendar"></i> За все время--}}
{{--                                            </div>--}}
{{--                                        </div>--}}
{{--                                    </div>--}}
{{--                                </div>--}}
{{--                                <div class="col-md-4">--}}
{{--                                    <div class="card card-stats">--}}
{{--                                        <div class="card-body">--}}
{{--                                            <div class="row">--}}
{{--                                                <div class="col-5">--}}
{{--                                                    <div class="info-icon text-center icon-info">--}}
{{--                                                        <i class="fas fa-chart-line"></i>--}}
{{--                                                    </div>--}}
{{--                                                </div>--}}
{{--                                                <div class="col-7">--}}
{{--                                                    <div class="numbers">--}}
{{--                                                        <p class="card-category">Активных</p>--}}
{{--                                                        <h3 class="card-title">{{ $stats['active_clients'] ?? 0 }}</h3>--}}
{{--                                                    </div>--}}
{{--                                                </div>--}}
{{--                                            </div>--}}
{{--                                        </div>--}}
{{--                                        <div class="card-footer">--}}
{{--                                            <hr>--}}
{{--                                            <div class="stats">--}}
{{--                                                <i class="fas fa-clock"></i> В текущем месяце--}}
{{--                                            </div>--}}
{{--                                        </div>--}}
{{--                                    </div>--}}
{{--                                </div>--}}
{{--                            </div>--}}
{{--                        </div>--}}

                        <!-- Пакеты продавца -->
                        <div class="mb-5">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h5 class="mb-0">Пакеты продавца</h5>
                                <button class="btn btn-primary assign-pack-btn"
                                        data-salesman-id="{{ $salesman->id }}">
                                    <i class="fas fa-plus"></i> Добавить пакет
                                </button>
                            </div>

                            @if($salesman->packs->isEmpty())
                                <div class="alert alert-info">
                                    У продавца нет назначенных пакетов
                                </div>
                            @else
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Название</th>
                                            <th>Цена</th>
                                            <th>Ключи</th>
                                            <th>Статус</th>
                                            <th>Дата добавления</th>
                                            <th>Действия</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @foreach($salesman->packs as $pack)
                                            @php
                                                $packSalesman = \App\Models\PackSalesman\PackSalesman::where('pack_id', $pack->id)->where('salesman_id', $salesman->id)->first();
                                            @endphp
                                            <tr>
                                                <td>{{ $pack->id }}</td>
                                                <td>{{ $pack->title }}</td>
                                                <td>{{ $pack->price }} ₽</td>
                                                <td>
                                                    <a href="{{ route('admin.module.key-activate.index', ['pack_salesman_id' => $packSalesman->id ?? 0]) }}"
                                                       class="text-primary"
                                                       title="Просмотреть ключи пакета">
                                                        {{ $pack->count }} ключей
                                                    </a>
                                                </td>
                                                <td>
                                                    @if($packSalesman)
                                                        <span class="badge {{ $packSalesman->isPaid() ? 'badge-success' : 'badge-warning' }}">
                                                                {{ $packSalesman->isPaid() ? 'Оплачен' : 'Ожидает оплаты' }}
                                                            </span>
                                                    @else
                                                        <span class="badge badge-secondary">Не назначен</span>
                                                    @endif
                                                </td>
                                                <td>{{ $pack->pivot->created_at->format('d.m.Y H:i') }}</td>
                                                <td>
                                                    @if($packSalesman && !$packSalesman->isPaid())
                                                        <button class="btn btn-sm btn-success mark-as-paid-btn"
                                                                data-pack-salesman-id="{{ $packSalesman->id }}">
                                                            Отметить оплаченным
                                                        </button>
                                                    @else
                                                        -
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>

                        <!-- Последние активности -->
{{--                        <div class="mb-5">--}}
{{--                            <h5 class="mb-4">Последние активности</h5>--}}
{{--                            @if($activities->isEmpty())--}}
{{--                                <div class="alert alert-info">--}}
{{--                                    Активности не найдены--}}
{{--                                </div>--}}
{{--                            @else--}}
{{--                                <div class="activities">--}}
{{--                                    @foreach($activities as $activity)--}}
{{--                                        <div class="activity-item mb-3">--}}
{{--                                            <div class="d-flex justify-content-between">--}}
{{--                                                <div>--}}
{{--                                                    <strong>{{ $activity->description }}</strong>--}}
{{--                                                    <div class="text-muted small">--}}
{{--                                                        {{ $activity->created_at->format('d.m.Y H:i') }}--}}
{{--                                                    </div>--}}
{{--                                                </div>--}}
{{--                                                <div class="badge badge-{{ $activity->type === 'success' ? 'success' : 'info' }}">--}}
{{--                                                    {{ $activity->type }}--}}
{{--                                                </div>--}}
{{--                                            </div>--}}
{{--                                            @if($activity->details)--}}
{{--                                                <div class="mt-2 p-2 bg-light rounded">--}}
{{--                                                    <pre class="mb-0">{{ json_encode($activity->details, JSON_PRETTY_PRINT) }}</pre>--}}
{{--                                                </div>--}}
{{--                                            @endif--}}
{{--                                        </div>--}}
{{--                                    @endforeach--}}
{{--                                </div>--}}
{{--                                <div class="d-flex justify-content-center mt-3">--}}
{{--                                    {{ $activities->links() }}--}}
{{--                                </div>--}}
{{--                            @endif--}}
{{--                        </div>--}}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Модальное окно для выбора пакета -->
    <div class="modal fade" id="packModal" tabindex="-1" role="dialog" aria-labelledby="packModalLabel"
         aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="packModalLabel">Добавить пакет</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="assignPackForm">
                        <input type="hidden" id="salesmanId" name="salesman_id">
                        <div class="form-group">
                            <label for="packId">Выберите пакет</label>
                            <select class="form-control" id="packId" name="pack_id" required>
                                @foreach($packs as $pack)
                                    <option value="{{ $pack->id }}">{{ $pack->title }}: {{ $pack->price }}₽</option>
                                @endforeach
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Отмена</button>
                    <button type="button" class="btn btn-primary" id="assignPackButton">Добавить</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Модальное окно для выбора панели -->
    <div class="modal fade" id="panelModal" tabindex="-1" role="dialog" aria-labelledby="panelModalLabel"
         aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="panelModalLabel">Привязать панель</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="assignPanelForm">
                        <input type="hidden" id="salesmanIdForPanel" name="salesman_id">
                        <div class="form-group">
                            <label for="panelId">Выберите панель</label>
                            <select class="form-control" id="panelId" name="panel_id" required>
                                @foreach($panels as $panel)
                                    <option value="{{ $panel->id }}">{{ $panel->panel_adress }}</option>
                                @endforeach
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Отмена</button>
                    <button type="button" class="btn btn-primary" id="assignPanelButton">Привязать</button>
                </div>
            </div>
        </div>
    </div>
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
            $('[data-clipboard-target]').on('click', function() {
                toastr.success('Скопировано в буфер обмена');
            });

            // Обработчик клика по кнопке статуса
            $('.status-toggle').on('click', function () {
                const id = $(this).data('id');
                $.ajax({
                    url: `/admin/module/salesman/${id}/toggle-status`,
                    type: 'POST',
                    success: function (response) {
                        if (response.success) {
                            const button = $(`.status-toggle[data-id="${id}"]`);
                            if (response.status) {
                                button.removeClass('btn-danger').addClass('btn-success');
                                button.text('Активен');
                            } else {
                                button.removeClass('btn-success').addClass('btn-danger');
                                button.text('Неактивен');
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
                $('#packModal').modal('show');
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
                            $('#packModal').modal('hide');
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
                $('#panelModal').modal('show');
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
                            location.reload();
                            $('#panelModal').modal('hide');
                        } else {
                            toastr.error(response.message || 'Произошла ошибка');
                        }
                    },
                    error: function (xhr) {
                        toastr.error(xhr.responseJSON?.message || 'Произошла ошибка при привязке панели');
                    }
                });
            });
        });
    </script>
@endpush

@push('css')
    <style>
        .info-icon {
            font-size: 1.5rem;
        }
        .icon-primary {
            color: #1d8cf8;
        }
        .icon-success {
            color: #00bf9a;
        }
        .icon-info {
            color: #00b5e2;
        }
        .activity-item {
            padding: 1rem;
            border-radius: 4px;
            background-color: #f8f9fa;
        }
        pre {
            white-space: pre-wrap;
            word-wrap: break-word;
            font-size: 0.8rem;
            color: #495057;
        }
    </style>
@endpush

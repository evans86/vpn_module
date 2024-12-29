@extends('layouts.app', ['page' => __('Продавцы'), 'pageSlug' => 'salesmans'])

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Список продавцов</h4>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="{{ url('/admin/module/salesman') }}" class="mb-4">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="id">ID</label>
                                        <input type="number" class="form-control" id="id" name="id"
                                               value="{{ request('id') }}" placeholder="Введите ID">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="telegram_id">Telegram ID</label>
                                        <input type="number" class="form-control" id="telegram_id" name="telegram_id"
                                               value="{{ request('telegram_id') }}" placeholder="Введите Telegram ID">
                                    </div>
                                </div>
                                <div class="col-md-3 d-flex align-items-end">
                                    <div class="form-group">
                                        <button type="submit" class="btn btn-primary">Фильтровать</button>
                                        @if(request('id') || request('telegram_id'))
                                            <a href="{{ url('/admin/module/salesman') }}" class="btn btn-secondary">Сбросить</a>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </form>

                        <div class="table-responsive">
                            <table class="table table-responsive-md">
                                <thead>
                                <tr>
                                    <th style="width:80px;"><strong>#</strong></th>
                                    <th><strong>Телеграм ID</strong></th>
                                    <th><strong>Имя пользователя</strong></th>
                                    <th><strong>Токен</strong></th>
                                    <th><strong>Ссылка на бота</strong></th>
                                    <th><strong>Статус</strong></th>
                                    <th><strong>Действия</strong></th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($salesmen as $salesman)
                                    <tr>
                                        <td><strong>{{ $salesman->id }}</strong></td>
                                        <td>{{ $salesman->telegram_id }}</td>
                                        <td>{{ $salesman->username }}</td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <span>{{ Str::limit($salesman->token, 20) }}</span>
                                                <button class="btn btn-sm btn-link ml-2"
                                                        data-clipboard-text="{{ $salesman->token }}"
                                                        title="Копировать токен">
                                                    <i class="fas fa-copy"></i>
                                                </button>
                                            </div>
                                        </td>
                                        <td><a href="{{ $salesman->bot_link }}"
                                               target="_blank">{{ $salesman->bot_link }}</a></td>
                                        <td>
                                            <button
                                                class="btn btn-sm status-toggle {{ $salesman->status ? 'btn-success' : 'btn-danger' }}"
                                                data-id="{{ $salesman->id }}">
                                                {{ $salesman->status ? 'Активен' : 'Неактивен' }}
                                            </button>
                                        </td>
                                        <td>
                                            <button
                                                class="btn btn-sm btn-primary assign-pack-btn"
                                                data-salesman-id="{{ $salesman->id }}">
                                                Предоставить пакет
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex">
                            {{ $salesmen->appends(request()->query())->links() }}
                        </div>
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
                    <h5 class="modal-title" id="packModalLabel">Предоставить пакет</h5>
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
                                    <option value="{{ $pack->id }}">{{ $pack->name }} - {{ $pack->price }}₽</option>
                                @endforeach
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Отмена</button>
                    <button type="button" class="btn btn-primary" id="assignPackButton">Предоставить</button>
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

            // Обработчик клика по кнопке "Предоставить пакет"
            $('.assign-pack-btn').on('click', function () {
                const salesmanId = $(this).data('salesman-id');
                $('#salesmanId').val(salesmanId);
                $('#packModal').modal('show');
            });

            // Обработчик клика по кнопке "Предоставить" в модальном окне
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
                            showNotification('success', 'Пакет успешно предоставлен');
                            $('#packModal').modal('hide');
                        } else {
                            showNotification('error', response.message || 'Произошла ошибка');
                        }
                    },
                    error: function (xhr) {
                        showNotification('error', xhr.responseJSON?.message || 'Произошла ошибка при назначении пакета');
                    }
                });
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
                            showNotification('success', response.message);
                        } else {
                            showNotification('error', response.message);
                        }
                    },
                    error: function (xhr) {
                        console.error('Error:', xhr);
                        showNotification('error', 'Произошла ошибка при изменении статуса');
                    }
                });
            });

            // Инициализация ClipboardJS
            var clipboard = new ClipboardJS('[data-clipboard-text]');

            clipboard.on('success', function (e) {
                toastr.success('Скопировано в буфер обмена');
                e.clearSelection();
            });

            clipboard.on('error', function (e) {
                toastr.error('Ошибка копирования');
            });

            function showNotification(type, message) {
                if (typeof toastr !== 'undefined') {
                    toastr[type](message);
                }
            }
        });
    </script>
@endpush

@push('css')
    <style>
        .btn-link {
            padding: 0 5px;
        }
        .btn-link:hover {
            text-decoration: none;
        }
    </style>
@endpush

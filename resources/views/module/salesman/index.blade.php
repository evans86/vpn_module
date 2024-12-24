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
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($salesmen as $salesman)
                                    <tr>
                                        <td><strong>{{ $salesman->id }}</strong></td>
                                        <td>{{ $salesman->telegram_id }}</td>
                                        <td>{{ $salesman->username }}</td>
                                        <td>{{ $salesman->token }}</td>
                                        <td><a href="{{ $salesman->bot_link }}"
                                               target="_blank">{{ $salesman->bot_link }}</a></td>
                                        <td>
                                            <button
                                                class="btn btn-sm status-toggle {{ $salesman->status ? 'btn-success' : 'btn-danger' }}"
                                                data-id="{{ $salesman->id }}"
                                                onclick="toggleStatus({{ $salesman->id }})">
                                                {{ $salesman->status ? 'Активен' : 'Неактивен' }}
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

    @push('js')
        <script>
            // Настройка CSRF-токена для всех AJAX-запросов
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });

            function toggleStatus(id) {
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
                            // Показываем уведомление об успехе
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
            }

            function showNotification(type, message) {
                if (typeof toastr !== 'undefined') {
                    toastr[type](message);
                } else {
                    alert(message);
                }
            }
        </script>
    @endpush
@endsection

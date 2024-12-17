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
                            {{ $salesmen->links() }}
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

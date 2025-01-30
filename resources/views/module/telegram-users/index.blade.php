@extends('layouts.app', ['page' => __('Пользователи'), 'pageSlug' => 'telegram-users'])

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Список пользователей</h4>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="{{ url('/admin/module/telegram-users') }}" class="mb-4">
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
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label>&nbsp;</label>
                                        <div class="btn-group btn-block">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-search"></i> Поиск
                                            </button>
                                            <a href="{{ route('admin.module.telegram-users.index') }}"
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
                                    <th style="width:80px;"><strong>#</strong></th>
                                    <th><strong>Telegram ID</strong></th>
                                    <th><strong>Имя пользователя</strong></th>
                                    <th><strong>Продавец</strong></th>
                                    <th><strong>Статус</strong></th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($users as $user)
                                    <tr>
                                        <td><strong>{{ $user->id }}</strong></td>
                                        <td>{{ $user->telegram_id }}</td>
                                        <td>{{ $user->username }}</td>
                                        <td>{{ $user->salesman->bot_link ?? 'Нет' }}</td>
                                        <td>
                                            <button
                                                class="btn btn-sm status-toggle {{ $user->status ? 'btn-success' : 'btn-danger' }}"
                                                data-id="{{ $user->id }}">
                                                {{ $user->status ? 'Активен' : 'Неактивен' }}
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex">
                            {{ $users->appends(request()->query())->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('js')
    <script>
        $(document).ready(function () {
            // Настройка CSRF-токена для всех AJAX-запросов
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });

            // Обработчик клика по кнопке статуса
            $('.status-toggle').on('click', function () {
                const id = $(this).data('id');
                $.ajax({
                    url: `/admin/module/telegram-users/${id}/toggle-status`,
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

            function showNotification(type, message) {
                if (typeof toastr !== 'undefined') {
                    toastr[type](message);
                }
            }
        });
    </script>
@endpush

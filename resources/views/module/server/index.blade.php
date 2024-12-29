@extends('layouts.app', ['page' => __('Серверы'), 'pageSlug' => 'servers'])

@php
    use App\Models\Server\Server;
@endphp

@section('styles')
    <style>
        .country-flag {
            width: 24px;
            height: 16px;
            margin-right: 0.5rem;
            display: inline-block;
            vertical-align: middle;
            object-fit: cover;
        }

        .d-flex {
            display: flex !important;
        }

        .align-items-center {
            align-items: center !important;
        }
    </style>
@endsection

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-12">
                <x-card title="Список серверов">
                    <x-slot name="tools">
                        <button type="button" class="btn btn-primary" data-toggle="modal"
                                data-target="#createServerModal">
                            <i class="fas fa-plus"></i> Добавить сервер
                        </button>
                    </x-slot>

                    <form method="GET" action="/admin/module/server" class="mb-4">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="name">Имя сервера</label>
                                    <input type="text" class="form-control" id="name" name="name"
                                           value="{{ request('name') }}" placeholder="Поиск по имени">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="ip">IP адрес</label>
                                    <input type="text" class="form-control" id="ip" name="ip"
                                           value="{{ request('ip') }}" placeholder="Поиск по IP">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="status">Статус</label>
                                    <select class="form-control" id="status" name="status">
                                        <option value="">Все статусы</option>
                                        <option value="{{ \App\Models\Server\Server::SERVER_CREATED }}"
                                            {{ request('status') == \App\Models\Server\Server::SERVER_CREATED ? 'selected' : '' }}>
                                            Создан
                                        </option>
                                        <option value="{{ \App\Models\Server\Server::SERVER_CONFIGURED }}"
                                            {{ request('status') == \App\Models\Server\Server::SERVER_CONFIGURED ? 'selected' : '' }}>
                                            Настроен
                                        </option>
                                        <option value="{{ \App\Models\Server\Server::SERVER_ERROR }}"
                                            {{ request('status') == \App\Models\Server\Server::SERVER_ERROR ? 'selected' : '' }}>
                                            Ошибка
                                        </option>
                                        <option value="{{ \App\Models\Server\Server::SERVER_DELETED }}"
                                            {{ request('status') == \App\Models\Server\Server::SERVER_DELETED ? 'selected' : '' }}>
                                            Удален
                                        </option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <div class="btn-group btn-block">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-search"></i> Поиск
                                        </button>
                                        <a href="{{ route('admin.module.server.index') }}" 
                                           class="btn btn-secondary">
                                            <i class="fas fa-times"></i> Сбросить
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>

                    <x-table :headers="['#', 'Название', 'IP', 'Логин', 'Пароль', 'Хост', 'Локация', 'Статус', '']">
                        @if($servers->isEmpty())
                            <tr>
                                <td colspan="8" class="text-center text-muted">
                                    <i class="fas fa-info-circle"></i> Серверы не найдены
                                </td>
                            </tr>
                        @else
                            @foreach($servers as $server)
                                <tr>
                                    <td><strong>{{ $server->id }}</strong></td>
                                    <td>{{ $server->name }}</td>
                                    <td>{{ $server->ip }}</td>
                                    <td>{{ $server->login }}</td>
                                    <td>{{ $server->password }}</td>
                                    <td>{{ $server->host }}</td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img
                                                src="https://flagcdn.com/w40/{{ strtolower($server->location->code) }}.png"
                                                class="country-flag"
                                                alt="{{ strtoupper($server->location->code) }}"
                                                title="{{ strtoupper($server->location->code) }}">
                                            <span>{{ strtoupper($server->location->code) }}</span>
                                        </div>
                                    </td>
                                    <td>
                                    <span class="badge badge-{{ $server->status_badge_class }}">
                                        {{ $server->status_label }}
                                    </span>
                                    </td>
                                    <td>
                                        @if($server->server_status !== \App\Models\Server\Server::SERVER_DELETED)
                                            <div class="dropdown">
                                                <button class="btn btn-link" type="button" data-toggle="dropdown"
                                                        aria-haspopup="true" aria-expanded="false">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                </button>
                                                <div class="dropdown-menu dropdown-menu-right">
                                                    @if($server->panel)
                                                        <a href="{{ route('admin.module.panel.index', ['panel_id' => $server->panel->id]) }}" 
                                                           class="dropdown-item">
                                                            <i class="fas fa-desktop"></i> Панель
                                                        </a>
                                                    @endif
                                                    <a href="{{ route('admin.module.server-users.index', ['server_id' => $server->id]) }}" 
                                                       class="dropdown-item">
                                                        <i class="fas fa-users"></i> Пользователи
                                                    </a>
                                                    <button class="dropdown-item" type="button"
                                                            onclick="deleteServer({{ $server->id }})">
                                                        <i class="fas fa-trash"></i> Удалить
                                                    </button>
                                                </div>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        @endif
                    </x-table>

                    <div class="d-flex justify-content-center mt-3">
                        {{ $servers->links() }}
                    </div>
                </x-card>
            </div>
        </div>
    </div>

    {{-- Модальное окно создания --}}
    <x-modal id="createServerModal" title="Добавить сервер">
        <form id="createServerForm">
            @csrf
            <x-form.select name="provider" id="createServerProvider" label="Провайдер" class="selectpicker"
                           :options="[Server::VDSINA => 'VDSina']" required/>

            <x-slot name="footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-primary create-server" id="createServerBtn" data-provider="vdsina"
                        data-location="1">Создать сервер
                </button>
            </x-slot>
        </form>
    </x-modal>

    @push('js')
        <script>
            // Глобальная функция удаления сервера
            function deleteServer(id) {
                if (confirm('Вы уверены, что хотите удалить этот сервер?')) {
                    $.ajax({
                        url: '{{ route('admin.module.server.destroy', ['server' => ':id']) }}'.replace(':id', id),
                        method: 'DELETE',
                        data: {
                            _token: '{{ csrf_token() }}'
                        },
                        success: function (response) {
                            toastr.success('Сервер успешно удален');
                            setTimeout(() => {
                                window.location.reload();
                            }, 1000);
                        },
                        error: function (xhr) {
                            let errorMessage = 'Произошла ошибка при удалении сервера';
                            if (xhr.responseJSON) {
                                errorMessage = xhr.responseJSON.message || errorMessage;
                            }
                            toastr.error(errorMessage);
                        }
                    });
                }
            }

            $(document).ready(function () {
                // Настройка toastr
                toastr.options = {
                    "closeButton": true,
                    "progressBar": true,
                    "positionClass": "toast-top-right",
                    "timeOut": "3000"
                };

                console.log('Document ready');

                // Обработчик создания сервера
                $('.create-server').on('click', function () {
                    const btn = $(this);
                    const provider = btn.data('provider');
                    const location_id = btn.data('location');

                    if (!provider || !location_id) {
                        toastr.error('Не указан провайдер или локация');
                        return;
                    }

                    // Отключаем кнопку
                    btn.prop('disabled', true);

                    // Показываем индикатор загрузки
                    const loadingHtml = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Создание...';
                    const originalHtml = btn.html();
                    btn.html(loadingHtml);

                    $.ajax({
                        url: '{{ route('admin.module.server.store') }}',
                        method: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}',
                            provider: provider,
                            location_id: location_id
                        },
                        success: function (response) {
                            if (response.success) {
                                toastr.success('Сервер успешно создан');
                                setTimeout(() => {
                                    window.location.reload();
                                }, 1000);
                            } else {
                                toastr.error(response.message || 'Произошла ошибка при создании сервера');
                            }
                        },
                        error: function (xhr) {
                            let errorMessage = 'Произошла ошибка при создании сервера';
                            if (xhr.responseJSON) {
                                if (xhr.responseJSON.errors) {
                                    errorMessage = Object.values(xhr.responseJSON.errors).join('<br>');
                                } else {
                                    errorMessage = xhr.responseJSON.message || errorMessage;
                                }
                            }
                            toastr.error(errorMessage);
                        },
                        complete: function () {
                            // Возвращаем кнопку в исходное состояние
                            btn.prop('disabled', false);
                            btn.html(originalHtml);
                        }
                    });
                });
            });
        </script>
    @endpush

    @if(session('success'))
        <x-alert type="success">
            {{ session('success') }}
        </x-alert>
    @endif

    @if($errors->any())
        <x-alert type="danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-alert>
    @endif
@endsection

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
                                    <label for="name">Название</label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="{{ request('name') }}" 
                                           placeholder="Поиск по названию">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="ip">IP-адрес</label>
                                    <input type="text" class="form-control" id="ip" name="ip" 
                                           value="{{ request('ip') }}" 
                                           placeholder="Поиск по IP">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label for="host">Хост</label>
                                    <input type="text" class="form-control" id="host" name="host" 
                                           value="{{ request('host') }}" 
                                           placeholder="Поиск по хосту">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label for="status">Статус</label>
                                    <select class="form-control" id="status" name="status">
                                        <option value="">Все статусы</option>
                                        <option value="{{ Server::SERVER_CREATED }}" {{ request('status') == Server::SERVER_CREATED ? 'selected' : '' }}>
                                            Создан
                                        </option>
                                        <option value="{{ Server::SERVER_CONFIGURED }}" {{ request('status') == Server::SERVER_CONFIGURED ? 'selected' : '' }}>
                                            Настроен
                                        </option>
                                        <option value="{{ Server::SERVER_ERROR }}" {{ request('status') == Server::SERVER_ERROR ? 'selected' : '' }}>
                                            Ошибка
                                        </option>
                                        <option value="{{ Server::SERVER_DELETED }}" {{ request('status') == Server::SERVER_DELETED ? 'selected' : '' }}>
                                            Удален
                                        </option>
                                        <option value="{{ Server::SERVER_PASSWORD_UPDATE }}" {{ request('status') == Server::SERVER_PASSWORD_UPDATE ? 'selected' : '' }}>
                                            Обновление пароля
                                        </option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <div class="form-group">
                                    <button type="submit" class="btn btn-primary">Фильтровать</button>
                                    @if(request()->anyFilled(['name', 'ip', 'host', 'status']))
                                        <a href="/admin/module/server" class="btn btn-secondary">Сбросить</a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </form>

                    <x-table :headers="['#', 'Название', 'IP', 'Логин', 'Хост', 'Локация', 'Статус', '']">
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
                                                <button class="btn btn-link" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                </button>
                                                <div class="dropdown-menu">
                                                    <a class="dropdown-item text-danger" href="#"
                                                       onclick="deleteServer({{ $server->id }})">
                                                        <i class="fas fa-trash mr-2"></i>Удалить
                                                    </a>
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

                    // Блокируем кнопку
                    btn.prop('disabled', true);

                    // Показываем индикатор загрузки
                    toastr.info('Создание сервера...', '', {timeOut: 0, extendedTimeOut: 0});

                    $.ajax({
                        url: '{{ route('admin.module.server.store') }}',
                        method: 'POST',
                        data: {
                            provider: provider,
                            location_id: location_id,
                            _token: '{{ csrf_token() }}'
                        },
                        success: function (response) {
                            if (response.success) {
                                toastr.success(response.message || 'Сервер успешно создан');
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
                                    // Показываем ошибки валидации
                                    const errors = xhr.responseJSON.errors;
                                    errorMessage = Object.values(errors).flat().join('\n');
                                } else if (xhr.responseJSON.message) {
                                    errorMessage = xhr.responseJSON.message;
                                }
                            }

                            toastr.error(errorMessage);
                        },
                        complete: function () {
                            // Разблокируем кнопку
                            btn.prop('disabled', false);
                            // Скрываем индикатор загрузки
                            toastr.clear();
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

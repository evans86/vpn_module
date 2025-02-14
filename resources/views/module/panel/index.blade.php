@extends('layouts.app', ['page' => __('Панели'), 'pageSlug' => 'panels'])

@php
    use App\Models\Panel\Panel;
@endphp

@section('content')
    <div class="container-fluid">
        @if(session('error'))
            <x-alert type="danger">{{ session('error') }}</x-alert>
        @endif

        @if(session('success'))
            <x-alert type="success">{{ session('success') }}</x-alert>
        @endif

        <div class="row">
            <div class="col-lg-12">
                <x-card title="Список панелей">
                    <x-slot name="tools">
                        <button type="button" class="btn btn-primary" data-toggle="modal"
                                data-target="#createPanelModal">
                            <i class="fas fa-plus"></i> Добавить панель
                        </button>
                    </x-slot>

                    <form method="GET" action="{{ route('admin.module.panel.index') }}" class="mb-4">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="server">Сервер</label>
                                    <input type="text" class="form-control" id="server" name="server"
                                           value="{{ request('server') }}"
                                           placeholder="Поиск по имени или IP сервера">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="panel_adress">Адрес панели</label>
                                    <input type="text" class="form-control" id="panel_adress" name="panel_adress"
                                           value="{{ request('panel_adress') }}"
                                           placeholder="Поиск по адресу панели">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <div class="btn-group btn-block">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-search"></i> Поиск
                                        </button>
                                        <a href="{{ route('admin.module.panel.index') }}"
                                           class="btn btn-secondary">
                                            <i class="fas fa-times"></i> Сбросить
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>

                    <x-table :headers="['#', 'Адрес панели', 'Тип', 'Логин', 'Пароль', 'Сервер', 'Статус', '']">
                        @forelse($panels as $panel)
                            <tr>
                                <td><strong>{{ $panel->id }}</strong></td>
                                <td>
                                    <a href="{{ $panel->panel_adress }}" target="_blank" class="text-primary">
                                        {{ $panel->formatted_address }}
                                        <i class="fas fa-external-link-alt fa-sm"></i>
                                    </a>
                                </td>
                                <td>{{ $panel->panel_type_label }}</td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <span>{{ $panel->panel_login }}</span>
                                        <button class="btn btn-sm btn-link ml-2"
                                                data-clipboard-text="{{ $panel->panel_login }}"
                                                title="Копировать логин">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <span>{{ $panel->panel_password }}</span>
                                        <button class="btn btn-sm btn-link ml-2"
                                                data-clipboard-text="{{ $panel->panel_password }}"
                                                title="Копировать пароль">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                </td>
                                <td>
                                    @if($panel->server)
                                        <a href="{{ route('admin.module.server.index', ['id' => $panel->server_id]) }}"
                                           class="text-primary"
                                           title="Перейти к серверу">
                                            {{ $panel->server->name }}
                                            <small class="d-block text-muted">
                                                {{ $panel->server->host }}
                                            </small>
                                        </a>
                                    @else
                                        <span class="text-danger">Сервер удален</span>
                                    @endif
                                </td>
                                <td>
                                <span class="badge badge-{{ $panel->status_badge_class }}">
                                    {{ $panel->status_label }}
                                </span>
                                </td>
                                <td class="text-right">
                                    <div class="dropdown">
                                        <button class="btn btn-link" type="button" data-toggle="dropdown">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <div class="dropdown-menu dropdown-menu-right">
                                            <a href="{{ route('admin.module.server-users.index', ['panel_id' => $panel->id]) }}"
                                               class="dropdown-item">
                                                <i class="fas fa-users"></i> Пользователи
                                            </a>
                                            <form action="{{ route('admin.module.panel.update-config', $panel) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="dropdown-item">
                                                    <i class="fas fa-sync"></i> Обновить конфигурацию
                                                </button>
                                            </form>
                                            <!-- Кнопка "Статистика" для настроенных панелей -->
                                            @if($panel->panel_status === \App\Models\Panel\Panel::PANEL_CONFIGURED)
                                                <a href="{{ route('admin.module.server-monitoring.index', ['panel_id' => $panel->id]) }}"
                                                   class="dropdown-item">
                                                    <i class="fas fa-chart-line"></i> Статистика
                                                </a>
                                            @endif
                                            <button type="button" class="dropdown-item text-danger"
                                                    onclick="deletePanel({{ $panel->id }})">
                                                <i class="fas fa-trash"></i> Удалить
                                            </button>
                                        </div>
                                    </div>
                                </td>
                            </tr>

                            {{-- Модальное окно редактирования --}}
                            <x-modal id="editPanelModal{{ $panel->id }}" title="Редактировать панель">
                                <form action="{{ route('admin.module.panel.update-credentials', $panel) }}" method="POST">
                                    @csrf
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label for="username">Логин</label>
                                                <input type="text" name="username" id="username" class="form-control" value="{{ $panel->panel_login }}" minlength="3" maxlength="255">
                                            </div>
                                        </div>
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label for="password">Пароль</label>
                                                <input type="password" name="password" id="password" class="form-control" minlength="6" maxlength="255">
                                                <small class="form-text text-muted">Оставьте поле пустым, если не хотите менять пароль</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Отмена</button>
                                        <button type="submit" class="btn btn-primary">Сохранить</button>
                                    </div>
                                </form>
                            </x-modal>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted">
                                    <i class="fas fa-info-circle"></i> Панели не найдены
                                </td>
                            </tr>
                        @endforelse
                    </x-table>

                    {{ $panels->links() }}
                </x-card>
            </div>
        </div>
    </div>

    {{-- Модальное окно создания --}}
    <x-modal id="createPanelModal" title="Добавить панель">
        <form action="{{ route('admin.module.panel.store') }}" method="POST">
            @csrf

            <x-select name="server_id"
                      label="Выберите сервер"
                      :options="$servers"
                      required
                      help="Будет создана панель Marzban на выбранном сервере"/>

            <div class="text-right">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    Отмена
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Создать
                </button>
            </div>
        </form>
    </x-modal>
@endsection

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

@push('js')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/clipboard.js/2.0.8/clipboard.min.js"></script>
    <script>
        $(document).ready(function () {
            var clipboard = new ClipboardJS('[data-clipboard-text]');

            clipboard.on('success', function (e) {
                toastr.success('Скопировано в буфер обмена');
                e.clearSelection();
            });

            clipboard.on('error', function (e) {
                toastr.error('Ошибка копирования');
            });
        });
    </script>
@endpush

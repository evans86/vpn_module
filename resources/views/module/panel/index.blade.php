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

                    <form method="GET" action="/admin/module/panel" class="mb-4">
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
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label for="status">Статус</label>
                                    <select class="form-control" id="status" name="status">
                                        <option value="">Все статусы</option>
                                        <option value="{{ \App\Models\Panel\Panel::PANEL_CREATED }}" 
                                            {{ request('status') == \App\Models\Panel\Panel::PANEL_CREATED ? 'selected' : '' }}>
                                            Создана
                                        </option>
                                        <option value="{{ \App\Models\Panel\Panel::PANEL_CONFIGURED }}" 
                                            {{ request('status') == \App\Models\Panel\Panel::PANEL_CONFIGURED ? 'selected' : '' }}>
                                            Настроена
                                        </option>
                                        <option value="{{ \App\Models\Panel\Panel::PANEL_ERROR }}" 
                                            {{ request('status') == \App\Models\Panel\Panel::PANEL_ERROR ? 'selected' : '' }}>
                                            Ошибка
                                        </option>
                                        <option value="{{ \App\Models\Panel\Panel::PANEL_DELETED }}" 
                                            {{ request('status') == \App\Models\Panel\Panel::PANEL_DELETED ? 'selected' : '' }}>
                                            Удалена
                                        </option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <div class="form-group">
                                    <button type="submit" class="btn btn-primary">Фильтровать</button>
                                    @if(request()->anyFilled(['server', 'panel_adress', 'status']))
                                        <a href="/admin/module/panel" class="btn btn-secondary">Сбросить</a>
                                    @endif
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
                                        <div class="d-flex align-items-center">
                                            <img
                                                src="https://flagcdn.com/w20/{{ strtolower($panel->server->location->code) }}.png"
                                                class="mr-2"
                                                alt="{{ strtoupper($panel->server->location->code) }}"
                                                width="20">
                                            <span>{{ $panel->server->name }}</span>
                                        </div>
                                    @else
                                        <span class="text-muted">Не назначен</span>
                                    @endif
                                </td>
                                <td>
                                <span class="badge badge-{{ $panel->status_badge_class }}">
                                    {{ $panel->status_label }}
                                </span>
                                </td>
                                <td>
                                    @if($panel->panel_status !== \App\Models\Panel\Panel::PANEL_DELETED)
                                        <div class="dropdown">
                                            <button class="btn btn-link" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <div class="dropdown-menu">
                                                <a class="dropdown-item text-danger" href="#"
                                                   onclick="deletePanel({{ $panel->id }})">
                                                    <i class="fas fa-trash mr-2"></i>Удалить
                                                </a>
                                            </div>
                                        </div>
                                    @endif
                                </td>
                            </tr>

                            {{-- Модальное окно редактирования --}}
                            <x-modal id="editPanelModal{{ $panel->id }}" title="Редактировать панель">
                                <form action="{{ route('admin.module.panel.update', $panel) }}" method="POST">
                                    @csrf
                                    @method('PUT')

                                    <x-input name="panel_adress" label="Адрес панели" type="url"
                                             value="{{ $panel->panel_adress }}" required/>

                                    <x-input name="panel_login" label="Логин" type="text"
                                             value="{{ $panel->panel_login }}" required/>

                                    <x-input name="panel_password" label="Пароль" type="password"
                                             help="Оставьте пустым, чтобы не менять"/>

                                    <x-select name="server_id" label="Сервер" :options="$servers"
                                              selected="{{ $panel->server_id }}" required/>

                                    <div class="text-right">
                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">
                                            Отмена
                                        </button>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save"></i> Сохранить
                                        </button>
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

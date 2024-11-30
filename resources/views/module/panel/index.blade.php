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
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#createPanelModal">
                        <i class="fas fa-plus"></i> Добавить панель
                    </button>
                </x-slot>

                <x-table :headers="['#', 'Адрес панели', 'Тип', 'Логин', 'Сервер', 'Статус', '']">
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
                            <td>{{ $panel->panel_login }}</td>
                            <td>
                                @if($panel->server)
                                    <div class="d-flex align-items-center">
                                        <span class="emoji-flag">{!! $panel->server->location->emoji !!}</span>
                                        <span class="ml-2">{{ $panel->server->name }}</span>
                                    </div>
                                @else
                                    <span class="text-muted">Не назначен</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge light badge-{{ $panel->status_badge_class }}">
                                    {{ $panel->status_label }}
                                </span>
                            </td>
                            <td>
                                <div class="dropdown">
                                    <button type="button" class="btn btn-link" data-toggle="dropdown">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-right">
                                        <a class="dropdown-item" href="#" data-toggle="modal" 
                                           data-target="#editPanelModal{{ $panel->id }}">
                                            <i class="fas fa-edit"></i> Редактировать
                                        </a>
                                        @if(!$panel->isConfigured())
                                            <a class="dropdown-item" href="{{ route('module.panel.configure', $panel) }}"
                                               onclick="return confirm('Вы уверены, что хотите настроить эту панель?')">
                                                <i class="fas fa-cog"></i> Настроить
                                            </a>
                                        @endif
                                        <div class="dropdown-divider"></div>
                                        <form action="{{ route('module.panel.destroy', $panel) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="dropdown-item text-danger" 
                                                    onclick="return confirm('Вы уверены, что хотите удалить эту панель?')">
                                                <i class="fas fa-trash-alt"></i> Удалить
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </td>
                        </tr>

                        {{-- Модальное окно редактирования --}}
                        <x-modal id="editPanelModal{{ $panel->id }}" title="Редактировать панель">
                            <form action="{{ route('module.panel.update', $panel) }}" method="POST">
                                @csrf
                                @method('PUT')
                                
                                <x-input name="panel_adress" label="Адрес панели" type="url" 
                                        value="{{ $panel->panel_adress }}" required />
                                
                                <x-input name="panel_login" label="Логин" type="text" 
                                        value="{{ $panel->panel_login }}" required />
                                
                                <x-input name="panel_password" label="Пароль" type="password" 
                                        help="Оставьте пустым, чтобы не менять" />
                                
                                <x-select name="server_id" label="Сервер" :options="$servers" 
                                         selected="{{ $panel->server_id }}" required />

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
    <form action="{{ route('module.panel.store') }}" method="POST">
        @csrf
        
        <x-input name="panel_adress" label="Адрес панели" type="url" required
                 placeholder="https://example.com:8080" />
        
        <x-input name="panel_login" label="Логин" type="text" required
                 placeholder="admin" />
        
        <x-input name="panel_password" label="Пароль" type="password" required />
        
        <x-select name="server_id" label="Сервер" :options="$servers" required />

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

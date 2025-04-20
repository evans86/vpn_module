@extends('layouts.app', ['page' => __('Пакеты'), 'pageSlug' => 'packs'])

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-12">
                <x-card title="Список пакетов">
                    <x-slot name="tools">
                        <button type="button" class="btn btn-primary" data-toggle="modal"
                                data-target="#createPackModal">
                            <i class="fas fa-plus"></i> Добавить пакет
                        </button>
                    </x-slot>

                    <form method="GET" action="/admin/module/pack" class="mb-4">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="id">ID пакета</label>
                                    <input type="number" class="form-control" id="id" name="id"
                                           value="{{ request('id') }}" placeholder="Введите ID пакета">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="title">Название</label>
                                    <input type="text" class="form-control" id="title" name="title"
                                           value="{{ request('title') }}" placeholder="Введите название пакета">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="status">Статус</label>
                                    <select class="form-control" id="status" name="status">
                                        <option value="">Все статусы</option>
                                        <option value="1" {{ request('status') == '1' ? 'selected' : '' }}>Активен
                                        </option>
                                        <option value="0" {{ request('status') == '0' ? 'selected' : '' }}>Неактивен
                                        </option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <div class="btn-group btn-block">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-search"></i> Поиск
                                        </button>
                                        <a href="{{ route('admin.module.pack.index') }}"
                                           class="btn btn-secondary">
                                            <i class="fas fa-times"></i> Сбросить
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>

                    @if(session('success'))
                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="alert alert-danger">
                            {{ session('error') }}
                        </div>
                    @endif

                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                            <tr>
                                <th>ID</th>
                                <th>Название</th>
                                <th>Цена</th>
                                <th>Период</th>
                                <th>Трафик</th>
                                <th>Ключи</th>
                                <th>Время активации</th>
                                <th>Статус</th>
                                <th>Действия</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($packs as $pack)
                                <tr>
                                    <td>{{ $pack->id }}</td>
                                    <td>{{ $pack->title }}</td>
                                    <td>{{ $pack->price }} ₽</td>
                                    <td>{{ $pack->period }} дней</td>
                                    <td>{{ number_format($pack->traffic_limit / (1024*1024*1024), 1) }} GB</td>
                                    <td>{{ $pack->count }}</td>
                                    <td>{{ floor($pack->activate_time / 3600) }} ч.</td>
                                    <td>
                                        <span class="badge badge-{{ $pack->status ? 'success' : 'danger' }}">
                                            {{ $pack->status ? 'Активен' : 'Неактивен' }}
                                        </span>
                                    </td>
                                    <td>
                                        <form action="/admin/module/pack/{{ $pack->id }}" method="POST"
                                              class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger"
                                                    onclick="return confirm('Вы уверены?')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center">Пакеты не найдены</td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{ $packs->links() }}
                </x-card>
            </div>
        </div>
    </div>

    {{-- Модальное окно создания --}}
    <x-modal id="createPackModal" title="Добавить пакет">
        <form action="/admin/module/pack" method="POST">
            @csrf
            <div class="form-group">
                <label for="price">Цена (₽)</label>
                <input type="number" class="form-control" id="price" name="price" required min="0">
            </div>
            <div class="form-group">
                <label for="title">Название</label>
                <input type="text" class="form-control" id="title" name="title" required min="0">
            </div>
            <div class="form-group">
                <label for="period">Период действия (дней)</label>
                <input type="number" class="form-control" id="period" name="period" required min="1" value="30">
            </div>
            <div class="form-group">
                <label for="traffic_limit">Лимит трафика (GB)</label>
                <input type="number" class="form-control" id="traffic_limit" name="traffic_limit" required min="1"
                       value="10">
            </div>
            <div class="form-group">
                <label for="count">Количество ключей</label>
                <input type="number" class="form-control" id="count" name="count" required min="1" value="5">
            </div>
            <div class="form-group">
                <label for="activate_time">Время на активацию (часов)</label>
                <input type="number" class="form-control" id="activate_time" name="activate_time" required min="1"
                       value="24">
            </div>
            <div class="modal-footer px-0 pb-0">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Отмена</button>
                <button type="submit" class="btn btn-primary">Создать</button>
            </div>
        </form>
    </x-modal>
@endsection

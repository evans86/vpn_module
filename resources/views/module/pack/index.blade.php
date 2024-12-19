@extends('layouts.app', ['page' => __('Пакеты'), 'pageSlug' => 'packs'])

@php
    use App\Models\Pack\Pack;
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
                <x-card title="Список пакетов VPN">
                    <x-slot name="tools">
                        <button type="button" class="btn btn-primary" data-toggle="modal"
                                data-target="#createPackModal">
                            <i class="fas fa-plus"></i> Добавить пакет
                        </button>
                    </x-slot>

                    <x-table :headers="['#', 'Цена', 'Период', 'Трафик', 'Ключи', 'Время активации', 'Статус', '']">
                        @if($packs->isEmpty())
                            <tr>
                                <td colspan="8" class="text-center text-muted">
                                    <i class="fas fa-info-circle"></i> Пакеты не найдены
                                </td>
                            </tr>
                        @else
                            @foreach($packs as $pack)
                                <tr>
                                    <td><strong>{{ $pack->id }}</strong></td>
                                    <td>{{ number_format($pack->price, 0, '.', ' ') }} ₽</td>
                                    <td>{{ $pack->period }} дней</td>
                                    <td>{{ number_format($pack->traffic_limit / 1024 / 1024 / 1024, 0) }} GB</td>
                                    <td>{{ $pack->count }} шт</td>
                                    <td>{{ floor($pack->activate_time / 3600) }} часов</td>
                                    <td>
                                    <span class="badge badge-{{ $pack->status ? 'success' : 'danger' }}">
                                        {{ $pack->status ? 'Активен' : 'Неактивен' }}
                                    </span>
                                    </td>
                                    <td>
                                        <div class="dropdown">
                                            <button class="btn btn-link" type="button" data-toggle="dropdown">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <div class="dropdown-menu">
                                                <a class="dropdown-item" href="#" data-toggle="modal"
                                                   data-target="#editPackModal{{ $pack->id }}">
                                                    <i class="fas fa-edit mr-2"></i>Редактировать
                                                </a>
                                                <a class="dropdown-item text-danger" href="#"
                                                   onclick="deletePack({{ $pack->id }})">
                                                    <i class="fas fa-trash mr-2"></i>Удалить
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        @endif
                    </x-table>

                    <div class="d-flex justify-content-center mt-3">
                        {{ $packs->links() }}
                    </div>
                </x-card>
            </div>
        </div>
    </div>

    {{-- Модальное окно создания --}}
    <x-modal id="createPackModal" title="Добавить пакет">
        <form action="{{ secure_url('admin/module/pack') }}" method="POST">
            @csrf

            <x-form.input type="number" name="price" label="Цена (₽)" required min="0"/>
            <x-form.input type="number" name="period" label="Период действия (дней)" required min="1" value="30"/>
            <x-form.input type="number" name="traffic_limit" label="Лимит трафика (GB)" required min="1" value="10"/>
            <x-form.input type="number" name="count" label="Количество ключей" required min="1" value="5"/>
            <x-form.input type="number" name="activate_time" label="Время на активацию (часов)" required min="1"
                          value="24"/>

            <div class="text-right">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Отмена</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Создать
                </button>
            </div>
        </form>
    </x-modal>
@endsection

@foreach($packs as $pack)
    {{-- Модальные окна редактирования --}}
    <x-modal id="editPackModal{{ $pack->id }}" title="Редактировать пакет #{{ $pack->id }}">
        <form action="{{ secure_url('admin/module/pack/' . $pack->id) }}" method="POST">
            @csrf
            @method('PUT')

            <x-form.input type="number" name="price" label="Цена (₽)" required min="0" :value="$pack->price"/>
            <x-form.input type="number" name="period" label="Период действия (дней)" required min="1"
                          :value="$pack->period"/>
            <x-form.input type="number" name="traffic_limit" label="Лимит трафика (GB)" required min="1"
                          :value="round($pack->traffic_limit/1024/1024/1024)"/>
            <x-form.input type="number" name="count" label="Количество ключей" required min="1" :value="$pack->count"/>
            <x-form.input type="number" name="activate_time" label="Время на активацию (часов)" required min="1"
                          :value="floor($pack->activate_time/3600)"/>

            <x-form.select name="status" label="Статус" :options="[1 => 'Активен', 0 => 'Неактивен']"
                           :value="$pack->status"/>

            <div class="text-right">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Отмена</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Сохранить
                </button>
            </div>
        </form>
    </x-modal>
@endforeach

@push('js')
    <script>
        function deletePack(id) {
            if (confirm('Вы уверены, что хотите удалить этот пакет?')) {
                $.ajax({
                    url: '{{ route('module.pack.destroy', ['pack' => ':id']) }}'.replace(':id', id),
                    method: 'DELETE',
                    data: {
                        _token: '{{ csrf_token() }}'
                    },
                    success: function (response) {
                        toastr.success('Пакет успешно удален');
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    },
                    error: function (xhr) {
                        let errorMessage = 'Произошла ошибка при удалении пакета';
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

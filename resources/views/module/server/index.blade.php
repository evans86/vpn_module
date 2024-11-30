@extends('layouts.app', ['page' => __('Серверы'), 'pageSlug' => 'servers'])

@php
    use App\Models\Server\Server;
@endphp

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-12">
            <x-card title="Список серверов">
                <x-slot name="tools">
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#createServerModal">
                        <i class="fas fa-plus"></i> Добавить сервер
                    </button>
                </x-slot>

                <x-table :headers="['#', 'Название', 'IP', 'Логин', 'Хост', 'Локация', 'Статус', '']">
                    @foreach($servers as $server)
                        <tr>
                            <td><strong>{{ $server->id }}</strong></td>
                            <td>{{ $server->name }}</td>
                            <td>{{ $server->ip }}</td>
                            <td>{{ $server->login }}</td>
                            <td>{{ $server->host }}</td>
                            <td>
                                {{ $server->location->code }} 
                                <span class="emoji-flag">{!! $server->location->emoji !!}</span>
                            </td>
                            <td>
                                <span class="badge light badge-{{ $server->status_badge_class }}">
                                    {{ $server->status_label }}
                                </span>
                            </td>
                            <td>
                                <div class="dropdown">
                                    <button type="button" class="btn btn-primary light sharp" data-toggle="dropdown">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item" href="#" data-toggle="modal" 
                                           data-target="#editServerModal{{ $server->id }}">
                                            <i class="fas fa-edit mr-2"></i>Редактировать
                                        </a>
                                        <a class="dropdown-item text-danger" href="#" data-toggle="modal" 
                                           data-target="#deleteServerModal{{ $server->id }}">
                                            <i class="fas fa-trash mr-2"></i>Удалить
                                        </a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforeach
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
                      :options="[Server::VDSINA => 'VDSina']" required />
        
        <x-slot name="footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Отмена</button>
            <button type="button" class="btn btn-primary" id="createServerBtn">Создать сервер</button>
        </x-slot>
    </form>
</x-modal>

{{-- Модальные окна редактирования --}}
@foreach($servers as $server)
    <x-modal id="editServerModal{{ $server->id }}" title="Редактировать сервер">
        <form action="{{ route('module.server.update', $server->id) }}" method="POST">
            @csrf
            @method('PUT')
            <x-form.input name="name" label="Название сервера" :value="$server->name" required />
            <x-form.input name="ip" label="IP адрес" :value="$server->ip" required />
            <x-form.input name="login" label="Логин" :value="$server->login" required />
            <x-form.input name="password" label="Пароль" type="password" />
            <x-form.input name="host" label="Хост" :value="$server->host" required />
            <x-form.select name="provider" id="editServerProvider{{ $server->id }}" label="Провайдер" 
                          :options="[Server::VDSINA => 'VDSina']" 
                          :value="$server->provider" required />
            <x-form.select name="location_id" id="editServerLocation{{ $server->id }}" label="Локация" 
                          :options="$locations" 
                          :value="$server->location_id" required />
            <x-form.select name="server_status" id="editServerStatus{{ $server->id }}" label="Статус" 
                          :options="[
                              Server::SERVER_CREATED => 'Создан',
                              Server::SERVER_CONFIGURED => 'Настроен',
                              Server::SERVER_PASSWORD_UPDATE => 'Обновление пароля',
                              Server::SERVER_ERROR => 'Ошибка'
                          ]" 
                          :value="$server->server_status" required />
            <x-form.select name="is_free" id="editServerIsFree{{ $server->id }}" label="Свободен" 
                          :options="['1' => 'Да', '0' => 'Нет']" 
                          :value="$server->is_free" required />
            <x-slot name="footer">
                <button type="submit" class="btn btn-primary">Обновить</button>
            </x-slot>
        </form>
    </x-modal>

    <x-modal id="deleteServerModal{{ $server->id }}" title="Удалить сервер">
        <p>Вы уверены, что хотите удалить этот сервер?</p>
        <form action="{{ route('module.server.destroy', $server->id) }}" method="POST">
            @csrf
            @method('DELETE')
            <x-slot name="footer">
                <button type="submit" class="btn btn-danger">Удалить</button>
            </x-slot>
        </form>
    </x-modal>
@endforeach

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

@push('js')
<script>
$(document).ready(function() {
    console.log('Document ready');
    
    // Инициализируем selectpicker при открытии модального окна
    $('#createServerModal').on('shown.bs.modal', function () {
        console.log('Modal shown');
        $(this).find('.selectpicker').selectpicker('refresh');
    });
    
    // Обработчик клика на кнопку создания
    $('#createServerBtn').on('click', function(e) {
        console.log('Create button clicked');
        e.preventDefault();
        
        const form = $('#createServerForm');
        const submitBtn = $(this);
        console.log('Form found:', form.length > 0);
        
        // Проверяем, что провайдер выбран
        const provider = $('#createServerProvider').val();
        console.log('Selected provider:', provider);
        if (!provider) {
            alert('Пожалуйста, выберите провайдера');
            return;
        }
        
        // Блокируем кнопку и показываем индикатор загрузки
        submitBtn.prop('disabled', true);
        submitBtn.html('<i class="fas fa-spinner fa-spin mr-2"></i>Создание...');
        
        // Отправляем запрос
        $.ajax({
            url: '{{ route("module.server.store") }}',
            type: 'POST',
            data: form.serialize(),
            success: function(response) {
                console.log('Success response:', response);
                if (response.status === 'success') {
                    // Закрываем модальное окно
                    $('#createServerModal').modal('hide');
                    
                    // Показываем уведомление об успехе
                    $('body').append(`
                        <div class="alert alert-success alert-dismissible fade show position-fixed" 
                             style="top: 1rem; right: 1rem; z-index: 9999;" role="alert">
                            ${response.message}
                            <button type="button" class="close" data-dismiss="alert">
                                <span>&times;</span>
                            </button>
                        </div>
                    `);
                    
                    // Перезагружаем страницу через 2 секунды
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    // Показываем ошибку
                    alert(response.message || 'Произошла ошибка при создании сервера');
                    submitBtn.prop('disabled', false);
                    submitBtn.html('Создать сервер');
                }
            },
            error: function(xhr) {
                console.log('Error response:', xhr);
                // Показываем ошибку
                const errorMessage = xhr.responseJSON?.message || 'Произошла ошибка при создании сервера';
                console.error('Error:', errorMessage);
                alert(errorMessage);
                submitBtn.prop('disabled', false);
                submitBtn.html('Создать сервер');
            }
        });
    });
});
</script>
@endpush

@extends('layouts.app', ['page' => __('Ключи активации'), 'pageSlug' => 'activate_keys'])

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Ключи активации</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-responsive-md">
                                <thead>
                                <tr>
                                    <th><strong>ID</strong></th>
                                    <th><strong>Трафик</strong></th>
                                    <th><strong>Пакет</strong></th>
                                    <th><strong>Действует до</strong></th>
                                    <th><strong>Telegram ID</strong></th>
                                    <th><strong>Активировать до</strong></th>
                                    <th><strong>Статус</strong></th>
                                    <th></th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($activate_keys as $key)
                                    <tr>
                                        <td><strong>{{ substr($key->id, 0, 8) }}</strong></td>
                                        <td>{{ number_format($key->traffic_limit / (1024*1024*1024), 1) }} GB</td>
                                        <td>
                                            @if($key->packSalesman)
                                                {{ $key->packSalesman->pack->name }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>{{ date('d.m.Y H:i', $key->finish_at) }}</td>
                                        <td>
                                            @if($key->user_tg_id)
                                                <a href="https://t.me/{{ $key->user_tg_id }}" target="_blank">
                                                    {{ $key->user_tg_id }}
                                                </a>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>{{ date('d.m.Y H:i', $key->deleted_at) }}</td>
                                        <td>
                                            <span class="badge {{ $key->getStatusBadgeClass() }}">
                                                {{ $key->getStatusText() }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="dropdown">
                                                <button type="button" class="btn btn-success light sharp" data-toggle="dropdown">
                                                    <svg width="20px" height="20px" viewBox="0 0 24 24" version="1.1">
                                                        <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                                            <rect x="0" y="0" width="24" height="24"/>
                                                            <circle fill="#000000" cx="5" cy="12" r="2"/>
                                                            <circle fill="#000000" cx="12" cy="12" r="2"/>
                                                            <circle fill="#000000" cx="19" cy="12" r="2"/>
                                                        </g>
                                                    </svg>
                                                </button>
                                                <div class="dropdown-menu">
                                                    <a class="dropdown-item" href="#" onclick="copyKeyId('{{ $key->id }}')">Копировать ID</a>
                                                    @if($key->status == \App\Models\KeyActivate\KeyActivate::PAID)
                                                        <a class="dropdown-item text-success" href="#" onclick="testActivation('{{ $key->id }}')">Тест активации</a>
                                                    @endif
                                                    <a class="dropdown-item text-danger" href="#" onclick="deleteKey('{{ $key->id }}')">Удалить</a>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex justify-content-center mt-3">
                            {{ $activate_keys->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<script>
    // Настройка CSRF-токена для всех AJAX-запросов
    const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    axios.defaults.headers.common['X-CSRF-TOKEN'] = token;

    function copyKeyId(id) {
        navigator.clipboard.writeText(id).then(function() {
            toastr.success('ID ключа скопирован в буфер обмена');
        }).catch(function() {
            toastr.error('Не удалось скопировать ID ключа');
        });
    }

    function testActivation(id) {
        if (confirm('Выполнить тестовую активацию ключа?')) {
            console.log('Отправка запроса на активацию ключа:', id);
            console.log('CSRF token:', token);
            
            axios.post(`/admin/module/key-activate/${id}/test-activate`, {}, {
                headers: {
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                }
            })
            .then(function (response) {
                console.log('Успешный ответ:', response.data);
                toastr.success(response.data.message || 'Ключ успешно активирован');
                setTimeout(() => window.location.reload(), 1000);
            })
            .catch(function (error) {
                console.error('Полная информация об ошибке:', error);
                console.error('Ответ сервера:', error.response?.data);
                toastr.error(error.response?.data?.message || 'Ошибка при активации ключа');
            });
        }
    }

    function deleteKey(id) {
        if (confirm('Вы уверены, что хотите удалить этот ключ?')) {
            axios.delete(`/admin/module/key-activate/${id}`, {
                headers: {
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json'
                }
            })
            .then(function (response) {
                toastr.success(response.data.message || 'Ключ успешно удален');
                setTimeout(() => window.location.reload(), 1000);
            })
            .catch(function (error) {
                console.error('Ошибка при удалении:', error.response?.data);
                toastr.error(error.response?.data?.message || 'Ошибка при удалении ключа');
            });
        }
    }
</script>
@endpush

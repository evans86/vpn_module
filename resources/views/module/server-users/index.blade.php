@extends('layouts.app', ['page' => __('Пользователи сервера'), 'pageSlug' => 'server_users'])

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Пользователи сервера</h4>
                    </div>

                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-responsive-md">
                                <thead>
                                    <tr>
                                        <th><strong>ID</strong></th>
                                        <th><strong>Статус</strong></th>
                                        <th><strong>Сервер</strong></th>
                                        <th><strong>Панель</strong></th>
                                        <th><strong>Ключи</strong></th>
                                        <th><strong>Использовано</strong></th>
                                        <th><strong>Действия</strong></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($serverUsers as $user)
                                        <tr>
                                            <td>
                                                <a href="{{ route('admin.server-users.show', $user) }}" class="text-primary">
                                                    {{ $user->id }}
                                                </a>
                                            </td>
                                            <td>
                                                <span class="badge {{ $user->status === 'active' ? 'badge-success' : 'badge-danger' }}">
                                                    {{ $user->status }}
                                                </span>
                                            </td>
                                            <td>
                                                @if($user->server)
                                                    <a href="{{ route('admin.module.server.show', $user->server) }}" class="text-primary">
                                                        {{ $user->server->name }}
                                                    </a>
                                                @else
                                                    <span class="text-muted">Нет сервера</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($user->server && $user->server->panel)
                                                    <a href="{{ route('admin.module.panel.show', $user->server->panel) }}" class="text-primary">
                                                        {{ $user->server->panel->name }}
                                                    </a>
                                                @else
                                                    <span class="text-muted">Нет панели</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($user->keys)
                                                    <div class="dropdown">
                                                        <button class="btn btn-link dropdown-toggle" type="button" data-toggle="dropdown">
                                                            Показать ключи
                                                        </button>
                                                        <div class="dropdown-menu">
                                                            @foreach(json_decode($user->keys, true) as $key)
                                                                <div class="dropdown-item">
                                                                    <div class="d-flex justify-content-between align-items-center">
                                                                        <span class="text-truncate mr-2">{{ $key }}</span>
                                                                        <button onclick="copyToClipboard('{{ $key }}')" 
                                                                                class="btn btn-sm btn-outline-primary">
                                                                            Копировать
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                @else
                                                    <span class="text-muted">Нет ключей</span>
                                                @endif
                                            </td>
                                            <td>
                                                {{ number_format($user->used_traffic / (1024*1024*1024), 2) }} GB
                                            </td>
                                            <td>
                                                @if($user->keyActivateUser)
                                                    <a href="{{ route('admin.module.key-activate.show', $user->keyActivateUser->keyActivate) }}" 
                                                       class="text-primary">
                                                        Перейти к ключу
                                                    </a>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex justify-content-center mt-4">
                            {{ $serverUsers->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

@push('js')
<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        // Можно добавить уведомление об успешном копировании
    });
}
</script>
@endpush
@endsection

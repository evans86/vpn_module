@extends('layouts.app', ['page' => __('Пользователи сервера'), 'pageSlug' => 'server_users'])

@section('content')
    <div class="container-fluid">
        <div class="keys-overlay"></div>
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">
                            Пользователи сервера
                            @if($panel)
                                - Панель: {{ $panel->panel_adress }}
                            @endif
                        </h4>
                    </div>

                    <div class="card-body">
                        <!-- Фильтры -->
                        <form method="GET" action="{{ route('admin.module.server-users.index') }}" class="mb-4">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="id">ID</label>
                                        <input type="text" class="form-control" id="id" name="id" 
                                               value="{{ request('id') }}" 
                                               placeholder="Поиск по ID">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="server">Сервер</label>
                                        <input type="text" class="form-control" id="server" name="server" 
                                               value="{{ request('server') }}" 
                                               placeholder="Поиск по имени или IP">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="panel">Панель</label>
                                        <input type="text" class="form-control" id="panel" name="panel" 
                                               value="{{ request('panel') }}" 
                                               placeholder="Поиск по адресу">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>&nbsp;</label>
                                        <div class="btn-group btn-block">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-search"></i> Поиск
                                            </button>
                                            <a href="{{ route('admin.module.server-users.index') }}" 
                                               class="btn btn-secondary">
                                                <i class="fas fa-times"></i> Сбросить
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>

                        <div class="table-responsive">
                            <table class="table table-responsive-md">
                                <thead>
                                    <tr>
                                        <th><strong>ID</strong></th>
                                        <th><strong>Сервер</strong></th>
                                        <th><strong>Панель</strong></th>
                                        <th><strong>Telegram ID</strong></th>
                                        <th><strong>Ключи</strong></th>
                                        <th><strong>Использовано</strong></th>
                                        <th><strong>Действия</strong></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($serverUsers as $user)
                                        <tr>
                                            <td>{{ $user->id }}</td>
                                            <td>
                                                @if($user->panel && $user->panel->server)
                                                    <a href="{{ route('admin.module.server.index', ['server_id' => $user->panel->server->id]) }}" 
                                                       class="text-primary">
                                                        {{ $user->panel->server->name }}
                                                    </a>
                                                @else
                                                    <span class="text-muted">Нет сервера</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($user->panel)
                                                    <a href="{{ route('admin.module.panel.index', ['panel_id' => $user->panel->id]) }}" 
                                                       class="text-primary" 
                                                       title="{{ $user->panel->panel_adress }}">
                                                        {{ Str::limit($user->panel->panel_adress, 30) }}
                                                    </a>
                                                @else
                                                    <span class="text-muted">Нет панели</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($user->telegram_id)
                                                    <a href="https://t.me/{{ $user->telegram_id }}" target="_blank">
                                                        {{ $user->telegram_id }}
                                                    </a>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($user->keys)
                                                    <div class="keys-dropdown">
                                                        <button class="btn btn-link p-0 text-primary" type="button" data-toggle="dropdown">
                                                            <i class="fas fa-key mr-1"></i>Показать ключи
                                                        </button>
                                                        <div class="dropdown-menu">
                                                            @foreach(json_decode($user->keys, true) as $key)
                                                                <div class="dropdown-item">
                                                                    <div class="key-content">
                                                                        <div class="key-text">
                                                                            {{ substr($key, 0, 35) }}...
                                                                        </div>
                                                                        <button class="btn btn-sm btn-link ml-2"
                                                                                data-clipboard-text="{{ $key }}"
                                                                                title="Копировать">
                                                                            <i class="fas fa-copy"></i>
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
                                            <td>{{ $user->used_at ? $user->used_at->format('d.m.Y H:i') : 'Не использован' }}</td>
                                            <td>
                                                <div class="dropdown">
                                                    <button class="btn btn-link" type="button" data-toggle="dropdown">
                                                        <i class="fas fa-ellipsis-v"></i>
                                                    </button>
                                                    <div class="dropdown-menu dropdown-menu-right">
                                                        <a href="{{ route('admin.module.server-users.show', $user) }}" 
                                                           class="dropdown-item">
                                                            <i class="fas fa-eye"></i> Просмотр
                                                        </a>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        {{ $serverUsers->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('css')
<style>
.keys-dropdown {
    position: relative;
    display: inline-block;
}
.key-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.key-text {
    flex: 1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.keys-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1000;
}
.keys-dropdown .dropdown-menu {
    max-width: 400px;
}
.keys-dropdown .dropdown-item {
    white-space: normal;
    word-break: break-all;
}
</style>
@endpush

@push('js')
<script src="https://cdnjs.cloudflare.com/ajax/libs/clipboard.js/2.0.8/clipboard.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    new ClipboardJS('.btn[data-clipboard-text]').on('success', function(e) {
        e.clearSelection();
        alert('Ключ скопирован в буфер обмена');
    });
});
</script>
@endpush

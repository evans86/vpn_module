@extends('layouts.app', ['page' => __('Пользователи сервера'), 'pageSlug' => 'server_users'])

@section('content')
    <div class="container-fluid">
        <div class="keys-overlay"></div>
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
                                                <a href="{{ route('admin.module.server-users.show', $user) }}" class="text-primary">
                                                    {{ $user->id }}
                                                </a>
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
                                            <td>
                                                {{ number_format($user->used_traffic / (1024*1024*1024), 2) }} GB
                                            </td>
                                            <td>
                                                @if($user->keyActivateUser && $user->keyActivateUser->keyActivate)
                                                    <a href="{{ route('admin.module.key-activate.index', ['id' => $user->keyActivateUser->key_activate_id]) }}"
                                                       class="btn btn-sm btn-primary">
                                                        <i class="fas fa-key"></i> Ключ активации
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

@push('css')
<style>
.keys-dropdown {
    position: relative;
    display: inline-block;
}

.keys-dropdown .dropdown-menu {
    position: absolute !important;
    transform: none !important;
    top: 100% !important;
    left: 155px !important;
    width: 350px;
    max-height: 400px;
    overflow-y: auto;
    padding: 8px;
    background: white;
    box-shadow: 0 3px 12px rgba(0,0,0,0.15);
    border: 1px solid rgba(0,0,0,0.1);
    border-radius: 6px;
    z-index: 9999;
    margin-top: 5px;
}

.keys-dropdown .dropdown-item {
    padding: 10px;
    margin-bottom: 4px;
    border-radius: 4px;
    background: #f8f9fa;
    border: 1px solid #e9ecef;
}

.keys-dropdown .dropdown-item:last-child {
    margin-bottom: 0;
}

.keys-dropdown .dropdown-item:hover {
    background: #e9ecef;
}

.keys-dropdown .key-content {
    display: flex;
    align-items: center;
    gap: 10px;
}

.keys-dropdown .key-text {
    flex: 1;
    font-family: 'Consolas', monospace;
    font-size: 13px;
    line-height: 1.4;
    color: #495057;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.btn-link {
    padding: 0 5px;
}

.btn-link:hover {
    text-decoration: none;
}

.keys-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: transparent;
    z-index: 1000;
    display: none;
}

.keys-overlay.show {
    display: block;
}

.keys-overlay.show ~ .table tr:hover {
    background: inherit !important;
}

.keys-overlay.show ~ .table tr:hover td {
    background: inherit !important;
}
</style>
@endpush

@push('js')
<script src="https://cdnjs.cloudflare.com/ajax/libs/clipboard.js/2.0.8/clipboard.min.js"></script>
<script>
$(document).ready(function () {
    const overlay = document.querySelector('.keys-overlay');

    // Показываем оверлей при открытии дропдауна
    $('.keys-dropdown').on('show.bs.dropdown', function () {
        overlay.classList.add('show');
    });

    // Скрываем оверлей при закрытии дропдауна
    $('.keys-dropdown').on('hide.bs.dropdown', function () {
        overlay.classList.remove('show');
    });

    // Закрываем дропдаун при клике на оверлей
    overlay.addEventListener('click', function() {
        $('.keys-dropdown .dropdown-menu').dropdown('hide');
    });

    // Инициализация clipboard.js
    var clipboard = new ClipboardJS('[data-clipboard-text]');

    clipboard.on('success', function (e) {
        toastr.success('Скопировано в буфер обмена');
        e.clearSelection();
    });

    clipboard.on('error', function (e) {
        toastr.error('Ошибка копирования');
    });

    // Предотвращаем закрытие dropdown при клике внутри
    $('.dropdown-menu').on('click', function(e) {
        e.stopPropagation();
    });

    // Проверяем позицию dropdown после открытия
    $('.keys-dropdown').on('shown.bs.dropdown', function () {
        const dropdown = $(this).find('.dropdown-menu');
        const rect = dropdown[0].getBoundingClientRect();

        if (rect.right > window.innerWidth) {
            dropdown.css('left', 'auto');
            dropdown.css('right', '0');
        }

        if (rect.bottom > window.innerHeight) {
            dropdown.css('top', 'auto');
            dropdown.css('bottom', '100%');
            dropdown.css('margin-top', '0');
            dropdown.css('margin-bottom', '5px');
        }
    });
});
</script>
@endpush
@endsection

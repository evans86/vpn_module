@extends('layouts.admin')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="mb-6">
        <a href="{{ route('admin.server-users.index') }}" class="text-blue-600 hover:text-blue-800">
            ← Назад к списку
        </a>
    </div>

    <div class="bg-white rounded-lg shadow-lg overflow-hidden">
        <div class="px-6 py-4 border-b">
            <h1 class="text-2xl font-bold">Пользователь сервера #{{ $serverUser->id }}</h1>
        </div>

        <div class="p-6 space-y-6">
            <!-- Основная информация -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h2 class="text-lg font-semibold mb-4">Основная информация</h2>
                    <dl class="space-y-2">
                        <div class="flex justify-between">
                            <dt class="text-gray-600">Статус:</dt>
                            <dd>
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    {{ $serverUser->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $serverUser->status }}
                                </span>
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-600">Использовано трафика:</dt>
                            <dd>{{ number_format($serverUser->used_traffic / (1024*1024*1024), 2) }} GB</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-600">Создан:</dt>
                            <dd>{{ $serverUser->created_at->format('d.m.Y H:i') }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-600">Обновлен:</dt>
                            <dd>{{ $serverUser->updated_at->format('d.m.Y H:i') }}</dd>
                        </div>
                    </dl>
                </div>

                <div>
                    <h2 class="text-lg font-semibold mb-4">Связанная информация</h2>
                    <dl class="space-y-2">
                        <div class="flex justify-between">
                            <dt class="text-gray-600">Сервер:</dt>
                            <dd>
                                <a href="{{ route('admin.module.server.show', $serverUser->server) }}" 
                                   class="text-blue-600 hover:text-blue-800">
                                    {{ $serverUser->server->name }}
                                </a>
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-600">Панель:</dt>
                            <dd>
                                <a href="{{ route('admin.module.panel.show', $serverUser->server->panel) }}" 
                                   class="text-blue-600 hover:text-blue-800">
                                    {{ $serverUser->server->panel->name }}
                                </a>
                            </dd>
                        </div>
                        @if($serverUser->keyActivateUser)
                        <div class="flex justify-between">
                            <dt class="text-gray-600">Активированный ключ:</dt>
                            <dd>
                                <a href="{{ route('admin.key-activates.show', $serverUser->keyActivateUser->keyActivate) }}" 
                                   class="text-blue-600 hover:text-blue-800">
                                    Перейти к ключу
                                </a>
                            </dd>
                        </div>
                        @endif
                    </dl>
                </div>
            </div>

            <!-- Ключи подключения -->
            @if($serverUser->keys)
            <div>
                <h2 class="text-lg font-semibold mb-4">Ключи подключения</h2>
                <div class="bg-gray-50 rounded-lg p-4 space-y-2">
                    @foreach(json_decode($serverUser->keys, true) as $key)
                        <div class="flex items-center justify-between space-x-4 p-2 bg-white rounded shadow-sm">
                            <div class="truncate flex-grow font-mono text-sm">{{ $key }}</div>
                            <button onclick="copyToClipboard('{{ $key }}')" 
                                    class="px-3 py-1 bg-blue-100 text-blue-700 rounded hover:bg-blue-200 transition-colors">
                                Копировать
                            </button>
                        </div>
                    @endforeach
                </div>
            </div>
            @endif

            <!-- Дополнительные действия -->
            <div class="border-t pt-6">
                <h2 class="text-lg font-semibold mb-4">Действия</h2>
                <div class="flex space-x-4">
                    <button type="button" 
                            class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                            onclick="showTransferModal()">
                        Перенести пользователя
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно для переноса пользователя (будет реализовано позже) -->
<div x-data="{ open: false }" x-show="open" class="hidden fixed z-10 inset-0 overflow-y-auto" id="transferModal">
    <!-- Содержимое модального окна будет добавлено позже -->
</div>

@push('scripts')
<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        // Можно добавить уведомление об успешном копировании
    });
}

function showTransferModal() {
    // Будет реализовано позже
    alert('Функционал переноса пользователя будет доступен в следующем обновлении');
}
</script>
@endpush
@endsection

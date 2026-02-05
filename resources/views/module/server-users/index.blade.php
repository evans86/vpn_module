@extends('layouts.admin')

@section('title', 'Пользователи сервера')
@section('page-title', 'Пользователи сервера' . ($panel ? ' - Панель: ' . $panel->panel_adress : ''))

@section('content')
    <div class="space-y-6">
        <x-admin.card title="Пользователи сервера">
            <!-- Filters -->
            <x-admin.filter-form action="{{ route('admin.module.server-users.index') }}">
                <x-admin.filter-input 
                    name="id" 
                    label="ID" 
                    value="{{ request('id') }}" 
                    placeholder="Поиск по ID" />
                
                <x-admin.filter-input 
                    name="server" 
                    label="Сервер" 
                    value="{{ request('server') }}" 
                    placeholder="Поиск по имени или IP" />
                
                <x-admin.filter-input 
                    name="panel" 
                    label="Панель" 
                    value="{{ request('panel') }}" 
                    placeholder="Поиск по адресу" />
            </x-admin.filter-form>

            <!-- Table -->
            @if($serverUsers->isEmpty())
                <x-admin.empty-state 
                    icon="fa-users" 
                    title="Пользователи не найдены"
                    description="Попробуйте изменить параметры фильтрации" />
            @else
                <x-admin.table :headers="['ID', 'Сервер', 'Панель', 'Telegram ID', 'Дата создания']">
                    @foreach($serverUsers as $user)
                        <tr class="hover:bg-gray-50">
                            <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-xs sm:text-sm">
                                <div class="flex items-center">
                                    <span class="font-mono text-xs">{{ substr($user->id, 0, 8) }}...</span>
                                    <button class="ml-2 text-gray-400 hover:text-gray-600"
                                            data-clipboard-text="{{ $user->id }}"
                                            title="Копировать ID">
                                        <i class="fas fa-copy text-xs"></i>
                                    </button>
                                </div>
                            </td>
                            <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-xs sm:text-sm">
                                @if($user->panel && $user->panel->server)
                                    <a href="{{ route('admin.module.server.index', ['server_id' => $user->panel->server->id]) }}"
                                       class="text-indigo-600 hover:text-indigo-800">
                                        {{ Str::limit($user->panel->server->name, 20) }}
                                    </a>
                                @else
                                    <span class="text-gray-400">Нет сервера</span>
                                @endif
                            </td>
                            <td class="px-3 sm:px-6 py-4 text-xs sm:text-sm">
                                @if($user->panel)
                                    <a href="{{ route('admin.module.panel.index', ['panel_id' => $user->panel->id]) }}"
                                       class="text-indigo-600 hover:text-indigo-800"
                                       title="{{ $user->panel->panel_adress }}">
                                        <span class="hidden sm:inline">{{ Str::limit($user->panel->panel_adress, 30) }}</span>
                                        <span class="sm:hidden">{{ Str::limit($user->panel->panel_adress, 15) }}</span>
                                    </a>
                                @else
                                    <span class="text-gray-400">Нет панели</span>
                                @endif
                            </td>
                            <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-xs sm:text-sm">
                                @if($user->telegram_id)
                                    <a href="https://t.me/{{ $user->telegram_id }}" 
                                       target="_blank"
                                       class="text-indigo-600 hover:text-indigo-800">
                                        <span class="hidden sm:inline">{{ $user->telegram_id }}</span>
                                        <span class="sm:hidden">{{ Str::limit((string)$user->telegram_id, 8) }}</span>
                                    </a>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-xs sm:text-sm text-gray-500">
                                <span class="hidden sm:inline">{{ $user->created_at->format('d.m.Y H:i') }}</span>
                                <span class="sm:hidden">{{ $user->created_at->format('d.m.Y') }}</span>
                            </td>
                        </tr>
                    @endforeach
                </x-admin.table>

                <!-- Pagination -->
                <x-admin.pagination-wrapper :paginator="$serverUsers" />
            @endif
        </x-admin.card>
    </div>
@endsection

@push('js')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/clipboard.js/2.0.8/clipboard.min.js"></script>
    <script>
        $(document).ready(function() {
            var clipboard = new ClipboardJS('[data-clipboard-text]');
            clipboard.on('success', function(e) {
                toastr.success('Скопировано в буфер обмена');
                e.clearSelection();
            });
            clipboard.on('error', function(e) {
                toastr.error('Ошибка копирования');
            });
        });
    </script>
@endpush

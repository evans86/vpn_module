@extends('layouts.admin')

@section('title', 'Панели управления')
@section('page-title', 'Панели управления')

@php
    use App\Models\Panel\Panel;
@endphp

@section('content')
    <div class="space-y-6">
        <x-admin.card title="Список панелей">
            <x-slot name="tools">
                <button type="button" 
                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                        onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: { id: 'createPanelModal' } }))">
                    <i class="fas fa-plus mr-2"></i>
                    Добавить панель
                </button>
            </x-slot>

            <!-- Filters -->
            <x-admin.filter-form action="{{ route('admin.module.panel.index') }}">
                <x-admin.filter-input 
                    name="server" 
                    label="Сервер" 
                    value="{{ request('server') }}" 
                    placeholder="Поиск по имени или IP сервера" />
                
                <x-admin.filter-input 
                    name="panel_adress" 
                    label="Адрес панели" 
                    value="{{ request('panel_adress') }}" 
                    placeholder="Поиск по адресу панели" />
            </x-admin.filter-form>

            <!-- Table -->
            @if($panels->isEmpty())
                <x-admin.empty-state 
                    icon="fa-desktop" 
                    title="Панели не найдены"
                    description="Попробуйте изменить параметры фильтрации или создать новую панель">
                    <x-slot name="action">
                        <button type="button" 
                                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: { id: 'createPanelModal' } }))">
                            <i class="fas fa-plus mr-2"></i>
                            Добавить панель
                        </button>
                    </x-slot>
                </x-admin.empty-state>
            @else
                <x-admin.table :headers="['#', 'Адрес панели', 'Тип', 'Логин', 'Пароль', 'Сервер', 'Статус', 'Действия']">
                    @php
                        $totalPanels = $panels->count();
                        $currentIndex = 0;
                    @endphp
                    @foreach($panels as $panel)
                        @php
                            $currentIndex++;
                            // Если записей 3 или меньше, все меню открываются сверху
                            // Если записей больше 3, последние 3 открываются сверху
                            $isLastRows = $totalPanels <= 3 || $currentIndex > ($totalPanels - 3);
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                {{ $panel->id }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <a href="{{ $panel->panel_adress }}" target="_blank" class="text-indigo-600 hover:text-indigo-800 flex items-center">
                                    {{ $panel->formatted_address }}
                                    <i class="fas fa-external-link-alt ml-1 text-xs"></i>
                                </a>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $panel->panel_type_label }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <div class="flex items-center">
                                    <span class="font-mono text-xs">{{ $panel->panel_login }}</span>
                                    <button class="ml-2 text-gray-400 hover:text-gray-600"
                                            data-clipboard-text="{{ $panel->panel_login }}"
                                            title="Копировать логин">
                                        <i class="fas fa-copy text-xs"></i>
                                    </button>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <div class="flex items-center">
                                    <span class="font-mono text-xs">{{ $panel->panel_password }}</span>
                                    <button class="ml-2 text-gray-400 hover:text-gray-600"
                                            data-clipboard-text="{{ $panel->panel_password }}"
                                            title="Копировать пароль">
                                        <i class="fas fa-copy text-xs"></i>
                                    </button>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                @if($panel->server && isset($panel->server->id))
                                    <a href="{{ route('admin.module.server.index', ['id' => $panel->server_id]) }}"
                                       class="text-indigo-600 hover:text-indigo-800"
                                       title="Перейти к серверу">
                                        {{ $panel->server->name ?? 'N/A' }}
                                        @if(isset($panel->server->host) && $panel->server->host)
                                            <div class="text-xs text-gray-500 mt-1">
                                                {{ $panel->server->host }}
                                            </div>
                                        @endif
                                    </a>
                                @else
                                    <span class="text-red-600">Сервер удален</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    {{ $panel->status_badge_class === 'success' ? 'bg-green-100 text-green-800' : '' }}
                                    {{ $panel->status_badge_class === 'danger' ? 'bg-red-100 text-red-800' : '' }}
                                    {{ $panel->status_badge_class === 'warning' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                    {{ $panel->status_badge_class === 'info' ? 'bg-blue-100 text-blue-800' : '' }}
                                    {{ $panel->status_badge_class === 'secondary' ? 'bg-gray-100 text-gray-800' : '' }}">
                                    {{ $panel->status_label }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="relative inline-block text-left" x-data="{ open: false }">
                                    <button @click="open = !open" 
                                            class="text-gray-400 hover:text-gray-600 focus:outline-none">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    
                                    <div x-show="open" 
                                         @click.away="open = false"
                                         x-cloak
                                         x-transition
                                         class="absolute right-0 w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50 {{ $isLastRows ? 'origin-bottom-right bottom-full mb-2' : 'origin-top-right top-full mt-2' }}">
                                        <div class="py-1">
                                            <a href="{{ route('admin.module.server-users.index', ['panel_id' => $panel->id]) }}"
                                               class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                <i class="fas fa-users mr-2"></i> Пользователи
                                            </a>
                                            <form action="{{ route('admin.module.panel.update-config', $panel) }}" method="POST" class="block">
                                                @csrf
                                                <button type="submit" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                    <i class="fas fa-sync mr-2"></i> Обновить конфигурацию
                                                </button>
                                            </form>
                                            @if($panel->panel_status === Panel::PANEL_CONFIGURED)
                                                <a href="{{ route('admin.module.server-monitoring.index', ['panel_id' => $panel->id]) }}"
                                                   class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                    <i class="fas fa-chart-line mr-2"></i> Статистика
                                                </a>
                                            @endif
                                            <button type="button" 
                                                    onclick="deletePanel({{ $panel->id }})"
                                                    class="block w-full text-left px-4 py-2 text-sm text-red-700 hover:bg-red-50">
                                                <i class="fas fa-trash mr-2"></i> Удалить
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </x-admin.table>

                <!-- Pagination -->
                <x-admin.pagination-wrapper :paginator="$panels" />
            @endif
        </x-admin.card>
    </div>

    <!-- Modal: Create Panel -->
    <x-admin.modal id="createPanelModal" title="Добавить панель">
        <form id="createPanelForm" action="{{ route('admin.module.panel.store') }}" method="POST">
            @csrf
            <div class="mb-4">
                <label for="server_id" class="block text-sm font-medium text-gray-700 mb-1">
                    Выберите сервер
                </label>
                <select id="server_id" name="server_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 sm:text-sm" required>
                    <option value="">Выберите сервер...</option>
                    @foreach($servers as $serverId => $serverName)
                        <option value="{{ $serverId }}">{{ $serverName }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-sm text-gray-500">Будет создана панель Marzban на выбранном сервере</p>
            </div>
        </form>
        <x-slot name="footer">
            <button type="submit" form="createPanelForm" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                <i class="fas fa-plus mr-2"></i> Создать
            </button>
            <button type="button" 
                    class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" 
                    onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: { id: 'createPanelModal' } }))">
                Отмена
            </button>
        </x-slot>
    </x-admin.modal>
@endsection

@push('js')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/clipboard.js/2.0.8/clipboard.min.js"></script>
    <script>
        $(document).ready(function () {
            var clipboard = new ClipboardJS('[data-clipboard-text]');

            clipboard.on('success', function (e) {
                toastr.success('Скопировано в буфер обмена');
                e.clearSelection();
            });

            clipboard.on('error', function (e) {
                toastr.error('Ошибка копирования');
            });

            function deletePanel(id) {
                if (confirm('Вы уверены, что хотите удалить эту панель?')) {
                    $.ajax({
                        url: '{{ route('admin.module.panel.destroy', ['panel' => ':id']) }}'.replace(':id', id),
                        method: 'DELETE',
                        data: {
                            _token: '{{ csrf_token() }}'
                        },
                        success: function (response) {
                            toastr.success('Панель успешно удалена');
                            setTimeout(() => {
                                window.location.reload();
                            }, 1000);
                        },
                        error: function (xhr) {
                            let errorMessage = 'Произошла ошибка при удалении панели';
                            if (xhr.responseJSON) {
                                errorMessage = xhr.responseJSON.message || errorMessage;
                            }
                            toastr.error(errorMessage);
                        }
                    });
                }
            }
            window.deletePanel = deletePanel;
        });
    </script>
@endpush

@extends('layouts.admin')

@section('title', 'Продавцы')
@section('page-title', 'Управление продавцами')

@section('content')
    <div class="space-y-6">
        <x-admin.card title="Список продавцов">
            <!-- Filters -->
            <x-admin.filter-form action="{{ route('admin.module.salesman.index') }}">
                <x-admin.filter-input 
                    name="id" 
                    label="ID" 
                    value="{{ request('id') }}" 
                    placeholder="Введите ID"
                    type="number" />
                
                <x-admin.filter-input 
                    name="telegram_id" 
                    label="Telegram ID" 
                    value="{{ request('telegram_id') }}" 
                    placeholder="Введите Telegram ID"
                    type="number" />
                
                <x-admin.filter-input 
                    name="username" 
                    label="Username" 
                    value="{{ request('username') }}" 
                    placeholder="Введите username" />
                
                <x-admin.filter-input 
                    name="bot_link" 
                    label="Ссылка на бота" 
                    value="{{ request('bot_link') }}" 
                    placeholder="Введите ссылку на бота" />
            </x-admin.filter-form>

            <!-- Table -->
            @if($salesmen->isEmpty())
                <x-admin.empty-state 
                    icon="fa-user-tie" 
                    title="Продавцы не найдены"
                    description="Попробуйте изменить параметры фильтрации" />
            @else
                <x-admin.table :headers="['#', 'Telegram ID', 'Username', 'Токен', 'Ссылка на бота', 'Статус', 'Действия']">
                    @foreach($salesmen as $salesman)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                {{ $salesman->id }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $salesman->telegram_id }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $salesman->username ?? 'N/A' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <div class="flex items-center">
                                    <span class="font-mono text-xs">{{ Str::limit($salesman->token, 20) }}</span>
                                    <button class="ml-2 text-gray-400 hover:text-gray-600"
                                            data-clipboard-text="{{ $salesman->token }}"
                                            title="Копировать токен">
                                        <i class="fas fa-copy text-xs"></i>
                                    </button>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                @if($salesman->bot_link)
                                    <a href="{{ $salesman->bot_link }}" 
                                       target="_blank" 
                                       class="text-indigo-600 hover:text-indigo-800">
                                        {{ Str::limit($salesman->bot_link, 30) }}
                                        <i class="fas fa-external-link-alt ml-1 text-xs"></i>
                                    </a>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <button class="btn btn-sm status-toggle {{ $salesman->status ? 'btn-success' : 'btn-danger' }}"
                                        data-id="{{ $salesman->id }}">
                                    {{ $salesman->status ? 'Активен' : 'Неактивен' }}
                                </button>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <a href="{{ route('admin.module.salesman.show', $salesman->id) }}"
                                   class="btn btn-sm btn-primary"
                                   title="Подробная информация">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </x-admin.table>

                <!-- Pagination -->
                <x-admin.pagination-wrapper :paginator="$salesmen" />
            @endif
        </x-admin.card>
    </div>
@endsection

@push('js')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/clipboard.js/2.0.8/clipboard.min.js"></script>
    <script>
        $(document).ready(function () {
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });

            // Обработчик клика по кнопке статуса
            $('.status-toggle').on('click', function () {
                const id = $(this).data('id');
                const btn = $(this);
                
                $.ajax({
                    url: `/admin/module/salesman/${id}/toggle-status`,
                    type: 'POST',
                    success: function (response) {
                        if (response.success) {
                            if (response.status) {
                                btn.removeClass('btn-danger').addClass('btn-success');
                                btn.text('Активен');
                            } else {
                                btn.removeClass('btn-success').addClass('btn-danger');
                                btn.text('Неактивен');
                            }
                            toastr.success(response.message);
                        } else {
                            toastr.error(response.message);
                        }
                    },
                    error: function (xhr) {
                        toastr.error('Произошла ошибка при изменении статуса');
                    }
                });
            });

            // Инициализация ClipboardJS
            var clipboard = new ClipboardJS('[data-clipboard-text]');

            clipboard.on('success', function (e) {
                toastr.success('Скопировано в буфер обмена');
                e.clearSelection();
            });

            clipboard.on('error', function (e) {
                toastr.error('Ошибка копирования');
            });
        });
    </script>
@endpush

@extends('layouts.admin')

@section('title', 'Пользователи Telegram')
@section('page-title', 'Список пользователей')

@section('content')
    <div class="space-y-6">
        <x-admin.card title="Список пользователей">
            <!-- Filters -->
            <x-admin.filter-form action="{{ route('admin.module.telegram-users.index') }}">
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
            </x-admin.filter-form>

            <!-- Table -->
            @if($users->isEmpty())
                <x-admin.empty-state 
                    icon="fa-user-friends" 
                    title="Пользователи не найдены"
                    description="Попробуйте изменить параметры фильтрации" />
            @else
                <x-admin.table :headers="['#', 'Telegram ID', 'Имя пользователя', 'Продавец', 'Статус']">
                    @foreach($users as $user)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                {{ $user->id }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $user->telegram_id }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $user->username ?? 'N/A' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $user->salesman->bot_link ?? 'Нет' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <button class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md shadow-sm text-white status-toggle {{ $user->status ? 'bg-green-600 hover:bg-green-700' : 'bg-red-600 hover:bg-red-700' }} focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                        data-id="{{ $user->id }}">
                                    {{ $user->status ? 'Активен' : 'Неактивен' }}
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </x-admin.table>

                <!-- Pagination -->
                <x-admin.pagination-wrapper :paginator="$users" />
            @endif
        </x-admin.card>
    </div>
@endsection

@push('js')
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
                    url: `/admin/module/telegram-users/${id}/toggle-status`,
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
        });
    </script>
@endpush

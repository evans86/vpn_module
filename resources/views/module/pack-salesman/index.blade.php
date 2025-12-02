@extends('layouts.admin')

@section('title', 'Пакеты продавцов')
@section('page-title', 'Купленные пакеты')

@section('content')
    <div class="space-y-6">
        <x-admin.card title="Купленные пакеты">
            <!-- Filters -->
            <x-admin.filter-form action="{{ route('admin.module.pack-salesman.index') }}">
                <x-admin.filter-input 
                    name="salesman_search" 
                    label="Продавец" 
                    value="{{ request('salesman_search') }}" 
                    placeholder="Поиск по ID или имени" />
                
                <x-admin.filter-select 
                    name="status" 
                    label="Статус"
                    :options="['paid' => 'Оплачен', 'pending' => 'Ожидает оплаты']"
                    value="{{ request('status') }}" />
                
                <x-admin.filter-input 
                    name="created_at" 
                    label="Дата создания" 
                    value="{{ request('created_at') }}" 
                    type="date" />
            </x-admin.filter-form>

            <!-- Table -->
            @if($pack_salesmans->isEmpty())
                <x-admin.empty-state 
                    icon="fa-file-invoice" 
                    title="Пакеты не найдены"
                    description="Попробуйте изменить параметры фильтрации" />
            @else
                <x-admin.table :headers="['#', 'Ключи', 'Продавец', 'Цена', 'Статус', 'Дата создания', 'Действия']">
                    @foreach($pack_salesmans as $pack_salesman)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                {{ $pack_salesman->id }}
                            </td>
                            <td class="px-6 py-4 text-sm">
                                @if($pack_salesman->pack)
                                    <a href="{{ route('admin.module.pack.index', ['title' => $pack_salesman->pack->title]) }}"
                                       class="text-indigo-600 hover:text-indigo-800"
                                       title="Просмотреть пакет">
                                        {{ $pack_salesman->pack->title }}
                                    </a>
                                    <div class="text-xs text-gray-500 mt-1">
                                        <a href="{{ route('admin.module.key-activate.index', ['pack_salesman_id' => $pack_salesman->id]) }}"
                                           class="text-indigo-600 hover:text-indigo-800"
                                           title="Просмотреть ключи пакета">
                                            Ключи пакета: {{ $pack_salesman->pack->count }}
                                        </a>
                                    </div>
                                @else
                                    <span class="text-red-600">Пакет удален</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm">
                                @if($pack_salesman->salesman)
                                    <a href="{{ route('admin.module.salesman.index', ['username' => $pack_salesman->salesman->username]) }}"
                                       class="text-indigo-600 hover:text-indigo-800"
                                       title="Просмотреть продавца">
                                        {{ $pack_salesman->salesman->username }}
                                    </a>
                                    <div class="text-xs text-gray-500 mt-1">
                                        <a href="/admin/module/salesman?telegram_id={{ $pack_salesman->salesman->telegram_id }}"
                                           class="text-indigo-600 hover:text-indigo-800">
                                            ID: {{ $pack_salesman->salesman->telegram_id }}
                                        </a>
                                    </div>
                                @else
                                    <span class="text-red-600">Продавец удален</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                @if($pack_salesman->pack)
                                    {{ number_format($pack_salesman->pack->price, 2) }} ₽
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    {{ $pack_salesman->getStatusBadgeClass() === 'success' ? 'bg-green-100 text-green-800' : '' }}
                                    {{ $pack_salesman->getStatusBadgeClass() === 'warning' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                    {{ $pack_salesman->getStatusBadgeClass() === 'danger' ? 'bg-red-100 text-red-800' : '' }}
                                    {{ $pack_salesman->getStatusBadgeClass() === 'info' ? 'bg-blue-100 text-blue-800' : '' }}
                                    {{ $pack_salesman->getStatusBadgeClass() === 'secondary' ? 'bg-gray-100 text-gray-800' : '' }}">
                                    {{ $pack_salesman->getStatusText() }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $pack_salesman->created_at->format('d.m.Y H:i') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                @if($pack_salesman->isPaid())
                                    <span class="text-gray-400">—</span>
                                @else
                                    <button type="button"
                                            class="btn btn-sm btn-success"
                                            onclick="markAsPaid({{ $pack_salesman->id }})">
                                        Отметить оплаченным
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </x-admin.table>

                <!-- Pagination -->
                <x-admin.pagination-wrapper :paginator="$pack_salesmans" />
            @endif
        </x-admin.card>
    </div>
@endsection

@push('js')
    <script>
        function markAsPaid(id) {
            if (confirm('Вы уверены, что хотите отметить пакет как оплаченный?')) {
                $.ajax({
                    url: `/admin/module/pack-salesman/${id}/mark-as-paid`,
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function (response) {
                        if (response.success) {
                            toastr.success('Пакет отмечен как оплаченный');
                            setTimeout(() => {
                                window.location.reload();
                            }, 1000);
                        } else {
                            toastr.error(response.message || 'Произошла ошибка');
                        }
                    },
                    error: function (xhr) {
                        let errorMessage = 'Произошла ошибка при обновлении статуса';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMessage = xhr.responseJSON.message;
                        }
                        toastr.error(errorMessage);
                    }
                });
            }
        }
    </script>
@endpush

@extends('layouts.admin')

@section('title', 'Пакеты')
@section('page-title', 'Управление пакетами')

@section('content')
    <div class="space-y-6">
        <x-admin.card title="Список пакетов">
            <x-slot name="tools">
                <button type="button" 
                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                        onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: { id: 'createPackModal' } }))">
                    <i class="fas fa-plus mr-2"></i>
                    Добавить пакет
                </button>
            </x-slot>

            <!-- Filters -->
            <x-admin.filter-form action="{{ route('admin.module.pack.index') }}">
                <x-admin.filter-input 
                    name="id" 
                    label="ID пакета" 
                    value="{{ request('id') }}" 
                    placeholder="Введите ID пакета"
                    type="number" />
                
                <x-admin.filter-input 
                    name="title" 
                    label="Название" 
                    value="{{ request('title') }}" 
                    placeholder="Введите название пакета" />
                
                <x-admin.filter-select 
                    name="status" 
                    label="Статус"
                    :options="['1' => 'Активен', '0' => 'Неактивен']"
                    value="{{ request('status') }}" />
            </x-admin.filter-form>

            <!-- Table -->
            @if($packs->isEmpty())
                <x-admin.empty-state 
                    icon="fa-box" 
                    title="Пакеты не найдены"
                    description="Попробуйте изменить параметры фильтрации или создать новый пакет">
                    <x-slot name="action">
                        <button type="button" 
                                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: { id: 'createPackModal' } }))">
                            <i class="fas fa-plus mr-2"></i>
                            Добавить пакет
                        </button>
                    </x-slot>
                </x-admin.empty-state>
            @else
                <x-admin.table :headers="['ID', 'Название', 'Тип', 'Цена', 'Период', 'Трафик', 'Ключи', 'Статус', 'Действия']">
                    @foreach($packs as $pack)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                {{ $pack->id }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $pack->title }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $pack->module_key ? 'bg-yellow-100 text-yellow-800' : 'bg-blue-100 text-blue-800' }}">
                                    {{ $pack->module_key ? 'Модуль' : 'Бот' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $pack->price }} ₽
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $pack->period }} дней
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ number_format($pack->traffic_limit / (1024*1024*1024), 1) }} GB
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $pack->count }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $pack->status ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $pack->status ? 'Активен' : 'Неактивен' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <form action="{{ route('admin.module.pack.destroy', $pack) }}" 
                                      method="POST" 
                                      class="inline"
                                      onsubmit="return confirm('Вы уверены, что хотите удалить этот пакет?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-800">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </x-admin.table>

                <!-- Pagination -->
                <x-admin.pagination-wrapper :paginator="$packs" />
            @endif
        </x-admin.card>
    </div>

    <!-- Modal: Create Pack -->
    <x-admin.modal id="createPackModal" title="Добавить пакет" size="lg">
        <form id="createPackForm" action="{{ route('admin.module.pack.store') }}" method="POST">
            @csrf
            <div class="space-y-4">
                <div>
                    <label for="price" class="block text-sm font-medium text-gray-700 mb-1">
                        Цена (₽)
                    </label>
                    <input type="number" 
                           id="price" 
                           name="price" 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 sm:text-sm" 
                           required 
                           min="0"
                           value="0">
                </div>
                
                <div>
                    <label for="title" class="block text-sm font-medium text-gray-700 mb-1">
                        Название
                    </label>
                    <input type="text" 
                           id="title" 
                           name="title" 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 sm:text-sm" 
                           required>
                </div>
                
                <div>
                    <label for="module_key" class="block text-sm font-medium text-gray-700 mb-1">
                        Тип пакета
                    </label>
                    <select id="module_key" name="module_key" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 sm:text-sm" required>
                        <option value="0">Для бота</option>
                        <option value="1">Для модуля</option>
                    </select>
                </div>
                
                <div>
                    <label for="period" class="block text-sm font-medium text-gray-700 mb-1">
                        Период действия (дней)
                    </label>
                    <input type="number" 
                           id="period" 
                           name="period" 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 sm:text-sm" 
                           required 
                           min="1" 
                           value="30">
                </div>
                
                <div>
                    <label for="traffic_limit" class="block text-sm font-medium text-gray-700 mb-1">
                        Лимит трафика (GB)
                    </label>
                    <input type="number" 
                           id="traffic_limit" 
                           name="traffic_limit" 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 sm:text-sm" 
                           required 
                           min="1" 
                           value="10">
                </div>
                
                <div>
                    <label for="count" class="block text-sm font-medium text-gray-700 mb-1">
                        Количество ключей
                    </label>
                    <input type="number" 
                           id="count" 
                           name="count" 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 sm:text-sm" 
                           required 
                           min="1" 
                           value="5">
                </div>
            </div>
        </form>
        <x-slot name="footer">
            <button type="submit" form="createPackForm" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                Создать
            </button>
            <button type="button" 
                    class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" 
                    onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: { id: 'createPackModal' } }))">
                Отмена
            </button>
        </x-slot>
    </x-admin.modal>
@endsection

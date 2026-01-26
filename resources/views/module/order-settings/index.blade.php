@extends('layouts.admin')

@section('title', 'Настройки системы заказов')
@section('page-title', 'Настройки системы заказов')

@section('content')
    <div class="space-y-6">
        <x-admin.card title="Основные настройки">
            <form action="{{ route('admin.module.order-settings.update') }}" method="POST">
                @csrf

                <div class="space-y-6">
                    <!-- System Enable/Disable -->
                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                        <div>
                            <h3 class="text-sm font-medium text-gray-900">Система заказов</h3>
                            <p class="text-sm text-gray-500 mt-1">Включить/выключить систему покупки пакетов через бота</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" 
                                   name="system_enabled" 
                                   value="1"
                                   {{ $systemEnabled ? 'checked' : '' }}
                                   class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                        </label>
                    </div>

                    <!-- Notification Telegram ID -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Telegram ID для уведомлений о новых заказах
                        </label>
                        <input type="text" 
                               name="notification_telegram_id" 
                               value="{{ $notificationTelegramId }}"
                               placeholder="Введите Telegram ID администратора"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        <p class="mt-1 text-sm text-gray-500">Оставьте пустым, если не хотите получать уведомления</p>
                    </div>

                    <!-- Available Packs -->
                    <div>
                        <h3 class="text-sm font-medium text-gray-900 mb-4">Доступные пакеты для заказа</h3>
                        <p class="text-sm text-gray-500 mb-4">Выберите, какие пакеты будут отображаться продавцам для покупки</p>

                        @if($packs->isEmpty())
                            <div class="text-center py-8 text-gray-500">
                                Нет активных пакетов. Создайте пакеты в разделе <a href="{{ route('admin.module.pack.index') }}" class="text-indigo-600 hover:text-indigo-800">Пакеты</a>.
                            </div>
                        @else
                            <div class="space-y-3">
                                @foreach($packs as $pack)
                                    @php
                                        $setting = $packSettings->get($pack->id);
                                        $isAvailable = $setting ? $setting->is_available : false;
                                        $sortOrder = $setting ? $setting->sort_order : 0;
                                    @endphp
                                    <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg hover:bg-gray-50">
                                        <div class="flex-1">
                                            <div class="flex items-center gap-3">
                                                <label class="relative inline-flex items-center cursor-pointer">
                                                    <input type="checkbox" 
                                                           name="pack_availability[{{ $pack->id }}]" 
                                                           value="1"
                                                           {{ $isAvailable ? 'checked' : '' }}
                                                           class="sr-only peer">
                                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                                                </label>
                                                <div>
                                                    <h4 class="text-sm font-medium text-gray-900">{{ $pack->title }}</h4>
                                                    <p class="text-xs text-gray-500">
                                                        {{ number_format($pack->price, 0, '.', ' ') }} ₽ | 
                                                        {{ $pack->count }} ключей | 
                                                        {{ $pack->period }} дней
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <label class="block text-xs text-gray-500 mb-1">Порядок</label>
                                            <input type="number" 
                                                   name="pack_sort_order[{{ $pack->id }}]" 
                                                   value="{{ $sortOrder }}"
                                                   min="0"
                                                   class="w-20 px-2 py-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <!-- Submit Button -->
                    <div class="flex justify-end pt-4 border-t">
                        <button type="submit" 
                                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            <i class="fas fa-save mr-2"></i>
                            Сохранить настройки
                        </button>
                    </div>
                </div>
            </form>
        </x-admin.card>
    </div>
@endsection


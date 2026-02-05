@extends('layouts.admin')

@php
    use App\Models\Order\Order;
    $currentTab = request()->get('tab', 'orders');
@endphp

@section('title', 'Система заказов')
@section('page-title', 'Система заказов')

@section('content')
    <div class="space-y-6">
        <!-- Tabs Navigation -->
        <div class="border-b border-gray-200">
            <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                <a href="{{ route('admin.module.order.index', ['tab' => 'orders']) }}"
                   class="{{ $currentTab === 'orders' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                    <i class="fas fa-shopping-cart mr-2"></i>
                    Заказы
                </a>
                <a href="{{ route('admin.module.order.index', ['tab' => 'payment-methods']) }}"
                   class="{{ $currentTab === 'payment-methods' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                    <i class="fas fa-credit-card mr-2"></i>
                    Способы оплаты
                </a>
                <a href="{{ route('admin.module.order.index', ['tab' => 'settings']) }}"
                   class="{{ $currentTab === 'settings' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                    <i class="fas fa-cog mr-2"></i>
                    Настройки
                </a>
            </nav>
        </div>

        @if($currentTab === 'orders')
        <x-admin.card title="Список заказов">
            <!-- Filters -->
            <x-admin.filter-form action="{{ route('admin.module.order.index') }}">
                <x-admin.filter-input 
                    name="search" 
                    label="Поиск" 
                    value="{{ request('search') }}" 
                    placeholder="ID заказа, продавца или пакета" />
                
                <x-admin.filter-select 
                    name="status" 
                    label="Статус"
                    :options="[
                        '0' => 'Ожидает оплаты',
                        '1' => 'Ожидает подтверждения',
                        '2' => 'Одобрен',
                        '3' => 'Отклонен',
                        '4' => 'Отменен'
                    ]"
                    value="{{ request('status') }}" />
            </x-admin.filter-form>

            <!-- Table -->
            @if($orders->isEmpty())
                <x-admin.empty-state 
                    icon="fa-shopping-cart" 
                    title="Заказы не найдены"
                    description="Попробуйте изменить параметры фильтрации" />
            @else
                <x-admin.table :headers="['ID', 'Пакет', 'Продавец', 'Способ оплаты', 'Сумма', 'Статус', 'Дата создания', 'Действия']">
                    @foreach($orders as $order)
                        <tr class="hover:bg-gray-50">
                            <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-xs sm:text-sm font-medium text-gray-900">
                                #{{ $order->id }}
                            </td>
                            <td class="px-3 sm:px-6 py-4 text-xs sm:text-sm">
                                @if($order->pack)
                                    <a href="{{ route('admin.module.pack.index', ['title' => $order->pack->title]) }}"
                                       class="text-indigo-600 hover:text-indigo-800">
                                        <span class="hidden sm:inline">{{ $order->pack->title }}</span>
                                        <span class="sm:hidden">{{ Str::limit($order->pack->title, 15) }}</span>
                                    </a>
                                @else
                                    <span class="text-red-600 text-xs">Пакет удален</span>
                                @endif
                            </td>
                            <td class="px-3 sm:px-6 py-4 text-xs sm:text-sm">
                                @if($order->salesman)
                                    <a href="{{ route('admin.module.salesman.index', ['username' => $order->salesman->username]) }}"
                                       class="text-indigo-600 hover:text-indigo-800">
                                        @if($order->salesman->username)
                                            <span class="hidden sm:inline">{{ '@' . $order->salesman->username }}</span>
                                            <span class="sm:hidden">{{ Str::limit('@' . $order->salesman->username, 10) }}</span>
                                        @else
                                            <span class="hidden sm:inline">Продавец #{{ $order->salesman->id }}</span>
                                            <span class="sm:hidden">#{{ $order->salesman->id }}</span>
                                        @endif
                                    </a>
                                    <div class="text-xs text-gray-500 mt-1 hidden sm:block">
                                        ID: {{ $order->salesman->telegram_id }}
                                    </div>
                                @else
                                    <span class="text-red-600 text-xs">Продавец удален</span>
                                @endif
                            </td>
                            <td class="px-3 sm:px-6 py-4 text-xs sm:text-sm text-gray-900">
                                @if($order->paymentMethod)
                                    <span class="hidden sm:inline">{{ $order->paymentMethod->getTypeIcon() }} {{ $order->paymentMethod->name }}</span>
                                    <span class="sm:hidden">{{ $order->paymentMethod->getTypeIcon() }} {{ Str::limit($order->paymentMethod->name, 8) }}</span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-xs sm:text-sm text-gray-900">
                                {{ number_format($order->amount, 0, '.', ' ') }} ₽
                            </td>
                            <td class="px-3 sm:px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2 sm:px-2.5 py-0.5 rounded-full text-xs font-medium
                                    @if($order->status == Order::STATUS_PENDING) bg-blue-100 text-blue-800 border border-blue-200
                                    @elseif($order->status == Order::STATUS_AWAITING_CONFIRMATION) bg-amber-100 text-amber-800 border border-amber-200
                                    @elseif($order->status == Order::STATUS_APPROVED) bg-emerald-100 text-emerald-800 border border-emerald-200
                                    @elseif($order->status == Order::STATUS_REJECTED) bg-rose-100 text-rose-800 border border-rose-200
                                    @elseif($order->status == Order::STATUS_CANCELLED) bg-slate-100 text-slate-800 border border-slate-200
                                    @else bg-gray-100 text-gray-800 border border-gray-200
                                    @endif">
                                    <span class="hidden sm:inline">{{ $order->getStatusText() }}</span>
                                    <span class="sm:hidden">{{ Str::limit($order->getStatusText(), 8) }}</span>
                                </span>
                            </td>
                            <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-xs sm:text-sm text-gray-500">
                                <span class="hidden sm:inline">{{ $order->created_at->format('d.m.Y H:i') }}</span>
                                <span class="sm:hidden">{{ $order->created_at->format('d.m.Y') }}</span>
                            </td>
                            <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-right text-xs sm:text-sm font-medium">
                                <div class="flex items-center justify-end gap-1 sm:gap-2">
                                    <a href="{{ route('admin.module.order.show', $order->id) }}"
                                       class="text-indigo-600 hover:text-indigo-900">
                                        <span class="hidden sm:inline">Просмотр</span>
                                        <i class="sm:hidden fas fa-eye"></i>
                                    </a>
                                    @if($order->status != Order::STATUS_APPROVED)
                                        <form action="{{ route('admin.module.order.destroy', $order->id) }}" 
                                              method="POST" 
                                              class="inline"
                                              onsubmit="return confirm('Вы уверены, что хотите удалить заказ #{{ $order->id }}? Это действие нельзя отменить.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" 
                                                    class="text-red-600 hover:text-red-900"
                                                    title="Удалить заказ">
                                                <i class="fas fa-trash text-xs sm:text-sm"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </x-admin.table>

                <!-- Pagination -->
                <x-admin.pagination-wrapper :paginator="$orders" />
            @endif
        </x-admin.card>
        @elseif($currentTab === 'payment-methods')
        <x-admin.card title="Способы оплаты">
            <x-slot name="tools">
                <button type="button" 
                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                        onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: { id: 'createPaymentMethodModal' } }))">
                    <i class="fas fa-plus mr-2"></i>
                    Добавить способ оплаты
                </button>
            </x-slot>

            <!-- Table -->
            @if($paymentMethods->isEmpty())
                <x-admin.empty-state 
                    icon="fa-credit-card" 
                    title="Способы оплаты не найдены"
                    description="Добавьте способ оплаты для работы системы заказов">
                    <x-slot name="action">
                        <button type="button" 
                                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700"
                                onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: { id: 'createPaymentMethodModal' } }))">
                            <i class="fas fa-plus mr-2"></i>
                            Добавить способ оплаты
                        </button>
                    </x-slot>
                </x-admin.empty-state>
            @else
                <x-admin.table :headers="['Название', 'Тип', 'Реквизиты', 'Статус', 'Порядок', 'Действия']">
                    @foreach($paymentMethods as $method)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                {{ $method->getTypeIcon() }} {{ $method->name }}
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900">
                                {{ $method->getTypeText() }}
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900">
                                <div class="max-w-xs truncate" title="{{ $method->details }}">
                                    {{ \Illuminate\Support\Str::limit($method->details, 50) }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    {{ $method->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                    {{ $method->is_active ? 'Активен' : 'Неактивен' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $method->sort_order }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <button type="button" 
                                        class="text-indigo-600 hover:text-indigo-900 mr-4"
                                        onclick="editPaymentMethod({{ $method->id }}, '{{ $method->name }}', '{{ $method->type }}', `{{ addslashes($method->details) }}`, `{{ addslashes($method->instructions ?? '') }}`, {{ $method->is_active ? 'true' : 'false' }}, {{ $method->sort_order }})">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form action="{{ route('admin.module.payment-method.destroy', $method->id) }}" 
                                      method="POST" 
                                      class="inline"
                                      onsubmit="return confirm('Вы уверены, что хотите удалить этот способ оплаты?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-900">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </x-admin.table>
            @endif
        </x-admin.card>

        <!-- Create/Edit Modal -->
        <div id="createPaymentMethodModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
            <div class="relative top-20 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white">
                <div class="mt-3">
                    <h3 class="text-lg font-medium text-gray-900 mb-4" id="modalTitle">Добавить способ оплаты</h3>
                    
                    @if($errors->any() && request()->get('tab') === 'payment-methods')
                        <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded">
                            <ul class="list-disc list-inside">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    
                    <form id="paymentMethodForm" method="POST" action="{{ route('admin.module.payment-method.store') }}">
                        @csrf
                        <input type="hidden" name="_method" id="formMethod" value="POST">
                        <input type="hidden" name="payment_method_id" id="paymentMethodId">

                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Название *</label>
                                <input type="text" 
                                       name="name" 
                                       id="name"
                                       value="{{ old('name') }}"
                                       required
                                       class="w-full px-3 py-2 border {{ $errors->has('name') ? 'border-red-300' : 'border-gray-300' }} rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                @if($errors->has('name'))
                                    <p class="mt-1 text-sm text-red-600">{{ $errors->first('name') }}</p>
                                @endif
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Тип *</label>
                                <select name="type" 
                                        id="type"
                                        required
                                        class="w-full px-3 py-2 border {{ $errors->has('type') ? 'border-red-300' : 'border-gray-300' }} rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                    <option value="bank" {{ old('type') == 'bank' ? 'selected' : '' }}>Банковский перевод</option>
                                    <option value="crypto" {{ old('type') == 'crypto' ? 'selected' : '' }}>Криптовалюта</option>
                                    <option value="ewallet" {{ old('type') == 'ewallet' ? 'selected' : '' }}>Электронный кошелек</option>
                                    <option value="other" {{ old('type') == 'other' ? 'selected' : '' }}>Другое</option>
                                </select>
                                @if($errors->has('type'))
                                    <p class="mt-1 text-sm text-red-600">{{ $errors->first('type') }}</p>
                                @endif
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Реквизиты для перевода *</label>
                                <textarea name="details" 
                                          id="details"
                                          rows="4"
                                          required
                                          class="w-full px-3 py-2 border {{ $errors->has('details') ? 'border-red-300' : 'border-gray-300' }} rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                          placeholder="Номер карты, кошелька и т.д.">{{ old('details') }}</textarea>
                                @if($errors->has('details'))
                                    <p class="mt-1 text-sm text-red-600">{{ $errors->first('details') }}</p>
                                @endif
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Инструкция (необязательно)</label>
                                <textarea name="instructions" 
                                          id="instructions"
                                          rows="3"
                                          class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                          placeholder="Дополнительные инструкции для пользователя">{{ old('instructions') }}</textarea>
                            </div>

                            <div class="flex items-center">
                                <input type="checkbox" 
                                       name="is_active" 
                                       id="is_active"
                                       value="1"
                                       {{ old('is_active', true) ? 'checked' : '' }}
                                       class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                <label for="is_active" class="ml-2 block text-sm text-gray-900">
                                    Активен
                                </label>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Порядок отображения</label>
                                <input type="number" 
                                       name="sort_order" 
                                       id="sort_order"
                                       value="{{ old('sort_order', 0) }}"
                                       min="0"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                        </div>

                        <div class="mt-6 flex gap-2">
                            <button type="submit" 
                                    class="flex-1 inline-flex justify-center items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700">
                                Сохранить
                            </button>
                            <button type="button" 
                                    class="flex-1 inline-flex justify-center items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50"
                                    onclick="closeModal()">
                                Отмена
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
            function editPaymentMethod(id, name, type, details, instructions, isActive, sortOrder) {
                document.getElementById('modalTitle').textContent = 'Редактировать способ оплаты';
                document.getElementById('formMethod').value = 'PUT';
                document.getElementById('paymentMethodForm').action = '{{ route('admin.module.payment-method.update', '') }}/' + id;
                document.getElementById('paymentMethodId').value = id;
                document.getElementById('name').value = name;
                document.getElementById('type').value = type;
                document.getElementById('details').value = details;
                document.getElementById('instructions').value = instructions || '';
                document.getElementById('is_active').checked = isActive;
                document.getElementById('is_active').value = isActive ? '1' : '0';
                document.getElementById('sort_order').value = sortOrder;
                document.getElementById('createPaymentMethodModal').classList.remove('hidden');
            }

            function closeModal() {
                document.getElementById('createPaymentMethodModal').classList.add('hidden');
                document.getElementById('paymentMethodForm').reset();
                document.getElementById('formMethod').value = 'POST';
                document.getElementById('paymentMethodForm').action = '{{ route('admin.module.payment-method.store') }}';
                document.getElementById('modalTitle').textContent = 'Добавить способ оплаты';
                // Сброс чекбокса
                document.getElementById('is_active').checked = true;
                document.getElementById('is_active').value = '1';
            }

            // Обработчик события открытия модального окна
            window.addEventListener('open-modal', function(event) {
                if (event.detail.id === 'createPaymentMethodModal') {
                    closeModal(); // Сброс формы
                    document.getElementById('createPaymentMethodModal').classList.remove('hidden');
                }
            });

            // Автоматически открыть модальное окно при наличии ошибок валидации
            @if($errors->any() && request()->get('tab') === 'payment-methods')
                document.addEventListener('DOMContentLoaded', function() {
                    document.getElementById('createPaymentMethodModal').classList.remove('hidden');
                });
            @endif
        </script>
        @elseif($currentTab === 'settings')
        <!-- Fixed Save Button at Bottom (only visible on settings tab) -->
        <div id="settingsSaveButton" class="fixed bottom-0 left-0 right-0 z-40 bg-white border-t border-gray-200 shadow-lg" style="margin-left: 0;">
            <div class="max-w-7xl mx-auto px-6 py-4 flex justify-end">
                <button type="submit" 
                        form="orderSettingsForm"
                        class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <i class="fas fa-save mr-2"></i>
                    Сохранить настройки
                </button>
            </div>
        </div>

        <x-admin.card title="Основные настройки">
            <form action="{{ route('admin.module.order-settings.update') }}?tab=settings" method="POST" id="orderSettingsForm">
                @csrf
                <input type="hidden" name="tab" value="settings">

                <div class="space-y-6 pb-24">
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
                </div>
            </form>
        </x-admin.card>

        <script>
            // Скрыть кнопку при переключении на другие вкладки
            document.addEventListener('DOMContentLoaded', function() {
                const saveButton = document.getElementById('settingsSaveButton');
                if (!saveButton) return;

                // Слушаем клики по вкладкам
                const tabs = document.querySelectorAll('a[href*="tab="]');
                tabs.forEach(tab => {
                    tab.addEventListener('click', function(e) {
                        const url = new URL(this.href);
                        const tabParam = url.searchParams.get('tab') || 'orders';
                        // Скрываем кнопку перед переходом
                        if (tabParam !== 'settings') {
                            saveButton.style.display = 'none';
                        }
                    });
                });

                // Проверяем текущую вкладку при загрузке
                const currentTab = new URL(window.location.href).searchParams.get('tab') || 'orders';
                if (currentTab !== 'settings') {
                    saveButton.style.display = 'none';
                }
            });
        </script>
        @endif
    </div>
@endsection


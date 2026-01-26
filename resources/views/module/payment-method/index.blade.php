@extends('layouts.admin')

@section('title', 'Способы оплаты')
@section('page-title', 'Управление способами оплаты')

@section('content')
    <div class="space-y-6">
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
    </div>

    <!-- Create/Edit Modal -->
    <div id="createPaymentMethodModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4" id="modalTitle">Добавить способ оплаты</h3>
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
                                   required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Тип *</label>
                            <select name="type" 
                                    id="type"
                                    required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="bank">Банковский перевод</option>
                                <option value="crypto">Криптовалюта</option>
                                <option value="ewallet">Электронный кошелек</option>
                                <option value="other">Другое</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Реквизиты для перевода *</label>
                            <textarea name="details" 
                                      id="details"
                                      rows="4"
                                      required
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                      placeholder="Номер карты, кошелька и т.д."></textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Инструкция (необязательно)</label>
                            <textarea name="instructions" 
                                      id="instructions"
                                      rows="3"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                      placeholder="Дополнительные инструкции для пользователя"></textarea>
                        </div>

                        <div class="flex items-center">
                            <input type="checkbox" 
                                   name="is_active" 
                                   id="is_active"
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
                                   value="0"
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
            document.getElementById('sort_order').value = sortOrder;
            document.getElementById('createPaymentMethodModal').classList.remove('hidden');
        }

        function closeModal() {
            document.getElementById('createPaymentMethodModal').classList.add('hidden');
            document.getElementById('paymentMethodForm').reset();
            document.getElementById('formMethod').value = 'POST';
            document.getElementById('paymentMethodForm').action = '{{ route('admin.module.payment-method.store') }}';
            document.getElementById('modalTitle').textContent = 'Добавить способ оплаты';
        }

        // Обработчик события открытия модального окна
        window.addEventListener('open-modal', function(event) {
            if (event.detail.id === 'createPaymentMethodModal') {
                closeModal(); // Сброс формы
                document.getElementById('createPaymentMethodModal').classList.remove('hidden');
            }
        });
    </script>
@endsection


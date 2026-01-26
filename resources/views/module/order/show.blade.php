@extends('layouts.admin')

@php
    use App\Models\Order\Order;
@endphp

@section('title', 'Заказ #' . $order->id)
@section('page-title', 'Просмотр заказа #' . $order->id)

@section('content')
    <div class="space-y-6">
        <!-- Order Info -->
        <x-admin.card title="Информация о заказе">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h3 class="text-sm font-medium text-gray-500 mb-2">Статус</h3>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                                    @if($order->status == Order::STATUS_PENDING) bg-gray-100 text-gray-800
                                    @elseif($order->status == Order::STATUS_AWAITING_CONFIRMATION) bg-yellow-100 text-yellow-800
                                    @elseif($order->status == Order::STATUS_APPROVED) bg-green-100 text-green-800
                                    @elseif($order->status == Order::STATUS_REJECTED) bg-red-100 text-red-800
                        @else bg-gray-100 text-gray-800
                        @endif">
                        {{ $order->getStatusText() }}
                    </span>
                </div>

                <div>
                    <h3 class="text-sm font-medium text-gray-500 mb-2">Сумма</h3>
                    <p class="text-lg font-semibold text-gray-900">{{ number_format($order->amount, 0, '.', ' ') }} ₽</p>
                </div>

                <div>
                    <h3 class="text-sm font-medium text-gray-500 mb-2">Пакет</h3>
                    @if($order->pack)
                        <a href="{{ route('admin.module.pack.index', ['title' => $order->pack->title]) }}"
                           class="text-indigo-600 hover:text-indigo-800">
                            {{ $order->pack->title }}
                        </a>
                        <div class="text-sm text-gray-600 mt-1">
                            Ключей: {{ $order->pack->count }} | Период: {{ $order->pack->period }} дней
                        </div>
                    @else
                        <span class="text-red-600">Пакет удален</span>
                    @endif
                </div>

                <div>
                    <h3 class="text-sm font-medium text-gray-500 mb-2">Продавец</h3>
                    @if($order->salesman)
                        <a href="{{ route('admin.module.salesman.index', ['username' => $order->salesman->username]) }}"
                           class="text-indigo-600 hover:text-indigo-800">
                            @if($order->salesman->username)
                                {{ '@' . $order->salesman->username }}
                            @else
                                Продавец #{{ $order->salesman->id }}
                            @endif
                        </a>
                        <div class="text-sm text-gray-600 mt-1">
                            Telegram ID: {{ $order->salesman->telegram_id }}
                        </div>
                    @else
                        <span class="text-red-600">Продавец удален</span>
                    @endif
                </div>

                <div>
                    <h3 class="text-sm font-medium text-gray-500 mb-2">Способ оплаты</h3>
                    @if($order->paymentMethod)
                        <p class="text-gray-900">{{ $order->paymentMethod->getTypeIcon() }} {{ $order->paymentMethod->name }}</p>
                        <div class="text-sm text-gray-600 mt-2">
                            <strong>Реквизиты:</strong><br>
                            <pre class="whitespace-pre-wrap mt-1">{{ $order->paymentMethod->details }}</pre>
                        </div>
                        @if($order->paymentMethod->instructions)
                            <div class="text-sm text-gray-600 mt-2">
                                <strong>Инструкция:</strong><br>
                                <pre class="whitespace-pre-wrap mt-1">{{ $order->paymentMethod->instructions }}</pre>
                            </div>
                        @endif
                    @else
                        <span class="text-gray-400">—</span>
                    @endif
                </div>

                <div>
                    <h3 class="text-sm font-medium text-gray-500 mb-2">Дата создания</h3>
                    <p class="text-gray-900">{{ $order->created_at->format('d.m.Y H:i:s') }}</p>
                </div>

                @if($order->pack_salesman_id)
                    <div>
                        <h3 class="text-sm font-medium text-gray-500 mb-2">Выданный пакет</h3>
                        <a href="{{ route('admin.module.pack-salesman.index', ['pack_salesman_id' => $order->pack_salesman_id]) }}"
                           class="text-indigo-600 hover:text-indigo-800">
                            Пакет продавца #{{ $order->pack_salesman_id }}
                        </a>
                    </div>
                @endif

                @if($order->admin_comment)
                    <div class="md:col-span-2">
                        <h3 class="text-sm font-medium text-gray-500 mb-2">Комментарий администратора</h3>
                        <p class="text-gray-900 bg-yellow-50 p-3 rounded">{{ $order->admin_comment }}</p>
                    </div>
                @endif
            </div>
        </x-admin.card>

        <!-- Payment Proof -->
        @if($order->payment_proof)
            <x-admin.card title="Подтверждение оплаты">
                <div class="mt-4">
                    <img src="{{ asset('storage/' . $order->payment_proof) }}" 
                         alt="Подтверждение оплаты" 
                         class="max-w-full h-auto rounded-lg shadow-md"
                         onclick="window.open('{{ asset('storage/' . $order->payment_proof) }}', '_blank')"
                         style="cursor: pointer;">
                </div>
            </x-admin.card>
        @endif

        <!-- Actions -->
        @if($order->status == Order::STATUS_AWAITING_CONFIRMATION)
            <x-admin.card title="Действия">
                <div class="flex gap-4">
                    <form action="{{ route('admin.module.order.approve', $order->id) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" 
                                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500"
                                onclick="return confirm('Вы уверены, что хотите одобрить этот заказ? Пакет будет выдан продавцу.')">
                            <i class="fas fa-check mr-2"></i>
                            Одобрить заказ
                        </button>
                    </form>

                    <button type="button" 
                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                            onclick="document.getElementById('rejectModal').classList.remove('hidden')">
                        <i class="fas fa-times mr-2"></i>
                        Отклонить заказ
                    </button>
                </div>

                <!-- Reject Modal -->
                <div id="rejectModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
                    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                        <div class="mt-3">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Отклонить заказ</h3>
                            <form action="{{ route('admin.module.order.reject', $order->id) }}" method="POST">
                                @csrf
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Комментарий (необязательно)
                                    </label>
                                    <textarea name="comment" 
                                              rows="4" 
                                              class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                              placeholder="Укажите причину отклонения..."></textarea>
                                </div>
                                <div class="flex gap-2">
                                    <button type="submit" 
                                            class="flex-1 inline-flex justify-center items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700">
                                        Отклонить
                                    </button>
                                    <button type="button" 
                                            class="flex-1 inline-flex justify-center items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50"
                                            onclick="document.getElementById('rejectModal').classList.add('hidden')">
                                        Отмена
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </x-admin.card>
        @endif

        <!-- Back Button -->
        <div class="flex justify-start">
            <a href="{{ route('admin.module.order.index') }}"
               class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                <i class="fas fa-arrow-left mr-2"></i>
                Назад к списку заказов
            </a>
        </div>
    </div>
@endsection


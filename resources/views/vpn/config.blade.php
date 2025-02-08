@extends('layouts.public')

@section('content')
    <div class="container mx-auto px-4 py-8">
        <div class="bg-white rounded-lg shadow-lg p-6">
            <!-- Bot Link and Copy URL Section -->
            <div class="mb-8 flex justify-between items-center">
                <div>
                    <span class="text-gray-600">Бот активации:</span>
                    <a href="{{ $botLink }}" target="_blank" class="ml-2 text-blue-600 hover:text-blue-800">
                        Перейти к боту
                    </a>
                </div>
                <button onclick="copyCurrentUrl()"
                        class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                    <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/>
                    </svg>
                    Скопировать ссылку
                </button>
            </div>

            <!-- User Information Section -->
            <div class="mb-8">
                <h2 class="text-2xl font-bold mb-6">Информация о подключении</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <div class="mb-4">
                            <span class="text-gray-600">Статус:</span>
                            <span
                                class="ml-2 px-2 py-1 rounded text-sm {{ $userInfo['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ $userInfo['status'] === 'active' ? 'Активен' : 'Неактивен' }}
                            </span>
                        </div>
                        <div class="mb-4">
                            <span class="text-gray-600">Лимит трафика:</span>
                            <span class="ml-2 font-semibold">{{ number_format($userInfo['data_limit'] / (1024*1024*1024), 1) }} GB ({{ number_format($userInfo['data_limit_tariff'] / (1024*1024*1024), 1) }} GB по тарифу)</span>
                        </div>
                        <div class="mb-4">
                            <span class="text-gray-600">Использовано:</span>
                            <span class="ml-2 font-semibold">{{ number_format($userInfo['data_used'] / (1024*1024*1024), 2) }} GB</span>
                            <div class="w-full bg-gray-200 rounded-full h-2.5 mt-2">
                                @php
                                    $percentage = min(($userInfo['data_used'] / $userInfo['data_limit']) * 100, 100);
                                @endphp
                                <div class="bg-blue-600 h-2.5 rounded-full" style="width: {{ $percentage }}%"></div>
                            </div>
                        </div>
                        <div>
                            <span class="text-gray-600">Действует до:</span>
                            <span
                                class="ml-2 font-semibold">{{ date('d.m.Y H:i', $userInfo['expiration_date']) }}</span>
                            <div class="text-sm text-gray-500 mt-1">(осталось {{ $userInfo['days_remaining'] }} дней)
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Connection Keys Section -->
            @if($userInfo['status'] === 'active')
                <div>
                    <h2 class="text-2xl font-bold mb-6">Доступные протоколы</h2>
                    <div class="space-y-4">
                        @foreach($formattedKeys as $key)
                            <div class="border rounded-lg p-4 hover:bg-gray-50 transition-colors">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center flex-grow">
                                        <span class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-blue-100 text-blue-800 font-semibold mr-3">
                                            {{ $key['icon'] }}
                                        </span>
                                        <div class="flex-grow">
                                            <div class="font-medium">{{ $key['protocol'] }}</div>
                                            <div class="text-xs text-blue-600 mt-1">{{ $key['connection_type'] }}</div>
                                        </div>
                                    </div>
                                    <div class="flex space-x-2 ml-4">
                                        <button onclick="copyToClipboard('{{ $key['link'] }}', '{{ $key['protocol'] }}')"
                                                class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors"
                                                title="Нажмите, чтобы скопировать конфигурацию {{ $key['protocol'] }}">
                                            <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/>
                                            </svg>
                                            Копировать
                                        </button>
                                        <button onclick="showQR('{{ $key['link'] }}')"
                                                class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                                            <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M12 4v1m6 11h-6m-6 0h6m6-3v-3m-6 0v-3"/>
                                            </svg>
                                            QR-код
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <div class="text-center text-gray-600 py-6">
                    Подписка неактивна. Ключи подключения недоступны.
                </div>
            @endif
        </div>
    </div>

    <!-- QR Code Modal -->
    <div id="qrModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white p-6 rounded-lg max-w-lg w-full mx-4">
            <div class="text-center mb-4">
                <h3 class="text-lg font-medium mb-2">QR-код для подключения</h3>
                <p class="text-sm text-gray-500">Отсканируйте этот код в вашем VPN-клиенте</p>
            </div>
            <div id="qrcode" class="flex flex-col items-center justify-center mb-4">
                <!-- QR код будет добавлен сюда -->
            </div>
            <div class="flex justify-end space-x-2">
                <button onclick="closeQR()"
                        class="px-4 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-colors">
                    Закрыть
                </button>
            </div>
        </div>
    </div>

    @push('scripts')
        <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
        <script>
            function copyCurrentUrl() {
                const url = window.location.href;
                navigator.clipboard.writeText(url).then(() => {
                    alert('Ссылка скопирована в буфер обмена!');
                }).catch(() => {
                    alert('Не удалось скопировать ссылку.');
                });
            }

            function copyToClipboard(text, protocol) {
                navigator.clipboard.writeText(text).then(() => {
                    alert(`Конфигурация ${protocol} скопирована в буфер обмена!`);
                }).catch(() => {
                    alert('Не удалось скопировать конфигурацию.');
                });
            }

            function showQR(link) {
                if (!link) {
                    alert('Ссылка для QR-кода отсутствует или некорректна.');
                    return;
                }

                // Кодируем всю строку конфигурации
                const encodedLink = encodeURIComponent(link);

                const qrModal = document.getElementById('qrModal');
                const qrcodeElement = document.getElementById('qrcode');
                qrcodeElement.innerHTML = ''; // Очищаем предыдущий QR-код

                // Создаем QR-код
                new QRCode(qrcodeElement, {
                    text: encodedLink, // Используем закодированную строку
                    width: 256,
                    height: 256,
                });

                // Показываем модальное окно
                qrModal.classList.remove('hidden');
            }

            function closeQR() {
                const qrModal = document.getElementById('qrModal');
                qrModal.classList.add('hidden');
            }

        </script>
    @endpush
@endsection

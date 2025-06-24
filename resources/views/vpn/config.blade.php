@extends('layouts.public')

@section('content')
    <div class="container mx-auto px-4 py-8">
        <div class="bg-white rounded-lg shadow-lg p-6">
            <!-- Bot Link and Copy URL Section -->
            <div class="mb-8 flex justify-between items-center">
{{--                <div>--}}
{{--                    <span class="text-gray-600">Бот активации:</span>--}}
{{--                    <a href="{{ $botLink }}" target="_blank" class="ml-2 text-blue-600 hover:text-blue-800">--}}
{{--                        Перейти к боту--}}
{{--                    </a>--}}
{{--                </div>--}}
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
{{--                        <div class="mb-4">--}}
{{--                            <span class="text-gray-600">Лимит трафика:</span>--}}
{{--                            <span class="ml-2 font-semibold">{{ number_format($userInfo['data_limit'] / (1024*1024*1024), 1) }} GB ({{ number_format($userInfo['data_limit_tariff'] / (1024*1024*1024), 1) }} GB по тарифу)</span>--}}
{{--                        </div>--}}
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
                            <span class="ml-2 font-semibold">{{ date('d.m.Y H:i', $userInfo['expiration_date']) }}</span>
                            @php
                                $days = $userInfo['days_remaining'];
                                $lastDigit = $days % 10;
                                $lastTwoDigits = $days % 100;

                                if ($lastTwoDigits >= 11 && $lastTwoDigits <= 19) {
                                    $daysText = 'дней';
                                } elseif ($lastDigit === 1) {
                                    $daysText = 'день';
                                } elseif ($lastDigit >= 2 && $lastDigit <= 4) {
                                    $daysText = 'дня';
                                } else {
                                    $daysText = 'дней';
                                }
                            @endphp
                            <div class="text-sm text-gray-500 mt-1">
                                (осталось {{ $days }} {{ $daysText }})
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

    <div id="copy-notification" class="notification hidden"></div>

    @push('scripts')
        <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
        <script src="https://unpkg.com/qr-code-styling@1.5.0/lib/qr-code-styling.js"></script>
        <script>
            let copyNotificationTimeout;

            function showCopyNotification(message) {
                const notification = document.getElementById('copy-notification');
                notification.textContent = message; // Устанавливаем текст уведомления
                notification.classList.remove('hidden'); // Показываем уведомление

                // Очищаем предыдущий таймаут, если он существует
                if (copyNotificationTimeout) {
                    clearTimeout(copyNotificationTimeout);
                }

                // Скрываем уведомление через 2 секунды
                copyNotificationTimeout = setTimeout(() => {
                    notification.classList.add('hidden');
                }, 2000);
            }

            function copyCurrentUrl() {
                const url = window.location.href;
                navigator.clipboard.writeText(url).then(() => {
                    showCopyNotification('Ссылка скопирована в буфер обмена!');
                }).catch(() => {
                    alert('Не удалось скопировать ссылку.');
                });
            }

            function copyToClipboard(text, protocol) {
                navigator.clipboard.writeText(text).then(() => {
                    showCopyNotification(`Конфигурация ${protocol} скопирована в буфер обмена!`);
                }).catch(() => {
                    alert('Не удалось скопировать конфигурацию.');
                });
            }

            function showQR(link) {
                if (!link) {
                    alert('Ссылка для QR-кода отсутствует или некорректна.');
                    return;
                }

                // Очищаем предыдущий QR-код, если он существует
                const qrcodeElement = document.getElementById('qrcode');
                qrcodeElement.innerHTML = ''; // Удаляем содержимое контейнера

                // Создаём новый экземпляр QRCodeStyling
                const qrCode = new QRCodeStyling({
                    width: 320, // Ширина QR-кода
                    height: 320, // Высота QR-кода
                    type: "svg", // Тип рендера (можно использовать "canvas")
                    data: link, // Данные для QR-кода
                    dotsOptions: {
                        color: "#635bd4", // Цвет точек QR-кода
                        type: "rounded" // Тип точек (rounded, square, circle и т.д.)
                    },
                    backgroundOptions: {
                        color: "#ffffff", // Цвет фона
                    },
                    image: "", // Можно добавить логотип в центр QR-кода (URL изображения)
                    imageOptions: {
                        crossOrigin: "anonymous", // Настройки кросс-оригина для изображения
                        margin: 10 // Отступ вокруг изображения
                    }
                });

                // Добавляем QR-код в DOM
                qrCode.append(qrcodeElement);

                // Показываем модальное окно с QR-кодом
                const qrModal = document.getElementById('qrModal');
                qrModal.classList.remove('hidden'); // Убираем класс hidden для показа модального окна
            }

            function closeQR() {
                const qrModal = document.getElementById('qrModal');
                qrModal.classList.add('hidden');
            }

        </script>
    @endpush
    <style>
        .notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 10px 20px;
            background-color: #4caf50; /* Зеленый цвет */
            color: white;
            border-radius: 5px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            font-size: 14px;
            z-index: 1000;
            opacity: 0;
            transform: translateY(20px);
            transition: opacity 0.3s ease, transform 0.3s ease;
        }

        .notification.hidden {
            opacity: 0;
            transform: translateY(20px);
        }

        .notification:not(.hidden) {
            opacity: 1;
            transform: translateY(0);
        }
    </style>
@endsection

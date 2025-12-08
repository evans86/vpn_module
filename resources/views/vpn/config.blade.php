@extends('layouts.public')

@section('title', 'Конфигурация VPN — VPN Service')
@section('header-subtitle', 'Профиль и ключи подключения')

@section('content')
    <div class="container mx-auto px-4 py-8 max-w-6xl">
        @if(isset($isDemoMode) && $isDemoMode && app()->environment('local'))
            <!-- Demo Mode Banner -->
            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6 rounded-r-lg">
                <div class="flex items-start justify-between">
                    <div class="flex items-start flex-1">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-yellow-800">
                                <strong>Демо-режим:</strong> Вы просматриваете демо-версию страницы конфигурации. Данные не являются реальными.
                            </p>
                        </div>
                    </div>
                    <a href="{{ route('vpn.config.error') }}" 
                       class="ml-4 inline-flex items-center px-3 py-1.5 border border-yellow-300 rounded-md text-xs font-medium text-yellow-800 bg-yellow-100 hover:bg-yellow-200 transition-colors whitespace-nowrap">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        Посмотреть ошибку
                    </a>
                </div>
            </div>
        @endif
        
        <!-- Hero Section -->
        <div class="bg-gradient-to-r from-blue-600 to-indigo-700 rounded-2xl shadow-xl p-6 md:p-8 mb-8 text-white">
            <div class="flex flex-col md:flex-row items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl md:text-3xl font-bold mb-2">Конфигурация VPN</h1>
                    <p class="text-blue-100 text-sm md:text-base">Управление подключением и проверка качества сети</p>
                </div>
                <a href="{{ $netcheckUrl ?? route('netcheck.index') }}" 
                   class="inline-flex items-center px-4 py-2 bg-white text-indigo-700 rounded-lg font-semibold hover:bg-blue-50 transition-colors shadow-lg">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Проверить качество сети
                </a>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-6 md:p-8">
            <!-- Action Buttons -->
            <div class="mb-8 flex flex-col sm:flex-row gap-3">
                <button onclick="copyCurrentUrl()"
                        class="inline-flex items-center justify-center px-4 py-3 border-2 border-indigo-200 text-indigo-700 rounded-xl font-medium bg-indigo-50 hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all shadow-sm hover:shadow">
                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/>
                    </svg>
                    Скопировать ссылку
                </button>

                <button onclick="showUrlQR('{{ url()->current() }}')"
                        class="inline-flex items-center justify-center px-4 py-3 border-2 border-gray-200 text-gray-700 rounded-xl font-medium bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-all shadow-sm hover:shadow">
                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9h14a2 2 0 012 2v2m0 0H3a2 2 0 01-2-2V9a2 2 0 012-2h14a2 2 0 012 2v2zm0 0h2a2 2 0 012 2v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4a2 2 0 012-2h2z"/>
                    </svg>
                    QR-код конфигурации
                </button>
            </div>

            <!-- User Information Section -->
            <div class="mb-8">
                <h2 class="text-2xl font-bold mb-6 text-gray-900 flex items-center">
                    <svg class="w-6 h-6 mr-2 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Информация о подключении
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-gradient-to-br from-gray-50 to-gray-100 p-6 rounded-xl border border-gray-200">
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <span class="text-gray-600 font-medium">Статус:</span>
                                <span class="px-3 py-1.5 rounded-full text-sm font-semibold {{ $userInfo['status'] === 'active' ? 'bg-green-100 text-green-800 border border-green-200' : 'bg-red-100 text-red-800 border border-red-200' }}">
                                    {{ $userInfo['status'] === 'active' ? '✓ Активен' : '✗ Неактивен' }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-600 font-medium">Использовано:</span>
                                <span class="font-bold text-gray-900">{{ number_format($userInfo['data_used'] / (1024*1024*1024), 2) }} GB</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-gradient-to-br from-blue-50 to-indigo-50 p-6 rounded-xl border border-blue-200">
                        <div class="space-y-4">
                            <div>
                                <span class="text-gray-600 font-medium block mb-2">Действует до:</span>
                                <span class="text-lg font-bold text-gray-900">{{ date('d.m.Y H:i', $userInfo['expiration_date']) }}</span>
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
                                <div class="text-sm text-indigo-600 font-medium mt-2">
                                    ⏱ Осталось {{ $days }} {{ $daysText }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Connection Keys Section -->
            @if($userInfo['status'] === 'active')
                <div>
                    <h2 class="text-2xl font-bold mb-6 text-gray-900 flex items-center">
                        <svg class="w-6 h-6 mr-2 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                        Доступные протоколы
                    </h2>
                    <div class="space-y-4">
                        @foreach($formattedKeys as $key)
                            <div class="border-2 border-gray-200 rounded-xl p-5 hover:border-indigo-300 hover:shadow-lg transition-all bg-white">
                                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                                    <div class="flex items-center flex-grow">
                                        <div class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-gradient-to-br from-indigo-500 to-blue-600 text-white font-bold text-lg mr-4 shadow-md">
                                            {{ $key['icon'] }}
                                        </div>
                                        <div class="flex-grow min-w-0">
                                            <div class="font-bold text-lg text-gray-900">{{ $key['protocol'] }}</div>
                                            <div class="text-sm text-indigo-600 font-medium mt-1">{{ $key['connection_type'] }}</div>
                                        </div>
                                    </div>
                                    <div class="flex flex-col sm:flex-row gap-3 md:ml-4 w-full md:w-auto">
                                        <button onclick="copyToClipboard('{{ $key['link'] }}', '{{ $key['protocol'] }}')"
                                                class="inline-flex items-center justify-center px-4 py-2.5 border-2 border-indigo-200 text-indigo-700 rounded-lg font-semibold bg-indigo-50 hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all shadow-sm hover:shadow"
                                                title="Нажмите, чтобы скопировать конфигурацию {{ $key['protocol'] }}">
                                            <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/>
                                            </svg>
                                            Копировать
                                        </button>
                                        <button onclick="showQR('{{ $key['link'] }}', '{{ $key['protocol'] }}')"
                                                class="inline-flex items-center justify-center px-4 py-2.5 border-2 border-gray-200 text-gray-700 rounded-lg font-semibold bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-all shadow-sm hover:shadow">
                                            <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9h14a2 2 0 012 2v2m0 0H3a2 2 0 01-2-2V9a2 2 0 012-2h14a2 2 0 012 2v2zm0 0h2a2 2 0 012 2v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4a2 2 0 012-2h2z"/>
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
                <div class="text-center py-12 bg-gray-50 rounded-xl border-2 border-dashed border-gray-300">
                    <svg class="w-16 h-16 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <p class="text-gray-600 font-medium text-lg">Подписка неактивна</p>
                    <p class="text-gray-500 text-sm mt-2">Ключи подключения недоступны</p>
                </div>
            @endif
        </div>
    </div>

    <!-- QR Code Modal -->
    <div id="qrModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
        <div class="bg-white p-6 md:p-8 rounded-2xl max-w-lg w-full mx-auto shadow-2xl">
            <div class="text-center mb-6">
                <h3 id="qrTitle" class="text-xl font-bold mb-2 text-gray-900">QR-код для подключения</h3>
                <p id="qrDescription" class="text-sm text-gray-500">Отсканируйте этот код в вашем VPN-клиенте</p>
            </div>
            <div id="qrcode" class="flex flex-col items-center justify-center mb-6 bg-gray-50 p-4 rounded-xl">
                <!-- QR код будет добавлен сюда -->
            </div>
            <div class="flex justify-end">
                <button onclick="closeQR()"
                        class="px-6 py-3 bg-gray-100 text-gray-700 rounded-lg font-semibold hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-colors">
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
            let currentQR = null;

            function showCopyNotification(message) {
                const notification = document.getElementById('copy-notification');
                notification.textContent = message;
                notification.classList.remove('hidden');

                if (copyNotificationTimeout) {
                    clearTimeout(copyNotificationTimeout);
                }

                copyNotificationTimeout = setTimeout(() => {
                    notification.classList.add('hidden');
                }, 3000);
            }

            function copyCurrentUrl() {
                const url = window.location.href;
                navigator.clipboard.writeText(url).then(() => {
                    showCopyNotification('✓ Ссылка скопирована в буфер обмена!');
                }).catch(() => {
                    alert('Не удалось скопировать ссылку.');
                });
            }

            function copyToClipboard(text, protocol) {
                navigator.clipboard.writeText(text).then(() => {
                    showCopyNotification(`✓ Конфигурация ${protocol} скопирована!`);
                }).catch(() => {
                    alert('Не удалось скопировать конфигурацию.');
                });
            }

            function showQR(link, protocol = '') {
                if (!link) {
                    alert('Ссылка для QR-кода отсутствует или некорректна.');
                    return;
                }

                const qrcodeElement = document.getElementById('qrcode');
                const qrTitle = document.getElementById('qrTitle');
                const qrDescription = document.getElementById('qrDescription');

                qrcodeElement.innerHTML = '';

                qrTitle.textContent = protocol ? `QR-код для ${protocol}` : 'QR-код';
                qrDescription.textContent = protocol
                    ? 'Отсканируйте этот код в вашем VPN-клиенте'
                    : 'Отсканируйте этот код для быстрого доступа';

                const qrCode = new QRCodeStyling({
                    width: 300,
                    height: 300,
                    type: "svg",
                    data: link,
                    dotsOptions: {
                        color: "#4f46e5",
                        type: "rounded"
                    },
                    backgroundOptions: {
                        color: "#ffffff",
                    },
                    image: "",
                    imageOptions: {
                        crossOrigin: "anonymous",
                        margin: 10
                    }
                });

                qrCode.append(qrcodeElement);
                currentQR = qrCode;

                const qrModal = document.getElementById('qrModal');
                qrModal.classList.remove('hidden');
                qrModal.classList.add('flex');
            }

            function showUrlQR(url) {
                showQR(url, 'конфигурации');
            }

            function closeQR() {
                const qrModal = document.getElementById('qrModal');
                qrModal.classList.add('hidden');
                qrModal.classList.remove('flex');

                if (currentQR) {
                    const qrcodeElement = document.getElementById('qrcode');
                    qrcodeElement.innerHTML = '';
                    currentQR = null;
                }
            }
        </script>
    @endpush
    <style>
        .notification {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 16px 24px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
            font-size: 15px;
            font-weight: 500;
            z-index: 1000;
            opacity: 0;
            transform: translateY(20px) scale(0.95);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .notification.hidden {
            opacity: 0;
            transform: translateY(20px) scale(0.95);
        }

        .notification:not(.hidden) {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    </style>
@endsection

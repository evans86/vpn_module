@extends('layouts.public')
@section('content')
    <h1>Информация о подключении</h1>

    <!-- Бот активации -->
    <div>
        <strong>Бот активации:</strong>
        <a href="{{ $botLink }}" target="_blank">Перейти к боту</a>
        <button onclick="copyTextToClipboard('{{ $botLink }}')">Скопировать ссылку</button>
    </div>

    <!-- Информация о подписке -->
    <div>
        <strong>Статус:</strong> {{ $userInfo['status'] === 'active' ? 'Активен' : 'Неактивен' }}
        <br>
        <strong>Лимит трафика:</strong>
        {{ number_format($userInfo['data_limit'] / (1024*1024*1024), 1) }} GB
        ({{ number_format($userInfo['data_limit_tariff'] / (1024*1024*1024), 1) }} GB по тарифу)
        <br>
        <strong>Использовано:</strong>
        {{ number_format($userInfo['data_used'] / (1024*1024*1024), 2) }} GB
        <div class="progress-bar">
            <div style="width: {{ min(($userInfo['data_used'] / $userInfo['data_limit']) * 100, 100) }}%; background-color: #4CAF50;"></div>
        </div>
        <strong>Действует до:</strong>
        {{ date('d.m.Y H:i', $userInfo['expiration_date']) }}
        (осталось {{ $userInfo['days_remaining'] }} дней)
    </div>

    <!-- Доступные протоколы -->
    @if($userInfo['status'] === 'active')
        <h2>Доступные протоколы</h2>
        <div id="protocols-container">
            @foreach($formattedKeys as $key)
                <div class="protocol-item">
                    <strong>{{ $key['icon'] }} {{ $key['protocol'] }} {{ $key['connection_type'] }}</strong>
                    <button onclick="copyTextToClipboard('{{ $key['link'] }}')">Копировать</button>
                    <button onclick="generateQR('{{ $key['link'] }}')">QR-код</button>
                    <div id="qr-code-{{ $loop->index }}" class="qr-code"></div>
                </div>
            @endforeach
        </div>
    @else
        <p>Подписка неактивна. Ключи подключения недоступны.</p>
    @endif

    <!-- QR-код для подключения -->
    <h2>QR-код для подключения</h2>
    <div id="qr-modal" class="modal hidden">
        <div class="modal-content">
            <span class="close" onclick="closeQRModal()">&times;</span>
            <h3>Отсканируйте этот код в вашем VPN-клиенте</h3>
            <div id="qr-canvas"></div>
            <button onclick="downloadQR()">Скачать QR-код</button>
        </div>
    </div>

    <script type="text/javascript" src="https://unpkg.com/qr-code-styling@1.5.0/lib/qr-code-styling.js"></script>
    <script>
        // Функция для копирования текста в буфер обмена
        function copyTextToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                alert('Текст скопирован в буфер обмена!');
            }).catch(err => {
                alert('Не удалось скопировать текст.');
            });
        }

        // Функция для генерации QR-кода
        let qrCodeInstance = null;
        function generateQR(data) {
            if (qrCodeInstance) {
                qrCodeInstance.clear(); // Очищаем предыдущий QR-код
            }

            qrCodeInstance = new QRCodeStyling({
                width: 300,
                height: 300,
                type: "svg",
                data: data,
                dotsOptions: {
                    color: "#000000",
                    type: "rounded"
                },
                backgroundOptions: {
                    color: "#ffffff",
                },
                image: "https://upload.wikimedia.org/wikipedia/commons/5/51/Facebook_f_logo_%282019%29.svg",
                imageOptions: {
                    crossOrigin: "anonymous",
                    margin: 10
                }
            });

            const canvas = document.getElementById("qr-canvas");
            canvas.innerHTML = ""; // Очищаем контейнер
            qrCodeInstance.append(canvas);

            openQRModal();
        }

        // Открытие модального окна QR-кода
        function openQRModal() {
            const modal = document.getElementById("qr-modal");
            modal.classList.remove("hidden");
        }

        // Закрытие модального окна QR-кода
        function closeQRModal() {
            const modal = document.getElementById("qr-modal");
            modal.classList.add("hidden");
        }

        // Скачивание QR-кода
        function downloadQR() {
            if (qrCodeInstance) {
                qrCodeInstance.download({ name: "qr-code", extension: "svg" });
            } else {
                alert("QR-код не сгенерирован.");
            }
        }
    </script>
@endsection

@extends('layouts.public')

@section('title', 'Проверка сети')
@section('header-subtitle', 'Тестирование подключения и доступности сайтов')

@section('content')
    <div class="max-w-6xl mx-auto px-4 py-8">
        <!-- Заголовок -->
        <div class="text-center mb-8">
            <h1 class="text-4xl font-bold text-gray-900 mb-4">Проверка сети</h1>
            <p class="text-xl text-gray-600 mb-6">Узнайте качество вашего подключения и доступность популярных
                сайтов</p>

            <button id="runTest"
                    class="bg-blue-600 hover:bg-blue-700 text-white px-12 py-6 rounded-2xl text-xl font-semibold transition-all duration-300 transform hover:scale-105 shadow-lg">
                🚀 Запустить проверку
            </button>
        </div>

        <!-- Информация о подключении -->
        <div id="connectionInfo" class="hidden bg-white rounded-2xl shadow-lg p-6 mb-8">
            <h3 class="text-lg font-semibold mb-4 flex items-center">
                <span class="mr-2">🌐</span>
                Информация о подключении
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="text-center p-4 bg-gray-50 rounded-lg">
                    <div class="text-2xl font-bold text-blue-600" id="ipAddress">—</div>
                    <div class="text-sm text-gray-600">Ваш IP-адрес</div>
                </div>
                <div class="text-center p-4 bg-gray-50 rounded-lg">
                    <div class="text-2xl font-bold text-green-600" id="countryInfo">—</div>
                    <div class="text-sm text-gray-600">Страна</div>
                </div>
                <div class="text-center p-4 bg-gray-50 rounded-lg">
                    <div class="text-2xl font-bold text-purple-600" id="providerInfo">—</div>
                    <div class="text-sm text-gray-600">Провайдер</div>
                </div>
            </div>
        </div>

        <!-- Прогресс-бар -->
        <div id="progressSection" class="hidden mb-8">
            <div class="bg-white rounded-2xl shadow-lg p-6">
                <div class="flex justify-between mb-2">
                    <span id="progressText" class="text-sm font-medium">Подготовка...</span>
                    <span id="progressPercent" class="text-sm font-medium">0%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-3">
                    <div id="progressBar" class="bg-blue-600 h-3 rounded-full transition-all duration-300"
                         style="width: 0%"></div>
                </div>
            </div>
        </div>

        <!-- Основные показатели -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <div class="bg-white rounded-lg shadow p-4 text-center">
                <div class="text-2xl font-bold text-blue-600 mb-2" id="pingValue">—</div>
                <div class="text-sm text-gray-600">Пинг, мс</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 text-center">
                <div class="text-2xl font-bold text-green-600 mb-2" id="speedValue">—</div>
                <div class="text-sm text-gray-600">Скорость, Мбит/с</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 text-center">
                <div class="text-2xl font-bold text-purple-600 mb-2" id="stabilityScore">—</div>
                <div class="text-sm text-gray-600">Стабильность</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 text-center">
                <div class="text-2xl font-bold text-orange-600 mb-2" id="availability">—</div>
                <div class="text-sm text-gray-600">Доступность</div>
            </div>
        </div>

        <!-- Результаты проверок -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Локальные сервисы -->
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b bg-green-50">
                    <h3 class="text-lg font-semibold flex items-center">
                        <span class="mr-2">🏠</span>
                        Локальные сервисы
                    </h3>
                    <p class="text-sm text-gray-600 mt-1">Обычно доступны всегда</p>
                </div>
                <div class="p-6 space-y-3" id="localResults">
                    <div class="text-gray-500 text-center py-4">Запустите проверку</div>
                </div>
            </div>

            <!-- Глобальные сервисы -->
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b bg-blue-50">
                    <h3 class="text-lg font-semibold flex items-center">
                        <span class="mr-2">🌍</span>
                        Глобальные сервисы
                    </h3>
                    <p class="text-sm text-gray-600 mt-1">Международные платформы</p>
                </div>
                <div class="p-6 space-y-3" id="globalResults">
                    <div class="text-gray-500 text-center py-4">Запустите проверку</div>
                </div>
            </div>
        </div>

        <!-- Здоровье сети -->
        <div class="bg-white rounded-lg shadow mb-8">
            <div class="px-6 py-4 border-b bg-purple-50">
                <h3 class="text-lg font-semibold flex items-center">
                    <span class="mr-2">📡</span>
                    Здоровье сети
                </h3>
                <p class="text-sm text-gray-600 mt-1">Проверка основных сетевых компонентов</p>
            </div>
            <div class="p-6 space-y-3" id="networkHealthResults">
                <div class="text-gray-500 text-center py-4">Проверка компонентов...</div>
            </div>
        </div>

        <!-- Итоговый вердикт и действия -->
        <div id="finalVerdict" class="hidden mt-8 bg-white rounded-lg shadow p-6">
            <h3 class="text-xl font-bold mb-4 flex items-center">
                <span class="mr-2">🎯</span>
                Итоги проверки
            </h3>
            <div id="verdictContent" class="space-y-4"></div>

            <div class="mt-6 pt-6 border-t flex flex-col sm:flex-row gap-4 justify-center">
                <button id="retryTest"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg transition-colors flex items-center justify-center">
                    <span class="mr-2">🔄</span>
                    Проверить снова
                </button>

                <button id="downloadPdf"
                        class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg transition-colors flex items-center justify-center">
                    <span class="mr-2">📄</span>
                    Скачать PDF-отчёт
                </button>
            </div>
        </div>
    </div>

    <script>
        class SimpleNetworkTester {
            constructor() {
                this.targets = @json($targets);
                this.pingUrl = @json(route('netcheck.ping'));
                this.payloadUrl = (size) => @json(route('netcheck.payload', ['size' => 'SIZE'])).
                replace('SIZE', size);
                this.reportUrl = @json(route('netcheck.report'));
                this.isRunning = false;
                this.currentResults = null;
                this.internetStatus = null; // null - неизвестно, 'full' - полный доступ, 'limited' - белый список, 'none' - нет интернета
                this.noInternetBanner = null;

                this.bindEvents();
                this.checkInitialConnection();
            }

            bindEvents() {
                document.getElementById('runTest').addEventListener('click', () => this.runFullTest());
                document.getElementById('downloadPdf').addEventListener('click', () => this.downloadPdf());
                document.getElementById('retryTest').addEventListener('click', () => this.retryTest());
            }

            // Проверка соединения при загрузке страницы
            async checkInitialConnection() {
                const status = await this.checkInternetConnection();
                if (status === 'limited' || status === 'none') {
                    this.showLimitedAccessWarning(status);
                }
            }

            async runFullTest() {
                if (this.isRunning) return;

                this.isRunning = true;
                this.showProgress();
                this.resetResults();
                this.showConnectionInfo();

                try {
                    // 0. Проверка уровня доступа к интернету
                    await this.updateProgress(5, 'Проверка интернет-соединения...');
                    const internetStatus = await this.checkInternetConnection();

                    if (internetStatus === 'limited' || internetStatus === 'none') {
                        this.showLimitedAccessWarning(internetStatus);
                    } else {
                        // Убираем предупреждение если полный доступ появился
                        this.hideLimitedAccessWarning();
                    }

                    // 1. Определение IP и геолокации (пробуем всегда, но не блокируем при ошибке)
                    let ipInfo = {ip: null, country: null, isp: null};
                    await this.updateProgress(10, 'Определение IP-адреса...');
                    try {
                        ipInfo = await this.detectIP();
                    } catch (e) {
                        console.log('IP detection failed:', e);
                        document.getElementById('ipAddress').textContent = 'Недоступно';
                        document.getElementById('countryInfo').textContent = '—';
                        document.getElementById('providerInfo').textContent = '—';
                    }

                    // 2. Базовые тесты (работают даже без интернета, так как используют локальный сервер)
                    await this.updateProgress(20, 'Проверка пинга...');
                    const ping = await this.testPing();

                    await this.updateProgress(40, 'Тест скорости...');
                    const speed = await this.testSpeed();

                    // 3. Проверка доступности сайтов (параллельно для скорости)
                    // Эти тесты могут не работать без интернета, но мы их все равно запускаем
                    await this.updateProgress(60, 'Проверка сервисов...');
                    const [localResults, globalResults, networkHealthResults] = await Promise.all([
                        this.testCategory('local_services', 'localResults'),
                        this.testCategory('global_services', 'globalResults'),
                        this.testCategory('network_health', 'networkHealthResults')
                    ]);

                    // 4. Сохранение результатов и итоги
                    await this.updateProgress(95, 'Анализ результатов...');

                    this.currentResults = {
                        ipInfo,
                        ping,
                        speed,
                        localResults,
                        globalResults,
                        networkHealthResults,
                        timestamp: new Date().toISOString()
                    };

                    this.calculateFinalScore(ping, speed, localResults, globalResults, networkHealthResults);

                } catch (error) {
                    console.error('Test failed:', error);
                    // При ошибке проверяем соединение, но не блокируем работу
                    const status = await this.checkInternetConnection();
                    if (status === 'limited' || status === 'none') {
                        this.showLimitedAccessWarning(status);
                    }
                    this.showError('Произошла ошибка при проверке: ' + error.message);
                } finally {
                    this.isRunning = false;
                    this.hideProgress();
                }
            }

            // Функция проверки уровня доступа к интернету
            // Возвращает: 'full' - полный доступ, 'limited' - белый список/ограниченный доступ, 'none' - нет интернета
            async checkInternetConnection() {
                try {
                    // Сначала проверяем доступность локального сервера (должен работать всегда)
                    let localServerAvailable = false;
                    try {
                        const localResponse = await fetch(this.pingUrl + '?t=' + Date.now(), {
                            cache: 'no-store',
                            signal: AbortSignal.timeout(2000)
                        });
                        if (localResponse.ok) {
                            localServerAvailable = true;
                        }
                    } catch (e) {
                        // Локальный сервер недоступен - это критично
                        this.internetStatus = 'none';
                        return 'none';
                    }

                    // Теперь проверяем доступность внешних ресурсов
                    const testEndpoints = [
                        {url: 'https://www.yandex.ru/favicon.ico', name: 'Яндекс'},
                        {url: 'https://www.google.com/favicon.ico', name: 'Google'},
                        {url: 'https://www.gstatic.com/generate_204', name: 'Google Static'}
                    ];

                    let accessibleCount = 0;
                    for (const endpoint of testEndpoints) {
                        try {
                            const response = await fetch(endpoint.url, {
                                method: 'HEAD',
                                mode: 'no-cors',
                                signal: AbortSignal.timeout(3000),
                                cache: 'no-store'
                            });
                            accessibleCount++;
                        } catch (e) {
                            continue; // Пробуем следующий endpoint
                        }
                    }

                    // Определяем уровень доступа
                    if (accessibleCount === 0) {
                        // Локальный сервер работает, но внешние ресурсы недоступны
                        // Это может быть белый список или полное отсутствие интернета
                        // Проверяем через попытку доступа к DNS
                        this.internetStatus = 'limited';
                        return 'limited';
                    } else if (accessibleCount < testEndpoints.length) {
                        // Частичный доступ - вероятно белый список
                        this.internetStatus = 'limited';
                        return 'limited';
                    } else {
                        // Полный доступ
                        this.internetStatus = 'full';
                        return 'full';
                    }

                } catch (error) {
                    this.internetStatus = 'limited';
                    return 'limited'; // В случае ошибки считаем ограниченным доступом
                }
            }

            // Показ предупреждения при ограниченном доступе (не блокирует работу)
            showLimitedAccessWarning(status) {
                // Если предупреждение уже показано - обновляем его
                if (this.noInternetBanner && document.body.contains(this.noInternetBanner)) {
                    this.updateLimitedAccessWarning(status);
                    return;
                }

                // Создаем предупреждение (не скрывает контент)
                this.noInternetBanner = document.createElement('div');
                this.noInternetBanner.id = 'limitedAccessWarning';
                this.updateLimitedAccessWarning(status);

                // Вставляем предупреждение в начало контента
                const container = document.querySelector('.max-w-6xl');
                if (container) {
                    container.insertBefore(this.noInternetBanner, container.firstChild);
                } else {
                    document.body.appendChild(this.noInternetBanner);
                }

                // Добавляем обработчик для кнопки повторной проверки
                const retryBtn = document.getElementById('retryConnection');
                if (retryBtn) {
                    retryBtn.addEventListener('click', () => {
                        this.retryConnectionCheck();
                    });
                }

                // Запускаем периодическую проверку соединения
                this.startConnectionMonitoring();
            }

            // Обновление содержимого предупреждения
            updateLimitedAccessWarning(status) {
                if (!this.noInternetBanner) return;

                let title, message, bgColor, borderColor, textColor, textColorDark;
                
                if (status === 'limited') {
                    title = 'Ограниченный доступ к интернету (белый список)';
                    message = 'Обнаружен режим белого списка или ограниченного доступа. Локальные тесты (ping, скорость) работают нормально. Проверка доступности внешних сайтов покажет результаты только для разрешенных ресурсов.';
                    bgColor = 'bg-blue-50';
                    borderColor = 'border-blue-400';
                    textColor = 'text-blue-800';
                    textColorDark = 'text-blue-700';
                } else {
                    title = 'Отсутствует интернет-соединение';
                    message = 'Страница работает в офлайн-режиме. Локальные тесты (ping, скорость) будут выполняться, но проверка доступности внешних сайтов может не работать.';
                    bgColor = 'bg-yellow-50';
                    borderColor = 'border-yellow-400';
                    textColor = 'text-yellow-800';
                    textColorDark = 'text-yellow-700';
                }

                this.noInternetBanner.className = `mb-6 ${bgColor} border-l-4 ${borderColor} p-4 rounded-lg`;
                this.noInternetBanner.innerHTML = `
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <span class="text-2xl">${status === 'limited' ? '🔒' : '⚠️'}</span>
                    </div>
                    <div class="ml-3 flex-1">
                        <h3 class="text-sm font-medium ${textColor} mb-2">
                            ${title}
                        </h3>
                        <p class="text-sm ${textColorDark} mb-3">
                            ${message}
                        </p>
                        <div class="flex gap-2">
                            <button id="retryConnection"
                                    class="text-sm ${status === 'limited' ? 'bg-blue-600 hover:bg-blue-700' : 'bg-yellow-600 hover:bg-yellow-700'} text-white px-4 py-2 rounded transition-colors font-medium">
                                🔄 Проверить соединение
                            </button>
                            <button onclick="document.getElementById('limitedAccessWarning')?.remove()"
                                    class="text-sm bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded transition-colors font-medium">
                                ✕ Скрыть
                            </button>
                        </div>
                    </div>
                </div>
            `;

                // Добавляем обработчик для кнопки повторной проверки
                const retryBtn = document.getElementById('retryConnection');
                if (retryBtn) {
                    retryBtn.addEventListener('click', () => {
                        this.retryConnectionCheck();
                    });
                }
            }

            // Скрытие предупреждения
            hideLimitedAccessWarning() {
                if (this.noInternetBanner && document.body.contains(this.noInternetBanner)) {
                    this.noInternetBanner.remove();
                    this.noInternetBanner = null;
                }
                this.stopConnectionMonitoring();
            }

            // Периодическая проверка соединения
            startConnectionMonitoring() {
                this.connectionMonitor = setInterval(async () => {
                    const status = await this.checkInternetConnection();
                    if (status === 'full') {
                        this.hideLimitedAccessWarning();
                        this.showReconnectedMessage();
                    } else if (status === 'limited' || status === 'none') {
                        // Обновляем предупреждение если статус изменился
                        if (this.noInternetBanner && document.body.contains(this.noInternetBanner)) {
                            this.updateLimitedAccessWarning(status);
                        } else {
                            this.showLimitedAccessWarning(status);
                        }
                    }
                }, 5000); // Проверяем каждые 5 секунд
            }

            // Остановка мониторинга
            stopConnectionMonitoring() {
                if (this.connectionMonitor) {
                    clearInterval(this.connectionMonitor);
                    this.connectionMonitor = null;
                }
            }

            // Сообщение о восстановлении соединения
            showReconnectedMessage() {
                const messageDiv = document.createElement('div');
                messageDiv.className = 'fixed top-4 left-1/2 transform -translate-x-1/2 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50';
                messageDiv.innerHTML = `
                <div class="flex items-center">
                    <span class="text-xl mr-2">✅</span>
                    <span class="font-semibold">Соединение восстановлено!</span>
                </div>
            `;

                document.body.appendChild(messageDiv);

                // Автоматически скрываем через 3 секунды
                setTimeout(() => {
                    if (messageDiv.parentNode) {
                        messageDiv.remove();
                    }
                }, 3000);
            }

            // Ручная проверка соединения
            async retryConnectionCheck() {
                const retryBtn = document.getElementById('retryConnection');
                if (!retryBtn) return;
                
                const originalText = retryBtn.textContent;

                retryBtn.disabled = true;
                retryBtn.textContent = 'Проверка...';
                retryBtn.classList.add('opacity-50');

                const status = await this.checkInternetConnection();

                if (status === 'full') {
                    this.hideLimitedAccessWarning();
                    this.showReconnectedMessage();
                } else {
                    // Обновляем предупреждение с текущим статусом
                    this.updateLimitedAccessWarning(status);
                    // Показываем, что проверка прошла
                    retryBtn.textContent = status === 'limited' ? 'Ограниченный доступ' : 'Интернета нет';
                    setTimeout(() => {
                        retryBtn.disabled = false;
                        retryBtn.textContent = originalText;
                        retryBtn.classList.remove('opacity-50');
                    }, 1000);
                }
            }

            async detectIP() {
                try {
                    const response = await fetch('https://api.ipify.org?format=json', {
                        cache: 'no-store',
                        signal: AbortSignal.timeout(3000)
                    });
                    const data = await response.json();

                    let country = 'Не определено';
                    let isp = 'Не определен';

                    try {
                        const geoResponse = await fetch('https://ipapi.co/json/', {
                            cache: 'no-store',
                            signal: AbortSignal.timeout(3000)
                        });
                        const geoData = await geoResponse.json();
                        country = geoData.country_name || 'Не определено';
                        isp = geoData.org || geoData.asn || 'Не определен';
                    } catch (e) {
                        console.log('Geo detection failed');
                    }

                    // Обновляем интерфейс
                    document.getElementById('ipAddress').textContent = data.ip;
                    document.getElementById('countryInfo').textContent = country;
                    document.getElementById('providerInfo').textContent = isp.length > 20 ? isp.substring(0, 20) + '...' : isp;

                    return {ip: data.ip, country, isp};
                } catch (error) {
                    // Если нет интернета, просто возвращаем null значения
                    document.getElementById('ipAddress').textContent = 'Недоступно';
                    document.getElementById('countryInfo').textContent = '—';
                    document.getElementById('providerInfo').textContent = '—';
                    return {ip: null, country: null, isp: null};
                }
            }

            async testPing() {
                const times = [];
                for (let i = 0; i < 3; i++) {
                    const start = performance.now();
                    try {
                        const response = await fetch(this.pingUrl + '?t=' + Date.now(), {
                            cache: 'no-store',
                            signal: AbortSignal.timeout(2000)
                        });

                        if (!response.ok) throw new Error('Ping failed');

                        const duration = performance.now() - start;
                        times.push(duration);
                    } catch (e) {
                        times.push(999);
                    }
                    if (i < 2) await this.delay(100);
                }

                const validTimes = times.filter(t => t < 500);
                const avgPing = validTimes.length > 0
                    ? Math.round(validTimes.reduce((a, b) => a + b) / validTimes.length)
                    : 999;

                document.getElementById('pingValue').textContent = avgPing;
                return avgPing;
            }

            async testSpeed() {
                const size = '2mb';
                const url = this.payloadUrl(size);
                const startTime = performance.now();
                let loadedBytes = 0;

                try {
                    const response = await fetch(url, {
                        cache: 'no-store',
                        signal: AbortSignal.timeout(8000)
                    });

                    if (!response.ok) throw new Error('Speed test failed');

                    const reader = response.body.getReader();

                    while (true) {
                        const {done, value} = await reader.read();
                        if (done) break;
                        loadedBytes += value.length;
                    }

                    const endTime = performance.now();
                    const duration = (endTime - startTime) / 1000;
                    const speedMbps = (loadedBytes * 8) / (1024 * 1024) / duration;

                    document.getElementById('speedValue').textContent = speedMbps.toFixed(1);
                    return speedMbps;
                } catch (error) {
                    document.getElementById('speedValue').textContent = '0';
                    return 0;
                }
            }

            async testCategory(categoryKey, resultsElementId) {
                const category = this.targets[categoryKey] || [];
                const container = document.getElementById(resultsElementId);
                if (!container) return [];

                container.innerHTML = '';

                // Запускаем все проверки параллельно для скорости
                const promises = category.map(target => this.testTarget(target));
                const results = await Promise.all(promises);

                // Отображаем результаты по мере готовности
                results.forEach((result, index) => {
                    const element = this.createResultElement(result, categoryKey);
                    container.appendChild(element);
                });

                return results;
            }

            async testTarget(target) {
                const startTime = performance.now();
                let status = 'error';
                let responseTime = 0;

                try {
                    // Сначала пробуем HEAD запрос (самый надежный)
                    status = await this.testWithHeadRequest(target.url);

                    // Если не сработало, пробуем через Image для favicon
                    if (status === 'error' && target.url.includes('favicon.ico')) {
                        status = await this.testWithImage(target.url);
                    }

                    responseTime = performance.now() - startTime;

                } catch (error) {
                    responseTime = performance.now() - startTime;
                    status = 'error';
                }

                return {
                    label: target.label,
                    status: status,
                    time: Math.round(responseTime),
                    url: target.url
                };
            }

            async testWithHeadRequest(url) {
                try {
                    const controller = new AbortController();
                    const timeoutId = setTimeout(() => controller.abort(), 3000);

                    const response = await fetch(url, {
                        method: 'HEAD',
                        mode: 'no-cors',
                        signal: controller.signal,
                        cache: 'no-store',
                        headers: {
                            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                        }
                    });

                    clearTimeout(timeoutId);
                    return 'success';
                } catch (error) {
                    return 'error';
                }
            }

            async testWithImage(url) {
                return new Promise((resolve) => {
                    const img = new Image();
                    const timeout = setTimeout(() => {
                        resolve('error');
                    }, 3000);

                    img.onload = () => {
                        clearTimeout(timeout);
                        resolve('success');
                    };

                    img.onerror = () => {
                        clearTimeout(timeout);
                        resolve('error');
                    };

                    img.src = url + '?t=' + Date.now();
                });
            }

            createResultElement(result, category) {
                const div = document.createElement('div');
                div.className = 'flex justify-between items-center py-3 px-4 border-b last:border-b-0 hover:bg-gray-50 rounded';

                let icon, statusText, colorClass, bgClass;

                if (result.status === 'success') {
                    icon = '✅';
                    statusText = `${result.time}мс`;
                    colorClass = 'text-green-600';
                    bgClass = category === 'local_services' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800';
                } else {
                    icon = '❌';
                    statusText = result.time > 2900 ? 'таймаут' : 'недоступен';
                    colorClass = 'text-red-600';
                    bgClass = 'bg-red-100 text-red-800';
                }

                div.innerHTML = `
            <span class="flex items-center">
                <span class="mr-3 text-xl">${icon}</span>
                <span>${result.label}</span>
            </span>
            <span class="${colorClass} font-medium px-3 py-1 rounded-full text-sm ${bgClass}">
                ${statusText}
            </span>
        `;

                return div;
            }

            calculateFinalScore(ping, speed, localResults, globalResults, networkHealthResults) {
                // Расчет доступности локальных сервисов
                const localSuccess = localResults.filter(r => r.status === 'success').length;
                const localTotal = localResults.length;
                const localPercent = Math.round((localSuccess / localTotal) * 100);

                // Расчет доступности глобальных сервисов
                const globalSuccess = globalResults.filter(r => r.status === 'success').length;
                const globalTotal = globalResults.length;
                const globalPercent = Math.round((globalSuccess / globalTotal) * 100);

                // Стабильность сети
                const networkSuccess = networkHealthResults.filter(r => r.status === 'success').length;
                const networkTotal = networkHealthResults.length;
                const stabilityScore = Math.round((networkSuccess / networkTotal) * 100);

                // Общая доступность
                const overallAvailability = Math.round((localPercent + globalPercent) / 2);

                // Обновление интерфейса
                document.getElementById('stabilityScore').textContent = stabilityScore + '%';
                document.getElementById('availability').textContent = overallAvailability + '%';

                // Итоговый вердикт
                this.showFinalVerdict(ping, speed, localPercent, globalPercent, stabilityScore, overallAvailability);
            }

            showFinalVerdict(ping, speed, localPercent, globalPercent, stabilityScore, overallAvailability) {
                const verdict = document.getElementById('finalVerdict');
                const content = document.getElementById('verdictContent');

                let message = '';
                let color = 'text-green-600';
                let emoji = '✅';

                // Анализируем доступность глобальных сервисов
                const hasGoodGlobalAccess = globalPercent >= 70;

                if (overallAvailability >= 80) {
                    if (hasGoodGlobalAccess) {
                        message = 'Отличное подключение! Большинство сайтов доступно без ограничений.';
                        color = 'text-green-600';
                        emoji = '🎉';
                    } else {
                        message = 'Хорошее подключение. Локальные сервисы работают стабильно.';
                        color = 'text-blue-600';
                        emoji = '👍';
                    }
                } else if (overallAvailability >= 50) {
                    if (hasGoodGlobalAccess) {
                        message = 'Удовлетворительное подключение. Есть небольшие проблемы с доступностью.';
                        color = 'text-orange-600';
                        emoji = '⚠️';
                    } else {
                        message = 'Ограниченный доступ. Многие международные сервисы недоступны.';
                        color = 'text-orange-600';
                        emoji = '🔒';
                    }
                } else {
                    message = 'Плохое подключение. Рекомендуется проверить настройки сети.';
                    color = 'text-red-600';
                    emoji = '❌';
                }

                content.innerHTML = `
            <div class="${color} font-semibold text-lg mb-4 flex items-center">
                <span class="mr-2 text-2xl">${emoji}</span>
                ${message}
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                <div class="text-center p-3 bg-gray-50 rounded">
                    <div class="font-bold text-lg">${ping}мс</div>
                    <div class="text-gray-600">Пинг</div>
                </div>
                <div class="text-center p-3 bg-gray-50 rounded">
                    <div class="font-bold text-lg">${speed.toFixed(1)}</div>
                    <div class="text-gray-600">Мбит/с</div>
                </div>
                <div class="text-center p-3 bg-gray-50 rounded">
                    <div class="font-bold text-lg">${localPercent}%</div>
                    <div class="text-gray-600">Локальные</div>
                </div>
                <div class="text-center p-3 bg-gray-50 rounded">
                    <div class="font-bold text-lg">${globalPercent}%</div>
                    <div class="text-gray-600">Глобальные</div>
                </div>
            </div>
            <div class="mt-4 p-4 bg-gray-50 rounded-lg">
                <h4 class="font-semibold mb-2">📊 Статистика доступности:</h4>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span>Локальные сервисы:</span>
                        <span class="font-semibold ${localPercent >= 80 ? 'text-green-600' : localPercent >= 50 ? 'text-orange-600' : 'text-red-600'}">
                            ${localPercent}% доступно
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span>Международные сервисы:</span>
                        <span class="font-semibold ${globalPercent >= 80 ? 'text-green-600' : globalPercent >= 50 ? 'text-orange-600' : 'text-red-600'}">
                            ${globalPercent}% доступно
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span>Стабильность сети:</span>
                        <span class="font-semibold ${stabilityScore >= 80 ? 'text-green-600' : stabilityScore >= 50 ? 'text-orange-600' : 'text-red-600'}">
                            ${stabilityScore}%
                        </span>
                    </div>
                </div>
            </div>
            ${!hasGoodGlobalAccess ? `
            <div class="mt-4 p-4 bg-blue-50 rounded-lg">
                <h4 class="font-semibold mb-2 text-blue-800">💡 Для полного доступа:</h4>
                <p class="text-sm text-blue-700">
                    Доступно только ${globalPercent}% международных сервисов.
                    Это может указывать на ограничения в вашей сети.
                </p>
            </div>
            ` : ''}
        `;

                verdict.classList.remove('hidden');
            }

            showConnectionInfo() {
                document.getElementById('connectionInfo').classList.remove('hidden');
            }

            async downloadPdf() {
                if (!this.currentResults) {
                    this.showError('Нет данных для отчёта. Сначала запустите проверку.');
                    return;
                }

                try {
                    // Правильно формируем данные для PDF
                    const reportData = {
                        summary: {
                            ip: this.currentResults.ipInfo?.ip || '—',
                            country: this.currentResults.ipInfo?.country || '—',
                            isp: this.currentResults.ipInfo?.isp || '—',
                            latency_avg_ms: this.currentResults.ping,
                            download_mbps: parseFloat(this.currentResults.speed.toFixed(1)),
                        },
                        latency: {
                            avg: this.currentResults.ping,
                            samples: []
                        },
                        download: {
                            mbps: parseFloat(this.currentResults.speed.toFixed(1)),
                            ok: this.currentResults.speed > 0
                        },
                        resources: {
                            local_services: this.currentResults.localResults.map(item => ({
                                label: item.label,
                                ok: item.status === 'success',
                                time: item.time
                            })),
                            global_services: this.currentResults.globalResults.map(item => ({
                                label: item.label,
                                ok: item.status === 'success',
                                time: item.time
                            })),
                            network_health: this.currentResults.networkHealthResults.map(item => ({
                                label: item.label,
                                ok: item.status === 'success',
                                time: item.time
                            }))
                        },
                        env: {
                            ua: navigator.userAgent,
                            tz: Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC',
                        },
                        startedAt: this.currentResults.timestamp,
                        finishedAt: new Date().toISOString(),
                    };

                    console.log('Sending PDF data:', reportData);

                    const response = await fetch(this.reportUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify(reportData),
                        signal: AbortSignal.timeout(15000)
                    });

                    if (!response.ok) {
                        const errorText = await response.text();
                        throw new Error(`PDF generation failed: ${response.status} - ${errorText}`);
                    }

                    const blob = await response.blob();
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `network-report-${new Date().toISOString().split('T')[0]}.pdf`;
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);

                } catch (error) {
                    console.error('PDF download error:', error);
                    this.showError('Ошибка при создании PDF: ' + error.message);
                }
            }

            retryTest() {
                this.resetResults();
                this.runFullTest();
            }

            showProgress() {
                document.getElementById('progressSection').classList.remove('hidden');
                document.getElementById('runTest').disabled = true;
                document.getElementById('runTest').textContent = 'Проверка...';
            }

            hideProgress() {
                document.getElementById('progressSection').classList.add('hidden');
                document.getElementById('runTest').disabled = false;
                document.getElementById('runTest').textContent = '🔄 Проверить снова';
            }

            updateProgress(percent, text) {
                document.getElementById('progressBar').style.width = percent + '%';
                document.getElementById('progressPercent').textContent = percent + '%';
                document.getElementById('progressText').textContent = text;
            }

            resetResults() {
                ['localResults', 'globalResults', 'networkHealthResults'].forEach(id => {
                    const element = document.getElementById(id);
                    if (element) {
                        element.innerHTML = '<div class="text-gray-500 text-center py-4">Проверка...</div>';
                    }
                });

                ['pingValue', 'speedValue', 'stabilityScore', 'availability'].forEach(id => {
                    const element = document.getElementById(id);
                    if (element) {
                        element.textContent = '—';
                    }
                });

                const finalVerdict = document.getElementById('finalVerdict');
                if (finalVerdict) {
                    finalVerdict.classList.add('hidden');
                }
            }

            showError(message) {
                const errorDiv = document.createElement('div');
                errorDiv.className = 'bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4';
                errorDiv.innerHTML = `
            <strong>Ошибка:</strong> ${message}
        `;

                const container = document.querySelector('.max-w-6xl');
                if (container) {
                    container.insertBefore(errorDiv, document.getElementById('progressSection'));
                }

                setTimeout(() => {
                    if (errorDiv.parentNode) {
                        errorDiv.remove();
                    }
                }, 5000);
            }

            delay(ms) {
                return new Promise(resolve => setTimeout(resolve, ms));
            }
        }

        // Инициализация при загрузке страницы
        document.addEventListener('DOMContentLoaded', () => {
            new SimpleNetworkTester();
        });
    </script>
@endsection

@extends('layouts.public')

@section('title', 'Проверка сети — ' . ($brand ?? 'High VPN'))
@section('header-subtitle', 'Диагностика подключения и качества VPN')

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <!-- PWA -->
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#4f46e5">
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js').catch(() => {
                });
            });
        }
    </script>

    <div class="px-4 py-6 sm:px-0">
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Левая колонка -->
            <div class="lg:col-span-1">
                <div class="bg-white shadow rounded-lg p-6 mb-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Старт проверки</h3>

                    <div class="mb-3">
                        <label class="block text-sm text-gray-600 mb-1">Режим проверки</label>
                        <div class="flex items-center gap-3 text-sm">
                            <label class="inline-flex items-center gap-2">
                                <input type="radio" name="mode" value="direct" class="modeRadio" checked>
                                <span>Без VPN</span>
                            </label>
                            <label class="inline-flex items-center gap-2">
                                <input type="radio" name="mode" value="vpn" class="modeRadio">
                                <span>С {{ $brand ?? 'High VPN' }}</span>
                            </label>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Скрипт сохранит результаты каждого режима и покажет
                            сравнение.</p>
                    </div>

                    <p class="text-sm text-gray-600 mb-3">
                        Тест: IP/гео, пинг/джиттер, потери, скорость, .ru-скорость, DoH, регионы, YouTube, .ru/банки,
                        мессенджеры (включая Telegram/WhatsApp), соцсети, HTTP:80, WebRTC/VoIP.
                    </p>

                    <div class="flex gap-2">
                        <button id="runBtn"
                                class="flex-1 inline-flex justify-center items-center px-4 py-2 text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Запустить тест
                        </button>

                        <button id="pdfBtn" disabled
                                class="inline-flex justify-center items-center px-4 py-2 text-sm font-medium rounded-md text-indigo-600 bg-indigo-50 hover:bg-indigo-100 focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50">
                            PDF
                        </button>
                    </div>

                    <button id="saveHtmlBtn" disabled
                            class="mt-2 w-full inline-flex justify-center items-center px-4 py-2 text-sm font-medium rounded-md text-indigo-600 bg-indigo-50 hover:bg-indigo-100 focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50">
                        Скачать страницу (HTML)
                    </button>

                    <div id="progress" class="mt-4 hidden">
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div id="progressBar" class="bg-indigo-600 h-2 rounded-full" style="width:0%"></div>
                        </div>
                        <p id="progressText" class="text-xs text-gray-500 mt-2">Готово 0%</p>
                    </div>
                </div>

                <div class="bg-white shadow rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Ваше соединение</h3>
                    <dl class="divide-y divide-gray-200 text-sm">
                        <div class="py-2 flex justify-between">
                            <dt class="text-gray-500">Внешний IP</dt>
                            <dd id="ip" class="font-mono">—</dd>
                        </div>
                        <div class="py-2 flex justify-between">
                            <dt class="text-gray-500">Страна</dt>
                            <dd id="country">—</dd>
                        </div>
                        <div class="py-2 flex justify-between">
                            <dt class="text-gray-500">IP (альт. сервис)</dt>
                            <dd id="ipAlt" class="font-mono">—</dd>
                        </div>
                        <div class="py-2">
                            <dt class="text-gray-500 mb-1">User-Agent</dt>
                            <dd>
                                <details class="group">
                                    <summary
                                        class="cursor-pointer text-indigo-600 hover:text-indigo-700 inline-flex items-center">
                                        Показать
                                        <svg class="w-4 h-4 ml-1 transition-transform group-open:rotate-180" fill="none"
                                             viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M19 9l-7 7-7-7"/>
                                        </svg>
                                    </summary>
                                    <pre id="ua"
                                         class="mt-2 p-2 bg-gray-50 border border-gray-200 rounded text-xs whitespace-pre-wrap break-words leading-5"></pre>
                                </details>
                            </dd>
                        </div>
                        <div class="py-2">
                            <dt class="text-gray-500 mb-1">WebRTC (кандидаты)</dt>
                            <dd id="webrtc"
                                class="text-xs text-gray-700 bg-gray-50 p-2 rounded border border-gray-200 max-h-28 overflow-auto">
                                —
                            </dd>
                        </div>
                    </dl>
                </div>

                <div class="bg-white shadow rounded-lg p-6 mt-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-2">Сравнение режимов</h3>
                    <p class="text-xs text-gray-500 mb-3">Результаты последних запусков «Без VPN» и
                        «С {{ $brand ?? 'High VPN' }}».</p>
                    <div id="compareBox" class="text-sm text-gray-700 space-y-1">
                        <div>Пинг: <span id="cmpLatency">—</span></div>
                        <div>Потери: <span id="cmpLoss">—</span></div>
                        <div>Скорость: <span id="cmpSpeed">—</span></div>
                        <div>.ru-скорость: <span id="cmpRuSpeed">—</span></div>
                        <div>Доступность (часто блок.): <span id="cmpBlocked">—</span></div>
                        <div>Мессенджеры/соцсети: <span id="cmpMsg">—</span></div>
                        <div>Оценка VPN: <span id="cmpScore">—</span></div>
                    </div>
                </div>
            </div>

            <!-- Правая колонка -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Качество связи -->
                <div class="bg-white shadow rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Качество связи</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-5 gap-4">
                        <div class="p-4 bg-gray-50 rounded border">
                            <div class="text-gray-500 text-xs mb-1"
                                 title="Как быстро откликается сеть. Меньше — лучше.">Задержка (пинг)
                            </div>
                            <div class="text-2xl font-semibold"><span id="latencyAvg">—</span> <span
                                    class="text-sm">мс</span></div>
                        </div>
                        <div class="p-4 bg-gray-50 rounded border">
                            <div class="text-gray-500 text-xs mb-1" title="Насколько прыгает задержка.">Джиттер</div>
                            <div class="text-2xl font-semibold"><span id="latencyJitter">—</span> <span class="text-sm">мс</span>
                            </div>
                        </div>
                        <div class="p-4 bg-gray-50 rounded border">
                            <div class="text-gray-500 text-xs mb-1" title="Сколько запросов теряется. >10% заметно.">
                                Потери пакетов
                            </div>
                            <div class="text-2xl font-semibold"><span id="lossPct">—</span> <span
                                    class="text-sm">%</span></div>
                        </div>
                        <div class="p-4 bg-gray-50 rounded border">
                            <div class="text-gray-500 text-xs mb-1" title="Как быстро грузятся данные.">Скорость
                                скачивания
                            </div>
                            <div class="text-2xl font-semibold"><span id="downloadMbps">—</span> <span class="text-sm">Мбит/с</span>
                            </div>
                        </div>
                        <div class="p-4 bg-gray-50 rounded border">
                            <div class="text-gray-500 text-xs mb-1"
                                 title="Оценка скорости на .ru (по статическому файлу).">.ru-скорость
                            </div>
                            <div class="text-2xl font-semibold"><span id="downloadRuMbps">—</span> <span
                                    class="text-sm">Мбит/с</span></div>
                            <div class="text-[10px] text-gray-500 mt-1" id="downloadRuSrc">Источник: —</div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <canvas id="latencyChart" height="120"></canvas>
                    </div>
                </div>

                <!-- Доступность ресурсов (без показа URL) -->
                <div class="bg-white shadow rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Доступность ресурсов</h3>

                    <h4 class="text-sm font-semibold text-gray-700 mb-2">Должны открываться</h4>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm" id="tblMust">
                            <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-gray-500">Ресурс</th>
                                <th class="px-4 py-2 text-left text-gray-500">Статус</th>
                                <th class="px-4 py-2 text-left text-gray-500">Время</th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                            @foreach($must as $i => $t)
                                <tr data-row="must-{{ $i }}">
                                    <td class="px-4 py-2">{{ $t['label'] }}</td>
                                    <td class="px-4 py-2" data-status>—</td>
                                    <td class="px-4 py-2" data-time>—</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>

                    <h4 class="mt-6 text-sm font-semibold text-gray-700 mb-2">Часто блокируемые</h4>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm" id="tblBlocked">
                            <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-gray-500">Ресурс</th>
                                <th class="px-4 py-2 text-left text-gray-500">Статус</th>
                                <th class="px-4 py-2 text-left text-gray-500">Время</th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                            @foreach($blocked as $i => $t)
                                <tr data-row="blocked-{{ $i }}">
                                    <td class="px-4 py-2">{{ $t['label'] }}</td>
                                    <td class="px-4 py-2" data-status>—</td>
                                    <td class="px-4 py-2" data-time>—</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- DoH -->
                <div class="bg-white shadow rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">DNS поверх HTTPS (DoH)</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm" id="tblDoh">
                            <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-gray-500">Домен</th>
                                <th class="px-4 py-2 text-left text-gray-500">Google DoH</th>
                                <th class="px-4 py-2 text-left text-gray-500">Cloudflare DoH</th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                            @foreach($doh as $d)
                                <tr data-domain="{{ $d }}">
                                    <td class="px-4 py-2">{{ $d }}</td>
                                    <td class="px-4 py-2" data-google>—</td>
                                    <td class="px-4 py-2" data-cf>—</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">Если оба резолвера не отвечают — вероятна блокировка DoH.</p>
                </div>

                <!-- Регионы -->
                <div class="bg-white shadow rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Региональная доступность</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm" id="tblRegions">
                            <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-gray-500">Регион</th>
                                <th class="px-4 py-2 text-left text-gray-500">Статус</th>
                                <th class="px-4 py-2 text-left text-gray-500">Время</th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                            @foreach($regions as $i => $p)
                                <tr data-region="{{ $i }}">
                                    <td class="px-4 py-2">{{ $p['label'] }}</td>
                                    <td class="px-4 py-2" data-status>—</td>
                                    <td class="px-4 py-2" data-time>—</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">
                        <canvas id="regionalChart" height="120"></canvas>
                    </div>
                </div>

                <!-- Расширенные проверки -->
                <div class="bg-white shadow rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Расширенные проверки</h3>

                    <h4 class="text-sm font-semibold text-gray-700 mb-2">YouTube</h4>
                    <div class="overflow-x-auto mb-4">
                        <table class="min-w-full divide-y divide-gray-200 text-sm" id="tblYT">
                            <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-gray-500">Точка</th>
                                <th class="px-4 py-2 text-left text-gray-500">Статус</th>
                                <th class="px-4 py-2 text-left text-gray-500">Время</th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                            @foreach($youtube as $i => $t)
                                <tr data-row="yt-{{ $i }}">
                                    <td class="px-4 py-2">{{ $t['label'] }}</td>
                                    <td class="px-4 py-2" data-status>—</td>
                                    <td class="px-4 py-2" data-time>—</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>

                    <h4 class="text-sm font-semibold text-gray-700 mb-2">.ru / банки / госуслуги</h4>
                    <div class="overflow-x-auto mb-4">
                        <table class="min-w-full divide-y divide-gray-200 text-sm" id="tblRU">
                            <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-gray-500">Сервис</th>
                                <th class="px-4 py-2 text-left text-gray-500">Статус</th>
                                <th class="px-4 py-2 text-left text-gray-500">Время</th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                            @foreach($ru as $i => $t)
                                <tr data-row="ru-{{ $i }}">
                                    <td class="px-4 py-2">{{ $t['label'] }}</td>
                                    <td class="px-4 py-2" data-status>—</td>
                                    <td class="px-4 py-2" data-time>—</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>

                    <h4 class="text-sm font-semibold text-gray-700 mb-2">Мессенджеры</h4>
                    <div class="overflow-x-auto mb-4">
                        <table class="min-w-full divide-y divide-gray-200 text-sm" id="tblMSG">
                            <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-gray-500">Сервис</th>
                                <th class="px-4 py-2 text-left text-gray-500">Статус</th>
                                <th class="px-4 py-2 text-left text-gray-500">Время</th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                            @foreach($mess as $i => $t)
                                <tr data-row="msg-{{ $i }}">
                                    <td class="px-4 py-2">{{ $t['label'] }}</td>
                                    <td class="px-4 py-2" data-status>—</td>
                                    <td class="px-4 py-2" data-time>—</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>

                    <h4 class="text-sm font-semibold text-gray-700 mb-2">Соцсети</h4>
                    <div class="overflow-x-auto mb-4">
                        <table class="min-w-full divide-y divide-gray-200 text-sm" id="tblSOC">
                            <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-gray-500">Сервис</th>
                                <th class="px-4 py-2 text-left text-gray-500">Статус</th>
                                <th class="px-4 py-2 text-left text-gray-500">Время</th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                            @foreach($socials as $i => $t)
                                <tr data-row="soc-{{ $i }}">
                                    <td class="px-4 py-2">{{ $t['label'] }}</td>
                                    <td class="px-4 py-2" data-status>—</td>
                                    <td class="px-4 py-2" data-time>—</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>

                    <h4 class="text-sm font-semibold text-gray-700 mb-2">HTTP (порт 80)</h4>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm" id="tblHTTP">
                            <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-gray-500">Точка</th>
                                <th class="px-4 py-2 text-left text-gray-500">Статус</th>
                                <th class="px-4 py-2 text-left text-gray-500">Время</th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                            @foreach($http80 as $i => $t)
                                <tr data-row="http-{{ $i }}">
                                    <td class="px-4 py-2">{{ $t['label'] }}</td>
                                    <td class="px-4 py-2" data-status>—</td>
                                    <td class="px-4 py-2" data-time>—</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4 text-sm text-gray-600">
                        <div id="voipSummary" class="p-3 bg-gray-50 border rounded">Готовность к звонкам: —</div>
                    </div>
                </div>

                <!-- Итоги -->
                <div class="bg-white shadow rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Итоги</h3>
                    <ul id="findings" class="list-disc ml-5 text-sm text-gray-800 space-y-1">
                        <li>Тест ещё не запускался</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1"></script>
    <script>
        (function () {
            let CURRENT_PCT = 0;
            // ==== прогресс и чекпоинты ====
            const CHECKPOINT_LABELS = {
                10: 'IP/гео',
                20: 'Пинг/джиттер',
                30: 'Пакетные потери',
                40: 'Скорость скачивания',
                45: '.ru-скорость',
                50: 'Доступность ресурсов',
                60: 'DNS поверх HTTPS (DoH)',
                70: 'Региональные пробы',
                80: 'Расширенные проверки (YouTube, .ru, мессенджеры, соцсети, HTTP:80)',
                90: 'WebRTC/VoIP',
                100: 'Готово'
            };

            const runBtn = document.getElementById('runBtn');
            const pdfBtn = document.getElementById('pdfBtn');
            const saveHtmlBtn = document.getElementById('saveHtmlBtn');
            const progress = document.getElementById('progress');
            const pbar = document.getElementById('progressBar');
            const ptext = document.getElementById('progressText');

            const env = {
                ua: navigator.userAgent,
                tz: Intl.DateTimeFormat().resolvedOptions().timeZone || 'unknown',
                startedAt: null,
                finishedAt: null,
            };

            const pingUrl = @json(route('netcheck.ping'));
            const payloadUrl = (size) => @json(route('netcheck.payload', ['size' => 'SIZE_PLACEHOLDER'])).
            replace('SIZE_PLACEHOLDER', size);
            const reportUrl = @json(route('netcheck.report'));
            const telemetryUrl = @json(route('netcheck.telemetry'));
            const runId = (crypto && crypto.randomUUID ? crypto.randomUUID() : (Date.now() + '-' + Math.random().toString(16).slice(2)));

            const ruSpeedAssets = @json($ruSpeed ?? []);
            const must = @json($must);
            const blocked = @json($blocked);
            const youtube = @json($youtube);
            const ru = @json($ru);
            const mess = @json($mess);
            const socials = @json($socials);
            const http80 = @json($http80);

            const brand = @json($brand ?? 'High VPN');

            function sendTelemetry(payload) {
                try {
                    if (!telemetryUrl) return;
                    const body = JSON.stringify(payload);
                    if (navigator.sendBeacon) {
                        navigator.sendBeacon(telemetryUrl, new Blob([body], {type: 'application/json'}));
                    } else {
                        fetch(telemetryUrl, {method: 'POST', headers: {'Content-Type': 'application/json'}, body});
                    }
                } catch (_) {
                }
            }

            function tStart() {
                sendTelemetry({event: 'run:start', runId, ts: new Date().toISOString(), ua: env.ua, tz: env.tz});
            }

            function tDone() {
                sendTelemetry({event: 'run:done', runId, ts: new Date().toISOString()});
            }

            function tCheckpoint(pct, status, error) {
                sendTelemetry({
                    event: 'checkpoint',
                    runId,
                    ts: new Date().toISOString(),
                    pct,
                    label: CHECKPOINT_LABELS[pct] || '',
                    status,
                    error: error || null
                });
            }

            const setProgress = (pct) => {
                CURRENT_PCT = pct; // <— запоминаем чекпоинт
                progress.classList.remove('hidden');
                pbar.style.width = pct + '%';
                ptext.textContent = `Готово ${pct}% — ${CHECKPOINT_LABELS[pct] || ''}`;
            };

            async function step(pct, fn) {
                setProgress(pct);
                try {
                    const res = await fn();
                    tCheckpoint(pct, 'success', null);
                    return res;
                } catch (e) {
                    const msg = (e && e.message) ? e.message : String(e);
                    tCheckpoint(pct, 'error', msg);
                    throw e;
                }
            }

            window.addEventListener('error', (e) => sendTelemetry({
                event: 'window:error',
                runId,
                ts: new Date().toISOString(),
                error: e.message, // <—
                line: e.lineno,
                col: e.colno
            }));
            window.addEventListener('unhandledrejection', (e) => sendTelemetry({
                event: 'window:unhandledrejection',
                runId,
                ts: new Date().toISOString(),
                error: (e && e.reason && (e.reason.message || String(e.reason))) || 'unknown' // <—
            }));

            const ms = () => performance.now();

            async function fetchWithTimeout(url, timeoutMs) {
                const ctrl = new AbortController();
                const tmo = setTimeout(() => ctrl.abort(), timeoutMs);
                const t0 = ms();
                try {
                    await fetch(url, {signal: ctrl.signal, cache: 'no-store'});
                    return {ok: true, time: ms() - t0};
                } catch (e) {
                    return {ok: false, time: ms() - t0, error: (e && e.name) || 'Error'};
                } finally {
                    clearTimeout(tmo);
                }
            }

            async function fetchJSON(url, opts = {}) {
                const t0 = ms();
                const resp = await fetch(url, opts);
                const dt = ms() - t0;
                let data = null;
                try {
                    data = await resp.json();
                } catch (_) {
                }
                return {ok: resp.ok || resp.type === 'opaque', time: dt, data, status: resp.status, type: resp.type};
            }

            function loadUA() {
                document.getElementById('ua').textContent = navigator.userAgent;
            }

            // IP (основной) + страна
            async function detectIP() {
                let ip = '—', country = '—';
                try {
                    const a = await fetchJSON('https://api.ipify.org?format=json', {cache: 'no-store'});
                    if (a?.data?.ip) ip = a.data.ip;
                } catch (_) {
                }
                try {
                    const b = await fetchJSON('https://ipapi.co/json/', {cache: 'no-store'});
                    if (b?.data?.country_name) country = b.data.country_name + (b?.data?.city ? `, ${b.data.city}` : '');
                } catch (_) {
                }
                document.getElementById('ip').textContent = ip;
                document.getElementById('country').textContent = country;
                return {ip, country};
            }

            // Альтернативный IP (2ip-подобный)
            async function detectIPAlt() {
                const candidates = [
                    {name: 'myip.com', url: 'https://api.myip.com/'},
                    {name: 'ipify.org', url: 'https://api.ipify.org?format=json'},
                    {name: 'ipapi.co', url: 'https://ipapi.co/json/'},
                ];
                for (const c of candidates) {
                    try {
                        const r = await fetchJSON(c.url, {cache: 'no-store'});
                        const d = r.data || {};
                        const ip = d.ip || d.query || '';
                        const country = d.country_name || d.country || '';
                        if (ip) {
                            document.getElementById('ipAlt').textContent = `${ip}${country ? ' (' + country + ')' : ''}`;
                            return {service: c.name, ip, country: country || null};
                        }
                    } catch (_) {
                    }
                }
                return {service: null, ip: null, country: null};
            }

            async function testLatency(samples = 15) {
                const rtts = [];
                for (let i = 0; i < samples; i++) {
                    const url = (i === 0) ? (pingUrl + '?debug=1') : pingUrl;
                    const r = await fetchWithTimeout(url, 2000);
                    rtts.push(r.time);
                    await new Promise(r2 => setTimeout(r2, 100));
                }
                const avg = rtts.reduce((a, b) => a + b, 0) / rtts.length;
                const jitter = Math.sqrt(rtts.reduce((a, b) => a + Math.pow(b - avg, 2), 0) / rtts.length);
                document.getElementById('latencyAvg').textContent = Math.round(avg);
                document.getElementById('latencyJitter').textContent = Math.round(jitter);
                drawLatencyChart(rtts);
                return {samples: rtts, avg, jitter};
            }

            async function testPacketLoss(total = 40, timeout = 1500, gap = 150) {
                let ok = 0;
                const times = [];
                for (let i = 0; i < total; i++) {
                    const r = await fetchWithTimeout(pingUrl, timeout);
                    if (r.ok) ok++;
                    times.push(r.time);
                    await new Promise(r2 => setTimeout(r2, gap));
                }
                const lossPct = Math.round((1 - ok / total) * 100);
                document.getElementById('lossPct').textContent = lossPct;
                const sorted = times.slice().sort((a, b) => a - b);
                const p = (q) => sorted[Math.min(sorted.length - 1, Math.floor(q * sorted.length))];
                return {total, ok, lossPct, p50: Math.round(p(0.5)), p95: Math.round(p(0.95))};
            }

            async function testDownload() {
                const url = payloadUrl('5mb');
                const t0 = ms();
                let ok = true, received = 0;
                try {
                    const resp = await fetch(url, {cache: 'no-store'});
                    const reader = resp.body.getReader();
                    while (true) {
                        const {done, value} = await reader.read();
                        if (done) break;
                        received += (value?.length || 0);
                    }
                } catch (_) {
                    ok = false;
                }
                const dt = (ms() - t0) / 1000;
                const mbits = (received * 8) / (1024 * 1024);
                const speed = dt > 0 ? (mbits / dt) : 0;
                document.getElementById('downloadMbps').textContent = ok ? speed.toFixed(1) : '—';
                return {ok, bytes: received, seconds: dt, mbps: speed};
            }

            // .ru-скорость: грузим <img> известного размера (указан в конфиге), CORS не нужен.
            async function testRuSpeed() {
                if (!Array.isArray(ruSpeedAssets) || ruSpeedAssets.length === 0) return {
                    ok: false,
                    mbps: 0,
                    source_label: null
                };
                const tryOne = (asset) => new Promise((resolve) => {
                    const img = new Image();
                    const t0 = ms();
                    let done = false;
                    const cleanup = (ok) => {
                        if (done) return;
                        done = true;
                        const dt = (ms() - t0) / 1000;
                        const bits = (asset.bytes || 0) * 8;
                        const mbps = dt > 0 && asset.bytes ? (bits / 1024 / 1024) / dt : 0;
                        resolve({ok, mbps, source_label: asset.label || ''});
                    };
                    img.onload = () => cleanup(true);
                    img.onerror = () => cleanup(false);
                    img.src = asset.url + (asset.url.includes('?') ? '&' : '?') + 'nc=' + Date.now();
                    // таймаут 8с
                    setTimeout(() => cleanup(false), 8000);
                });

                for (const a of ruSpeedAssets) {
                    const r = await tryOne(a);
                    if (r.ok && r.mbps > 0) {
                        document.getElementById('downloadRuMbps').textContent = r.mbps.toFixed(1);
                        document.getElementById('downloadRuSrc').textContent = 'Источник: ' + (r.source_label || '—');
                        return r;
                    }
                }
                document.getElementById('downloadRuMbps').textContent = '—';
                document.getElementById('downloadRuSrc').textContent = 'Источник: —';
                return {ok: false, mbps: 0, source_label: null};
            }

            async function fetchNoCors(url, timeoutMs = 7000) {
                const ctrl = new AbortController();
                const t0 = ms();
                const timer = setTimeout(() => ctrl.abort(), timeoutMs);
                try {
                    await fetch(url, {mode: 'no-cors', cache: 'no-store', signal: ctrl.signal});
                    return {ok: true, time: ms() - t0};
                } catch (_) {
                    return {ok: false, time: ms() - t0};
                } finally {
                    clearTimeout(timer);
                }
            }

            async function probe(item) {
                // item: {label, url, mode?}
                try {
                    const r = await fetchNoCors(item.url, 7000);
                    return {ok: r.ok, time: r.time};
                } catch (_) {
                    return {ok: false, time: 0};
                }
            }

            function paintRow(prefix, idx, ok, tms) {
                const row = document.querySelector(`tr[data-row="${prefix}-${idx}"]`);
                if (!row) return;
                const statusCell = row.querySelector('[data-status]');
                const timeCell = row.querySelector('[data-time]');
                statusCell.innerHTML = ok
                    ? '<span class="px-2 py-0.5 text-xs rounded-full bg-green-100 text-green-800">доступен</span>'
                    : '<span class="px-2 py-0.5 text-xs rounded-full bg-red-100 text-red-800">недоступен</span>';
                timeCell.textContent = Math.round(tms) + ' мс';
            }

            async function testTable(prefix, list) {
                const results = [];
                for (let i = 0; i < list.length; i++) {
                    const r = await probe(list[i]);
                    results.push({label: list[i].label, ok: r.ok, time: r.time});
                    paintRow(prefix, i, r.ok, r.time);
                }
                return results;
            }

            async function testResources() {
                return {
                    must: await testTable('must', must),
                    blocked: await testTable('blocked', blocked),
                };
            }

            async function testDoH() {
                const rows = Array.from(document.querySelectorAll('#tblDoh tbody tr'));
                const out = {};
                const google = async (domain) => {
                    const t0 = ms();
                    try {
                        const r = await fetch(`https://dns.google/resolve?name=${encodeURIComponent(domain)}&type=A`, {cache: 'no-store'});
                        const dt = ms() - t0;
                        const j = await r.json();
                        const answers = Array.isArray(j.Answer) ? j.Answer.length : 0;
                        return {ok: r.ok && answers > 0 && (j.Status === 0), time: dt, answers};
                    } catch (_) {
                        return {ok: false, time: 0, answers: 0};
                    }
                };
                const cf = async (domain) => {
                    const t0 = ms();
                    try {
                        const r = await fetch(`https://cloudflare-dns.com/dns-query?name=${encodeURIComponent(domain)}&type=A`, {
                            cache: 'no-store', headers: {'Accept': 'application/dns-json'}
                        });
                        const dt = ms() - t0;
                        const j = await r.json();
                        const answers = Array.isArray(j.Answer) ? j.Answer.length : 0;
                        return {ok: r.ok && answers > 0 && (j.Status === 0), time: dt, answers};
                    } catch (_) {
                        return {ok: false, time: 0, answers: 0};
                    }
                };
                for (const row of rows) {
                    const domain = row.getAttribute('data-domain');
                    out[domain] = {google: {ok: false, time: 0, answers: 0}, cf: {ok: false, time: 0, answers: 0}};
                    const g = await google(domain);
                    const c = await cf(domain);
                    out[domain].google = g;
                    out[domain].cf = c;
                    const paint = (cell, r) => {
                        cell.innerHTML = r.ok
                            ? `<span class="px-2 py-0.5 text-xs rounded-full bg-green-100 text-green-800">OK</span> <span class="text-xs text-gray-500">${Math.round(r.time)} мс</span>`
                            : `<span class="px-2 py-0.5 text-xs rounded-full bg-red-100 text-red-800">FAIL</span>`;
                    };
                    paint(row.querySelector('[data-google]'), g);
                    paint(row.querySelector('[data-cf]'), c);
                }
                return out;
            }

            async function probeRegions() {
                const list = @json($regions);
                const out = [];
                for (let i = 0; i < list.length; i++) {
                    const r = await fetchNoCors(list[i].url, 7000);
                    out.push({label: list[i].label, ok: r.ok, time: r.time});
                    const row = document.querySelector(`tr[data-region="${i}"]`);
                    if (!row) continue;
                    const st = row.querySelector('[data-status]');
                    const tm = row.querySelector('[data-time]');
                    st.innerHTML = r.ok
                        ? '<span class="px-2 py-0.5 text-xs rounded-full bg-green-100 text-green-800">доступен</span>'
                        : '<span class="px-2 py-0.5 text-xs rounded-full bg-red-100 text-red-800">недоступен</span>';
                    tm.textContent = Math.round(r.time) + ' мс';
                }
                drawRegionalChart(out);
                return out;
            }

            async function detectWebRTC() {
                const pc = new RTCPeerConnection({iceServers: [{urls: 'stun:stun.l.google.com:19302'}]});
                const discovered = new Set();
                try {
                    pc.createDataChannel('t');
                    const offer = await pc.createOffer();
                    await pc.setLocalDescription(offer);
                    pc.onicecandidate = (e) => {
                        if (!e.candidate) return;
                        const c = e.candidate.candidate;
                        const m = c.match(/candidate:\d+ \d+ \w+ \d+ ([\d.:a-fA-F]+) \d+ typ (\w+)/);
                        if (m && m[1]) discovered.add(m[1]);
                        document.getElementById('webrtc').textContent = [...discovered].join(', ');
                    };
                    await new Promise(r => setTimeout(r, 4000)); // дольше — меньше ложных
                } catch (_) {
                }
                pc.close();
                return [...discovered];
            }

            // Консервативная эвристика VoIP
            async function testVoipReadiness() {
                const ice = [];
                const pc = new RTCPeerConnection({iceServers: [{urls: 'stun:stun.l.google.com:19302'}]});
                try {
                    pc.createDataChannel('t');
                    const offer = await pc.createOffer({iceRestart: true});
                    await pc.setLocalDescription(offer);
                    pc.onicecandidate = (e) => {
                        if (e.candidate) ice.push(e.candidate.candidate);
                    };
                    await new Promise(r => setTimeout(r, 4000));
                } catch (_) {
                }
                pc.close();

                const hasSrflx = ice.some(c => /typ srflx/.test(c));
                const hasRelay = ice.some(c => /typ relay/.test(c));
                const hasHost = ice.some(c => /typ host/.test(c));

                // Консерватизм: считаем OK только если есть srflx ИЛИ relay; иначе — «есть риск проблем»
                const ok = !!(hasSrflx || hasRelay);

                const text = ok
                    ? (hasRelay ? 'Есть TURN/relay — звонки вероятно будут работать.' : 'Найдены srflx — звонки скорее всего будут работать.')
                    : 'UDP/TURN не обнаружен — звонки могут не работать в этой сети.';

                document.getElementById('voipSummary').textContent = `Готовность к звонкам: ${text}`;
                return {candidates: ice, hasSrflx, hasRelay, hasHost, ok};
            }

            // графики
            function drawLatencyChart(samples) {
                if (typeof window.Chart === 'undefined') {
                    // Один раз можно показать мягкое уведомление, что графики отключены
                    console.warn('Chart.js не загружен, пропускаем отрисовку графика задержки');
                    return;
                }
                try {
                    const ctx = document.getElementById('latencyChart').getContext('2d');
                    new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: samples.map((_, i) => i + 1),
                            datasets: [{label: 'RTT, мс', data: samples.map(x => Math.round(x))}]
                        },
                        options: {responsive: true, maintainAspectRatio: false, plugins: {legend: {display: false}}}
                    });
                } catch (e) {
                    console.warn('Ошибка отрисовки графика задержки:', e);
                }
            }

            function drawRegionalChart(rows) {
                if (typeof window.Chart === 'undefined') {
                    console.warn('Chart.js не загружен, пропускаем отрисовку регионального графика');
                    return;
                }
                try {
                    const ctx = document.getElementById('regionalChart').getContext('2d');
                    const labels = rows.map(r => r.label);
                    const data = rows.map(r => Math.round(r.time));
                    new Chart(ctx, {
                        type: 'bar',
                        data: {labels, datasets: [{label: 'Отклик, мс', data}]},
                        options: {responsive: true, maintainAspectRatio: false, plugins: {legend: {display: false}}}
                    });
                } catch (e) {
                    console.warn('Ошибка отрисовки регионального графика:', e);
                }
            }

            // итоги
            function pctBad(arr) {
                const t = (arr || []).length || 1;
                const b = (arr || []).filter(x => !x.ok).length;
                return Math.round((b / t) * 100);
            }

            function composeUserMessage({ip, latency, loss, download, ruSpeed, ru, yt, msg, soc, http80, voip}) {
                const parts = [];
                parts.push(`Внешний IP: ${ip?.ip || '—'}.`);
                parts.push(`Задержка ~${Math.round(latency?.avg || 0)} мс, джиттер ~${Math.round(latency?.jitter || 0)} мс.`);
                parts.push((loss?.lossPct || 0) >= 10 ? `Пакетные потери повышены (${loss?.lossPct || 0}%).` : `Потери низкие (${loss?.lossPct || 0}%).`);
                parts.push(!download?.ok || (download?.mbps || 0) < 5
                    ? `Скорость скачивания низкая (${(download?.mbps || 0).toFixed(1)} Мбит/с).`
                    : `Скорость скачивания достаточная (${(download?.mbps || 0).toFixed(1)} Мбит/с).`);
                parts.push(ruSpeed?.ok ? `.ru-скорость ${ruSpeed.mbps.toFixed(1)} Мбит/с (источник: ${ruSpeed.source_label || '—'}).` : `.ru-скорость не измерена.`);
                if (pctBad(ru) >= 50) parts.push(`Часть .ru/банков/госуслуг недоступна (${pctBad(ru)}%).`);
                if (pctBad(yt) > 0) parts.push(`YouTube частично недоступен (${pctBad(yt)}%).`);
                if (pctBad(msg) > 0) parts.push(`Мессенджеры частично недоступны (${pctBad(msg)}%).`);
                if (pctBad(soc) > 0) parts.push(`Соцсети частично недоступны (${pctBad(soc)}%).`);
                if (pctBad(http80) > 0) parts.push(`HTTP (порт 80) местами недоступен (${pctBad(http80)}%).`);
                parts.push(voip?.ok ? `VoIP/WebRTC: вероятно OK.` : `VoIP/WebRTC: есть риск проблем.`);
                return parts.join(' ');
            }

            function makeVerdict({
                                     latency,
                                     loss,
                                     download,
                                     ruSpeed,
                                     resources,
                                     doh,
                                     regions,
                                     yt,
                                     ru,
                                     msg,
                                     soc,
                                     http80,
                                     voip
                                 }) {
                const v = [];
                if ((loss?.lossPct || 0) >= 10) v.push('Высокие потери пакетов (≥10%).');
                if ((latency?.avg || 0) > 150) v.push('Высокая задержка (>150 мс).');
                if (!download?.ok || (download?.mbps || 0) < 5) v.push('Низкая скорость скачивания (<5 Мбит/с).');
                if (ruSpeed?.ok && (ruSpeed.mbps || 0) < 3) v.push('Низкая .ru-скорость (<3 Мбит/с).');

                const dohFails = Object.values(doh || {}).filter(x => !(x.google?.ok) && !(x.cf?.ok)).length;
                if (dohFails === Object.keys(doh || {}).length && dohFails > 0) v.push('Вероятна блокировка DoH.');
                else if (dohFails > 0) v.push('Частичные проблемы DoH.');

                const blocked = (resources?.blocked || []);
                if (blocked.filter(r => !r.ok).length >= Math.ceil(blocked.length / 2)) v.push('Недоступна большая часть часто блокируемых ресурсов.');

                const slowRegions = (regions || []).filter(r => r.ok && r.time > 700).map(r => r.label);
                if (slowRegions.length) v.push('Высокое время до регионов: ' + slowRegions.join(', ') + '.');

                if (pctBad(ru) >= 50) v.push('.ru/банки/госуслуги — существенные ограничения.');
                if (pctBad(yt) > 0) v.push('Проблемы с YouTube.');
                if (pctBad(msg) > 0) v.push('Проблемы с мессенджерами (вкл. Telegram/WhatsApp).');
                if (pctBad(soc) > 0) v.push('Проблемы с соцсетями.');
                if (pctBad(http80) > 0) v.push('Порт 80 (HTTP) местами недоступен.');
                if (!voip?.ok) v.push('VoIP: UDP/TURN не обнаружен — возможны проблемы со звонками.');

                if (!v.length) v.push('Критичных проблем не обнаружено.');
                return v;
            }

            // оценка качества VPN (0..100)
            function scoreVPN({latency, loss, download, ruSpeed, msg, soc, http80, voip}) {
                let score = 100;
                const lat = latency?.avg || 0;
                const jit = latency?.jitter || 0;
                const lp = loss?.lossPct || 0;
                const dmb = download?.mbps || 0;
                const rmb = ruSpeed?.mbps || 0;

                // штрафы
                if (lat > 50) score -= Math.min(30, Math.round((lat - 50) / 2));
                if (jit > 20) score -= Math.min(20, Math.round((jit - 20) / 1));
                if (lp > 2) score -= Math.min(25, Math.round((lp - 2) * 1.5));
                if (dmb < 10) score -= Math.min(25, Math.round((10 - dmb) * 1.5));
                if (rmb && rmb < 5) score -= Math.min(15, Math.round((5 - rmb) * 2));
                score -= Math.min(10, Math.round(pctBad(msg) / 10));
                score -= Math.min(10, Math.round(pctBad(soc) / 10));
                score -= Math.min(10, Math.round(pctBad(http80) / 10));
                if (!voip?.ok) score -= 10;

                score = Math.max(0, Math.min(100, score));
                return score;
            }

            function renderFindings(humanText, verdictArray) {
                const ul = document.getElementById('findings');
                ul.innerHTML = '';
                const li1 = document.createElement('li');
                li1.textContent = humanText;
                ul.appendChild(li1);
                const li2 = document.createElement('li');
                li2.textContent = 'Техническое резюме: ' + (verdictArray || []).join(' ');
                ul.appendChild(li2);
            }

            async function downloadPDF() {
                if (!window.__lastReport) return;
                const resp = await fetch(reportUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/pdf',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(window.__lastReport)
                });

                const ct = resp.headers.get('Content-Type') || '';
                if (resp.status === 429) {
                    alert('Слишком много запросов. Попробуйте позже.');
                    return;
                }
                if (!resp.ok || !ct.includes('application/pdf')) {
                    let msg = 'Ошибка генерации PDF';
                    try {
                        msg += ': ' + (await resp.text()).slice(0, 500);
                    } catch (_) {
                    }
                    alert(msg);
                    return;
                }

                const blob = await resp.blob();
                const a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = 'network-report.pdf';
                a.click();
                URL.revokeObjectURL(a.href);
            }

            // Сохранение как автономный HTML (без внешних CDN, только таблицы/текст)
            function downloadAsHTML() {
                if (!window.__lastReport) return;
                const R = window.__lastReport;
                const html =
                    `<!doctype html><html lang="ru"><meta charset="utf-8"><title>Netcheck Report (${brand})</title>
<style>body{font-family:Arial,Helvetica,sans-serif;margin:20px;color:#111}h1,h2{margin:0 0 8px}
table{width:100%;border-collapse:collapse;margin-top:8px}th,td{border:1px solid #ddd;padding:6px;font-size:13px}th{text-align:left;background:#f5f5f5}
.badge{display:inline-block;padding:2px 6px;border-radius:4px;font-size:11px}.ok{background:#DCFCE7;color:#166534}.fail{background:#FEE2E2;color:#991B1B}
.small{font-size:12px;color:#444}</style>
<h1>Отчёт проверки сети — ${brand}</h1>
<p class="small">Сгенерировано: ${(new Date()).toLocaleString()}</p>
<h2>Итоги</h2>
<table>
<tr><th>Внешний IP</th><td>${R.summary.ip || '—'}</td></tr>
<tr><th>Страна</th><td>${R.summary.country || '—'}</td></tr>
<tr><th>Пинг (ср.)</th><td>${R.summary.latency_avg_ms || '—'} мс</td></tr>
<tr><th>Джиттер</th><td>${R.summary.jitter_ms || '—'} мс</td></tr>
<tr><th>Потери</th><td>${(R.summary.packet_loss_pct ?? '—')}%</td></tr>
<tr><th>Скорость</th><td>${R.summary.download_mbps || '—'} Мбит/с</td></tr>
<tr><th>.ru-скорость</th><td>${(R.ru_speed && R.ru_speed.ok) ? (R.ru_speed.mbps.toFixed(1) + ' Мбит/с') : '—'}</td></tr>
<tr><th>VoIP</th><td>${(R.voip && R.voip.ok) ? 'OK' : 'Риск проблем'}</td></tr>
</table>`;
                const blob = new Blob([html], {type: 'text/html;charset=utf-8'});
                const a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = 'network-report.html';
                a.click();
                URL.revokeObjectURL(a.href);
            }

            // хранение baseline (direct/vpn)
            function saveBaseline(mode, payload) {
                localStorage.setItem('netcheck:last:' + mode, JSON.stringify(payload));
            }

            function loadBaseline(mode) {
                try {
                    const s = localStorage.getItem('netcheck:last:' + mode);
                    return s ? JSON.parse(s) : null;
                } catch (_) {
                    return null;
                }
            }

            function renderCompare() {
                const A = loadBaseline('direct');
                const B = loadBaseline('vpn');
                const el = (id, v) => document.getElementById(id).textContent = v;
                if (!A || !B) {
                    el('cmpLatency', '—');
                    el('cmpLoss', '—');
                    el('cmpSpeed', '—');
                    el('cmpRuSpeed', '—');
                    el('cmpBlocked', '—');
                    el('cmpMsg', '—');
                    el('cmpScore', '—');
                    return;
                }
                const d = (x, y) => (x != null && y != null) ? (y - x) : null;
                const latA = A.latency?.avg || 0, latB = B.latency?.avg || 0;
                const lossA = A.packetLoss?.lossPct || 0, lossB = B.packetLoss?.lossPct || 0;
                const spA = A.download?.mbps || 0, spB = B.download?.mbps || 0;
                const ruA = A.ru_speed?.mbps || 0, ruB = B.ru_speed?.mbps || 0;
                const blkA = pctBad(A.resources?.blocked || []), blkB = pctBad(B.resources?.blocked || []);
                const msgA = pctBad(A.messengers || []) + pctBad(A.socials || []);
                const msgB = pctBad(B.messengers || []) + pctBad(B.socials || []);
                el('cmpLatency', `${Math.round(latA)} → ${Math.round(latB)} мс (${d(latA, latB) > 0 ? '+' : ''}${Math.round(d(latA, latB))} мс)`);
                el('cmpLoss', `${lossA}% → ${lossB}% (${d(lossA, lossB) > 0 ? '+' : ''}${d(lossA, lossB)} п.п.)`);
                el('cmpSpeed', `${spA.toFixed(1)} → ${spB.toFixed(1)} Мбит/с (${d(spA, spB) > 0 ? '+' : ''}${d(spA, spB).toFixed(1)})`);
                el('cmpRuSpeed', `${ruA.toFixed(1)} → ${ruB.toFixed(1)} Мбит/с (${d(ruA, ruB) > 0 ? '+' : ''}${d(ruA, ruB).toFixed(1)})`);
                el('cmpBlocked', `${blkA}% → ${blkB}% (${d(blkA, blkB) > 0 ? '+' : ''}${d(blkA, blkB)} п.п.)`);
                el('cmpMsg', `${msgA}% → ${msgB}% (${d(msgA, msgB) > 0 ? '+' : ''}${d(msgA, msgB)} п.п.)`);
                el('cmpScore', `${A.vpn?.score ?? '—'} → ${B.vpn?.score ?? '—'}`);
            }

            // выбор режима (radio)
            const modeRadios = document.querySelectorAll('.modeRadio');

            function currentMode() {
                const el = Array.from(modeRadios).find(r => r.checked);
                return el ? el.value : 'direct';
            }

            async function runAll() {
                runBtn.disabled = true;
                pdfBtn.disabled = true;
                saveHtmlBtn.disabled = true;
                env.startedAt = new Date().toISOString();
                loadUA();
                tStart();
                const mode = currentMode(); // 'direct' | 'vpn'

                let ip = {ip: '—', country: '—'}, ipAlt = {service: null, ip: null, country: null};
                let latency = {avg: 0, jitter: 0, samples: []};
                let loss = {lossPct: 0, p50: 0, p95: 0, total: 0, ok: 0};
                let download = {ok: false, mbps: 0};
                let ruSpeed = {ok: false, mbps: 0, source_label: null};
                let resources = {must: [], blocked: []};
                let doh = {}, regions = [];
                let yt = [], ruRes = [], msg = [], soc = [], httpRes = [];
                let webrtc = [], voip = {ok: false};

                try {
                    ip = await step(10, async () => await detectIP());
                    latency = await step(20, async () => await testLatency(15));
                    loss = await step(30, async () => await testPacketLoss(40, 1500, 150));
                    download = await step(40, async () => await testDownload());
                    ruSpeed = await step(45, async () => await testRuSpeed());
                    resources = await step(50, async () => await testResources());
                    doh = await step(60, async () => await testDoH());
                    regions = await step(70, async () => await probeRegions());
                    const ext = await step(80, async () => {
                        const ytR = await testTable('yt', youtube);
                        const ruR = await testTable('ru', ru);
                        const msgR = await testTable('msg', mess);      // Telegram/WhatsApp включены в $mess
                        const socR = await testTable('soc', socials);    // новые соцсети
                        const httpR = await testTable('http', http80);
                        return {yt: ytR, ru: ruR, msg: msgR, soc: socR, http80: httpR};
                    });
                    yt = ext.yt;
                    ruRes = ext.ru;
                    msg = ext.msg;
                    soc = ext.soc;
                    httpRes = ext.http80;

                    const wv = await step(90, async () => {
                        const w = await detectWebRTC();
                        const v = await testVoipReadiness();
                        return {webrtc: w, voip: v};
                    });
                    webrtc = wv.webrtc;
                    voip = wv.voip;

                    // альтернативный IP в самом конце, чтобы не влиять на тайминги
                    ipAlt = await detectIPAlt();

                    env.finishedAt = new Date().toISOString();

                    const vpnScore = scoreVPN({latency, loss, download, ruSpeed, msg, soc, http80: httpRes, voip});

                    window.__lastReport = {
                        summary: {
                            ip: ip.ip, country: ip.country,
                            latency_avg_ms: Math.round(latency.avg),
                            jitter_ms: Math.round(latency.jitter),
                            packet_loss_pct: loss.lossPct,
                            download_mbps: download.mbps ? download.mbps.toFixed(1) : null,
                            webrtc_candidates: webrtc,
                        },
                        latency, packetLoss: loss, download, ru_speed: ruSpeed,
                        resources, doh, regional: regions,
                        youtube: yt, ru_services: ruRes, messengers: msg, socials: soc, http80: httpRes, voip,
                        ip_alt: ipAlt,
                        vpn: {mode, score: vpnScore},
                        env: {ua: env.ua, tz: env.tz},
                        startedAt: env.startedAt, finishedAt: env.finishedAt
                    };

                    // сохранить baseline по режиму
                    saveBaseline(mode, window.__lastReport);
                    renderCompare();

                    const userMsg = composeUserMessage({
                        ip,
                        latency,
                        loss,
                        download,
                        ruSpeed,
                        ru: ruRes,
                        yt,
                        msg,
                        soc,
                        http80: httpRes,
                        voip
                    });
                    const verdictArr = makeVerdict({
                        latency,
                        loss,
                        download,
                        ruSpeed,
                        resources,
                        doh,
                        regions,
                        yt,
                        ru: ruRes,
                        msg,
                        soc,
                        http80: httpRes,
                        voip
                    });
                    renderFindings(userMsg, verdictArr);

                    setProgress(100);
                    tCheckpoint(100, 'success', null);
                    tDone();

                    pdfBtn.disabled = false;
                    saveHtmlBtn.disabled = false;
                } catch (e) {
                    sendTelemetry({
                        event: 'run:error',
                        runId,
                        ts: new Date().toISOString(),
                        error: (e && e.message) ? e.message : String(e),   // <— было message, стало error
                        pct: CURRENT_PCT,
                        label: CHECKPOINT_LABELS[CURRENT_PCT] || null
                    });
                    alert('Что-то пошло не так во время теста. Попробуйте ещё раз.');
                    pdfBtn.disabled = !window.__lastReport;
                } finally {
                    runBtn.disabled = false;
                }
            }

            runBtn.addEventListener('click', runAll);
            pdfBtn.addEventListener('click', downloadPDF);
            saveHtmlBtn.addEventListener('click', downloadAsHTML);
            loadUA();
            renderCompare();
        })();
    </script>
@endsection

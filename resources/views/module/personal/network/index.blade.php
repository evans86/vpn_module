@extends('module.personal.layouts.app')

@section('title', 'Проверка доступности и качества сети')

@section('content')
    <div class="px-4 py-6 sm:px-0">
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Левая колонка -->
            <div class="lg:col-span-1">
                <div class="bg-white shadow rounded-lg p-6 mb-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Старт проверки</h3>
                    <p class="text-sm text-gray-600 mb-3">
                        Тест: IP/гео, пинг/джиттер, пакетные потери, скорость, DoH, региональная доступность.
                    </p>
                    <button id="runBtn"
                            class="w-full inline-flex justify-center items-center px-4 py-2 text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Запустить тест
                    </button>

                    <button id="pdfBtn" disabled
                            class="mt-2 w-full inline-flex justify-center items-center px-4 py-2 text-sm font-medium rounded-md text-indigo-600 bg-indigo-50 hover:bg-indigo-100 focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50">
                        Скачать PDF отчет
                    </button>

                    <div id="progress" class="mt-4 hidden">
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div id="progressBar" class="bg-indigo-600 h-2 rounded-full" style="width: 0%;"></div>
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
                                    <pre
                                        class="mt-2 p-2 bg-gray-50 border border-gray-200 rounded text-xs whitespace-pre-wrap break-words leading-5"
                                        id="ua">
{{ request()->header('User-Agent') }}
            </pre>
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
            </div>

            <!-- Правая колонка -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Качество связи -->
                <div class="bg-white shadow rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Качество связи</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                        <div class="p-4 bg-gray-50 rounded border">
                            <div class="text-gray-500 text-xs mb-1" title="Как быстро откликается сеть. Меньше — лучше.">Задержка (пинг)</div>
                            <div class="text-2xl font-semibold"><span id="latencyAvg">—</span> <span
                                    class="text-sm">мс</span></div>
                        </div>
                        <div class="p-4 bg-gray-50 rounded border">
                            <div class="text-gray-500 text-xs mb-1" title="Насколько прыгает задержка между запросами.">Джиттер</div>
                            <div class="text-2xl font-semibold"><span id="latencyJitter">—</span> <span class="text-sm">мс</span>
                            </div>
                        </div>
                        <div class="p-4 bg-gray-50 rounded border">
                            <div class="text-gray-500 text-xs mb-1" title="Сколько запросов теряется по дороге. >10% уже заметно.">Потери пакетов</div>
                            <div class="text-2xl font-semibold"><span id="lossPct">—</span> <span
                                    class="text-sm">%</span></div>
                        </div>
                        <div class="p-4 bg-gray-50 rounded border">
                            <div class="text-gray-500 text-xs mb-1" title="Как быстро загрузятся файлы и видео.">Скорость скачивания</div>
                            <div class="text-2xl font-semibold"><span id="downloadMbps">—</span> <span class="text-sm">Мбит/с</span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <canvas id="latencyChart" height="120"></canvas>
                    </div>
                </div>

                <!-- Доступность ресурсов -->
                <div class="bg-white shadow rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Доступность ресурсов</h3>

                    <h4 class="text-sm font-semibold text-gray-700 mb-2">Должны открываться</h4>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm" id="tblMust">
                            <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-gray-500">Ресурс</th>
                                <th class="px-4 py-2 text-left text-gray-500">URL</th>
                                <th class="px-4 py-2 text-left text-gray-500">Статус</th>
                                <th class="px-4 py-2 text-left text-gray-500">Время</th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                            @foreach(config('networkcheck.resources_must') as $i => $t)
                                <tr data-row="must-{{ $i }}">
                                    <td class="px-4 py-2">{{ $t['label'] }}</td>
                                    <td class="px-4 py-2 text-blue-600 break-all">{{ $t['url'] }}</td>
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
                                <th class="px-4 py-2 text-left text-gray-500">URL</th>
                                <th class="px-4 py-2 text-left text-gray-500">Статус</th>
                                <th class="px-4 py-2 text-left text-gray-500">Время</th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                            @foreach(config('networkcheck.resources_often_blocked') as $i => $t)
                                <tr data-row="blocked-{{ $i }}">
                                    <td class="px-4 py-2">{{ $t['label'] }}</td>
                                    <td class="px-4 py-2 text-blue-600 break-all">{{ $t['url'] }}</td>
                                    <td class="px-4 py-2" data-status>—</td>
                                    <td class="px-4 py-2" data-time>—</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- DoH диагностика -->
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
                            @foreach(config('networkcheck.doh_domains') as $d)
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

                <!-- Региональные пробы -->
                <div class="bg-white shadow rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Региональная доступность</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm" id="tblRegions">
                            <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-gray-500">Регион</th>
                                <th class="px-4 py-2 text-left text-gray-500">URL</th>
                                <th class="px-4 py-2 text-left text-gray-500">Статус</th>
                                <th class="px-4 py-2 text-left text-gray-500">Время</th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                            @foreach(config('networkcheck.regional_probes') as $i => $p)
                                <tr data-region="{{ $i }}">
                                    <td class="px-4 py-2">{{ $p['label'] }}</td>
                                    <td class="px-4 py-2 text-blue-600 break-all">{{ $p['url'] }}</td>
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
            const RUN_LABELS = {
                ip: 'Определяем IP/гео',
                latency: 'Пинг/джиттер',
                loss: 'Пакетные потери',
                speed: 'Скорость скачивания',
                res: 'Доступность ресурсов',
                doh: 'DNS поверх HTTPS',
                regions: 'Региональные пробы',
                webrtc: 'WebRTC кандидаты',
                done: 'Готово'
            };

            const runBtn = document.getElementById('runBtn');
            const pdfBtn = document.getElementById('pdfBtn');
            const progress = document.getElementById('progress');
            const pbar = document.getElementById('progressBar');
            const ptext = document.getElementById('progressText');

            const env = {
                ua: navigator.userAgent,
                tz: Intl.DateTimeFormat().resolvedOptions().timeZone || 'unknown',
                startedAt: null,
                finishedAt: null,
            };

            // Helpers
            const setProgress = (pct, key) => {
                progress.classList.remove('hidden');
                pbar.style.width = pct + '%';
                ptext.textContent = `Готово ${pct}% — ${RUN_LABELS[key] || ''}`;
            };
            const ms = () => performance.now();

            async function fetchWithTimeout(url, timeoutMs) {
                const ctrl = new AbortController();
                const t = setTimeout(() => ctrl.abort(), timeoutMs);
                const t0 = ms();
                try {
                    await fetch(url, {signal: ctrl.signal, cache: 'no-store'});
                    return {ok: true, time: ms() - t0};
                } catch (e) {
                    return {ok: false, time: timeoutMs};
                } finally {
                    clearTimeout(t);
                }
            }

            async function fetchJSON(url, opts = {}) {
                const t0 = ms();
                const resp = await fetch(url, opts);
                const dt = ms() - t0;
                let data = null;
                try {
                    data = await resp.json();
                } catch (e) {}
                return {ok: resp.ok || resp.type === 'opaque', time: dt, data, status: resp.status, type: resp.type};
            }

            async function detectIP() {
                let ip = '—', country = '—';
                try {
                    const a = await fetchJSON('https://api.ipify.org?format=json', {cache: 'no-store'});
                    if (a?.data?.ip) ip = a.data.ip;
                } catch (e) {}
                try {
                    const b = await fetchJSON('https://ipapi.co/json/', {cache: 'no-store'});
                    if (b?.data?.country_name) country = b.data.country_name + (b?.data?.city ? `, ${b.data.city}` : '');
                } catch (e) {}
                document.getElementById('ip').textContent = ip;
                document.getElementById('country').textContent = country;
                return {ip, country};
            }

            async function testLatency(samples = 15) {
                const url = @json(route('personal.network.ping'));
                const rtts = [];
                for (let i = 0; i < samples; i++) {
                    const t0 = ms();
                    try { await fetch(url, {cache: 'no-store'}); } catch (e) {}
                    rtts.push(ms() - t0);
                    await new Promise(r => setTimeout(r, 100));
                }
                const avg = rtts.reduce((a, b) => a + b, 0) / rtts.length;
                const jitter = Math.sqrt(rtts.reduce((a, b) => a + Math.pow(b - avg, 2), 0) / rtts.length);
                document.getElementById('latencyAvg').textContent = Math.round(avg);
                document.getElementById('latencyJitter').textContent = Math.round(jitter);

                drawLatencyChart(rtts);
                return {samples: rtts, avg, jitter};
            }

            async function testPacketLoss(total = 40, timeout = 1500, gap = 150) {
                const url = @json(route('personal.network.ping'));
                let ok = 0;
                const times = [];
                for (let i = 0; i < total; i++) {
                    const r = await fetchWithTimeout(url, timeout);
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
                const url = @json(route('personal.network.payload', ['size' => '5mb']));
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
                } catch (e) { ok = false; }

                const dt = (ms() - t0) / 1000; // seconds
                const mbits = (received * 8) / (1024 * 1024);
                const speed = dt > 0 ? (mbits / dt) : 0;
                document.getElementById('downloadMbps').textContent = ok ? speed.toFixed(1) : '—';
                return {ok, bytes: received, seconds: dt, mbps: speed};
            }

            async function fetchNoCors(url, timeoutMs = 5000) {
                const ctrl = new AbortController();
                const timer = setTimeout(() => ctrl.abort(), timeoutMs);
                const t0 = ms();
                try {
                    await fetch(url, {mode: 'no-cors', cache: 'no-store', signal: ctrl.signal});
                    return {ok: true, time: ms() - t0};
                } catch (e) {
                    return {ok: false, time: ms() - t0};
                } finally {
                    clearTimeout(timer);
                }
            }

            async function probe({url, mode}) {
                if (mode === 'json') {
                    try {
                        const r = await fetchJSON(url, {cache: 'no-store'});
                        return {ok: r.ok, time: r.time};
                    } catch (e) {
                        return {ok: false, time: 0};
                    }
                }
                // по умолчанию — nocors
                return await fetchNoCors(url, 7000);
            }

            async function testResources() {
                const must = @json(config('networkcheck.resources_must'));
                const blocked = @json(config('networkcheck.resources_often_blocked'));
                const results = {must: [], blocked: []};

                const paintRow = (prefix, idx, ok, t) => {
                    const row = document.querySelector(`tr[data-row="${prefix}-${idx}"]`);
                    if (!row) return;
                    const statusCell = row.querySelector('[data-status]');
                    const timeCell = row.querySelector('[data-time]');
                    statusCell.innerHTML = ok
                        ? '<span class="px-2 py-0.5 text-xs rounded-full bg-green-100 text-green-800">доступен</span>'
                        : '<span class="px-2 py-0.5 text-xs rounded-full bg-red-100 text-red-800">недоступен</span>';
                    timeCell.textContent = Math.round(t) + ' мс';
                };

                for (let i = 0; i < must.length; i++) {
                    const res = await probe(must[i]);
                    results.must.push({url: must[i].url, ok: res.ok, time: res.time});
                    paintRow('must', i, res.ok, res.time);
                }
                for (let i = 0; i < blocked.length; i++) {
                    const res = await probe(blocked[i]);
                    results.blocked.push({url: blocked[i].url, ok: res.ok, time: res.time});
                    paintRow('blocked', i, res.ok, res.time);
                }
                return results;
            }

            async function testDoH() {
                const domains = @json(config('networkcheck.doh_domains'));
                const rows = Array.from(document.querySelectorAll('#tblDoh tbody tr'));
                const out = {};
                for (const d of domains) out[d] = {google: {ok:false,time:0,answers:0}, cf:{ok:false,time:0,answers:0}};

                const google = async (domain) => {
                    const t0 = ms();
                    try {
                        const r = await fetch(`https://dns.google/resolve?name=${encodeURIComponent(domain)}&type=A`, {cache: 'no-store'});
                        const dt = ms() - t0;
                        const j = await r.json();
                        const answers = Array.isArray(j.Answer) ? j.Answer.length : 0;
                        return {ok: r.ok && answers > 0 && (j.Status === 0), time: dt, answers};
                    } catch (e) { return {ok:false,time:0,answers:0}; }
                };
                const cf = async (domain) => {
                    const t0 = ms();
                    try {
                        const r = await fetch(`https://cloudflare-dns.com/dns-query?name=${encodeURIComponent(domain)}&type=A`, {
                            cache: 'no-store',
                            headers: {'Accept': 'application/dns-json'}
                        });
                        const dt = ms() - t0;
                        const j = await r.json();
                        const answers = Array.isArray(j.Answer) ? j.Answer.length : 0;
                        return {ok: r.ok && answers > 0 && (j.Status === 0), time: dt, answers};
                    } catch (e) { return {ok:false,time:0,answers:0}; }
                };

                for (const row of rows) {
                    const domain = row.getAttribute('data-domain');
                    const g = await google(domain);
                    const c = await cf(domain);
                    out[domain].google = g; out[domain].cf = c;

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
                const list = @json(config('networkcheck.regional_probes'));
                const out = [];
                for (let i = 0; i < list.length; i++) {
                    const r = await fetchNoCors(list[i].url, 7000);
                    out.push({label: list[i].label, url: list[i].url, ok: r.ok, time: r.time});

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
                        const m = c.match(/candidate:\d+ \d+ \w+ \d+ ([\d.:a-fA-F]+) \d+/);
                        if (m && m[1]) discovered.add(m[1]);
                        document.getElementById('webrtc').textContent = [...discovered].join(', ');
                    };
                    await new Promise(r => setTimeout(r, 1500));
                } catch (e) {}
                pc.close();
                return [...discovered];
            }

            function drawLatencyChart(samples) {
                const ctx = document.getElementById('latencyChart').getContext('2d');
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: samples.map((_, i) => i + 1),
                        datasets: [{label: 'RTT, мс', data: samples.map(x => Math.round(x))}]
                    },
                    options: {responsive: true, maintainAspectRatio: false, plugins: {legend: {display: false}}}
                });
            }

            function drawRegionalChart(rows) {
                const ctx = document.getElementById('regionalChart').getContext('2d');
                const labels = rows.map(r => r.label);
                const data = rows.map(r => Math.round(r.time));
                new Chart(ctx, {
                    type: 'bar',
                    data: {labels, datasets: [{label: 'Отклик, мс', data}]},
                    options: {responsive: true, maintainAspectRatio: false, plugins: {legend: {display: false}}}
                });
            }

            function composeUserMessage({ip, latency, loss, download, resources}) {
                const blockedBad = (resources?.blocked||[]).filter(r=>!r.ok).length;
                const parts = [];
                parts.push(`Мы проверили подключение. Внешний IP: ${ip.ip || '—'}.`);

                if (loss.lossPct >= 10) {
                    parts.push(`Пакетные потери повышенные (${loss.lossPct}%). Это вызывает подвисания/обрывы. Часто причина — Wi-Fi/мобильная сеть или маршрут провайдера.`);
                } else {
                    parts.push(`Пакетные потери низкие (${loss.lossPct}%).`);
                }

                parts.push(`Задержка ~${Math.round(latency.avg)} мс, джиттер ~${Math.round(latency.jitter)} мс.`);

                if (!download.ok || (download.mbps||0) < 5) {
                    parts.push(`Скорость скачивания низкая (${(download.mbps||0).toFixed(1)} Мбит/с). Это может замедлять загрузку сайтов и видео.`);
                } else {
                    parts.push(`Скорость скачивания достаточная (${(download.mbps||0).toFixed(1)} Мбит/с).`);
                }

                if (blockedBad >= Math.ceil(((resources?.blocked||[]).length || 1)/2)) {
                    parts.push(`Многие популярные сервисы недоступны напрямую в вашем регионе — используйте сервер/профиль VPN, оптимизированный под регион.`);
                }

                parts.push(`Итог: ${loss.lossPct >= 10 || (download.mbps||0) < 5 ? 'наблюдаются проблемы со стабильностью сети' : 'критичных проблем не выявлено'}.`);
                return parts.join(' ');
            }

            function makeVerdict({latency, loss, download, resources, doh, regions}) {
                const v = [];
                if (loss.lossPct >= 10) v.push('Высокие потери пакетов (≥10%).');
                if (latency.avg > 150)  v.push('Высокая средняя задержка (>150 мс).');
                if (!download.ok || (download.mbps||0) < 5) v.push('Низкая скорость скачивания (<5 Мбит/с).');

                const dohFails = Object.values(doh || {}).filter(x => !(x.google?.ok) && !(x.cf?.ok)).length;
                if (dohFails === Object.keys(doh||{}).length && dohFails > 0) {
                    v.push('Вероятна блокировка DoH/HTTPS (оба резолвера не отвечают).');
                } else if (dohFails > 0) {
                    v.push('Частичные проблемы с DoH.');
                }

                const blocked = (resources?.blocked||[]);
                if (blocked.filter(r => !r.ok).length >= Math.ceil(blocked.length/2)) {
                    v.push('Недоступна большая часть часто блокируемых ресурсов.');
                }

                const slowRegions = (regions||[]).filter(r=>r.ok && r.time>700).map(r=>r.label);
                if (slowRegions.length) v.push('Высокое время до регионов: ' + slowRegions.join(', ') + '.');

                if (!v.length) v.push('Критичных проблем не обнаружено.');
                return v;
            }

            function renderFindings(humanText, verdictArray) {
                const ul = document.getElementById('findings');
                ul.innerHTML = '';

                const li1 = document.createElement('li');
                li1.textContent = humanText; // «Объяснение для пользователя»
                ul.appendChild(li1);

                const li2 = document.createElement('li');
                li2.textContent = 'Техническое резюме: ' + verdictArray.join(' ');
                ul.appendChild(li2);
            }

            async function downloadPDF() {
                if (!window.__lastReport) return;
                const url = @json(route('personal.network.report'));
                const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                const resp = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/pdf'
                    },
                    body: JSON.stringify(window.__lastReport)
                });

                const ct = resp.headers.get('Content-Type') || '';
                if (!resp.ok || !ct.includes('application/pdf')) {
                    let msg = 'Ошибка генерации PDF';
                    try { msg += ': ' + (await resp.text()).slice(0, 500); } catch (e) {}
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

            async function runAll() {
                try {
                    runBtn.disabled = true;
                    pdfBtn.disabled = true;
                    env.startedAt = new Date().toISOString();

                    setProgress(5, 'ip');
                    const ip = await detectIP();

                    setProgress(20, 'latency');
                    const latency = await testLatency(15);

                    setProgress(35, 'loss');
                    const loss = await testPacketLoss(40, 1500, 150);

                    setProgress(50, 'speed');
                    const download = await testDownload();

                    setProgress(65, 'res');
                    const resources = await testResources();

                    setProgress(78, 'doh');
                    const doh = await testDoH();

                    setProgress(90, 'regions');
                    const regions = await probeRegions();

                    setProgress(95, 'webrtc');
                    const webrtc = await detectWebRTC();

                    env.finishedAt = new Date().toISOString();
                    setProgress(100, 'done');

                    // Сформируем отчет для PDF
                    window.__lastReport = {
                        summary: {
                            ip: ip.ip, country: ip.country,
                            latency_avg_ms: Math.round(latency.avg),
                            jitter_ms: Math.round(latency.jitter),
                            packet_loss_pct: loss.lossPct,
                            download_mbps: download.mbps ? download.mbps.toFixed(1) : null,
                            webrtc_candidates: webrtc,
                        },
                        latency,
                        packetLoss: loss,
                        download,
                        resources,
                        doh,
                        regional: regions,
                        env: {ua: env.ua, tz: env.tz},
                        startedAt: env.startedAt,
                        finishedAt: env.finishedAt
                    };

                    // Итоги (объяснение + тех.резюме) и включаем PDF
                    const userMsg    = composeUserMessage({ ip, latency, loss, download, resources });
                    const verdictArr = makeVerdict({ latency, loss, download, resources, doh, regions });
                    renderFindings(userMsg, verdictArr);
                    pdfBtn.disabled = false;
                } catch (e) {
                    console.error(e);
                    alert('Что-то пошло не так во время теста. Попробуйте ещё раз.');
                    pdfBtn.disabled = !window.__lastReport;
                } finally {
                    runBtn.disabled = false;
                }
            }

            runBtn.addEventListener('click', runAll);
            pdfBtn.addEventListener('click', downloadPDF);
            document.getElementById('ua').textContent = navigator.userAgent;
        })();
    </script>
@endsection

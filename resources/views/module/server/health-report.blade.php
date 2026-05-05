@extends('layouts.admin')

@section('title', $title)
@section('page-title', $pageTitle)

@section('content')
    @php
        $fm = $fleetReportMeta ?? [
            'panel_targets_count' => 0,
            'our_domains_targets_count' => 0,
        ];
    @endphp
    <div class="space-y-5">
        <x-admin.card title="О разделе">
            <div class="text-sm text-slate-700 space-y-3">
                <p>
                    Страница совмещает проверку <strong>с вашего браузера</strong> (задержка и скачивание до панели)
                    и <strong>с сервера приложения</strong> (Laravel): доступность HTTP/HTTPS на IP каждого VPS,
                    статус заглушки из БД, опционально приманка и полный отчёт <code class="text-xs bg-slate-100 px-1 rounded">/test-speed</code>,
                    плюс замеры до панелей и «своих» доменов по настройкам окружения.
                </p>
                <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-800">
                    <div class="font-medium text-slate-900 mb-1">Эвристика «веб vs VPN»</div>
                    <p class="mb-2">
                        Ниже можно подставить IP или домен: эндпоинт классификации возвращает грубую оценку
                        («похоже на веб-фронт / CDN / классический хостинг» vs «вероятнее выход VPN / неклассический набор открытых портов»).
                        Полезно при разборе жалоб («сайт не открывается»), когда нужно понять, не упирается ли обращение в «не тот» хост или в режим блокировки.
                    </p>
                    <div class="text-xs text-slate-600 mb-3">
                        Сейчас в списках целей: панелей <strong>{{ (int) ($fm['panel_targets_count'] ?? 0) }}</strong>,
                        доменов <strong>{{ (int) ($fm['our_domains_targets_count'] ?? 0) }}</strong>.
                    </div>
                    <div class="flex flex-wrap items-end gap-2 pt-1">
                        <div class="min-w-[12rem] flex-1">
                            <label for="fleetClassifyHost" class="block text-xs font-medium text-slate-700 mb-1">Хост или IP</label>
                            <input type="text" id="fleetClassifyHost" maxlength="512" placeholder="например 203.0.113.50 или probe.example.net"
                                   class="w-full text-sm rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" autocomplete="off">
                        </div>
                        <button type="button" id="fleetClassifyBtn"
                                class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md bg-slate-800 text-white hover:bg-slate-900 disabled:opacity-50">
                            Проверить
                        </button>
                    </div>
                    <pre id="fleetClassifyOut" class="hidden text-xs font-mono bg-slate-900 text-green-100 rounded-md p-3 overflow-x-auto max-h-64 overflow-y-auto mt-3"></pre>
                </div>
            </div>

            <div id="globalProbePanel" class="hidden mt-4 rounded-md border border-indigo-100 bg-indigo-50/60 px-3 py-2 text-sm">
                <div class="text-xs font-semibold text-indigo-950 mb-1">Результат: с хоста Laravel (ICMP + HTTPS по целям выше)</div>
                <div id="globalProbeBody" class="text-slate-800 space-y-2 font-mono text-xs"></div>
            </div>
        </x-admin.card>

        <x-admin.card title="Проверка">
            <div class="flex flex-wrap items-center gap-x-6 gap-y-3 mb-4">
                <label class="inline-flex items-center gap-2 cursor-pointer text-sm text-slate-800">
                    <input type="checkbox" id="includeTestSpeed"
                           class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 shrink-0">
                    <span>Полный /test-speed на узлах</span>
                </label>
                <button type="button" id="runCheckBtn"
                        class="inline-flex items-center px-5 py-2.5 text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50">
                    <i class="fas fa-play mr-2 text-xs"></i>
                    Запустить проверку
                </button>
                <button type="button" id="saveReportTxt" disabled
                        class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md border border-slate-300 bg-white text-slate-800 hover:bg-slate-50 disabled:opacity-40">
                    Сохранить .txt
                </button>
                <button type="button" id="copyReportBtn" disabled
                        class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md border border-slate-300 bg-white text-slate-800 hover:bg-slate-50 disabled:opacity-40">
                    Копировать
                </button>
            </div>
            <p id="checkStatus" class="text-sm text-slate-700 mb-3 min-h-[1.375rem]"></p>
            <textarea id="fullReport" readonly rows="20"
                      class="w-full font-mono text-sm text-slate-800 border border-slate-300 rounded-md p-3 bg-slate-50 mb-6"></textarea>

            <div id="fleetSummary" class="hidden grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-2 text-sm mb-4"></div>

            <div id="fleetTableCard" class="hidden border border-slate-200 rounded-md overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-slate-800">
                    <tr>
                        <th class="px-2 py-2 text-left font-medium">#</th>
                        <th class="px-2 py-2 text-left font-medium">Имя / IP</th>
                        <th class="px-2 py-2 text-left font-medium">HTTP</th>
                        <th class="px-2 py-2 text-left font-medium">HTTPS</th>
                        <th class="px-2 py-2 text-left font-medium">Заглушка БД</th>
                        <th class="px-2 py-2 text-left font-medium">/123.rar</th>
                        <th class="px-2 py-2 text-left font-medium">/test-speed <span class="font-normal text-slate-500">(скорость)</span></th>
                    </tr>
                    </thead>
                    <tbody id="fleetTableBody" class="divide-y divide-slate-100 bg-white"></tbody>
                </table>
            </div>
        </x-admin.card>
    </div>

    @push('scripts')
        <script>
            (function () {
                var pingUrl = @json(route('netcheck.ping'));
                var payloadUrl = @json(route('netcheck.payload', ['size' => '2mb']));
                var fleetRunUrl = @json(route('admin.module.server-fleet.report.run'));
                var classifyUrl = @json($classifyHostUrl ?? '');
                var btn = document.getElementById('runCheckBtn');
                var chk = document.getElementById('includeTestSpeed');
                var st = document.getElementById('checkStatus');
                var ta = document.getElementById('fullReport');
                var saveBtn = document.getElementById('saveReportTxt');
                var copyBtn = document.getElementById('copyReportBtn');
                var tbody = document.getElementById('fleetTableBody');
                var sum = document.getElementById('fleetSummary');
                var tableCard = document.getElementById('fleetTableCard');
                var gpPanel = document.getElementById('globalProbePanel');
                var gpBody = document.getElementById('globalProbeBody');
                var meta = document.querySelector('meta[name="csrf-token"]');
                var csrf = meta ? meta.getAttribute('content') : '';

                function esc(s) {
                    return String(s == null ? '' : s)
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;');
                }

                function avg(arr) {
                    var n = arr.filter(function (x) { return x != null; });
                    if (!n.length) return null;
                    var s = 0;
                    n.forEach(function (x) { s += x; });
                    return Math.round(s / n.length);
                }

                function pad2(n) {
                    return (n < 10 ? '0' : '') + n;
                }

                function stampFile() {
                    var d = new Date();
                    return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate())
                        + '_' + pad2(d.getHours()) + '-' + pad2(d.getMinutes()) + '-' + pad2(d.getSeconds());
                }

                function mbpsHeatClass(mbps) {
                    if (mbps == null || isNaN(Number(mbps))) return '';
                    var v = Number(mbps);
                    if (v >= 200) return 'text-emerald-600 font-semibold';
                    if (v >= 80) return 'text-lime-700 font-semibold';
                    if (v >= 35) return 'text-amber-700 font-medium';
                    if (v >= 8) return 'text-orange-700 font-medium';
                    return 'text-rose-700 font-semibold';
                }

                function testSpeedMbpsSpan(tsObj) {
                    var eff = tsObj.effective_mbps;
                    if (eff == null || isNaN(Number(eff)) || Number(eff) <= 0) return '';
                    return ' <span class="' + mbpsHeatClass(Number(eff)) + '">~'
                        + Number(eff).toFixed(1) + ' Мбит/с</span>';
                }

                function latencyToneClass(ms) {
                    if (ms == null || isNaN(Number(ms))) return 'text-slate-600';
                    var v = Number(ms);
                    if (v < 120) return 'text-emerald-700';
                    if (v < 380) return 'text-amber-700';
                    return 'text-rose-700';
                }

                function badge(ok) {
                    return ok
                        ? '<span class="text-emerald-700 font-medium">OK</span>'
                        : '<span class="text-rose-700 font-medium">нет</span>';
                }

                function cellHttpsFleetGlobal(h) {
                    if (!h) return '—';
                    var c = h.code != null ? h.code : '—';
                    var msCls = h.ms != null ? latencyToneClass(h.ms) : 'text-slate-600';
                    var err = h.error ? (' <span class="text-slate-500">' + esc(h.error) + '</span>') : '';
                    var b;
                    if (h.ok) {
                        b = badge(true);
                    } else if (h.code != null && h.code >= 400 && h.code < 500) {
                        b = '<span class="text-amber-700 font-medium">ответ 4xx</span>';
                    } else {
                        b = badge(false);
                    }
                    return b + ' <span class="' + msCls + '">' + esc(String(c))
                        + (h.ms != null ? (' ' + h.ms + ' мс') : '') + '</span>' + err;
                }

                function cellHttp(h) {
                    if (!h) return '—';
                    var c = h.code != null ? h.code : '—';
                    var msCls = h.ms != null ? latencyToneClass(h.ms) : 'text-slate-600';
                    var err = h.error ? (' <span class="text-slate-500">' + esc(h.error) + '</span>') : '';
                    return badge(!!h.ok) + ' <span class="' + msCls + '">' + esc(String(c))
                        + (h.ms != null ? (' ' + h.ms + ' мс') : '') + '</span>' + err;
                }

                function kv(label, val) {
                    return '<div class="rounded border border-slate-200 bg-white px-2 py-1.5 shadow-sm">'
                        + '<div class="text-xs uppercase text-slate-500">' + esc(label) + '</div>'
                        + '<div class="text-base font-semibold text-slate-900">' + esc(val) + '</div></div>';
                }

                async function measurePingMs(url, trials) {
                    var out = [];
                    for (var i = 0; i < trials; i++) {
                        var t0 = performance.now();
                        try {
                            var u = url + (url.indexOf('?') >= 0 ? '&' : '?') + 't=' + Date.now() + '_' + i;
                            var res = await fetch(u, {
                                cache: 'no-store',
                                credentials: 'same-origin'
                            });
                            await res.blob();
                            out.push(Math.round(performance.now() - t0));
                        } catch (e) {
                            out.push(null);
                        }
                        await new Promise(function (r) { setTimeout(r, 120); });
                    }
                    return out;
                }

                async function measureNoCorsMs(url) {
                    var t0 = performance.now();
                    try {
                        await fetch(url, { mode: 'no-cors', cache: 'no-store' });
                        return Math.round(performance.now() - t0);
                    } catch (e) {
                        return null;
                    }
                }

                async function measureDownload(url, maxBytes, label, lines) {
                    var t0 = performance.now();
                    var res = await fetch(url, { cache: 'no-store', credentials: 'same-origin' });
                    if (!res.ok) {
                        lines.push(label + ': ошибка HTTP ' + res.status);
                        return;
                    }
                    var reader = res.body ? res.body.getReader() : null;
                    var total = 0;
                    if (!reader) {
                        var buf = await res.arrayBuffer();
                        total = buf.byteLength;
                    } else {
                        while (true) {
                            var rd = await reader.read();
                            if (rd.done) break;
                            total += rd.value.length;
                            if (maxBytes && total >= maxBytes) {
                                try { await reader.cancel(); } catch (e) {}
                                break;
                            }
                        }
                    }
                    var sec = (performance.now() - t0) / 1000;
                    var mbps = sec > 0 ? (total * 8 / 1e6 / sec) : 0;
                    lines.push(label + ': ' + total + ' байт за ' + sec.toFixed(2) + ' с (~' + mbps.toFixed(2) + ' Мбит/s)');
                }

                async function buildBrowserReport(setPhase) {
                    var lines = [];
                    lines.push('=== Сеть (ваш браузер) ===');
                    lines.push('Страница: ' + window.location.href);
                    lines.push('Время (локально): ' + new Date().toString());
                    try {
                        lines.push('Часовой пояс: ' + Intl.DateTimeFormat().resolvedOptions().timeZone);
                    } catch (e) {
                        lines.push('Часовой пояс: —');
                    }
                    lines.push('User-Agent: ' + navigator.userAgent);
                    lines.push('');

                    try {
                        setPhase('Публичный IP и Geo…');
                        lines.push('--- IP / Geo ---');
                        var rip = await fetch('https://api.ipify.org?format=json', { cache: 'no-store' });
                        var jip = await rip.json();
                        lines.push('IPv4/v6 (ipify): ' + (jip.ip || '—'));
                        try {
                            var rg = await fetch('https://ipwho.is/' + encodeURIComponent(jip.ip), { cache: 'no-store' });
                            var g = await rg.json();
                            if (g.success) {
                                lines.push('Страна: ' + g.country + ' (' + g.country_code + ')');
                                lines.push('Город: ' + (g.city || '—') + ', регион: ' + (g.region || '—'));
                                if (g.connection) {
                                    lines.push('Провайдер: ' + (g.connection.isp || '—') + ', ASN: ' + (g.connection.asn || '—'));
                                }
                            } else {
                                var gm = g.message || 'нет данных';
                                if (/cors/i.test(gm) || /free plan/i.test(gm)) {
                                    gm += ' — типично для бесплатного плана ipwho: браузерный запрос без ключа; на проверку VPS не влияет.';
                                }
                                lines.push('GeoIP: ' + gm);
                            }
                        } catch (e) {
                            lines.push('GeoIP (ipwho): ' + e.message);
                        }
                    } catch (e) {
                        lines.push('Публичный IP: ' + e.message);
                    }
                    lines.push('');

                    setPhase('Задержка до сайта панели…');
                    lines.push('--- До хоста панели ---');
                    var pingTimes = await measurePingMs(pingUrl, 4);
                    lines.push(pingUrl + ' (мс): ' + pingTimes.map(function (x) {
                        return x == null ? '×' : x;
                    }).join(', ') + ' — среднее: ' + (avg(pingTimes) != null ? avg(pingTimes) + ' мс' : '—'));
                    try {
                        await measureDownload(payloadUrl, 2 * 1024 * 1024, 'Скачивание ' + payloadUrl, lines);
                    } catch (e) {
                        lines.push('Скачивание: ' + e.message);
                    }
                    lines.push('');

                    setPhase('Внешние HTTPS (задержка)…');
                    lines.push('--- Внешние точки ---');
                    var ext = [
                        { label: 'Яндекс', url: 'https://yandex.ru/favicon.ico' },
                        { label: 'Google', url: 'https://www.google.com/favicon.ico' },
                        { label: 'Cloudflare', url: 'https://www.cloudflare.com/favicon.ico' },
                        { label: 'Telegram', url: 'https://telegram.org/favicon.ico' }
                    ];
                    var k = 0;
                    for (; k < ext.length; k++) {
                        var ms = await measureNoCorsMs(ext[k].url);
                        lines.push(ext[k].label + ': ' + (ms != null ? ms + ' мс' : '—'));
                    }
                    return lines.join('\n');
                }

                function renderGlobalProbes(gp) {
                    if (!gpBody || !gpPanel) return;
                    gpBody.innerHTML = '';
                    if (!gp || typeof gp !== 'object') {
                        gpPanel.classList.add('hidden');
                        return;
                    }
                    var icmp = gp.icmp_cli_available ? 'ICMP (ping): доступен' : 'ICMP: только HTTPS (ОС/права)';
                    var div0 = document.createElement('div');
                    div0.className = 'text-indigo-900/90';
                    div0.textContent = icmp;
                    gpBody.appendChild(div0);
                    if (gp.meta) {
                        var m = gp.meta;
                        var dm = document.createElement('div');
                        dm.className = 'text-indigo-950/90 mt-1';
                        dm.textContent = 'Целей: панели ' + (m.panels_target_count != null ? m.panels_target_count : '—')
                            + ', домены ' + (m.our_domains_target_count != null ? m.our_domains_target_count : '—')
                            + (m.merge_panels_from_db ? ' · +БД панелей' : '')
                            + (m.merge_app_domain_hosts ? ' · +APP_URL/зеркала' : '');
                        gpBody.appendChild(dm);
                    }

                    function section(title, list) {
                        var h = document.createElement('div');
                        h.className = 'font-semibold text-slate-800 mt-2';
                        h.textContent = title;
                        gpBody.appendChild(h);
                        if (!list || !list.length) {
                            var empty = document.createElement('div');
                            empty.className = 'pl-1 text-slate-500';
                            empty.textContent = 'нет целей в списке';
                            gpBody.appendChild(empty);
                            return;
                        }
                        list.forEach(function (row) {
                            var line = document.createElement('div');
                            line.className = 'pl-1 border-l-2 border-indigo-200 ml-0.5';
                            var https = row.https || {};
                            var ic = row.icmp_ms != null ? ('~' + row.icmp_ms + ' мс') : (row.icmp_error || '—');
                            line.innerHTML = esc(row.raw || '') + ': ICMP ' + esc(String(ic))
                                + '; HTTPS ' + cellHttpsFleetGlobal(https);
                            gpBody.appendChild(line);
                        });
                    }
                    section('Панели', gp.panel_hosts || []);
                    section('Наши домены', gp.our_domains || []);
                    gpPanel.classList.remove('hidden');
                }

                function renderFleetTables(j) {
                    if (!j.success) {
                        sum.classList.add('hidden');
                        tableCard.classList.add('hidden');
                        if (gpPanel) gpPanel.classList.add('hidden');
                        return;
                    }
                    var d = j.data || {};
                    renderGlobalProbes(d.global_probes || null);
                    var s = d.summary || {};
                    sum.innerHTML =
                        kv('Всего', s.total)
                        + kv('HTTP OK', s.http_ok)
                        + kv('HTTPS OK', s.https_ok)
                        + kv('Заглушка БД', s.stub_ok_db)
                        + kv('/123 OK', s.lure_http_ok)
                        + kv('/test-speed OK', s.test_speed_ok)
                        + kv('/test-speed ошиб.', s.test_speed_fail)
                        + kv('/test-speed проп.', s.test_speed_skipped);
                    sum.classList.remove('hidden');

                    tbody.innerHTML = '';
                    (d.rows || []).forEach(function (row) {
                        if (row.error) {
                            var tr = document.createElement('tr');
                            tr.innerHTML =
                                '<td class="px-2 py-1.5 text-slate-600">' + esc(row.server_id) + '</td>' +
                                '<td class="px-2 py-1.5" colspan="6"><span class="text-rose-700">' + esc(row.name || '') + ' — ' + esc(row.ip || '') + ': ' + esc(row.error) + '</span></td>';
                            tbody.appendChild(tr);
                            return;
                        }
                        var lure = row.lure || {};
                        var ts = row.test_speed || {};
                        var lureHtml;
                        if (lure.skipped) {
                            lureHtml = '<span class="text-slate-400">—</span>';
                        } else {
                            lureHtml = (lure.ok ? '<span class="text-emerald-700">OK</span>' : '<span class="text-rose-700">нет</span>')
                                + (lure.code != null ? (' <span class="text-slate-500">' + lure.code + '</span>') : '')
                                + (lure.error ? (' <span class="text-slate-500">' + esc(lure.error) + '</span>') : '');
                        }
                        var tsTxt = '';
                        if ((ts.state || '') === 'skipped') {
                            tsTxt = '<span class="text-slate-500">пропуск</span>'
                                + (ts.error ? (' — ' + esc(ts.error)) : '');
                        } else if ((ts.state || '') === 'ok') {
                            tsTxt = '<span class="text-emerald-700">OK</span>'
                                + (ts.seconds != null ? (' ' + ts.seconds + ' с') : '')
                                + testSpeedMbpsSpan(ts);
                        } else {
                            tsTxt = '<span class="text-rose-700">ошибка</span>'
                                + (ts.error ? (' — ' + esc(ts.error)) : '')
                                + testSpeedMbpsSpan(ts);
                        }
                        var tr2 = document.createElement('tr');
                        tr2.className = 'align-top';
                        tr2.innerHTML =
                            '<td class="px-2 py-1.5 text-slate-600">' + esc(row.server_id) + '</td>' +
                            '<td class="px-2 py-1.5"><div class="font-medium">' + esc(row.name) + '</div><div class="text-slate-500">' + esc(row.ip) + '</div></td>' +
                            '<td class="px-2 py-1.5">' + cellHttp(row.http) + '</td>' +
                            '<td class="px-2 py-1.5">' + cellHttp(row.https) + '</td>' +
                            '<td class="px-2 py-1.5">' + (row.stub_ok_db ? badge(true) : badge(false)) + '</td>' +
                            '<td class="px-2 py-1.5">' + lureHtml + '</td>' +
                            '<td class="px-2 py-1.5 whitespace-pre-wrap">' + tsTxt + '</td>';
                        tbody.appendChild(tr2);
                    });
                    tableCard.classList.remove('hidden');
                }

                var clBtn = document.getElementById('fleetClassifyBtn');
                var clIn = document.getElementById('fleetClassifyHost');
                var clOut = document.getElementById('fleetClassifyOut');
                if (clBtn && clIn && clOut && classifyUrl) {
                    clBtn.addEventListener('click', async function () {
                        var v = (clIn.value || '').trim();
                        if (!v) {
                            clOut.classList.remove('hidden');
                            clOut.textContent = 'Укажите IP или домен.';
                            return;
                        }
                        clBtn.disabled = true;
                        clOut.classList.remove('hidden');
                        clOut.textContent = 'Запрос…';
                        try {
                            var res = await fetch(classifyUrl, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': csrf,
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest'
                                },
                                body: JSON.stringify({ host: v })
                            });
                            var txt = await res.text();
                            try {
                                clOut.textContent = JSON.stringify(JSON.parse(txt), null, 2);
                            } catch (e) {
                                clOut.textContent = txt || res.status;
                            }
                        } catch (err) {
                            clOut.textContent = String(err.message || err);
                        } finally {
                            clBtn.disabled = false;
                        }
                    });
                }

                btn.addEventListener('click', async function () {
                    btn.disabled = true;
                    saveBtn.disabled = true;
                    copyBtn.disabled = true;
                    ta.value = '';
                    sum.classList.add('hidden');
                    tableCard.classList.add('hidden');
                    tbody.innerHTML = '';
                    sum.innerHTML = '';
                    if (gpBody) gpBody.innerHTML = '';
                    if (gpPanel) gpPanel.classList.add('hidden');

                    function setPhase(msg) {
                        st.textContent = msg;
                    }

                    try {
                        setPhase('Сеть (браузер)…');
                        var part1 = await buildBrowserReport(setPhase);

                        setPhase('Серверы панели…');
                        var res = await fetch(fleetRunUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrf,
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: JSON.stringify({
                                include_test_speed: chk.checked
                            })
                        });
                        var j = {};
                        try {
                            j = await res.json();
                        } catch (parseErr) {
                            j = {};
                        }

                        var part2 = '';
                        if (j.success) {
                            renderFleetTables(j);
                            var d = j.data || {};
                            var fleetNote = '';
                            fleetNote += 'Время прогона на сервере (Laravel, все узлы + глобальные цели): '
                                + (d.elapsed_ms != null ? (d.elapsed_ms + ' мс') : '—')
                                + ' · Узлов VPS: ' + (j.included_count != null ? j.included_count : '—')
                                + '\n\n';
                            part2 = '\n\n' + '='.repeat(72) + '\n\n=== Узлы VPS (хост панели) ===\n\n'
                                + fleetNote + (d.text_report || '');
                        } else {
                            renderFleetTables({ success: false });
                            var fleetErr = (j.message != null && j.message !== '') ? String(j.message) : ('HTTP ' + res.status);
                            if (res.status === 429 || /too many attempts/i.test(fleetErr)) {
                                fleetErr = 'Слишком частые запросы (лимит защиты API). Подождите минуту и запустите снова — не жмите кнопку подряд и не держите несколько вкладок с одновременным прогоном.';
                            }
                            part2 = '\n\n' + '='.repeat(72) + '\n\n=== Узлы VPS ===\n\nОшибка: ' + fleetErr;
                        }

                        ta.value = part1 + part2;
                        setPhase(j.success ? 'Готово.' : (res.status === 429 ? 'Лимит запросов.' : 'Частично готово (ошибка проверки флота).'));
                        saveBtn.disabled = false;
                        copyBtn.disabled = false;
                    } catch (err) {
                        setPhase('Ошибка.');
                        ta.value = ta.value ? (ta.value + '\n\n[Ошибка] ' + err.message) : ('[Ошибка] ' + err.message);
                        saveBtn.disabled = !ta.value;
                        copyBtn.disabled = !ta.value;
                    } finally {
                        btn.disabled = false;
                    }
                });

                saveBtn.addEventListener('click', function () {
                    if (!ta.value) return;
                    var blob = new Blob([ta.value], { type: 'text/plain;charset=utf-8' });
                    var a = document.createElement('a');
                    a.href = URL.createObjectURL(blob);
                    a.download = 'network-and-fleet-' + stampFile() + '.txt';
                    document.body.appendChild(a);
                    a.click();
                    URL.revokeObjectURL(a.href);
                    a.remove();
                });

                copyBtn.addEventListener('click', function () {
                    if (!ta.value) return;
                    ta.select();
                    document.execCommand('copy');
                });
            })();
        </script>
    @endpush
@endsection

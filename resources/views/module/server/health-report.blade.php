@extends('layouts.admin')

@section('title', $title)
@section('page-title', $pageTitle)

@section('content')
    <div class="space-y-5">
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
                        <th class="px-2 py-2 text-left font-medium">/test-speed</th>
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
                var btn = document.getElementById('runCheckBtn');
                var chk = document.getElementById('includeTestSpeed');
                var st = document.getElementById('checkStatus');
                var ta = document.getElementById('fullReport');
                var saveBtn = document.getElementById('saveReportTxt');
                var copyBtn = document.getElementById('copyReportBtn');
                var tbody = document.getElementById('fleetTableBody');
                var sum = document.getElementById('fleetSummary');
                var tableCard = document.getElementById('fleetTableCard');
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

                function badge(ok) {
                    return ok
                        ? '<span class="text-emerald-700 font-medium">OK</span>'
                        : '<span class="text-rose-700 font-medium">нет</span>';
                }

                function cellHttp(h) {
                    if (!h) return '—';
                    var c = h.code != null ? h.code : '—';
                    var ms = h.ms != null ? (' ' + h.ms + ' мс') : '';
                    var err = h.error ? (' <span class="text-slate-500">' + esc(h.error) + '</span>') : '';
                    return badge(!!h.ok) + ' <span class="text-slate-600">' + c + ms + '</span>' + err;
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
                                lines.push('GeoIP: ' + (g.message || 'нет данных'));
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

                function renderFleetTables(j) {
                    if (!j.success) {
                        sum.classList.add('hidden');
                        tableCard.classList.add('hidden');
                        return;
                    }
                    var d = j.data || {};
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
                                + (ts.seconds != null ? (' ' + ts.seconds + ' с') : '');
                        } else {
                            tsTxt = '<span class="text-rose-700">ошибка</span>'
                                + (ts.error ? (' — ' + esc(ts.error)) : '');
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

                btn.addEventListener('click', async function () {
                    btn.disabled = true;
                    saveBtn.disabled = true;
                    copyBtn.disabled = true;
                    ta.value = '';
                    sum.classList.add('hidden');
                    tableCard.classList.add('hidden');
                    tbody.innerHTML = '';
                    sum.innerHTML = '';

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
                        var j = await res.json();

                        var part2 = '';
                        if (j.success) {
                            renderFleetTables(j);
                            var d = j.data || {};
                            var fleetNote = '';
                            fleetNote += 'Время панели: ' + (d.elapsed_ms != null ? (d.elapsed_ms + ' мс') : '—')
                                + ' · Серверов: ' + (j.included_count != null ? j.included_count : '—')
                                + '\n\n';
                            part2 = '\n\n' + '='.repeat(72) + '\n\n=== Узлы VPS (хост панели) ===\n\n'
                                + fleetNote + (d.text_report || '');
                        } else {
                            renderFleetTables({ success: false });
                            part2 = '\n\n' + '='.repeat(72) + '\n\n=== Узлы VPS ===\n\nОшибка: ' + (j.message != null ? String(j.message) : 'нет ответа');
                        }

                        ta.value = part1 + part2;
                        setPhase(j.success ? 'Готово.' : 'Частично готово (ошибка панели).');
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

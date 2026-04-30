@extends('layouts.admin')

@section('title', $title)
@section('page-title', $pageTitle)

@section('content')
    <div class="space-y-5">
        {{-- Проверка из браузера: сеть того, кто открыл страницу --}}
        <x-admin.card title="Проверка с вашей стороны">
            <p class="text-sm text-slate-700 mb-4 max-w-3xl">
                Нажмите кнопку — замеры выполняются в <strong class="font-normal">вашем браузере</strong> (ваш провайдер и регион).
                Результат можно скопировать или сохранить в файл .txt.
            </p>
            <div class="flex flex-wrap items-center gap-3 mb-4">
                <button type="button" id="clientProbeBtn"
                        class="inline-flex items-center px-5 py-2.5 text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50">
                    <i class="fas fa-play mr-2 text-xs"></i>
                    Запустить проверку
                </button>
                <button type="button" id="clientProbeSaveTxt" disabled
                        class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md border border-slate-300 bg-white text-slate-800 hover:bg-slate-50 disabled:opacity-40">
                    Сохранить .txt
                </button>
                <button type="button" id="clientProbeCopy" disabled
                        class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md border border-slate-300 bg-white text-slate-800 hover:bg-slate-50 disabled:opacity-40">
                    Копировать
                </button>
            </div>
            <p id="clientProbeStatus" class="text-sm text-slate-600 mb-3 min-h-[1.25rem]"></p>
            <textarea id="clientProbeReport" readonly rows="22" placeholder="Отчёт появится здесь после проверки…"
                      class="w-full font-mono text-sm text-slate-800 border border-slate-300 rounded-md p-3 bg-slate-50"></textarea>
        </x-admin.card>

        {{-- Массовый опрос VPS с бэкенда панели — по желанию --}}
        <details class="group border border-slate-200 rounded-lg bg-white shadow-sm">
            <summary class="cursor-pointer list-none px-4 py-3 text-sm font-medium text-slate-800 flex items-center gap-2 hover:bg-slate-50 rounded-lg">
                <i class="fas fa-chevron-right text-slate-400 group-open:rotate-90 transition-transform text-xs"></i>
                Проверка ваших VPS с сервера панели
                <span class="text-slate-500 font-normal">({{ $configuredCount }} в статусе «Настроен»)</span>
            </summary>
            <div class="px-4 pb-4 pt-0 space-y-4 border-t border-slate-100">
                <p class="text-sm text-slate-600 pt-3">
                    Это не ваша «домашняя» сеть, а обход узлов <strong class="font-normal">с хоста, где установлена панель</strong>.
                    Опция <code class="text-xs bg-slate-100 px-1 rounded">/test-speed</code> может занимать долго на каждый сервер.
                </p>
                <x-admin.card title="">
                    <div class="flex flex-wrap items-center gap-4 text-sm">
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" id="fleetIncludeTestSpeed" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                            <span>Включить полный <code class="text-xs">/test-speed</code></span>
                        </label>
                        <button type="button" id="fleetRunBtn"
                                class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md text-white bg-slate-700 hover:bg-slate-800 disabled:opacity-50">
                            <i class="fas fa-server mr-2 text-xs"></i>
                            Проверить VPS
                        </button>
                    </div>
                    <p id="fleetStatus" class="mt-3 text-sm text-slate-500 hidden"></p>
                </x-admin.card>

                <div id="fleetSummary" class="hidden grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-2 text-sm"></div>

                <div id="fleetTableCard" class="hidden">
                    <x-admin.card title="Результаты по узлам">
                        <div class="overflow-x-auto border border-slate-200 rounded-md">
                            <table class="min-w-full divide-y divide-slate-200 text-xs">
                                <thead class="bg-slate-50 text-slate-700">
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

                <div id="fleetReportCard" class="hidden">
                    <x-admin.card title="Текстовый отчёт (панель → VPS)">
                        <div class="flex flex-wrap gap-2 mb-2">
                            <button type="button" id="fleetCopyReport"
                                    class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded border border-slate-300 bg-white hover:bg-slate-50">
                                <i class="fas fa-copy mr-1.5"></i> Копировать
                            </button>
                            <button type="button" id="fleetSaveTxt"
                                    class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded border border-slate-300 bg-white hover:bg-slate-50">
                                Сохранить .txt
                            </button>
                        </div>
                        <textarea id="fleetTextReport" readonly rows="14"
                                  class="w-full font-mono text-xs text-slate-800 border border-slate-300 rounded-md p-2 bg-slate-50"></textarea>
                    </x-admin.card>
                </div>
            </div>
        </details>
    </div>

    @push('scripts')
        <script>
            (function () {
                var pingUrl = @json(route('netcheck.ping'));
                var payloadUrl = @json(route('netcheck.payload', ['size' => '2mb']));
                var probeBtn = document.getElementById('clientProbeBtn');
                var probeStatus = document.getElementById('clientProbeStatus');
                var probeTa = document.getElementById('clientProbeReport');
                var probeSave = document.getElementById('clientProbeSaveTxt');
                var probeCopy = document.getElementById('clientProbeCopy');

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

                function downloadTxt(text, baseName) {
                    var blob = new Blob([text], { type: 'text/plain;charset=utf-8' });
                    var a = document.createElement('a');
                    a.href = URL.createObjectURL(blob);
                    a.download = baseName + '.txt';
                    document.body.appendChild(a);
                    a.click();
                    URL.revokeObjectURL(a.href);
                    a.remove();
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

                probeBtn.addEventListener('click', async function () {
                    probeBtn.disabled = true;
                    probeSave.disabled = true;
                    probeCopy.disabled = true;
                    probeTa.value = '';
                    probeStatus.textContent = 'Выполняется…';

                    var lines = [];
                    lines.push('=== Сетевые замеры из браузера ===');
                    lines.push('Страница: ' + window.location.href);
                    lines.push('Дата и время (локально): ' + new Date().toString());
                    try {
                        lines.push('Часовой пояс: ' + Intl.DateTimeFormat().resolvedOptions().timeZone);
                    } catch (e) {
                        lines.push('Часовой пояс: —');
                    }
                    lines.push('User-Agent: ' + navigator.userAgent);
                    lines.push('');

                    try {
                        lines.push('--- Публичный IP и GeoIP ---');
                        var rip = await fetch('https://api.ipify.org?format=json', { cache: 'no-store' });
                        var jip = await rip.json();
                        lines.push('Публичный IPv4/IPv6 (ipify): ' + (jip.ip || '—'));
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
                                lines.push('GeoIP (ipwho.is): ' + (g.message || 'нет данных'));
                            }
                        } catch (e) {
                            lines.push('GeoIP (ipwho.is): ошибка — ' + e.message);
                        }
                    } catch (e) {
                        lines.push('Публичный IP (ipify): ошибка — ' + e.message);
                    }
                    lines.push('');

                    try {
                    probeStatus.textContent = 'Задержки до вашего сайта (панель)…';
                    lines.push('--- До хоста панели (ваш трафик → этот сайт) ---');
                    var pingTimes = await measurePingMs(pingUrl, 4);
                    lines.push('GET ' + pingUrl + ' (4 замера, мс): ' + pingTimes.map(function (x) { return x == null ? '×' : x; }).join(', ') + ' — среднее: ' + (avg(pingTimes) != null ? avg(pingTimes) + ' мс' : '—'));

                    try {
                        await measureDownload(payloadUrl, 2 * 1024 * 1024, 'Скачивание с панели (до ~2 MiB, ' + payloadUrl + ')', lines);
                    } catch (e) {
                        lines.push('Скачивание с панели: ошибка — ' + e.message);
                    }
                    lines.push('');

                    probeStatus.textContent = 'Доступность крупных HTTPS-ресурсов (время до ответа, из вашей сети)…';
                    lines.push('--- Доступность внешних сервисов (no-cors, только время) ---');
                    var ext = [
                        { label: 'Яндекс', url: 'https://yandex.ru/favicon.ico' },
                        { label: 'Google', url: 'https://www.google.com/favicon.ico' },
                        { label: 'Cloudflare', url: 'https://www.cloudflare.com/favicon.ico' },
                        { label: 'Telegram', url: 'https://telegram.org/favicon.ico' }
                    ];
                    for (var k = 0; k < ext.length; k++) {
                        var ms = await measureNoCorsMs(ext[k].url);
                        lines.push(ext[k].label + ' (' + ext[k].url + '): ' + (ms != null ? ms + ' мс' : 'ошибка'));
                    }

                    lines.push('');
                    lines.push('--- Конец отчёта ---');

                    var text = lines.join('\n');
                    probeTa.value = text;
                    probeStatus.textContent = 'Готово. Сохраните отчёт в .txt при необходимости.';
                    probeSave.disabled = false;
                    probeCopy.disabled = false;
                    } catch (err) {
                    probeStatus.textContent = 'Прервано из-за ошибки.';
                    var failText = (probeTa.value ? probeTa.value + '\n\n' : '') + '[Ошибка] ' + err.message;
                    probeTa.value = failText;
                    probeSave.disabled = !probeTa.value;
                    probeCopy.disabled = !probeTa.value;
                } finally {
                    probeBtn.disabled = false;
                }
                });

                probeSave.addEventListener('click', function () {
                    if (!probeTa.value) return;
                    downloadTxt(probeTa.value, 'network-check-browser-' + stampFile());
                });

                probeCopy.addEventListener('click', function () {
                    if (!probeTa.value) return;
                    probeTa.select();
                    document.execCommand('copy');
                });
            })();

            (function () {
                var btn = document.getElementById('fleetRunBtn');
                var st = document.getElementById('fleetStatus');
                var sum = document.getElementById('fleetSummary');
                var tbody = document.getElementById('fleetTableBody');
                var tableCard = document.getElementById('fleetTableCard');
                var reportCard = document.getElementById('fleetReportCard');
                var ta = document.getElementById('fleetTextReport');
                var copyBtn = document.getElementById('fleetCopyReport');
                var saveFleetBtn = document.getElementById('fleetSaveTxt');
                var meta = document.querySelector('meta[name="csrf-token"]');
                var csrf = meta ? meta.getAttribute('content') : '';

                function esc(s) {
                    return String(s == null ? '' : s)
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;');
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

                function pad2(n) {
                    return (n < 10 ? '0' : '') + n;
                }

                function stampFile() {
                    var d = new Date();
                    return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate())
                        + '_' + pad2(d.getHours()) + '-' + pad2(d.getMinutes()) + '-' + pad2(d.getSeconds());
                }

                btn.addEventListener('click', function () {
                    btn.disabled = true;
                    st.classList.remove('hidden');
                    st.textContent = 'Выполняется проверка…';
                    sum.classList.add('hidden');
                    sum.innerHTML = '';
                    tableCard.classList.add('hidden');
                    reportCard.classList.add('hidden');
                    tbody.innerHTML = '';

                    fetch(@json(route('admin.module.server-fleet.report.run')), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({
                            include_test_speed: document.getElementById('fleetIncludeTestSpeed').checked
                        })
                    }).then(function (r) { return r.json(); }).then(function (j) {
                        if (!j.success) {
                            st.textContent = j.message || 'Ошибка';
                            return;
                        }
                        var d = j.data || {};
                        var s = d.summary || {};
                        st.textContent = 'Готово за ' + (d.elapsed_ms || '?') + ' мс · серверов: ' + (j.included_count || 0);
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
                        ta.value = d.text_report || '';
                        reportCard.classList.remove('hidden');
                    }).catch(function () {
                        st.textContent = 'Ошибка сети или сервера';
                    }).finally(function () {
                        btn.disabled = false;
                    });
                });

                function kv(label, val) {
                    return '<div class="rounded border border-slate-200 bg-white px-2 py-1.5 shadow-sm">'
                        + '<div class="text-[10px] uppercase text-slate-500">' + esc(label) + '</div>'
                        + '<div class="text-base font-semibold text-slate-900">' + esc(val) + '</div></div>';
                }

                copyBtn.addEventListener('click', function () {
                    ta.select();
                    document.execCommand('copy');
                });

                saveFleetBtn.addEventListener('click', function () {
                    if (!ta.value) return;
                    var blob = new Blob([ta.value], { type: 'text/plain;charset=utf-8' });
                    var a = document.createElement('a');
                    a.href = URL.createObjectURL(blob);
                    a.download = 'fleet-panel-check-' + stampFile() + '.txt';
                    document.body.appendChild(a);
                    a.click();
                    URL.revokeObjectURL(a.href);
                    a.remove();
                });
            })();
        </script>
    @endpush
@endsection

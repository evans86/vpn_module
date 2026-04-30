@extends('layouts.admin')

@section('title', $title)
@section('page-title', $pageTitle)

@section('content')
    <div class="space-y-4">
        @if(session('success'))
            <div class="rounded-md bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3">
                {{ session('success') }}
            </div>
        @endif
        @if($errors->any())
            <div class="rounded-md bg-red-50 border border-red-200 text-red-800 text-sm px-4 py-3 space-y-1">
                @foreach ($errors->all() as $e)
                    <div>{{ $e }}</div>
                @endforeach
            </div>
        @endif

        <p class="text-sm text-slate-700">
            Одна страница для <strong class="font-normal">сводной картины</strong>: здесь ваши VPS проверяются с хоста панели,
            а ниже учитываются отчёты, которые выполняют <strong class="font-normal">в других сетях или странах</strong> вашим же скриптом (GeoIP в тексте).
            Сравните результаты с панели с «видом снаружи» — так проще понять объективность ситуации.
        </p>
        <p class="text-sm text-slate-700">
            В выборке сейчас <span class="font-semibold">{{ $configuredCount }}</span> серверов в статусе <span class="font-medium">«Настроен»</span> с непустым IP.
        </p>
        <p class="text-xs text-slate-600 leading-relaxed max-w-4xl">
            Для каждого узла: HTTP/HTTPS на корень, поля заглушки из БД (последнее применение без префикса «Ошибка:»),
            при необходимости — <code class="text-[11px] bg-slate-100 px-1 rounded">/123.rar</code> (если приманка включена в БД и заглушка успешна).
            Опционально — <strong class="font-normal">полный</strong>
            <code class="text-[11px] bg-slate-100 px-1 rounded">/test-speed</code>
            только при сохранённом токене в БД; на каждый сервер до ~10 мин ожидания ответа.
        </p>

        <div id="external-probes">
            <x-admin.card title="Внешние точки замера (отчёт скриптов с других территорий)">
                <p class="text-xs text-slate-600 mb-4 max-w-4xl">
                    Вставьте текст файла <code class="bg-slate-100 px-1 rounded">report_*.txt</code> или приложите .txt.
                    По блоку GeoIP подставится страна/город — это не новый раздел, а часть той же сводной проверки.
                </p>
                <form method="post"
                      action="{{ route('admin.module.server-fleet.territory-reports.store') }}"
                      enctype="multipart/form-data"
                      class="space-y-4 text-sm mb-8">
                    @csrf
                    <div>
                        <label for="submitter_note" class="block text-xs font-medium text-slate-600 mb-1">Метка (необязательно)</label>
                        <input type="text" name="submitter_note" id="submitter_note" value="{{ old('submitter_note') }}" maxlength="255"
                               class="block w-full max-w-xl rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                               placeholder="Например: коллега, Франкфурт">
                    </div>
                    <div>
                        <label for="report_file" class="block text-xs font-medium text-slate-600 mb-1">Файл (.txt)</label>
                        <input type="file" name="report_file" id="report_file" accept=".txt,text/plain" class="block w-full max-w-xl text-xs text-slate-700">
                    </div>
                    <div>
                        <label for="raw_report" class="block text-xs font-medium text-slate-600 mb-1">Или вставьте текст отчёта</label>
                        <textarea name="raw_report" id="raw_report" rows="6"
                                  class="w-full font-mono text-xs rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                  placeholder="Содержимое report_*.txt">{{ old('raw_report') }}</textarea>
                    </div>
                    <button type="submit"
                            class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md text-white bg-slate-700 hover:bg-slate-800">
                        Сохранить и разобрать GeoIP
                    </button>
                </form>

                <p class="text-xs font-medium text-slate-700 mb-2">Последние сохранённые пробы</p>
                <div class="overflow-x-auto border border-slate-200 rounded-md">
                    <table class="min-w-full divide-y divide-slate-200 text-xs">
                        <thead class="bg-slate-50 text-slate-700">
                        <tr>
                            <th class="px-2 py-2 text-left font-medium">#</th>
                            <th class="px-2 py-2 text-left font-medium">Дата</th>
                            <th class="px-2 py-2 text-left font-medium">Метка</th>
                            <th class="px-2 py-2 text-left font-medium">Территория</th>
                            <th class="px-2 py-2 text-left font-medium">IP</th>
                            <th class="px-2 py-2 text-left font-medium">Режим</th>
                            <th class="px-2 py-2 text-left font-medium"></th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse($recentTerritoryReports as $r)
                            <tr>
                                <td class="px-2 py-2 whitespace-nowrap">{{ $r->id }}</td>
                                <td class="px-2 py-2 whitespace-nowrap text-slate-600">{{ $r->created_at?->format('Y-m-d H:i') }}</td>
                                <td class="px-2 py-2">{{ $r->submitter_note ?? '—' }}</td>
                                <td class="px-2 py-2">
                                    @if($r->country_code || $r->country_name)
                                        <span class="font-medium">{{ $r->country_name }}</span>
                                        @if($r->country_code)
                                            <span class="text-slate-500">({{ $r->country_code }})</span>
                                        @endif
                                        @if($r->city)
                                            <div class="text-slate-600">{{ $r->city }}</div>
                                        @endif
                                    @elseif($r->geo_parse_error)
                                        <span class="text-amber-800">Не распознано</span>
                                        <div class="text-slate-500 max-w-xs truncate" title="{{ $r->geo_parse_error }}">{{ \Illuminate\Support\Str::limit($r->geo_parse_error, 56) }}</div>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-2 py-2 font-mono">{{ $r->public_ip ?? '—' }}</td>
                                <td class="px-2 py-2 max-w-[10rem] truncate" title="{{ $r->mode_label }}">{{ $r->mode_label ?? '—' }}</td>
                                <td class="px-2 py-2 whitespace-nowrap">
                                    <a href="{{ route('admin.module.server-fleet.territory-reports.show', $r) }}"
                                       class="text-indigo-600 hover:text-indigo-800 font-medium">Полный текст</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-2 py-6 text-center text-slate-500">Пока ни одного сохранённого отчёта.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $recentTerritoryReports->fragment('external-probes')->links() }}</div>
            </x-admin.card>
        </div>

        <x-admin.card title="Запуск">
            <div class="flex flex-wrap items-center gap-4 text-sm">
                <label class="inline-flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" id="fleetIncludeTestSpeed" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                    <span>Включить полный <code class="text-xs">/test-speed</code> (до ~10 мин на сервер из-за загрузок и speedtest)</span>
                </label>
                <button type="button" id="fleetRunBtn"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50">
                    <i class="fas fa-play mr-2 text-xs"></i>
                    Запустить проверку
                </button>
            </div>
            <p id="fleetStatus" class="mt-3 text-xs text-slate-500 hidden"></p>
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
            <x-admin.card title="Текстовый отчёт">
                <div class="flex flex-wrap gap-2 mb-2">
                    <button type="button" id="fleetCopyReport"
                            class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded border border-slate-300 bg-white hover:bg-slate-50">
                        <i class="fas fa-copy mr-1.5"></i> Копировать
                    </button>
                </div>
                <textarea id="fleetTextReport" readonly rows="22"
                          class="w-full font-mono text-xs text-slate-800 border border-slate-300 rounded-md p-2 bg-slate-50"></textarea>
            </x-admin.card>
        </div>
    </div>

    @push('scripts')
        <script>
            (function () {
                const btn = document.getElementById('fleetRunBtn');
                const st = document.getElementById('fleetStatus');
                const sum = document.getElementById('fleetSummary');
                const tbody = document.getElementById('fleetTableBody');
                const tableCard = document.getElementById('fleetTableCard');
                const reportCard = document.getElementById('fleetReportCard');
                const ta = document.getElementById('fleetTextReport');
                const copyBtn = document.getElementById('fleetCopyReport');
                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

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
                        st.textContent = 'Готово за ' + (d.elapsed_ms || '?') + ' мс · серверов в отчёте: ' + (j.included_count || 0);
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
            })();
        </script>
    @endpush
@endsection

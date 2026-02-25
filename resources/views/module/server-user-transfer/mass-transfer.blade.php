@extends('layouts.admin')

@section('title', 'Массовый перенос ключей')
@section('page-title', 'Массовый перенос ключей')

@section('content')
    <div class="space-y-6">
        <x-admin.card title="Перенос ключей с нерабочей панели">
            <p class="text-sm text-gray-600 mb-4">
                Используйте эту страницу, когда исходная панель недоступна. Ключи переносятся по данным из БД на выбранную рабочую панель. Ссылка на конфиг не меняется — пользователю достаточно обновить подписку в боте.
            </p>

            <form id="mass-transfer-form" class="space-y-4" x-data="massTransferForm()">
                @csrf
                <input type="hidden" name="max_total" id="mass-transfer-max-total" value="">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="source_panel_id" class="block text-sm font-medium text-gray-700 mb-1">Исходная панель (нерабочая)</label>
                        <select id="source_panel_id" name="source_panel_id" required
                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                x-model="sourcePanelId"
                                @change="loadKeyCount()">
                            <option value="">— Выберите панель —</option>
                            @foreach($panels as $p)
                                <option value="{{ $p->id }}">
                                    Панель #{{ $p->id }} — {{ $p->server->name ?? 'Без сервера' }}
                                    @if($p->panel_status !== 2)
                                        (не настроена)
                                    @endif
                                </option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-sm text-gray-500" x-show="keyCount !== null" x-cloak>
                            <span x-text="'Активных ключей на панели: ' + keyCount"></span>
                        </p>
                    </div>
                    <div>
                        <label for="target_panel_id" class="block text-sm font-medium text-gray-700 mb-1">Целевая панель (рабочая)</label>
                        <select id="target_panel_id" name="target_panel_id" required
                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <option value="">— Выберите панель —</option>
                            @foreach($panels as $p)
                                <option value="{{ $p->id }}">
                                    Панель #{{ $p->id }} — {{ $p->server->name ?? 'Без сервера' }}
                                    @if($p->panel_status !== 2)
                                        (не настроена)
                                    @endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <p class="text-xs text-gray-500" x-show="keyCount !== null && keyCount > 100" x-cloak>
                    При большом количестве ключей (свыше 100) перенос идёт порциями по 100, без таймаута.
                </p>
                <div class="flex items-center gap-4 flex-wrap">
                    <button type="submit" id="btn-submit"
                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed">
                        <span x-show="!loading">Перенести ключи</span>
                        <span x-show="loading" x-cloak>Перенос…</span>
                    </button>
                    <button type="button" id="btn-test-transfer"
                            class="inline-flex items-center px-4 py-2 border border-amber-300 text-sm font-medium rounded-md shadow-sm text-amber-800 bg-amber-50 hover:bg-amber-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500 disabled:opacity-50 disabled:cursor-not-allowed"
                            :disabled="loading || keyCount === 0"
                            title="Перенести только 2 ключа и остановиться, чтобы проверить работу">
                        <i class="fas fa-vial mr-2"></i> Тест (2 ключа)
                    </button>
                </div>
            </form>

            <div id="progress-block" class="mt-6 p-4 rounded-lg border border-blue-200 bg-blue-50 hidden" aria-live="polite">
                <h4 class="font-medium text-gray-900 mb-2">Прогресс</h4>
                <div class="w-full bg-gray-200 rounded-full h-3 mb-2">
                    <div id="progress-bar" class="bg-indigo-600 h-3 rounded-full transition-all duration-300" style="width: 0%"></div>
                </div>
                <p id="progress-text" class="text-sm text-gray-700"></p>
            </div>

            <div id="result-block" class="mt-6 p-4 rounded-lg border hidden" aria-live="polite">
                <h4 class="font-medium text-gray-900 mb-2">Результат</h4>
                <p id="result-message" class="text-sm"></p>
                <div id="result-errors" class="mt-2 text-sm text-red-600 max-h-48 overflow-y-auto hidden"></div>
                <div id="result-report-wrap" class="mt-4 hidden">
                    <div class="flex items-center justify-between gap-2 flex-wrap mb-2">
                        <h5 class="font-medium text-gray-800">Отчёт о перенесённых ключах</h5>
                        <button type="button" id="btn-download-report" class="inline-flex items-center px-3 py-1.5 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            <i class="fas fa-download mr-1.5"></i> Скачать отчёт (CSV)
                        </button>
                    </div>
                    <div class="overflow-x-auto max-h-64 overflow-y-auto border border-gray-200 rounded-md">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 sticky top-0">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">ID ключа</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Пользователь сервера</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Трафик (МБ)</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Окончание</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Telegram ID</th>
                                </tr>
                            </thead>
                            <tbody id="result-report-body" class="bg-white divide-y divide-gray-200"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </x-admin.card>

        <x-admin.card title="Выравнивание нагрузки по панелям">
            <p class="text-sm text-gray-600 mb-4">
                Перенос ключей с самых загруженных панелей на наименее загруженные (тот же принцип, что и выше). Ссылка на конфиг не меняется — пользователю достаточно обновить подписку.
            </p>
            <div class="flex items-center gap-4 mb-4 flex-wrap">
                <button type="button" id="btn-refresh-balance-stats" class="inline-flex items-center px-3 py-1.5 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                    <i class="fas fa-sync-alt mr-1.5"></i> Обновить статистику
                </button>
                <label class="inline-flex items-center gap-2 text-sm text-gray-600">
                    <span>Порция за шаг:</span>
                    <input type="number" id="balance-batch-size" value="50" min="10" max="150" class="rounded border-gray-300 w-20 text-sm">
                </label>
                <label class="inline-flex items-center gap-2 text-sm text-gray-600">
                    <span>Считать выровненным при разнице ≤</span>
                    <input type="number" id="balance-threshold" value="100" min="0" max="2000" class="rounded border-gray-300 w-20 text-sm">
                </label>
            </div>
            <div id="balance-summary" class="mb-4 p-3 bg-gray-50 rounded-lg text-sm hidden">
                <p><strong>Всего пользователей:</strong> <span id="stats-total">—</span></p>
                <p><strong>Среднее на панель:</strong> <span id="stats-average">—</span></p>
                <p><strong>Разница (макс − мин):</strong> <span id="stats-diff">—</span></p>
            </div>
            <div class="overflow-x-auto border border-gray-200 rounded-md mb-4">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Панель</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Сервер</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Активных пользователей</th>
                        </tr>
                    </thead>
                    <tbody id="balance-table-body" class="bg-white divide-y divide-gray-200">
                        <tr><td colspan="3" class="px-3 py-4 text-gray-500 text-center">Нажмите «Обновить статистику»</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="flex items-center gap-4 flex-wrap">
                <button type="button" id="btn-balance" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="fas fa-balance-scale mr-2"></i> Выровнять нагрузку
                </button>
                <button type="button" id="btn-balance-stop" class="hidden inline-flex items-center px-4 py-2 border border-red-300 text-sm font-medium rounded-md shadow-sm text-red-700 bg-red-50 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                    <i class="fas fa-stop mr-2"></i> Остановить
                </button>
            </div>
            <div id="balance-progress" class="mt-6 p-4 rounded-lg border border-blue-200 bg-blue-50 hidden" aria-live="polite">
                <h4 class="font-medium text-gray-900 mb-2">Прогресс</h4>
                <p id="balance-progress-text" class="text-sm text-gray-700"></p>
            </div>
            <div id="balance-result" class="mt-6 p-4 rounded-lg border hidden" aria-live="polite">
                <h4 class="font-medium text-gray-900 mb-2">Результат</h4>
                <p id="balance-result-message" class="text-sm"></p>
            </div>
        </x-admin.card>
    </div>

    <script>
        function massTransferForm() {
            return {
                sourcePanelId: '',
                keyCount: null,
                loading: false,
                loadKeyCount() {
                    const id = this.sourcePanelId;
                    this.keyCount = null;
                    if (!id) return;
                    fetch('{{ route('admin.module.server-user-transfer.mass-transfer.key-count') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || document.querySelector('input[name="_token"]')?.value,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({ panel_id: id }),
                    })
                        .then(r => r.json())
                        .then(data => {
                            this.keyCount = data.count;
                            var el = document.getElementById('source_panel_id');
                            if (el) el.dataset.count = data.count;
                        })
                        .catch(() => { this.keyCount = 0; });
                },
            };
        }

        var BATCH_SIZE = 100;
        var totalKeys = 0;
        var lastTransferReport = [];

        document.getElementById('btn-download-report').addEventListener('click', function () {
            if (!lastTransferReport.length) return;
            var headers = ['key_activate_id', 'server_user_id', 'traffic_limit_mb', 'traffic_limit_bytes', 'expire_date', 'finish_at', 'user_tg_id'];
            var rows = [headers.join(',')];
            lastTransferReport.forEach(function (r) {
                rows.push([
                    (r.key_activate_id || '').replace(/"/g, '""'),
                    (r.server_user_id || '').replace(/"/g, '""'),
                    r.traffic_limit_mb != null ? r.traffic_limit_mb : '',
                    r.traffic_limit_bytes != null ? r.traffic_limit_bytes : '',
                    (r.expire_date || '').replace(/"/g, '""'),
                    r.finish_at != null ? r.finish_at : '',
                    r.user_tg_id != null ? r.user_tg_id : ''
                ].map(function (cell) { return typeof cell === 'string' && (cell.indexOf(',') >= 0 || cell.indexOf('"') >= 0) ? '"' + cell + '"' : cell; }).join(','));
            });
            var csv = '\uFEFF' + rows.join('\r\n');
            var blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
            var a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = 'mass-transfer-report-' + new Date().toISOString().slice(0, 10) + '.csv';
            a.click();
            URL.revokeObjectURL(a.href);
        });

        document.getElementById('btn-test-transfer').addEventListener('click', function () {
            var form = document.getElementById('mass-transfer-form');
            var input = document.getElementById('mass-transfer-max-total');
            if (input) input.value = '2';
            form.requestSubmit();
        });

        document.getElementById('mass-transfer-form').addEventListener('submit', function (e) {
            e.preventDefault();
            const form = this;
            const btn = document.getElementById('btn-submit');
            const resultBlock = document.getElementById('result-block');
            const resultMessage = document.getElementById('result-message');
            const resultErrors = document.getElementById('result-errors');
            const progressBlock = document.getElementById('progress-block');
            const progressBar = document.getElementById('progress-bar');
            const progressText = document.getElementById('progress-text');

            totalKeys = parseInt(document.getElementById('source_panel_id').dataset.count || '0', 10) || 0;
            var alpineData = window.Alpine && document.querySelector('[x-data="massTransferForm()"]') && document.querySelector('[x-data="massTransferForm()"]').__x;
            if (alpineData && alpineData.$data.keyCount != null) totalKeys = parseInt(alpineData.$data.keyCount, 10) || totalKeys;

            var maxTotalInput = form.querySelector('input[name="max_total"]');
            if (maxTotalInput && e.submitter && e.submitter.id === 'btn-submit') maxTotalInput.value = '';
            btn.disabled = true;
            resultBlock.classList.add('hidden');
            progressBlock.classList.remove('hidden');
            progressBar.style.width = '0%';
            progressText.textContent = 'Запуск переноса…';

            var totalTransferred = 0;
            var totalFailed = 0;
            var allErrors = [];
            var allTransferredKeys = [];

            function runBatch() {
                var fd = new FormData(form);
                fd.set('batch_size', BATCH_SIZE);
                var maxTotalInputEl = form.querySelector('input[name="max_total"]');
                if (maxTotalInputEl && maxTotalInputEl.value) fd.set('max_total', maxTotalInputEl.value);
                fetch('{{ route('admin.module.server-user-transfer.mass-transfer.run-batch') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': fd.get('_token'),
                        'Accept': 'application/json',
                    },
                    body: fd,
                })
                    .then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); })
                    .then(function (_ref) {
                        var ok = _ref.ok;
                        var data = _ref.data;
                        if (!ok || !data.success) {
                            progressBlock.classList.add('hidden');
                            resultBlock.classList.remove('hidden');
                            resultBlock.classList.remove('border-green-200', 'bg-green-50');
                            resultBlock.classList.add('border-red-200', 'bg-red-50');
                            resultMessage.textContent = data.message || 'Ошибка переноса';
                            resultErrors.classList.add('hidden');
                            if (typeof toastr !== 'undefined') toastr.error(data.message);
                            btn.disabled = false;
                            return;
                        }
                        totalTransferred += data.transferred || 0;
                        totalFailed += data.failed || 0;
                        if (data.errors && data.errors.length) {
                            allErrors = allErrors.concat(data.errors);
                        }
                        if (data.transferred_keys && data.transferred_keys.length) {
                            allTransferredKeys = allTransferredKeys.concat(data.transferred_keys);
                        }
                        var processed = totalTransferred + totalFailed;
                        if (totalKeys === 0 && (processed > 0 || (data.remaining !== undefined && data.remaining > 0))) {
                            totalKeys = processed + (data.remaining || 0);
                        }
                        var pct = totalKeys > 0 ? Math.min(100, Math.round((processed / totalKeys) * 100)) : 0;
                        progressBar.style.width = pct + '%';
                        progressText.textContent = totalKeys > 0
                            ? ('Обработано примерно ' + processed + ' из ' + totalKeys + '. Перенесено: ' + totalTransferred + ', ошибок: ' + totalFailed)
                            : ('Обработано: ' + processed + '. Перенесено: ' + totalTransferred + ', ошибок: ' + totalFailed);
                        if (data.done) {
                            progressBlock.classList.add('hidden');
                            resultBlock.classList.remove('hidden');
                            resultBlock.classList.remove('border-red-200', 'bg-red-50');
                            resultBlock.classList.add('border-green-200', 'bg-green-50');
                            var isTest = data.test_run === true;
                            resultMessage.textContent = isTest
                                ? ('Тестовый перенос завершён. Перенесено: ' + totalTransferred + ', ошибок: ' + totalFailed + '. Остальные ключи не трогались.')
                                : ('Готово. Перенесено: ' + totalTransferred + ', ошибок: ' + totalFailed + '.');
                            if (isTest && maxTotalInputEl) maxTotalInputEl.value = '';
                            if (allErrors.length > 0) {
                                resultErrors.classList.remove('hidden');
                                resultErrors.innerHTML = '<ul class="list-disc pl-5">' +
                                    allErrors.slice(0, 50).map(function (err) {
                                        return '<li>' + (err.key_id || '') + ': ' + (err.message || '') + '</li>';
                                    }).join('') + (allErrors.length > 50 ? '<li class="text-gray-500">… и ещё ' + (allErrors.length - 50) + ' ошибок</li>' : '') + '</ul>';
                            } else {
                                resultErrors.classList.add('hidden');
                                resultErrors.innerHTML = '';
                            }
                            var reportWrap = document.getElementById('result-report-wrap');
                            var reportBody = document.getElementById('result-report-body');
                            if (allTransferredKeys.length > 0 && reportWrap && reportBody) {
                                reportWrap.classList.remove('hidden');
                                reportBody.innerHTML = allTransferredKeys.map(function (r) {
                                    function esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
                                    return '<tr><td class="px-3 py-1.5 font-mono text-xs">' + esc(r.key_activate_id) + '</td><td class="px-3 py-1.5 font-mono text-xs">' + esc(r.server_user_id) + '</td><td class="px-3 py-1.5">' + esc(r.traffic_limit_mb) + '</td><td class="px-3 py-1.5">' + esc(r.expire_date) + '</td><td class="px-3 py-1.5">' + esc(r.user_tg_id) + '</td></tr>';
                                }).join('');
                            } else if (reportWrap) {
                                reportWrap.classList.add('hidden');
                                reportBody.innerHTML = '';
                            }
                            lastTransferReport = allTransferredKeys.slice(0);
                            if (typeof toastr !== 'undefined') toastr.success('Перенос завершён.');
                            if (alpineData && alpineData.$data.loadKeyCount) alpineData.$data.loadKeyCount();
                            btn.disabled = false;
                            return;
                        }
                        runBatch();
                    })
                    .catch(function (err) {
                        progressBlock.classList.add('hidden');
                        resultBlock.classList.remove('hidden');
                        resultBlock.classList.remove('border-green-200', 'bg-green-50');
                        resultBlock.classList.add('border-red-200', 'bg-red-50');
                        resultMessage.textContent = 'Ошибка запроса: ' + (err.message || 'неизвестная ошибка');
                        resultErrors.classList.add('hidden');
                        if (typeof toastr !== 'undefined') toastr.error('Ошибка запроса');
                        btn.disabled = false;
                    });
            }
            runBatch();
        });

        (function () {
            var balancePanels = @json($panels->keyBy('id'));
            var balanceStatsUrl = '{{ route('admin.module.server-user-transfer.balance.stats') }}';
            var balanceStepUrl = '{{ route('admin.module.server-user-transfer.balance.step') }}';
            var balanceCsrf = document.querySelector('meta[name="csrf-token"]') && document.querySelector('meta[name="csrf-token"]').getAttribute('content') || (document.querySelector('input[name="_token"]') && document.querySelector('input[name="_token"]').value);

            function balanceRenderTable(counts) {
                var tbody = document.getElementById('balance-table-body');
                if (!counts || Object.keys(counts).length === 0) {
                    tbody.innerHTML = '<tr><td colspan="3" class="px-3 py-4 text-gray-500 text-center">Нет данных</td></tr>';
                    return;
                }
                var rows = [];
                Object.keys(counts).sort(function (a, b) { return parseInt(a, 10) - parseInt(b, 10); }).forEach(function (panelId) {
                    var p = balancePanels[panelId];
                    var name = p && p.server ? p.server.name : '—';
                    rows.push('<tr><td class="px-3 py-2">Панель #' + panelId + '</td><td class="px-3 py-2">' + name + '</td><td class="px-3 py-2 text-right font-medium">' + counts[panelId] + '</td></tr>');
                });
                tbody.innerHTML = rows.join('');
            }

            function balanceUpdateSummary(data) {
                document.getElementById('stats-total').textContent = data.total != null ? data.total : '—';
                document.getElementById('stats-average').textContent = data.average != null ? data.average : '—';
                document.getElementById('stats-diff').textContent = data.diff != null ? data.diff : '—';
                document.getElementById('balance-summary').classList.remove('hidden');
            }

            function loadBalanceStats() {
                var tbody = document.getElementById('balance-table-body');
                tbody.innerHTML = '<tr><td colspan="3" class="px-3 py-4 text-gray-500 text-center">Загрузка…</td></tr>';
                fetch(balanceStatsUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': balanceCsrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify({}),
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.success && data.counts) {
                            balanceRenderTable(data.counts);
                            balanceUpdateSummary(data);
                        } else {
                            tbody.innerHTML = '<tr><td colspan="3" class="px-3 py-4 text-red-500 text-center">Ошибка загрузки</td></tr>';
                        }
                    })
                    .catch(function () {
                        tbody.innerHTML = '<tr><td colspan="3" class="px-3 py-4 text-red-500 text-center">Ошибка сети</td></tr>';
                    });
            }

            document.getElementById('btn-refresh-balance-stats').addEventListener('click', loadBalanceStats);

            var balanceAborted = false;
            var btnBalanceStop = document.getElementById('btn-balance-stop');

            document.getElementById('btn-balance').addEventListener('click', function () {
                var btn = this;
                var progressBlock = document.getElementById('balance-progress');
                var progressText = document.getElementById('balance-progress-text');
                var resultBlock = document.getElementById('balance-result');
                var resultMessage = document.getElementById('balance-result-message');
                var batchSize = parseInt(document.getElementById('balance-batch-size').value, 10) || 50;
                var threshold = parseInt(document.getElementById('balance-threshold').value, 10) || 100;
                balanceAborted = false;
                btn.disabled = true;
                if (btnBalanceStop) btnBalanceStop.classList.remove('hidden');
                resultBlock.classList.add('hidden');
                progressBlock.classList.remove('hidden');
                progressText.textContent = 'Запуск выравнивания…';
                var totalMoved = 0;

                function runBalanceStep() {
                    if (balanceAborted) return;
                    fetch(balanceStepUrl, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': balanceCsrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                        body: JSON.stringify({ batch_size: batchSize, max_diff_threshold: threshold }),
                    })
                        .then(function (r) { return r.json(); })
                        .then(function (data) {
                            if (balanceAborted) return;
                            if (!data.success) {
                                progressBlock.classList.add('hidden');
                                resultBlock.classList.remove('hidden');
                                resultBlock.classList.add('border-red-200', 'bg-red-50');
                                resultMessage.textContent = data.message || 'Ошибка';
                                btn.disabled = false;
                                if (btnBalanceStop) btnBalanceStop.classList.add('hidden');
                                if (typeof toastr !== 'undefined') toastr.error(data.message);
                                return;
                            }
                            totalMoved += data.moved || 0;
                            if (data.counts) {
                                balanceRenderTable(data.counts);
                                balanceUpdateSummary({
                                    total: Object.values(data.counts).reduce(function (a, b) { return a + b; }, 0),
                                    average: data.counts && Object.keys(data.counts).length ? Math.round(Object.values(data.counts).reduce(function (a, b) { return a + b; }, 0) / Object.keys(data.counts).length) : 0,
                                    diff: data.counts && Object.keys(data.counts).length ? Math.max.apply(null, Object.values(data.counts)) - Math.min.apply(null, Object.values(data.counts)) : 0,
                                });
                            }
                            progressText.textContent = (data.message || '') + ' Всего перенесено: ' + totalMoved;
                            if (data.done) {
                                progressBlock.classList.add('hidden');
                                resultBlock.classList.remove('hidden');
                                resultBlock.classList.remove('border-red-200', 'bg-red-50');
                                resultBlock.classList.add('border-green-200', 'bg-green-50');
                                resultMessage.textContent = 'Выравнивание завершено. Всего перенесено ключей: ' + totalMoved + '.';
                                btn.disabled = false;
                                if (btnBalanceStop) btnBalanceStop.classList.add('hidden');
                                if (typeof toastr !== 'undefined') toastr.success('Выравнивание завершено.');
                                loadBalanceStats();
                                return;
                            }
                            if (balanceAborted) {
                                progressBlock.classList.add('hidden');
                                resultBlock.classList.remove('hidden');
                                resultBlock.classList.remove('border-red-200', 'bg-red-50');
                                resultBlock.classList.add('border-amber-200', 'bg-amber-50');
                                resultMessage.textContent = 'Остановлено пользователем. Перенесено ключей: ' + totalMoved + '.';
                                btn.disabled = false;
                                if (btnBalanceStop) btnBalanceStop.classList.add('hidden');
                                if (typeof toastr !== 'undefined') toastr.info('Выравнивание остановлено.');
                                return;
                            }
                            runBalanceStep();
                        })
                        .catch(function (err) {
                            if (balanceAborted) return;
                            progressBlock.classList.add('hidden');
                            resultBlock.classList.remove('hidden');
                            resultBlock.classList.add('border-red-200', 'bg-red-50');
                            resultMessage.textContent = 'Ошибка запроса: ' + (err.message || 'неизвестная ошибка');
                            btn.disabled = false;
                            if (btnBalanceStop) btnBalanceStop.classList.add('hidden');
                            if (typeof toastr !== 'undefined') toastr.error('Ошибка запроса');
                        });
                }

                btnBalanceStop && btnBalanceStop.addEventListener('click', function () {
                    balanceAborted = true;
                }, { once: true });

                runBalanceStep();
            });
        })();
    </script>
@endsection

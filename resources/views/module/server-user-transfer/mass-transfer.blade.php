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

        <x-admin.card title="Миграция на мульти-провайдер">
            <p class="text-sm text-gray-600 mb-4">
                Добавление недостающих провайдер-слотов к уже активным ключам. У каждого ключа будет по одному слоту на каждый провайдер из настройки (например VDSINA и Timeweb); подписка объединит конфиги — при падении одного сервера пользователь сможет переключиться на другой. Сначала обязательно запустите «Только проверка» или «Тест (2 ключа)». Полная миграция обрабатывает ключи порциями (не все сразу); можно запустить в фоне через очередь и наблюдать за прогрессом.
            </p>

            <div class="mb-6 p-4 rounded-lg border border-gray-200 bg-gray-50">
                <h4 class="font-medium text-gray-900 mb-2">Добавить мульти-провайдер к одному ключу</h4>
                <p class="text-sm text-gray-600 mb-3">Вставьте ID ключа (UUID), проверьте его и добавьте недостающие слоты.</p>
                <div class="flex flex-wrap items-end gap-3 mb-3">
                    <label class="flex-1 min-w-[200px]">
                        <span class="block text-sm font-medium text-gray-700 mb-1">ID ключа</span>
                        <input type="text" id="multi-provider-single-key-id" placeholder="например: 550e8400-e29b-41d4-a716-446655440000"
                               class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm font-mono">
                    </label>
                    <button type="button" id="btn-multi-provider-check-key" class="inline-flex items-center px-3 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        <i class="fas fa-check-circle mr-2"></i> Проверить ключ
                    </button>
                </div>
                <div id="multi-provider-single-check-result" class="mb-3 p-3 rounded-lg border hidden" aria-live="polite">
                    <p id="multi-provider-single-check-message" class="text-sm"></p>
                    <p id="multi-provider-single-check-detail" class="text-xs text-gray-600 mt-1 hidden"></p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <label class="inline-flex items-center gap-2 text-sm text-gray-600">
                        <input type="checkbox" id="multi-provider-single-dry-run" class="rounded border-gray-300">
                        <span>Только проверка (не создавать слоты)</span>
                    </label>
                    <button type="button" id="btn-multi-provider-add-to-key" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                        <i class="fas fa-plus-circle mr-2"></i> Добавить мульти-провайдер к ключу
                    </button>
                </div>
                <div id="multi-provider-single-result" class="mt-3 p-3 rounded-lg border hidden" aria-live="polite">
                    <p id="multi-provider-single-result-message" class="text-sm"></p>
                </div>
            </div>

            <div class="flex items-center gap-4 mb-4 flex-wrap" x-data="{ multiProviderCount: null, multiProviderSlots: [], multiProviderLoading: false }">
                <button type="button" id="btn-multi-provider-count" class="inline-flex items-center px-3 py-1.5 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                        @click="multiProviderLoading = true; fetch('{{ route('admin.module.server-user-transfer.multi-provider-migration.count') }}', { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || document.querySelector('input[name=_token]')?.value, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: '{}' }).then(r => r.json()).then(d => { multiProviderCount = d.count != null ? d.count : 0; multiProviderSlots = d.slots || []; multiProviderLoading = false; if (!d.success && d.message && typeof toastr !== 'undefined') toastr.error(d.message); }).catch(() => { multiProviderLoading = false; if (typeof toastr !== 'undefined') toastr.error('Ошибка запроса счётчика'); });">
                    <i class="fas fa-sync-alt mr-1.5"></i> <span x-text="multiProviderLoading ? 'Загрузка…' : 'Обновить счётчик'"></span>
                </button>
                <p class="text-sm text-gray-600" x-show="multiProviderCount !== null" x-cloak>
                    <span x-text="'Ключей-кандидатов: ' + multiProviderCount"></span>
                    <span x-show="multiProviderSlots.length" x-text="'. Провайдеры: ' + multiProviderSlots.join(', ')"></span>
                </p>
            </div>
            <div class="flex items-center gap-4 mb-4 flex-wrap">
                <label class="inline-flex items-center gap-2 text-sm text-gray-600">
                    <span>Порция за шаг:</span>
                    <input type="number" id="multi-provider-batch-size" value="20" min="1" max="200" class="rounded border-gray-300 w-20 text-sm" title="Меньше порция — меньше риск таймаута (524)">
                </label>
                <label class="inline-flex items-center gap-2 text-sm text-gray-600">
                    <input type="checkbox" id="multi-provider-dry-run" class="rounded border-gray-300">
                    <span>Только проверка (dry-run)</span>
                </label>
            </div>
            <p class="text-sm text-gray-500 mb-3">Обработка идёт порциями (по «Порция за шаг» ключей за один шаг). Либо в этой вкладке — запросы по очереди, вкладку не закрывать; либо в фоне — через очередь, можно закрыть страницу и смотреть прогресс при следующем заходе. <strong>При ошибке 524 (таймаут)</strong> уменьшите порцию до 10–20 или используйте «Запустить в фоне».</p>
            <div class="flex items-center gap-4 flex-wrap">
                <button type="button" id="btn-multi-provider-run-background" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed" title="Запуск в очереди: нужны QUEUE_CONNECTION=database и php artisan queue:work">
                    <i class="fas fa-cloud-upload-alt mr-2"></i> Запустить в фоне (очередь)
                </button>
                <button type="button" id="btn-multi-provider-run" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="fas fa-layer-group mr-2"></i> Запустить в этой вкладке
                </button>
                <button type="button" id="btn-multi-provider-test" class="inline-flex items-center px-4 py-2 border border-amber-300 text-sm font-medium rounded-md shadow-sm text-amber-800 bg-amber-50 hover:bg-amber-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500 disabled:opacity-50 disabled:cursor-not-allowed" title="Обработать только 2 ключа">
                    <i class="fas fa-vial mr-2"></i> Тест (2 ключа)
                </button>
            </div>
            <div id="multi-provider-progress" class="mt-6 p-4 rounded-lg border border-blue-200 bg-blue-50 hidden" aria-live="polite">
                <div class="flex items-center justify-between gap-4 flex-wrap mb-2">
                    <h4 class="font-medium text-gray-900">Прогресс</h4>
                    <button type="button" id="btn-multi-provider-cancel" class="hidden inline-flex items-center px-3 py-1.5 border border-red-300 text-sm font-medium rounded-md text-red-700 bg-red-50 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500" title="Остановить постановку следующих порций в очередь. Уже обработанные ключи сохранятся.">
                        <i class="fas fa-stop mr-1.5"></i> Отменить миграцию
                    </button>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-3 mb-2">
                    <div id="multi-provider-progress-bar" class="bg-indigo-600 h-3 rounded-full transition-all duration-300" style="width: 0%"></div>
                </div>
                <p id="multi-provider-progress-text" class="text-sm text-gray-700"></p>
            </div>
            <div id="multi-provider-result" class="mt-6 p-4 rounded-lg border hidden" aria-live="polite">
                <h4 class="font-medium text-gray-900 mb-2">Результат</h4>
                <p id="multi-provider-result-message" class="text-sm"></p>
                <div id="multi-provider-result-errors" class="mt-2 text-sm text-red-600 max-h-48 overflow-y-auto hidden"></div>
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
            var multiProviderCountUrl = '{{ route('admin.module.server-user-transfer.multi-provider-migration.count') }}';
            var multiProviderCheckKeyUrl = '{{ route('admin.module.server-user-transfer.multi-provider-migration.check-key') }}';
            var multiProviderSingleKeyUrl = '{{ route('admin.module.server-user-transfer.multi-provider-migration.single-key') }}';
            var multiProviderBatchUrl = '{{ route('admin.module.server-user-transfer.multi-provider-migration.run-batch') }}';
            var multiProviderStartUrl = '{{ route('admin.module.server-user-transfer.multi-provider-migration.start') }}';
            var multiProviderCancelUrl = '{{ route('admin.module.server-user-transfer.multi-provider-migration.cancel') }}';
            var multiProviderStatusUrl = '{{ route('admin.module.server-user-transfer.multi-provider-migration.status') }}';
            var multiProviderCsrf = document.querySelector('meta[name="csrf-token"]') && document.querySelector('meta[name="csrf-token"]').getAttribute('content') || (document.querySelector('input[name="_token"]') && document.querySelector('input[name="_token"]').value);

            function multiProviderShowCancelButton(runId) {
                var progressBlock = document.getElementById('multi-provider-progress');
                var btnCancel = document.getElementById('btn-multi-provider-cancel');
                if (progressBlock) progressBlock.dataset.currentRunId = runId || '';
                if (btnCancel) btnCancel.classList.remove('hidden');
            }
            function multiProviderHideCancelButton() {
                var progressBlock = document.getElementById('multi-provider-progress');
                var btnCancel = document.getElementById('btn-multi-provider-cancel');
                if (progressBlock) delete progressBlock.dataset.currentRunId;
                if (btnCancel) btnCancel.classList.add('hidden');
            }
            var MULTI_PROVIDER_CONFIRM_THRESHOLD = 200;
            function multiProviderConfirmLargeRun(count, isDryRun, label) {
                if (isDryRun || count < MULTI_PROVIDER_CONFIRM_THRESHOLD) return true;
                return confirm('Ключей к обработке: ' + count + '. ' + (label || 'Запустить миграцию в фоне?'));
            }

            var lastCheckedKeyId = null;
            var lastCheckCanAdd = false;

            document.getElementById('btn-multi-provider-check-key').addEventListener('click', function () {
                var input = document.getElementById('multi-provider-single-key-id');
                var keyId = (input && input.value) ? input.value.trim() : '';
                var resultBlock = document.getElementById('multi-provider-single-check-result');
                var messageEl = document.getElementById('multi-provider-single-check-message');
                var detailEl = document.getElementById('multi-provider-single-check-detail');
                var btnAdd = document.getElementById('btn-multi-provider-add-to-key');
                if (!keyId) {
                    if (typeof toastr !== 'undefined') toastr.warning('Введите ID ключа');
                    return;
                }
                this.disabled = true;
                fetch(multiProviderCheckKeyUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': multiProviderCsrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify({ key_id: keyId }),
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        resultBlock.classList.remove('hidden');
                        detailEl.classList.add('hidden');
                        if (data.success && data.valid) {
                            resultBlock.classList.remove('border-red-200', 'bg-red-50');
                            resultBlock.classList.add('border-green-200', 'bg-green-50');
                            messageEl.textContent = data.message || 'Ключ подходит.';
                            if (data.missing_slots && data.missing_slots.length) {
                                detailEl.textContent = 'Не хватает провайдеров: ' + data.missing_slots.join(', ');
                                detailEl.classList.remove('hidden');
                            }
                            lastCheckedKeyId = data.key_id;
                            lastCheckCanAdd = data.can_add === true;
                            btnAdd.disabled = !lastCheckCanAdd;
                        } else {
                            resultBlock.classList.remove('border-green-200', 'bg-green-50');
                            resultBlock.classList.add('border-red-200', 'bg-red-50');
                            messageEl.textContent = data.message || 'Ключ не подходит.';
                            lastCheckCanAdd = false;
                            btnAdd.disabled = true;
                        }
                    })
                    .catch(function () {
                        resultBlock.classList.remove('hidden', 'border-green-200', 'bg-green-50');
                        resultBlock.classList.add('border-red-200', 'bg-red-50');
                        messageEl.textContent = 'Ошибка запроса.';
                        btnAdd.disabled = true;
                    })
                    .finally(function () { this.disabled = false; }.bind(this));
            });

            document.getElementById('btn-multi-provider-add-to-key').addEventListener('click', function () {
                var input = document.getElementById('multi-provider-single-key-id');
                var keyId = (input && input.value) ? input.value.trim() : '';
                var dryRun = document.getElementById('multi-provider-single-dry-run') && document.getElementById('multi-provider-single-dry-run').checked;
                var resultBlock = document.getElementById('multi-provider-single-result');
                var messageEl = document.getElementById('multi-provider-single-result-message');
                var btnAdd = this;
                if (!keyId) {
                    if (typeof toastr !== 'undefined') toastr.warning('Введите ID ключа и нажмите «Проверить ключ»');
                    return;
                }
                btnAdd.disabled = true;
                fetch(multiProviderSingleKeyUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': multiProviderCsrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify({ key_id: keyId, dry_run: dryRun }),
                })
                    .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
                    .then(function (res) {
                        resultBlock.classList.remove('hidden');
                        if (res.ok && res.data.success) {
                            resultBlock.classList.remove('border-red-200', 'bg-red-50');
                            resultBlock.classList.add('border-green-200', 'bg-green-50');
                            messageEl.textContent = res.data.message || (res.data.added ? 'Добавлено слотов: ' + res.data.added : 'Готово.');
                            if (typeof toastr !== 'undefined') toastr.success(res.data.message || 'Готово.');
                            if (!dryRun && res.data.added > 0) {
                                document.getElementById('btn-multi-provider-check-key').click();
                            }
                        } else {
                            resultBlock.classList.remove('border-green-200', 'bg-green-50');
                            resultBlock.classList.add('border-red-200', 'bg-red-50');
                            messageEl.textContent = res.data.message || 'Ошибка';
                            if (typeof toastr !== 'undefined') toastr.error(res.data.message);
                        }
                    })
                    .catch(function (err) {
                        resultBlock.classList.remove('hidden', 'border-green-200', 'bg-green-50');
                        resultBlock.classList.add('border-red-200', 'bg-red-50');
                        messageEl.textContent = 'Ошибка запроса: ' + (err.message || 'неизвестная ошибка');
                        if (typeof toastr !== 'undefined') toastr.error('Ошибка запроса');
                    })
                    .finally(function () { btnAdd.disabled = false; });
            });

            function getMultiProviderTotal(cb) {
                fetch(multiProviderCountUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': multiProviderCsrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    body: '{}',
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.success && data.count != null) cb(data.count);
                        else cb(0);
                    })
                    .catch(function () { cb(0); });
            }

            document.getElementById('btn-multi-provider-run').addEventListener('click', function () {
                runMultiProviderMigration(false, null);
            });
            document.getElementById('btn-multi-provider-run-background').addEventListener('click', function () {
                runMultiProviderMigrationBackground();
            });
            document.getElementById('btn-multi-provider-test').addEventListener('click', function () {
                runMultiProviderMigration(false, 2);
            });
            document.getElementById('btn-multi-provider-cancel').addEventListener('click', function () {
                var progressBlock = document.getElementById('multi-provider-progress');
                var runId = progressBlock && progressBlock.dataset.currentRunId;
                if (!runId) return;
                var btnCancel = document.getElementById('btn-multi-provider-cancel');
                if (btnCancel) btnCancel.disabled = true;
                fetch(multiProviderCancelUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': multiProviderCsrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify({ run_id: runId }),
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (typeof toastr !== 'undefined') toastr.info(data.message || 'Запрос на отмену отправлен.');
                        if (btnCancel) btnCancel.disabled = false;
                    })
                    .catch(function () {
                        if (typeof toastr !== 'undefined') toastr.error('Ошибка запроса отмены');
                        if (btnCancel) btnCancel.disabled = false;
                    });
            });

            function checkLatestMigrationAndResumePolling() {
                fetch(multiProviderStatusUrl, { method: 'GET', headers: { 'Accept': 'application/json' } })
                    .then(function (r) { return r.json(); })
                    .then(function (s) {
                        if (!s.success || !s.found || !s.run_id) return;
                        var runId = s.run_id;
                        var progressBlock = document.getElementById('multi-provider-progress');
                        var progressBar = document.getElementById('multi-provider-progress-bar');
                        var progressText = document.getElementById('multi-provider-progress-text');
                        var resultBlock = document.getElementById('multi-provider-result');
                        var resultMessage = document.getElementById('multi-provider-result-message');
                        var resultErrors = document.getElementById('multi-provider-result-errors');
                        var btnBg = document.getElementById('btn-multi-provider-run-background');
                        var btnRun = document.getElementById('btn-multi-provider-run');
                        var btnTest = document.getElementById('btn-multi-provider-test');
                        if (s.done) {
                            multiProviderHideCancelButton();
                            resultBlock.classList.remove('hidden');
                            progressBlock.classList.add('hidden');
                            if (s.error) {
                                resultBlock.classList.add('border-red-200', 'bg-red-50');
                                resultMessage.textContent = s.error;
                            } else {
                                resultBlock.classList.remove('border-red-200', 'bg-red-50');
                                if (s.cancelled) {
                                    resultBlock.classList.add('border-amber-200', 'bg-amber-50');
                                    resultMessage.textContent = 'Миграция отменена. Обработано ключей: ' + (s.processed || 0) + ', добавлено слотов: ' + (s.added_total || 0) + (s.errors && s.errors.length ? ', ошибок: ' + s.errors.length : '') + '.';
                                } else {
                                    resultBlock.classList.add('border-green-200', 'bg-green-50');
                                    resultMessage.textContent = 'Миграция завершена. Обработано ключей: ' + (s.processed || 0) + ', добавлено слотов: ' + (s.added_total || 0) + (s.errors && s.errors.length ? ', ошибок: ' + s.errors.length : '') + '.';
                                }
                                if (s.errors && s.errors.length > 0) {
                                    resultErrors.classList.remove('hidden');
                                    resultErrors.innerHTML = '<ul class="list-disc pl-5">' + s.errors.slice(0, 30).map(function (e) {
                                        return '<li>' + (e.key_id || '') + ': ' + (e.message || '') + '</li>';
                                    }).join('') + (s.errors.length > 30 ? '<li class="text-gray-500">… и ещё ' + (s.errors.length - 30) + ' ошибок</li>' : '') + '</ul>';
                                } else {
                                    resultErrors.classList.add('hidden');
                                    resultErrors.innerHTML = '';
                                }
                            }
                            if (btnBg) btnBg.disabled = false;
                            if (btnRun) btnRun.disabled = false;
                            if (btnTest) btnTest.disabled = false;
                            return;
                        }

                        progressBlock.classList.remove('hidden');
                        resultBlock.classList.add('hidden');
                        multiProviderShowCancelButton(runId);
                        var total = s.total || 0;
                        var processed = s.processed || 0;
                        var added = s.added_total || 0;
                        var isStale = (total === 0 && processed === 0);
                        if (!isStale) {
                            if (btnRun) btnRun.disabled = true;
                            if (btnTest) btnTest.disabled = true;
                            if (btnBg) btnBg.disabled = false;
                        }
                        var pct = total > 0 ? (processed / total) * 100 : 0;
                        if (pct > 0 && pct < 1) pct = 1;
                        if (pct > 100) pct = 100;
                        progressBar.style.width = Math.round(pct) + '%';
                        progressText.textContent = (s.message || '') + ' Обработано: ' + processed + ' из ' + total + ', добавлено слотов: ' + added + (isStale ? '. Можно нажать «Запустить в фоне» для нового запуска.' : '.');

                        function poll() {
                            fetch(multiProviderStatusUrl + '?run_id=' + encodeURIComponent(runId), { method: 'GET', headers: { 'Accept': 'application/json' } })
                                .then(function (r) { return r.json(); })
                                .then(function (s) {
                                    if (!s.found) return;
                                    var total = s.total || 0;
                                    var processed = s.processed || 0;
                                    var added = s.added_total || 0;
                                    var pct = total > 0 ? (processed / total) * 100 : 0;
                                    if (pct > 0 && pct < 1) pct = 1;
                                    if (pct > 100) pct = 100;
                                    progressBar.style.width = Math.round(pct) + '%';
                                    progressText.textContent = 'Обработано: ' + processed + ' из ' + total + ', добавлено слотов: ' + added + '.';
                                    if (s.done) {
                                        multiProviderHideCancelButton();
                                        progressBlock.classList.add('hidden');
                                        resultBlock.classList.remove('hidden');
                                        if (s.error) {
                                            resultBlock.classList.add('border-red-200', 'bg-red-50');
                                            resultMessage.textContent = s.error;
                                        } else {
                                            resultBlock.classList.remove('border-red-200', 'bg-red-50');
                                            if (s.cancelled) {
                                                resultBlock.classList.add('border-amber-200', 'bg-amber-50');
                                                resultMessage.textContent = 'Миграция отменена. Обработано ключей: ' + processed + ', добавлено слотов: ' + added + (s.errors && s.errors.length ? ', ошибок: ' + s.errors.length : '') + '.';
                                            } else {
                                                resultBlock.classList.add('border-green-200', 'bg-green-50');
                                                resultMessage.textContent = 'Миграция завершена. Обработано ключей: ' + processed + ', добавлено слотов: ' + added + (s.errors && s.errors.length ? ', ошибок: ' + s.errors.length : '') + '.';
                                            }
                                            if (s.errors && s.errors.length > 0) {
                                                resultErrors.classList.remove('hidden');
                                                resultErrors.innerHTML = '<ul class="list-disc pl-5">' + s.errors.slice(0, 30).map(function (e) {
                                                    return '<li>' + (e.key_id || '') + ': ' + (e.message || '') + '</li>';
                                                }).join('') + (s.errors.length > 30 ? '<li class="text-gray-500">… и ещё ' + (s.errors.length - 30) + ' ошибок</li>' : '') + '</ul>';
                                            } else {
                                                resultErrors.classList.add('hidden');
                                                resultErrors.innerHTML = '';
                                            }
                                        }
                                        if (btnBg) btnBg.disabled = false;
                                        if (btnRun) btnRun.disabled = false;
                                        if (btnTest) btnTest.disabled = false;
                                        if (typeof toastr !== 'undefined') toastr.success(s.cancelled ? 'Миграция отменена.' : 'Миграция завершена.');
                                        return;
                                    }
                                    setTimeout(poll, 2500);
                                })
                                .catch(function () { setTimeout(poll, 5000); });
                        }
                        setTimeout(poll, 2500);
                    })
                    .catch(function () {});
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', checkLatestMigrationAndResumePolling);
            } else {
                checkLatestMigrationAndResumePolling();
            }

            function runMultiProviderMigrationBackground() {
                var btnBg = document.getElementById('btn-multi-provider-run-background');
                var btnRun = document.getElementById('btn-multi-provider-run');
                var btnTest = document.getElementById('btn-multi-provider-test');
                var progressBlock = document.getElementById('multi-provider-progress');
                var progressBar = document.getElementById('multi-provider-progress-bar');
                var progressText = document.getElementById('multi-provider-progress-text');
                var resultBlock = document.getElementById('multi-provider-result');
                var resultMessage = document.getElementById('multi-provider-result-message');
                var resultErrors = document.getElementById('multi-provider-result-errors');
                var batchSize = parseInt(document.getElementById('multi-provider-batch-size').value, 10) || 50;
                var isDryRun = document.getElementById('multi-provider-dry-run') && document.getElementById('multi-provider-dry-run').checked;

                btnBg.disabled = true;
                btnRun.disabled = true;
                if (btnTest) btnTest.disabled = true;
                resultBlock.classList.add('hidden');
                progressBlock.classList.remove('hidden');
                progressBar.style.width = '0%';
                progressText.textContent = 'Проверка количества…';

                fetch(multiProviderCountUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': multiProviderCsrf, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: '{}' })
                    .then(function (r) { return r.json(); })
                    .then(function (countData) {
                        var totalCount = (countData && countData.count != null) ? parseInt(countData.count, 10) : 0;
                        if (!multiProviderConfirmLargeRun(totalCount, isDryRun, 'Запустить миграцию в фоне?')) {
                            progressBlock.classList.add('hidden');
                            btnBg.disabled = false;
                            btnRun.disabled = false;
                            if (btnTest) btnTest.disabled = false;
                            return Promise.reject(new Error('cancelled'));
                        }
                        progressText.textContent = 'Постановка в очередь…';
                        return fetch(multiProviderStartUrl, {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': multiProviderCsrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                            body: JSON.stringify({ batch_size: batchSize, dry_run: isDryRun }),
                        }).then(function (r) { return r.json(); });
                    })
                    .then(function (data) {
                        if (!data || !data.success || !data.run_id) {
                            progressBlock.classList.add('hidden');
                            resultBlock.classList.remove('hidden');
                            resultBlock.classList.add('border-red-200', 'bg-red-50');
                            resultMessage.textContent = (data && data.message) || 'Не удалось запустить. Проверьте: QUEUE_CONNECTION=database и php artisan queue:work.';
                            if (typeof toastr !== 'undefined') toastr.error(data && data.message);
                            btnBg.disabled = false;
                            btnRun.disabled = false;
                            if (btnTest) btnTest.disabled = false;
                            return;
                        }
                        var runId = data.run_id;
                        multiProviderShowCancelButton(runId);
                        if (data.message && data.message.indexOf('уже запущена') !== -1) {
                            progressText.textContent = data.message;
                            if (typeof toastr !== 'undefined') toastr.info(data.message);
                        } else {
                            progressText.textContent = 'Миграция в фоне. Обновление прогресса…';
                        }

                        function poll() {
                            fetch(multiProviderStatusUrl + '?run_id=' + encodeURIComponent(runId), {
                                method: 'GET',
                                headers: { 'Accept': 'application/json' },
                            })
                                .then(function (r) { return r.json(); })
                                .then(function (s) {
                                    if (!s.found) {
                                        progressText.textContent = 'Сессия не найдена.';
                                        return;
                                    }
                                    var total = s.total || 0;
                                    var processed = s.processed || 0;
                                    var added = s.added_total || 0;
                                    var pct = total > 0 ? (processed / total) * 100 : 0;
                                    if (pct > 0 && pct < 1) pct = 1;
                                    if (pct > 100) pct = 100;
                                    progressBar.style.width = Math.round(pct) + '%';
                                    progressText.textContent = 'Обработано: ' + processed + ' из ' + total + ', добавлено слотов: ' + added + '.';

                                    if (s.done) {
                                        multiProviderHideCancelButton();
                                        progressBlock.classList.add('hidden');
                                        resultBlock.classList.remove('hidden');
                                        if (s.error) {
                                            resultBlock.classList.add('border-red-200', 'bg-red-50');
                                            resultMessage.textContent = s.error;
                                        } else {
                                            resultBlock.classList.remove('border-red-200', 'bg-red-50');
                                            if (s.cancelled) {
                                                resultBlock.classList.add('border-amber-200', 'bg-amber-50');
                                                resultMessage.textContent = 'Миграция отменена. Обработано ключей: ' + processed + ', добавлено слотов: ' + added + (s.errors && s.errors.length ? ', ошибок: ' + s.errors.length : '') + '.';
                                            } else {
                                                resultBlock.classList.add('border-green-200', 'bg-green-50');
                                                resultMessage.textContent = (isDryRun ? 'Проверка завершена. ' : 'Миграция завершена. ') + 'Обработано ключей: ' + processed + ', добавлено слотов: ' + added + (s.errors && s.errors.length ? ', ошибок: ' + s.errors.length : '') + '.';
                                            }
                                            if (s.errors && s.errors.length > 0) {
                                                resultErrors.classList.remove('hidden');
                                                resultErrors.innerHTML = '<ul class="list-disc pl-5">' + s.errors.slice(0, 30).map(function (e) {
                                                    return '<li>' + (e.key_id || '') + ': ' + (e.message || '') + '</li>';
                                                }).join('') + (s.errors.length > 30 ? '<li class="text-gray-500">… и ещё ' + (s.errors.length - 30) + ' ошибок</li>' : '') + '</ul>';
                                            } else {
                                                resultErrors.classList.add('hidden');
                                                resultErrors.innerHTML = '';
                                            }
                                        }
                                        if (typeof toastr !== 'undefined') toastr.success(s.cancelled ? 'Миграция отменена.' : (isDryRun ? 'Проверка завершена.' : 'Миграция завершена.'));
                                        btnBg.disabled = false;
                                        btnRun.disabled = false;
                                        if (btnTest) btnTest.disabled = false;
                                        return;
                                    }
                                    setTimeout(poll, 2500);
                                })
                                .catch(function () {
                                    progressText.textContent = 'Ошибка запроса статуса. Повтор через 5 сек…';
                                    setTimeout(poll, 5000);
                                });
                        }
                        setTimeout(poll, 1500);
                    })
                    .catch(function (err) {
                        if (err && err.message === 'cancelled') return;
                        multiProviderHideCancelButton();
                        progressBlock.classList.add('hidden');
                        resultBlock.classList.remove('hidden');
                        resultBlock.classList.add('border-red-200', 'bg-red-50');
                        resultMessage.textContent = 'Ошибка запуска: ' + (err.message || 'неизвестная ошибка');
                        if (typeof toastr !== 'undefined') toastr.error('Ошибка запуска');
                        btnBg.disabled = false;
                        btnRun.disabled = false;
                        if (btnTest) btnTest.disabled = false;
                    });
            }

            function runMultiProviderMigration(dryRun, maxTotal) {
                var btnBg = document.getElementById('btn-multi-provider-run-background');
                var btnRun = document.getElementById('btn-multi-provider-run');
                var btnTest = document.getElementById('btn-multi-provider-test');
                var progressBlock = document.getElementById('multi-provider-progress');
                var progressBar = document.getElementById('multi-provider-progress-bar');
                var progressText = document.getElementById('multi-provider-progress-text');
                var resultBlock = document.getElementById('multi-provider-result');
                var resultMessage = document.getElementById('multi-provider-result-message');
                var resultErrors = document.getElementById('multi-provider-result-errors');
                var batchSize = parseInt(document.getElementById('multi-provider-batch-size').value, 10) || 50;
                var isDryRun = dryRun || (document.getElementById('multi-provider-dry-run') && document.getElementById('multi-provider-dry-run').checked);

                btnBg.disabled = true;
                btnRun.disabled = true;
                if (btnTest) btnTest.disabled = true;
                resultBlock.classList.add('hidden');
                progressBlock.classList.remove('hidden');
                progressBar.style.width = '0%';
                progressText.textContent = 'Запуск…';

                var totalKeys = 0;
                var totalProcessed = 0;
                var totalAdded = 0;
                var allErrors = [];

                function runBatch(offset) {
                    var body = {
                        offset: offset,
                        batch_size: batchSize,
                        dry_run: isDryRun,
                    };
                    if (maxTotal != null) body.max_total = maxTotal; // при тесте передаём каждый раз, чтобы бэкенд учитывал лимит

                    fetch(multiProviderBatchUrl, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': multiProviderCsrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                        body: JSON.stringify(body),
                    })
                        .then(function (r) {
                            var ct = (r.headers.get('Content-Type') || '').toLowerCase();
                            return r.text().then(function (text) {
                                var data = null;
                                if (ct.indexOf('application/json') !== -1 || (text && text.trim().indexOf('{') === 0)) {
                                    try { data = JSON.parse(text); } catch (e) { }
                                }
                                if (!data) {
                                    var msg = 'Сервер вернул страницу ошибки вместо JSON. Код ответа: ' + r.status + '.';
                                    if (r.status === 524) {
                                        msg += ' Это таймаут (запрос слишком долгий). Уменьшите «Порция за шаг» до 10–20 или нажмите «Запустить в фоне (очередь)».';
                                    } else {
                                        msg += ' Проверьте storage/logs/laravel.log.';
                                    }
                                    data = { success: false, message: msg };
                                }
                                return { ok: r.ok, data: data };
                            });
                        })
                        .then(function (res) {
                            if (!res.ok || !res.data.success) {
                                progressBlock.classList.add('hidden');
                                resultBlock.classList.remove('hidden');
                                resultBlock.classList.add('border-red-200', 'bg-red-50');
                                resultMessage.textContent = res.data.message || 'Ошибка';
                                resultErrors.classList.add('hidden');
                                if (typeof toastr !== 'undefined') toastr.error(res.data.message);
                                btnBg.disabled = false;
                                btnRun.disabled = false;
                                if (btnTest) btnTest.disabled = false;
                                return;
                            }
                            var d = res.data;
                            totalProcessed += d.processed || 0;
                            totalAdded += d.added_total || 0;
                            if (d.errors && d.errors.length) allErrors = allErrors.concat(d.errors);
                            if (totalKeys === 0 && d.total != null) totalKeys = d.total;
                            var pct = totalKeys > 0 ? Math.min(100, Math.round((totalProcessed / totalKeys) * 100)) : 0;
                            progressBar.style.width = pct + '%';
                            progressText.textContent = (d.message || '') + ' Всего обработано: ' + totalProcessed + ', добавлено слотов: ' + totalAdded + '.';

                            if (d.done) {
                                progressBlock.classList.add('hidden');
                                resultBlock.classList.remove('hidden');
                                resultBlock.classList.remove('border-red-200', 'bg-red-50');
                                resultBlock.classList.add('border-green-200', 'bg-green-50');
                                resultMessage.textContent = (isDryRun ? 'Проверка завершена. ' : 'Миграция завершена. ') + 'Обработано ключей: ' + totalProcessed + ', добавлено слотов: ' + totalAdded + (allErrors.length ? ', ошибок: ' + allErrors.length : '') + '.';
                                if (allErrors.length > 0) {
                                    resultErrors.classList.remove('hidden');
                                    resultErrors.innerHTML = '<ul class="list-disc pl-5">' + allErrors.slice(0, 30).map(function (e) {
                                        return '<li>' + (e.key_id || '') + ': ' + (e.message || '') + '</li>';
                                    }).join('') + (allErrors.length > 30 ? '<li class="text-gray-500">… и ещё ' + (allErrors.length - 30) + ' ошибок</li>' : '') + '</ul>';
                                } else {
                                    resultErrors.classList.add('hidden');
                                    resultErrors.innerHTML = '';
                                }
                                if (typeof toastr !== 'undefined') toastr.success(isDryRun ? 'Проверка завершена.' : 'Миграция завершена.');
                                btnBg.disabled = false;
                                btnRun.disabled = false;
                                if (btnTest) btnTest.disabled = false;
                                return;
                            }
                            runBatch(d.next_offset != null ? d.next_offset : offset + (d.processed || 0));
                        })
                        .catch(function (err) {
                            progressBlock.classList.add('hidden');
                            resultBlock.classList.remove('hidden');
                            resultBlock.classList.add('border-red-200', 'bg-red-50');
                            resultMessage.textContent = 'Ошибка запроса: ' + (err.message || 'неизвестная ошибка');
                            resultErrors.classList.add('hidden');
                            if (typeof toastr !== 'undefined') toastr.error('Ошибка запроса');
                            btnBg.disabled = false;
                            btnRun.disabled = false;
                            if (btnTest) btnTest.disabled = false;
                        });
                }

                runBatch(0);
            }
        })();
    </script>
@endsection

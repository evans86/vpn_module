@extends('layouts.admin')

@section('title', 'Массовый перенос ключей')
@section('page-title', 'Массовый перенос ключей')

@php
    use App\Models\Panel\Panel;
    /** @var \Illuminate\Support\Collection<int, Panel> $panels */
    $panelsConfigured = $panels->filter(fn (Panel $p) => (int) $p->panel_status === Panel::PANEL_CONFIGURED)->sortBy('id')->values();
    $panelsOther = $panels->filter(fn (Panel $p) => (int) $p->panel_status !== Panel::PANEL_CONFIGURED)->sortBy('id')->values();

    $panelOptionLabel = static function (Panel $p): string {
        $name = $p->server->name ?? 'Без сервера';
        $type = $p->panel ? strtoupper((string) $p->panel) : '—';

        return '№'.$p->id.' — '.$name.' ('.$type.')';
    };
@endphp

@section('content')
    <div class="max-w-3xl mx-auto space-y-6">
        <x-admin.card title="Перенос с недоступной панели">
            <p class="text-sm text-gray-600 mb-5 leading-relaxed">
                Когда исходная панель недоступна, ключи восстанавливаются по данным из БД на выбранной рабочей панели Marzban. Ссылка подписки не меняется — пользователю достаточно обновить конфиг в боте или клиенте.
            </p>

            <div class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-950 mb-5">
                <strong class="font-medium">Рекомендуемый порядок:</strong> выберите исходную и целевую панели → проверьте счётчик ключей → при большом числе ключей уменьшите размер порции → сначала «Тест (2 ключа)» → затем полный перенос.
            </div>

            <form id="mass-transfer-form" class="space-y-6" x-data="massTransferForm()">

                @csrf
                <input type="hidden" name="max_total" id="mass-transfer-max-total" value="">

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                        <label for="source_panel_id" class="block text-sm font-medium text-gray-900 mb-1">Откуда</label>
                        <p class="text-xs text-gray-500 mb-2">Исходная панель (часто нерабочая или старая).</p>
                        <select id="source_panel_id" name="source_panel_id" required
                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                x-model="sourcePanelId"
                                @change="loadKeyCount()">
                            <option value="">Выберите панель…</option>
                            @if($panelsConfigured->isNotEmpty())
                                <optgroup label="Настроенные">
                                    @foreach($panelsConfigured as $p)
                                        <option value="{{ $p->id }}">{{ $panelOptionLabel($p) }}</option>
                                    @endforeach
                                </optgroup>
                            @endif
                            @if($panelsOther->isNotEmpty())
                                <optgroup label="Не настроены / ошибка">
                                    @foreach($panelsOther as $p)
                                        <option value="{{ $p->id }}">{{ $panelOptionLabel($p) }}</option>
                                    @endforeach
                                </optgroup>
                            @endif
                        </select>
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <template x-if="keyCount === null && sourcePanelId">
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs text-gray-600">Считаем ключи…</span>
                            </template>
                            <template x-if="keyCount !== null">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                      :class="keyCount > 0 ? 'bg-emerald-100 text-emerald-900' : 'bg-gray-100 text-gray-600'"
                                      x-text="'Активных ключей: ' + keyCount"></span>
                            </template>
                        </div>
                    </div>

                    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                        <label for="target_panel_id" class="block text-sm font-medium text-gray-900 mb-1">Куда</label>
                        <p class="text-xs text-gray-500 mb-2">Целевая рабочая панель <span class="text-gray-700">Marzban</span> (должна быть настроена и доступна).</p>
                        <select id="target_panel_id" name="target_panel_id" required
                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                x-model="targetPanelId">
                            <option value="">Выберите панель…</option>
                            @if($panelsConfigured->isNotEmpty())
                                <optgroup label="Настроенные">
                                    @foreach($panelsConfigured as $p)
                                        <option value="{{ $p->id }}">{{ $panelOptionLabel($p) }}</option>
                                    @endforeach
                                </optgroup>
                            @endif
                            @if($panelsOther->isNotEmpty())
                                <optgroup label="Не настроены / ошибка">
                                    @foreach($panelsOther as $p)
                                        <option value="{{ $p->id }}">{{ $panelOptionLabel($p) }}</option>
                                    @endforeach
                                </optgroup>
                            @endif
                        </select>
                        <p class="mt-3 text-xs text-gray-500" x-show="samePanel && sourcePanelId" x-cloak>
                            Целевая панель совпадает с исходной — так перенести нельзя.
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap items-end gap-4 rounded-lg border border-gray-100 bg-gray-50/80 p-4">
                    <div>
                        <label for="mass-transfer-batch-size" class="block text-sm font-medium text-gray-800 mb-1">Ключей за один запрос</label>
                        <input type="number" id="mass-transfer-batch-size" name="mass_transfer_batch_size" value="100" min="1" max="200" step="1"
                               class="block w-28 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                               title="Меньше значение — короче запрос при таймаутах шлюза (например 524)">
                    </div>
                    <p class="text-xs text-gray-600 max-w-md flex-1 min-w-[12rem]">
                        Перенос идёт порциями без одного огромного HTTP-ответа. При ошибках по времени попробуйте <strong class="font-medium">25–50</strong>.
                    </p>
                </div>

                <div class="flex flex-wrap gap-3">
                    <button type="submit" id="btn-submit"
                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
                            :disabled="blockedSubmit">
                        <span x-show="!loading">Перенести все ключи</span>
                        <span x-show="loading" x-cloak>Идёт перенос…</span>
                    </button>
                    <button type="button" id="btn-test-transfer"
                            class="inline-flex items-center px-4 py-2 border border-amber-300 text-sm font-medium rounded-md shadow-sm text-amber-900 bg-amber-50 hover:bg-amber-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500 disabled:opacity-50 disabled:cursor-not-allowed"
                            :disabled="blockedTest">
                        <i class="fas fa-vial mr-2"></i> Тест: 2 ключа
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
                            <i class="fas fa-download mr-1.5"></i> Скачать CSV
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
    </div>

    <script>
        function massTransferForm() {
            return {
                sourcePanelId: '',
                targetPanelId: '',
                keyCount: null,
                loading: false,

                get samePanel() {
                    return this.sourcePanelId !== '' && this.targetPanelId !== ''
                        && String(this.sourcePanelId) === String(this.targetPanelId);
                },

                get blockedTest() {
                    return this.loading || this.samePanel || !this.sourcePanelId || !this.targetPanelId
                        || this.keyCount === null || this.keyCount === 0;
                },

                get blockedSubmit() {
                    return this.loading || this.samePanel || !this.sourcePanelId || !this.targetPanelId
                        || this.keyCount === null || this.keyCount === 0;
                },

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
                            this.keyCount = data.count ?? 0;
                            var el = document.getElementById('source_panel_id');
                            if (el) el.dataset.count = this.keyCount;
                        })
                        .catch(() => { this.keyCount = 0; });
                },
            };
        }

        function getMassTransferAlpine(form) {
            if (!form) return null;
            if (window.Alpine && typeof Alpine.$data === 'function') {
                try { return Alpine.$data(form); } catch (e) { /* ignore */ }
            }
            if (form._x_dataStack && form._x_dataStack.length) return form._x_dataStack[0];
            return null;
        }

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
            var alpineEl = document.getElementById('mass-transfer-form');
            var root = alpineEl ? getMassTransferAlpine(alpineEl) : null;
            if (root && root.samePanel) {
                if (typeof toastr !== 'undefined') toastr.warning('Выберите разные панели');
                return;
            }
            if (input) input.value = '2';
            form.requestSubmit();
        });

        document.getElementById('mass-transfer-form').addEventListener('submit', function (e) {
            e.preventDefault();
            const form = this;
            const alpineEl = form;
            const alpineData = getMassTransferAlpine(alpineEl);

            if (alpineData && alpineData.samePanel) {
                if (typeof toastr !== 'undefined') toastr.warning('Выберите разные панели');
                return;
            }

            function readBatchSize() {
                var el = document.getElementById('mass-transfer-batch-size');
                var v = parseInt(el && el.value, 10);
                if (!(v >= 1)) v = 100;
                if (v > 200) v = 200;
                return v;
            }
            var BATCH_SIZE = readBatchSize();

            const btn = document.getElementById('btn-submit');
            const resultBlock = document.getElementById('result-block');
            const resultMessage = document.getElementById('result-message');
            const resultErrors = document.getElementById('result-errors');
            const progressBlock = document.getElementById('progress-block');
            const progressBar = document.getElementById('progress-bar');
            const progressText = document.getElementById('progress-text');

            totalKeys = parseInt(document.getElementById('source_panel_id').dataset.count || '0', 10) || 0;
            if (alpineData && alpineData.keyCount != null) totalKeys = parseInt(alpineData.keyCount, 10) || totalKeys;

            var maxTotalInput = form.querySelector('input[name="max_total"]');
            if (maxTotalInput && e.submitter && e.submitter.id === 'btn-submit') maxTotalInput.value = '';
            BATCH_SIZE = readBatchSize();
            if (alpineData) alpineData.loading = true;
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
                fd.set('batch_size', String(readBatchSize()));
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
                            if (alpineData) alpineData.loading = false;
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
                            if (alpineData && alpineData.loadKeyCount) alpineData.loadKeyCount();
                            if (alpineData) alpineData.loading = false;
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
                        if (alpineData) alpineData.loading = false;
                        btn.disabled = false;
                    });
            }
            runBatch();
        });
    </script>
@endsection

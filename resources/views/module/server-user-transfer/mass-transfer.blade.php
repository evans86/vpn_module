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
    </script>
@endsection

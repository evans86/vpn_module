@extends('layouts.public')

@section('title', 'Конфигурация VPN — VPN Service')
@section('header-subtitle', 'Профиль и ключи подключения')

@section('content')
    @if(!empty($configRefreshUrlForButton))
    <div id="config-refresh-bar" class="container mx-auto px-4 pt-4 max-w-6xl">
        <div class="bg-indigo-100 border-2 border-indigo-300 rounded-xl p-4 flex flex-wrap items-center justify-between gap-4 shadow-sm">
            <div class="flex items-center gap-3">
                <span class="flex items-center justify-center w-10 h-10 rounded-full bg-indigo-600 text-white flex-shrink-0" title="Время обновления">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
                <div>
                    <div class="text-xs font-semibold uppercase tracking-wide text-indigo-600">Конфигурация обновлена</div>
                    <div id="config-last-updated" class="text-lg font-bold text-indigo-900">{{ $configLastUpdated ?? '—' }}</div>
                </div>
            </div>
            <button type="button" id="config-btn-refresh" class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 transition-colors shadow">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Обновить
            </button>
        </div>
    </div>
    <div id="config-progress-bar" class="container mx-auto px-4 pt-4 max-w-6xl hidden">
        <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-4 flex items-center gap-4">
            <div id="config-progress-spinner" class="flex items-center gap-4 flex-1 min-w-0">
                <div class="flex-shrink-0">
                    <svg class="animate-spin h-6 w-6 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-indigo-800">Обновление конфигурации с серверов…</p>
                    <div class="mt-2 w-full bg-indigo-200 rounded-full h-2 overflow-hidden">
                        <div id="config-progress-fill" class="h-full bg-indigo-600 rounded-full transition-all duration-300 ease-out" style="width: 0%;"></div>
                    </div>
                </div>
            </div>
            <div id="config-progress-error" class="hidden mt-2 flex-1">
                <p class="text-sm text-red-600">Обновление не удалось.</p>
                <button type="button" id="config-progress-retry" class="mt-2 px-3 py-1.5 text-sm font-medium bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">Повторить</button>
            </div>
        </div>
    </div>
    @endif
    <div id="config-content">
    @include('vpn.partials.config-content')
    </div>
    {{-- Делегирование: клики по выпадающим спискам протоколов --}}
    <script>
    (function(){
        var content = document.getElementById('config-content');
        if (!content) return;
        content.addEventListener('click', function(e) {
            var btn = e.target.closest('.config-location-toggle');
            if (!btn) return;
            e.preventDefault();
            var targetId = btn.getAttribute('data-target');
            var body = document.getElementById(targetId);
            var chevron = btn.querySelector('.config-location-chevron');
            if (body && body.classList.contains('hidden')) {
                body.classList.remove('hidden');
                if (chevron) chevron.style.transform = 'rotate(0deg)';
                btn.setAttribute('aria-expanded', 'true');
            } else if (body) {
                body.classList.add('hidden');
                if (chevron) chevron.style.transform = 'rotate(-90deg)';
                btn.setAttribute('aria-expanded', 'false');
            }
        });
    })();
    </script>
    @if(!empty($configRefreshUrlForButton))
    <script>
    (function(){
        var refreshUrl = @json($configRefreshUrlForButton);
        var refreshBar = document.getElementById('config-refresh-bar');
        var progressBar = document.getElementById('config-progress-bar');
        var lastUpdatedEl = document.getElementById('config-last-updated');
        var btnRefresh = document.getElementById('config-btn-refresh');
        var fill = document.getElementById('config-progress-fill');
        var spinnerBlock = document.getElementById('config-progress-spinner');
        var errBlock = document.getElementById('config-progress-error');
        var retryBtn = document.getElementById('config-progress-retry');

        function showErrorState() {
            if (spinnerBlock) spinnerBlock.classList.add('hidden');
            if (errBlock) errBlock.classList.remove('hidden');
        }
        function showSpinnerState() {
            if (spinnerBlock) spinnerBlock.classList.remove('hidden');
            if (errBlock) errBlock.classList.add('hidden');
        }

        function runRefresh() {
            if (!progressBar || !refreshBar) return;
            progressBar.classList.remove('hidden');
            refreshBar.classList.add('hidden');
            showSpinnerState();
            if (fill) fill.style.width = '0%';

            var start = Date.now();
            var t = setInterval(function() {
                if (!fill) return;
                var p = Math.min(85, ((Date.now() - start) / 20000) * 85);
                fill.style.width = p + '%';
            }, 200);

            fetch(refreshUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function(r) {
                    var ct = (r.headers.get('Content-Type') || '').toLowerCase();
                    if (ct.indexOf('application/json') !== -1) {
                        return r.json()
                            .then(function(d) { return { ok: r.ok, data: d }; })
                            .catch(function() { return { ok: false, data: {} }; });
                    }
                    return r.text().then(function() { return { ok: false, data: {} }; });
                })
                .then(function(res){
                    clearInterval(t);
                    if (fill) fill.style.width = '100%';
                    if (res.ok && res.data && res.data.success) {
                        var el = document.getElementById('config-content');
                        if (el && res.data.html) el.innerHTML = res.data.html;
                        if (res.data.lastUpdated && lastUpdatedEl) lastUpdatedEl.textContent = res.data.lastUpdated;
                        progressBar.classList.add('hidden');
                        refreshBar.classList.remove('hidden');
                    } else {
                        showErrorState();
                    }
                })
                .catch(function(){
                    clearInterval(t);
                    if (fill) fill.style.width = '0%';
                    showErrorState();
                });
        }

        if (btnRefresh) btnRefresh.addEventListener('click', runRefresh);
        if (retryBtn) retryBtn.addEventListener('click', runRefresh);
    })();
    </script>
    @endif
@endsection

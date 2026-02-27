@extends('layouts.public')

@section('title', 'Конфигурация VPN — VPN Service')
@section('header-subtitle', 'Профиль и ключи подключения')

@section('content')
    @if(!empty($configRefreshUrl))
    <div id="config-progress-bar" class="container mx-auto px-4 pt-4 max-w-6xl">
        <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-4 flex items-center gap-4">
            <div id="config-progress-spinner" class="flex-shrink-0">
                <svg class="animate-spin h-6 w-6 text-emerald-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>
            <div id="config-progress-done-icon" class="flex-shrink-0 hidden">
                <svg class="h-6 w-6 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
            </div>
            <div class="flex-1 min-w-0">
                <p id="config-progress-text" class="text-sm font-medium text-emerald-800">Загрузка актуальных данных с серверов… Подождите.</p>
                <p class="text-xs text-emerald-600 mt-1">Ниже уже показаны сохранённые данные; блок обновится по завершении.</p>
                <div class="mt-2 w-full bg-emerald-200 rounded-full h-2 overflow-hidden">
                    <div id="config-progress-fill" class="h-full bg-emerald-500 rounded-full transition-all duration-500 ease-out" style="width: 0%;"></div>
                </div>
                <div id="config-progress-error" class="hidden mt-2">
                    <p class="text-sm text-red-600">Обновление не удалось. Проверьте сеть или попробуйте позже.</p>
                    <button type="button" id="config-progress-retry" class="mt-2 px-3 py-1.5 text-sm font-medium bg-emerald-600 text-white rounded-lg hover:bg-emerald-700">Повторить</button>
                </div>
            </div>
        </div>
    </div>
    @endif
    <div id="config-content">
    @include('vpn.partials.config-content')
    </div>
    {{-- Делегирование: клики по выпадающим спискам протоколов работают и после подстановки контента через refresh --}}
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
    @if(!empty($configRefreshUrl))
    <script>
    (function(){
        var url = @json($configRefreshUrl ?? '');
        if (!url) return;
        var bar = document.getElementById('config-progress-bar');
        var fill = document.getElementById('config-progress-fill');
        var text = document.getElementById('config-progress-text');
        var spinner = document.getElementById('config-progress-spinner');
        var doneIcon = document.getElementById('config-progress-done-icon');
        var errBlock = document.getElementById('config-progress-error');
        var retryBtn = document.getElementById('config-progress-retry');

        function runRefresh() {
            if (errBlock) errBlock.classList.add('hidden');
            if (text) text.textContent = 'Загрузка актуальных данных с серверов… Подождите.';
            if (fill) fill.style.width = '0%';
            if (spinner) spinner.classList.remove('hidden');
            if (doneIcon) doneIcon.classList.add('hidden');

            fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function(r){ if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
                .then(function(d){
                    if (fill) fill.style.width = '100%';
                    if (spinner) spinner.classList.add('hidden');
                    if (doneIcon) doneIcon.classList.remove('hidden');
                    if (text) text.textContent = 'Данные обновлены.';
                    if (d.success && d.html) {
                        var el = document.getElementById('config-content');
                        if (el) el.innerHTML = d.html;
                    }
                    setTimeout(function(){ if (bar) bar.remove(); }, 1500);
                })
                .catch(function(){
                    if (fill) fill.style.width = '0%';
                    if (spinner) spinner.classList.add('hidden');
                    if (doneIcon) doneIcon.classList.add('hidden');
                    if (text) text.textContent = 'Ошибка обновления.';
                    if (errBlock) errBlock.classList.remove('hidden');
                });
        }

        runRefresh();
        if (retryBtn) retryBtn.addEventListener('click', runRefresh);
    })();
    </script>
    @endif
@endsection

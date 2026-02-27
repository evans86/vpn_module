@extends('layouts.public')

@section('title', 'Загрузка конфигурации — VPN Service')
@section('header-subtitle', 'Обновление данных')

@section('content')
<div class="container mx-auto px-4 py-12 max-w-2xl">
    <div id="config-loading-state" class="bg-white rounded-2xl shadow-xl p-8 md:p-10 text-center">
        <div class="mb-6">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-indigo-100 text-indigo-600 mb-4">
                <svg class="animate-spin h-8 w-8" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>
            <h1 class="text-xl md:text-2xl font-bold text-gray-900 mb-2">Обновление конфигурации</h1>
            <p class="text-gray-600 text-sm md:text-base">Загружаем серверы и ключи подключения…</p>
        </div>
        <div class="w-full bg-gray-200 rounded-full h-2.5 overflow-hidden">
            <div id="progress-bar" class="h-full bg-indigo-600 rounded-full transition-all duration-300 ease-out" style="width: 0%"></div>
        </div>
        <p id="progress-text" class="mt-3 text-sm text-gray-500">0%</p>
    </div>
    <div id="config-error-state" class="hidden bg-white rounded-2xl shadow-xl p-8 border-2 border-red-200">
        <div class="text-red-600 mb-2">
            <svg class="w-12 h-12 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
        <h2 class="text-lg font-semibold text-gray-900 mb-2">Не удалось загрузить конфигурацию</h2>
        <p id="error-message" class="text-sm text-gray-600 mb-4"></p>
        <button type="button" onclick="location.reload()" class="px-4 py-2 bg-indigo-600 text-white rounded-lg font-medium hover:bg-indigo-700 transition-colors">
            Обновить страницу
        </button>
    </div>
</div>

<script>
(function () {
    var refreshUrl = @json($refresh_url);
    var progressBar = document.getElementById('progress-bar');
    var progressText = document.getElementById('progress-text');
    var loadingState = document.getElementById('config-loading-state');
    var errorState = document.getElementById('config-error-state');
    var errorMessage = document.getElementById('error-message');

    function setProgress(pct) {
        var p = Math.min(100, Math.max(0, pct));
        if (progressBar) progressBar.style.width = p + '%';
        if (progressText) progressText.textContent = Math.round(p) + '%';
    }

    function simulateProgress() {
        var start = Date.now();
        var duration = 8000;
        var tick = function () {
            var elapsed = Date.now() - start;
            var pct = elapsed < duration ? 15 + (elapsed / duration) * 75 : 90;
            setProgress(pct);
            if (elapsed < duration) requestAnimationFrame(tick);
        };
        requestAnimationFrame(tick);
    }

    simulateProgress();

    fetch(refreshUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success && data.html) {
                setProgress(100);
                document.open();
                document.write(data.html);
                document.close();
            } else {
                if (loadingState) loadingState.classList.add('hidden');
                if (errorState) errorState.classList.remove('hidden');
                if (errorMessage) errorMessage.textContent = data.message || 'Неизвестная ошибка';
            }
        })
        .catch(function (err) {
            if (loadingState) loadingState.classList.add('hidden');
            if (errorState) errorState.classList.remove('hidden');
            if (errorMessage) errorMessage.textContent = err.message || 'Ошибка сети. Проверьте подключение и обновите страницу.';
        });
})();
</script>
@endsection

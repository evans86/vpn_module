@extends('layouts.public')

@section('title', 'Конфигурация VPN — VPN Service')
@section('header-subtitle', 'Профиль и ключи подключения')

@section('content')
    @if(!empty($configRefreshUrl))
    <div id="config-progress-bar" class="container mx-auto px-4 pt-4 max-w-6xl">
        <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-4 flex items-center gap-4">
            <div class="flex-shrink-0">
                <svg class="animate-spin h-6 w-6 text-emerald-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-emerald-800">Загрузка актуальных данных с серверов… Подождите.</p>
                <p class="text-xs text-emerald-600 mt-1">Ниже уже показаны сохранённые данные; блок обновится, когда загрузка завершится.</p>
                <div class="mt-2 w-full bg-emerald-200 rounded-full h-2 overflow-hidden">
                    <div id="config-progress-fill" class="config-progress-fill h-full rounded-full" style="width: 40%;"></div>
                </div>
            </div>
        </div>
    </div>
    <style>
        .config-progress-fill { background: linear-gradient(90deg, #059669, #10b981, #34d399); background-size: 200% 100%; animation: config-progress-shimmer 1.5s ease-in-out infinite; }
        @keyframes config-progress-shimmer { 0%, 100% { background-position: 100% 0; } 50% { background-position: 0% 0; } }
    </style>
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
        fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(r){ return r.json(); })
            .then(function(d){
                if (d.success && d.html) {
                    var el = document.getElementById('config-content');
                    if (el) el.innerHTML = d.html;
                }
                if (bar) bar.remove();
            })
            .catch(function(){ if (bar) bar.remove(); });
    })();
    </script>
    @endif
@endsection

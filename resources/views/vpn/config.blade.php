@extends('layouts.public')

@section('title', 'Конфигурация VPN — VPN Service')
@section('header-subtitle', 'Профиль и ключи подключения')

@section('content')
    @if(!empty($configRefreshUrl))
    <div id="config-progress-bar" class="container mx-auto px-4 pt-4 max-w-6xl">
        <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-4 flex items-center gap-4">
            <div class="flex-shrink-0">
                <svg class="animate-spin h-6 w-6 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-indigo-800">Обновление конфигурации и серверов…</p>
                <div class="mt-2 w-full bg-indigo-200 rounded-full h-2 overflow-hidden">
                    <div id="config-progress-fill" class="h-full bg-indigo-600 rounded-full transition-all duration-300" style="width: 0%"></div>
                </div>
            </div>
        </div>
    </div>
    @endif
    <div id="config-content">
    @include('vpn.partials.config-content')
    </div>
    @if(!empty($configRefreshUrl))
    <script>
    (function(){
        var url = @json($configRefreshUrl ?? '');
        if (!url) return;
        var bar = document.getElementById('config-progress-bar');
        var fill = document.getElementById('config-progress-fill');
        var start = Date.now();
        var t = setInterval(function(){ if (fill) { var p = Math.min(90, 15 + (Date.now()-start)/8000*75); fill.style.width = p + '%'; } }, 200);
        fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(r){ return r.json(); })
            .then(function(d){
                clearInterval(t);
                if (fill) fill.style.width = '100%';
                if (d.success && d.html) {
                    var el = document.getElementById('config-content');
                    if (el) el.innerHTML = d.html;
                    if (bar) bar.remove();
                }
            })
            .catch(function(){ clearInterval(t); if (bar) bar.remove(); });
    })();
    </script>
    @endif
@endsection

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
                <p class="text-sm font-medium text-indigo-800">Загрузка актуальных данных с серверов… Подождите.</p>
                <p class="text-xs text-indigo-600 mt-1">Ниже уже показаны сохранённые данные; блок обновится, когда загрузка завершится.</p>
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

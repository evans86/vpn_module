<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Network Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        h1,h2 { margin: 0 0 6px; }
        .muted { color:#666; }
        .badge { display:inline-block; padding:2px 6px; border-radius:4px; font-size:10px; }
        .ok { background:#DCFCE7; color:#166534; }
        .fail { background:#FEE2E2; color:#991B1B; }
        table { width:100%; border-collapse: collapse; margin-top:8px; }
        th,td { border:1px solid #e5e7eb; padding:6px; vertical-align: top; }
        th { background:#f8fafc; text-align:left; }
        .small { font-size: 10px; }
    </style>
</head>
<body>
<h1>Отчёт проверки сети</h1>
<p class="muted small">Сгенерировано: {{ $generatedAt }}</p>

<h2>Общие сведения</h2>
<table>
    <tr><th>Период теста</th>
        <td>{{ $data['period_display']['start'] }} — {{ $data['period_display']['finish'] }} ({{ $data['period_display']['tz'] }})</td>
    </tr>
    <tr><th>User-Agent</th><td class="small">{{ $data['env']['ua'] }}</td></tr>
    <tr><th>Таймзона браузера</th><td>{{ $data['env']['tz'] }}</td></tr>
</table>

<h2>Итоги</h2>
<table>
    <tr><th>Внешний IP</th><td>{{ $data['summary']['ip'] }}</td></tr>
    <tr><th>Страна</th><td>{{ $data['summary']['country'] }}</td></tr>
    <tr><th>Пинг (ср.)</th><td>{{ $data['summary']['latency_avg_ms'] }} мс</td></tr>
    <tr><th>Джиттер</th><td>{{ $data['summary']['jitter_ms'] }} мс</td></tr>
    <tr><th>Потери пакетов</th><td>{{ $data['summary']['packet_loss_pct'] ?? '—' }} %</td></tr>
    <tr><th>Скорость скачивания</th><td>{{ $data['summary']['download_mbps'] }} Мбит/с</td></tr>
    <tr><th>WebRTC кандидаты</th><td class="small">{{ implode(', ', $data['summary']['webrtc_candidates'] ?? []) }}</td></tr>
</table>

<h2>Доступность ресурсов (должны открываться)</h2>
<table>
    <thead><tr><th>URL</th><th>Статус</th><th>Время</th></tr></thead>
    <tbody>
    @foreach(($data['resources']['must'] ?? []) as $r)
        <tr>
            <td class="small">{{ $r['url'] }}</td>
            <td>{!! !empty($r['ok']) ? '<span class="badge ok">доступен</span>' : '<span class="badge fail">недоступен</span>' !!}</td>
            <td>{{ isset($r['time']) ? round($r['time']) : '—' }} мс</td>
        </tr>
    @endforeach
    </tbody>
</table>

<h2>Доступность ресурсов (часто блокируемые)</h2>
<table>
    <thead><tr><th>URL</th><th>Статус</th><th>Время</th></tr></thead>
    <tbody>
    @foreach(($data['resources']['blocked'] ?? []) as $r)
        <tr>
            <td class="small">{{ $r['url'] }}</td>
            <td>{!! !empty($r['ok']) ? '<span class="badge ok">доступен</span>' : '<span class="badge fail">недоступен</span>' !!}</td>
            <td>{{ isset($r['time']) ? round($r['time']) : '—' }} мс</td>
        </tr>
    @endforeach
    </tbody>
</table>

@if(!empty($data['packetLoss']))
    <h2>Пакетные потери (HTTP-пинги)</h2>
    <table>
        <tr><th>Выборка</th><td>{{ $data['packetLoss']['total'] ?? '—' }}</td></tr>
        <tr><th>OK</th><td>{{ $data['packetLoss']['ok'] ?? '—' }}</td></tr>
        <tr><th>Потери</th><td>{{ $data['packetLoss']['lossPct'] ?? '—' }} %</td></tr>
        <tr><th>p50</th><td>{{ $data['packetLoss']['p50'] ?? '—' }} мс</td></tr>
        <tr><th>p95</th><td>{{ $data['packetLoss']['p95'] ?? '—' }} мс</td></tr>
    </table>
@endif

@if(!empty($data['doh']))
    <h2>DNS поверх HTTPS (DoH)</h2>
    <table>
        <thead><tr><th>Домен</th><th>Google DoH</th><th>Cloudflare DoH</th></tr></thead>
        <tbody>
        @foreach($data['doh'] as $domain => $res)
            @php $g = $res['google'] ?? null; $c = $res['cf'] ?? null; @endphp
            <tr>
                <td>{{ $domain }}</td>
                <td>{!! (!empty($g['ok'])) ? '<span class="badge ok">OK</span>' : '<span class="badge fail">FAIL</span>' !!} {{ isset($g['time']) ? round($g['time']).' мс' : '' }}</td>
                <td>{!! (!empty($c['ok'])) ? '<span class="badge ok">OK</span>' : '<span class="badge fail">FAIL</span>' !!} {{ isset($c['time']) ? round($c['time']).' мс' : '' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif

@if(!empty($data['regional']))
    <h2>Региональные пробы</h2>
    <table>
        <thead><tr><th>Регион</th><th>URL</th><th>Статус</th><th>Время</th></tr></thead>
        <tbody>
        @foreach($data['regional'] as $r)
            <tr>
                <td>{{ $r['label'] }}</td>
                <td class="small">{{ $r['url'] }}</td>
                <td>{!! !empty($r['ok']) ? '<span class="badge ok">доступен</span>' : '<span class="badge fail">недоступен</span>' !!}</td>
                <td>{{ isset($r['time']) ? round($r['time']) : '—' }} мс</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif

@if(!empty($data['youtube']))
    <h2>YouTube</h2>
    <table>
        <thead><tr><th>Точка</th><th>URL</th><th>Статус</th><th>Время</th></tr></thead>
        <tbody>
        @foreach($data['youtube'] as $r)
            <tr>
                <td>{{ $r['label'] ?? '' }}</td>
                <td class="small">{{ $r['url'] ?? '' }}</td>
                <td>{!! !empty($r['ok']) ? '<span class="badge ok">OK</span>' : '<span class="badge fail">FAIL</span>' !!}</td>
                <td>{{ isset($r['time']) ? round($r['time']) : '—' }} мс</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif

@if(!empty($data['ru_services']))
    <h2>.ru / банки / госуслуги</h2>
    <table>
        <thead><tr><th>Сервис</th><th>URL</th><th>Статус</th><th>Время</th></tr></thead>
        <tbody>
        @foreach($data['ru_services'] as $r)
            <tr>
                <td>{{ $r['label'] ?? '' }}</td>
                <td class="small">{{ $r['url'] ?? '' }}</td>
                <td>{!! !empty($r['ok']) ? '<span class="badge ok">OK</span>' : '<span class="badge fail">FAIL</span>' !!}</td>
                <td>{{ isset($r['time']) ? round($r['time']) : '—' }} мс</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif

@if(!empty($data['messengers']))
    <h2>Мессенджеры</h2>
    <table>
        <thead><tr><th>Сервис</th><th>URL</th><th>Статус</th><th>Время</th></tr></thead>
        <tbody>
        @foreach($data['messengers'] as $r)
            <tr>
                <td>{{ $r['label'] ?? '' }}</td>
                <td class="small">{{ $r['url'] ?? '' }}</td>
                <td>{!! !empty($r['ok']) ? '<span class="badge ok">OK</span>' : '<span class="badge fail">FAIL</span>' !!}</td>
                <td>{{ isset($r['time']) ? round($r['time']) : '—' }} мс</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif

@if(!empty($data['http80']))
    <h2>HTTP (порт 80)</h2>
    <table>
        <thead><tr><th>Точка</th><th>URL</th><th>Статус</th><th>Время</th></tr></thead>
        <tbody>
        @foreach($data['http80'] as $r)
            <tr>
                <td>{{ $r['label'] ?? '' }}</td>
                <td class="small">{{ $r['url'] ?? '' }}</td>
                <td>{!! !empty($r['ok']) ? '<span class="badge ok">OK</span>' : '<span class="badge fail">FAIL</span>' !!}</td>
                <td>{{ isset($r['time']) ? round($r['time']) : '—' }} мс</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif

@if(!empty($data['latency']['samples']))
    <h2>Подробности пинга</h2>
    <table>
        <tr><th>Выборка (шт.)</th><td>{{ count($data['latency']['samples']) }}</td></tr>
        <tr><th>Значения</th><td class="small">{{ implode(', ', array_map(function($x){ return (string)round($x); }, $data['latency']['samples'])) }} (мс)</td></tr>
    </table>
@endif

@if(!empty($data['voip']))
    <h2>VoIP / WebRTC</h2>
    <table>
        <tr><th>Готовность</th><td>{!! !empty($data['voip']['ok']) ? '<span class="badge ok">OK</span>' : '<span class="badge fail">Проблемы</span>' !!}</td></tr>
        <tr><th>host/srflx/relay</th>
            <td class="small">
                host: {{ !empty($data['voip']['hasHost']) ? 'да' : 'нет' }},
                srflx: {{ !empty($data['voip']['hasSrflx']) ? 'да' : 'нет' }},
                relay: {{ !empty($data['voip']['hasRelay']) ? 'да' : 'нет' }}
            </td>
        </tr>
    </table>
@endif

</body>
</html>

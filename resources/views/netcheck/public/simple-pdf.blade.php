<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Отчёт проверки сети</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        h1,h2,h3 { margin: 0 0 8px; }
        .muted { color:#666; }
        .badge { display:inline-block; padding:2px 6px; border-radius:4px; font-size:10px; }
        .ok { background:#DCFCE7; color:#166534; }
        .fail { background:#FEE2E2; color:#991B1B; }
        table { width:100%; border-collapse: collapse; margin-top:8px; }
        th,td { border:1px solid #e5e7eb; padding:6px; vertical-align: top; }
        th { background:#f8fafc; text-align:left; }
        .small { font-size: 10px; }
        .summary-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin: 10px 0; }
        .summary-item { padding: 8px; border: 1px solid #e5e7eb; border-radius: 4px; }
        .section { margin: 15px 0; }
        .status-cell { text-align: center; }
    </style>
</head>
<body>
<h1>Отчёт проверки сети</h1>
<p class="muted small">Сгенерировано: {{ $generatedAt }}</p>

<h2>Основные показатели</h2>
<div class="summary-grid">
    <div class="summary-item">
        <strong>IP-адрес:</strong> {{ $data['summary']['ip'] ?? '—' }}
    </div>
    <div class="summary-item">
        <strong>Страна:</strong> {{ $data['summary']['country'] ?? '—' }}
    </div>
    <div class="summary-item">
        <strong>Провайдер:</strong> {{ $data['summary']['isp'] ?? '—' }}
    </div>
    <div class="summary-item">
        <strong>Пинг:</strong> {{ $data['summary']['latency_avg_ms'] ?? '—' }} мс
    </div>
    <div class="summary-item">
        <strong>Скорость:</strong> {{ $data['summary']['download_mbps'] ?? '—' }} Мбит/с
    </div>
</div>

<div class="section">
    <h3>🏠 Локальные сервисы</h3>
    <table>
        <thead>
        <tr>
            <th>Сайт</th>
            <th class="status-cell">Статус</th>
            <th>Время ответа</th>
        </tr>
        </thead>
        <tbody>
        @if(!empty($data['resources']['local_services']))
            @foreach($data['resources']['local_services'] as $item)
                <tr>
                    <td>{{ $item['label'] ?? '—' }}</td>
                    <td class="status-cell">
                        @if(($item['ok'] ?? false))
                            <span class="badge ok">Доступен</span>
                        @else
                            <span class="badge fail">Недоступен</span>
                        @endif
                    </td>
                    <td>{{ $item['time'] ?? '—' }} мс</td>
                </tr>
            @endforeach
        @else
            <tr><td colspan="3" class="muted">Нет данных</td></tr>
        @endif
        </tbody>
    </table>
</div>

<div class="section">
    <h3>🌍 Глобальные сервисы</h3>
    <table>
        <thead>
        <tr>
            <th>Сайт</th>
            <th class="status-cell">Статус</th>
            <th>Время ответа</th>
        </tr>
        </thead>
        <tbody>
        @if(!empty($data['resources']['global_services']))
            @foreach($data['resources']['global_services'] as $item)
                <tr>
                    <td>{{ $item['label'] ?? '—' }}</td>
                    <td class="status-cell">
                        @if(($item['ok'] ?? false))
                            <span class="badge ok">Доступен</span>
                        @else
                            <span class="badge fail">Недоступен</span>
                        @endif
                    </td>
                    <td>{{ $item['time'] ?? '—' }} мс</td>
                </tr>
            @endforeach
        @else
            <tr><td colspan="3" class="muted">Нет данных</td></tr>
        @endif
        </tbody>
    </table>
</div>

<div class="section">
    <h3>📡 Здоровье сети</h3>
    <table>
        <thead>
        <tr>
            <th>Компонент</th>
            <th class="status-cell">Статус</th>
            <th>Время ответа</th>
        </tr>
        </thead>
        <tbody>
        @if(!empty($data['resources']['network_health']))
            @foreach($data['resources']['network_health'] as $item)
                <tr>
                    <td>{{ $item['label'] ?? '—' }}</td>
                    <td class="status-cell">
                        @if(($item['ok'] ?? false))
                            <span class="badge ok">Работает</span>
                        @else
                            <span class="badge fail">Недоступен</span>
                        @endif
                    </td>
                    <td>{{ $item['time'] ?? '—' }} мс</td>
                </tr>
            @endforeach
        @else
            <tr><td colspan="3" class="muted">Нет данных</td></tr>
        @endif
        </tbody>
    </table>
</div>

@php
    // Расчет статистики
    $localServices = $data['resources']['local_services'] ?? [];
    $globalServices = $data['resources']['global_services'] ?? [];
    $networkHealth = $data['resources']['network_health'] ?? [];

    $localCount = count(array_filter($localServices, fn($s) => $s['ok'] ?? false));
    $localTotal = count($localServices) ?: 1;
    $localPercent = round(($localCount / $localTotal) * 100);

    $globalCount = count(array_filter($globalServices, fn($s) => $s['ok'] ?? false));
    $globalTotal = count($globalServices) ?: 1;
    $globalPercent = round(($globalCount / $globalTotal) * 100);

    $networkCount = count(array_filter($networkHealth, fn($s) => $s['ok'] ?? false));
    $networkTotal = count($networkHealth) ?: 1;
    $networkPercent = round(($networkCount / $networkTotal) * 100);
@endphp

<div class="section">
    <h3>📈 Статистика доступности</h3>
    <table>
        <tr>
            <th>Категория</th>
            <th>Доступность</th>
            <th class="status-cell">Статус</th>
        </tr>
        <tr>
            <td>Локальные сервисы</td>
            <td>{{ $localPercent }}% ({{ $localCount }}/{{ $localTotal }})</td>
            <td class="status-cell">
                @if($localPercent >= 80)
                    <span class="badge ok">Отлично</span>
                @elseif($localPercent >= 50)
                    <span class="badge ok">Хорошо</span>
                @else
                    <span class="badge fail">Плохо</span>
                @endif
            </td>
        </tr>
        <tr>
            <td>Глобальные сервисы</td>
            <td>{{ $globalPercent }}% ({{ $globalCount }}/{{ $globalTotal }})</td>
            <td class="status-cell">
                @if($globalPercent >= 80)
                    <span class="badge ok">Отлично</span>
                @elseif($globalPercent >= 50)
                    <span class="badge ok">Хорошо</span>
                @else
                    <span class="badge fail">Ограничено</span>
                @endif
            </td>
        </tr>
        <tr>
            <td>Стабильность сети</td>
            <td>{{ $networkPercent }}% ({{ $networkCount }}/{{ $networkTotal }})</td>
            <td class="status-cell">
                @if($networkPercent >= 80)
                    <span class="badge ok">Стабильно</span>
                @elseif($networkPercent >= 50)
                    <span class="badge ok">Удовлетворительно</span>
                @else
                    <span class="badge fail">Нестабильно</span>
                @endif
            </td>
        </tr>
    </table>
</div>

@if($globalPercent >= 70)
    <div class="section" style="background: #f0fdf4; padding: 10px; border-radius: 5px; border: 1px solid #bbf7d0;">
        <h3 style="color: #166534; margin: 0 0 5px 0;">✅ Отличная доступность</h3>
        <p style="color: #166534; margin: 0; font-size: 11px;">
            Доступ к {{ $globalPercent }}% международных сайтов указывает на хорошее сетевое подключение.
        </p>
    </div>
@else
    <div class="section" style="background: #eff6ff; padding: 10px; border-radius: 5px; border: 1px solid #bfdbfe;">
        <h3 style="color: #1e40af; margin: 0 0 5px 0;">💡 Рекомендация</h3>
        <p style="color: #1e40af; margin: 0; font-size: 11px;">
            Доступно только {{ $globalPercent }}% международных сервисов.
            Это может указывать на ограничения в сети.
        </p>
    </div>
@endif

<p class="muted small" style="margin-top: 20px;">
    Отчёт сгенерирован автоматически. Время проверки:
    {{ $data['period_display']['start'] }} - {{ $data['period_display']['finish'] }}
</p>
</body>
</html>

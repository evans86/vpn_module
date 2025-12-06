<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Статистика панелей</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .header h1 {
            margin: 0;
            font-size: 18px;
            font-weight: bold;
        }
        .header p {
            margin: 5px 0;
            font-size: 10px;
        }
        .summary {
            margin-bottom: 20px;
            padding: 10px;
            background-color: #f5f5f5;
            border-radius: 5px;
        }
        .summary h2 {
            font-size: 14px;
            margin-bottom: 10px;
            border-bottom: 1px solid #ccc;
            padding-bottom: 5px;
        }
        .summary-row {
            display: table;
            width: 100%;
            margin-bottom: 5px;
        }
        .summary-cell {
            display: table-cell;
            padding: 5px;
            width: 33.33%;
        }
        .summary-label {
            font-weight: bold;
        }
        .summary-value {
            font-size: 12px;
            color: #2563eb;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th {
            background-color: #4b5563;
            color: white;
            padding: 8px;
            text-align: left;
            font-size: 9px;
            font-weight: bold;
        }
        td {
            padding: 6px;
            border: 1px solid #ddd;
            font-size: 9px;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .panel-id {
            font-weight: bold;
            color: #2563eb;
        }
        .positive {
            color: #059669;
        }
        .negative {
            color: #dc2626;
        }
        .traffic-bar {
            background-color: #e5e7eb;
            height: 10px;
            border-radius: 5px;
            margin: 2px 0;
        }
        .traffic-fill {
            background-color: #2563eb;
            height: 100%;
            border-radius: 5px;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 8px;
            color: #666;
            border-top: 1px solid #ccc;
            padding-top: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Статистика использования панелей</h1>
        <p>Период: {{ $lastMonth }} → {{ $currentMonth }}</p>
        <p>Сформировано: {{ $generatedAt }}</p>
    </div>

    <div class="summary">
        <h2>Общие итоги</h2>
        <div class="summary-row">
            <div class="summary-cell">
                <span class="summary-label">Активные пользователи:</span><br>
                <span class="summary-value">{{ number_format($summary['current_month']['total_active_users']) }}</span>
                @if($summary['changes']['active_users'] != 0)
                    <span class="{{ $summary['changes']['active_users'] > 0 ? 'positive' : 'negative' }}">
                        ({{ $summary['changes']['active_users'] > 0 ? '+' : '' }}{{ $summary['changes']['active_users'] }})
                    </span>
                @endif
            </div>
            <div class="summary-cell">
                <span class="summary-label">Онлайн пользователей:</span><br>
                <span class="summary-value">{{ number_format($summary['current_month']['total_online_users']) }}</span>
                @if($summary['changes']['online_users'] != 0)
                    <span class="{{ $summary['changes']['online_users'] > 0 ? 'positive' : 'negative' }}">
                        ({{ $summary['changes']['online_users'] > 0 ? '+' : '' }}{{ $summary['changes']['online_users'] }})
                    </span>
                @endif
            </div>
            <div class="summary-cell">
                <span class="summary-label">Средний % трафика:</span><br>
                <span class="summary-value">{{ number_format($summary['current_month']['avg_traffic_percent'], 2) }}%</span>
                @if($summary['changes']['traffic_percent'] != 0)
                    <span class="{{ $summary['changes']['traffic_percent'] > 0 ? 'negative' : 'positive' }}">
                        ({{ $summary['changes']['traffic_percent'] > 0 ? '+' : '' }}{{ number_format($summary['changes']['traffic_percent'], 2) }}%)
                    </span>
                @endif
            </div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Панель</th>
                <th>Активные пользователи</th>
                <th>Онлайн</th>
                <th>Трафик (ТБ)</th>
                <th>% использования</th>
                <th>Динамика трафика</th>
            </tr>
        </thead>
        <tbody>
            @foreach($statistics as $stat)
                @php
                    $current = $stat['current_month'];
                    $last = $stat['last_month'];
                    $changes = $stat['changes'];
                @endphp
                <tr>
                    <td>
                        <span class="panel-id">ID-{{ $stat['panel_id'] }}</span><br>
                        <small>{{ $stat['panel_address'] }}</small><br>
                        <small style="color: #666;">{{ $stat['server_name'] }}</small>
                    </td>
                    <td>
                        <strong>{{ $current['active_users'] ?? 'N/A' }}</strong>
                        @if($last['active_users'] !== null)
                            <br><small>({{ $last['active_users'] }})</small>
                        @endif
                        @if($changes['active_users'] !== null)
                            <br>
                            <span class="{{ $changes['active_users'] >= 0 ? 'positive' : 'negative' }}">
                                {{ $changes['active_users'] >= 0 ? '▲' : '▼' }} {{ abs($changes['active_users']) }}
                            </span>
                        @endif
                    </td>
                    <td>
                        <strong>{{ $current['online_users'] ?? 'N/A' }}</strong>
                        @if($last['online_users'] !== null)
                            <br><small>({{ $last['online_users'] }})</small>
                        @endif
                        @if($changes['online_users'] !== null)
                            <br>
                            <span class="{{ $changes['online_users'] >= 0 ? 'positive' : 'negative' }}">
                                {{ $changes['online_users'] >= 0 ? '▲' : '▼' }} {{ abs($changes['online_users']) }}
                            </span>
                        @endif
                    </td>
                    <td>
                        @if($current['traffic'])
                            <strong>{{ number_format($current['traffic']['used_tb'], 2) }}</strong> / {{ number_format($current['traffic']['limit_tb'], 2) }}
                        @else
                            N/A
                        @endif
                    </td>
                    <td>
                        @if($current['traffic'])
                            <div class="traffic-bar">
                                <div class="traffic-fill" style="width: {{ min(100, $current['traffic']['used_percent']) }}%"></div>
                            </div>
                            <strong>{{ number_format($current['traffic']['used_percent'], 2) }}%</strong>
                            @if($last['traffic'])
                                <br><small>({{ number_format($last['traffic']['used_percent'], 2) }}%)</small>
                            @endif
                        @else
                            N/A
                        @endif
                    </td>
                    <td>
                        @if($changes['traffic_percent'] !== null)
                            <span class="{{ $changes['traffic_percent'] > 0 ? 'negative' : 'positive' }}">
                                {{ $changes['traffic_percent'] > 0 ? '▲' : '▼' }} {{ abs($changes['traffic_percent']) }}%
                            </span>
                            <br><small>{{ $stat['period']['last']['name'] }} → {{ $stat['period']['current']['name'] }}</small>
                        @else
                            N/A
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>Данные автоматически сформированы системой управления VPN панелями</p>
    </div>
</body>
</html>


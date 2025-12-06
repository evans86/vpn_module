@extends('layouts.admin')

@section('title', 'Статистика панелей')
@section('page-title', 'Статистика использования панелей')

@section('content')
    <div class="space-y-6">
        <!-- Информация о периоде -->
        <div class="bg-white shadow rounded-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Статистика использования панелей</h3>
                    <p class="text-sm text-gray-600 mt-1">
                        Сравнение: {{ $statistics[0]['period']['last']['name'] ?? 'Прошлый месяц' }} → {{ $statistics[0]['period']['current']['name'] ?? 'Текущий месяц' }}
                    </p>
                </div>
                <div>
                    <a href="{{ route('admin.module.panel-statistics.export-pdf') }}"
                       class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                        <i class="fas fa-file-pdf mr-2"></i>
                        Экспорт в PDF
                    </a>
                </div>
            </div>
        </div>

        <!-- Общие итоги -->
        <div class="bg-white shadow rounded-lg p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Общие итоги</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                    <h4 class="text-sm font-semibold text-gray-700 mb-2">Активные пользователи</h4>
                    <div class="text-2xl font-bold text-indigo-600">
                        {{ number_format($summary['current_month']['total_active_users']) }}
                    </div>
                    @if($summary['changes']['active_users'] != 0)
                        <div class="text-sm mt-1 {{ $summary['changes']['active_users'] > 0 ? 'text-green-600' : 'text-red-600' }}">
                            {{ $summary['changes']['active_users'] > 0 ? '▲' : '▼' }} {{ abs($summary['changes']['active_users']) }}
                            ({{ $summary['last_month']['total_active_users'] > 0 ? round(($summary['changes']['active_users'] / $summary['last_month']['total_active_users']) * 100, 1) : 0 }}%)
                        </div>
                    @endif
                </div>
                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                    <h4 class="text-sm font-semibold text-gray-700 mb-2">Онлайн пользователей</h4>
                    <div class="text-2xl font-bold text-indigo-600">
                        {{ number_format($summary['current_month']['total_online_users']) }}
                    </div>
                    @if($summary['changes']['online_users'] != 0)
                        <div class="text-sm mt-1 {{ $summary['changes']['online_users'] > 0 ? 'text-green-600' : 'text-red-600' }}">
                            {{ $summary['changes']['online_users'] > 0 ? '▲' : '▼' }} {{ abs($summary['changes']['online_users']) }}
                        </div>
                    @endif
                </div>
                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                    <h4 class="text-sm font-semibold text-gray-700 mb-2">Средний % использования трафика</h4>
                    <div class="text-2xl font-bold text-indigo-600">
                        {{ number_format($summary['current_month']['avg_traffic_percent'], 2) }}%
                    </div>
                    @if($summary['changes']['traffic_percent'] != 0)
                        <div class="text-sm mt-1 {{ $summary['changes']['traffic_percent'] > 0 ? 'text-red-600' : 'text-green-600' }}">
                            {{ $summary['changes']['traffic_percent'] > 0 ? '▲' : '▼' }} {{ abs($summary['changes']['traffic_percent']) }}%
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Детальная статистика по панелям -->
        <div class="bg-white shadow rounded-lg p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Детальная статистика по панелям</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Панель</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Активные пользователи</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Онлайн</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Трафик</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Динамика</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($statistics as $stat)
                            @php
                                $current = $stat['current_month'];
                                $last = $stat['last_month'];
                                $changes = $stat['changes'];
                            @endphp
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        ID-{{ $stat['panel_id'] }}
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        {{ $stat['panel_address'] }}
                                    </div>
                                    <div class="text-xs text-gray-400">
                                        {{ $stat['server_name'] }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <strong>{{ $current['active_users'] ?? 'N/A' }}</strong>
                                    </div>
                                    @if($last['active_users'] !== null)
                                        <div class="text-xs text-gray-500">
                                            {{ $stat['period']['last']['name'] }}: {{ $last['active_users'] }}
                                        </div>
                                    @endif
                                    @if($changes['active_users'] !== null)
                                        <div class="text-xs {{ $changes['active_users'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                            {{ $changes['active_users'] >= 0 ? '▲' : '▼' }} {{ abs($changes['active_users']) }}
                                        </div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <strong>{{ $current['online_users'] ?? 'N/A' }}</strong>
                                    </div>
                                    @if($last['online_users'] !== null)
                                        <div class="text-xs text-gray-500">
                                            {{ $stat['period']['last']['name'] }}: {{ $last['online_users'] }}
                                        </div>
                                    @endif
                                    @if($changes['online_users'] !== null)
                                        <div class="text-xs {{ $changes['online_users'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                            {{ $changes['online_users'] >= 0 ? '▲' : '▼' }} {{ abs($changes['online_users']) }}
                                        </div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($current['traffic'])
                                        <div class="text-sm text-gray-900">
                                            <strong>{{ number_format($current['traffic']['used_tb'], 2) }} ТБ</strong> / {{ number_format($current['traffic']['limit_tb'], 2) }} ТБ
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-2 mt-1">
                                            <div class="bg-indigo-600 h-2 rounded-full" style="width: {{ min(100, $current['traffic']['used_percent']) }}%"></div>
                                        </div>
                                        <div class="text-xs text-gray-500 mt-1">
                                            {{ number_format($current['traffic']['used_percent'], 2) }}%
                                        </div>
                                        @if($last['traffic'])
                                            <div class="text-xs text-gray-500">
                                                {{ $stat['period']['last']['name'] }}: {{ number_format($last['traffic']['used_percent'], 2) }}%
                                            </div>
                                        @endif
                                    @else
                                        <div class="text-sm text-gray-400">N/A</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($changes['traffic_percent'] !== null)
                                        <div class="text-sm font-medium {{ $changes['traffic_percent'] > 0 ? 'text-red-600' : 'text-green-600' }}">
                                            {{ $changes['traffic_percent'] > 0 ? '▲' : '▼' }} {{ abs($changes['traffic_percent']) }}%
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            {{ $stat['period']['last']['name'] }} → {{ $stat['period']['current']['name'] }}
                                        </div>
                                    @else
                                        <div class="text-sm text-gray-400">N/A</div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Графики -->
        <div class="bg-white shadow rounded-lg p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Графики использования за последние 6 месяцев</h3>
            
            <!-- Chart.js -->
            <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
            
            <div class="space-y-6">
                @foreach($historicalData as $panelData)
                    <div class="border rounded-lg p-4">
                        <h4 class="text-md font-semibold text-gray-800 mb-4">
                            ID-{{ $panelData['panel_id'] }} - {{ $panelData['panel_address'] }}
                        </h4>
                        
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                            <!-- График трафика -->
                            <div class="h-64">
                                <h5 class="text-sm font-medium text-gray-700 mb-2">Использование трафика (%)</h5>
                                <div class="h-52">
                                    <canvas id="traffic-chart-{{ $panelData['panel_id'] }}"></canvas>
                                </div>
                            </div>
                            
                            <!-- График активных пользователей -->
                            <div class="h-64">
                                <h5 class="text-sm font-medium text-gray-700 mb-2">Активные пользователи</h5>
                                <div class="h-52">
                                    <canvas id="active-users-chart-{{ $panelData['panel_id'] }}"></canvas>
                                </div>
                            </div>
                            
                            <!-- График онлайн пользователей -->
                            <div class="h-64">
                                <h5 class="text-sm font-medium text-gray-700 mb-2">Онлайн пользователей</h5>
                                <div class="h-52">
                                    <canvas id="online-users-chart-{{ $panelData['panel_id'] }}"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            @foreach($historicalData as $panelData)
                @php
                    $labels = array_map(function($m) { return $m['month_name']; }, $panelData['months']);
                    $trafficData = array_map(function($m) { return $m['traffic_used_percent']; }, $panelData['months']);
                    $activeUsersData = array_map(function($m) { return $m['active_users']; }, $panelData['months']);
                    $onlineUsersData = array_map(function($m) { return $m['online_users']; }, $panelData['months']);
                @endphp

                // График трафика
                new Chart(document.getElementById('traffic-chart-{{ $panelData['panel_id'] }}'), {
                    type: 'line',
                    data: {
                        labels: @json($labels),
                        datasets: [{
                            label: 'Использование трафика (%)',
                            data: @json($trafficData),
                            borderColor: 'rgb(99, 102, 241)',
                            backgroundColor: 'rgba(99, 102, 241, 0.1)',
                            tension: 0.4,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                max: 100
                            }
                        }
                    }
                });

                // График активных пользователей
                new Chart(document.getElementById('active-users-chart-{{ $panelData['panel_id'] }}'), {
                    type: 'line',
                    data: {
                        labels: @json($labels),
                        datasets: [{
                            label: 'Активные пользователи',
                            data: @json($activeUsersData),
                            borderColor: 'rgb(34, 197, 94)',
                            backgroundColor: 'rgba(34, 197, 94, 0.1)',
                            tension: 0.4,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        },
                        layout: {
                            padding: {
                                top: 5,
                                bottom: 5,
                                left: 5,
                                right: 5
                            }
                        }
                    }
                });

                // График онлайн пользователей
                new Chart(document.getElementById('online-users-chart-{{ $panelData['panel_id'] }}'), {
                    type: 'line',
                    data: {
                        labels: @json($labels),
                        datasets: [{
                            label: 'Онлайн пользователей',
                            data: @json($onlineUsersData),
                            borderColor: 'rgb(239, 68, 68)',
                            backgroundColor: 'rgba(239, 68, 68, 0.1)',
                            tension: 0.4,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        },
                        layout: {
                            padding: {
                                top: 5,
                                bottom: 5,
                                left: 5,
                                right: 5
                            }
                        }
                    }
                });
            @endforeach
        });
    </script>
@endsection


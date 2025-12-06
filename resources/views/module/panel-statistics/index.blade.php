@extends('layouts.admin')

@section('title', 'Статистика панелей')
@section('page-title', 'Статистика использования панелей')

@section('content')
    <div class="space-y-6">
        <!-- Фильтр по месяцу -->
        <div class="bg-white shadow rounded-lg p-6">
            <form method="GET" action="{{ route('admin.module.panel-statistics.index') }}" class="flex items-end gap-4">
                <div class="flex-1">
                    <label for="year" class="block text-sm font-medium text-gray-700 mb-1">Год</label>
                    <select name="year" id="year" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                        @for($y = $currentYear; $y >= 2020; $y--)
                            <option value="{{ $y }}" {{ $selectedYear == $y ? 'selected' : '' }}>{{ $y }}</option>
                        @endfor
                    </select>
                </div>
                <div class="flex-1">
                    <label for="month" class="block text-sm font-medium text-gray-700 mb-1">Месяц</label>
                    <select name="month" id="month" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                        @foreach(range(1, 12) as $m)
                            <option value="{{ $m }}" {{ $selectedMonth == $m ? 'selected' : '' }}>
                                {{ Carbon\Carbon::create(null, $m, 1)->locale('ru')->monthName }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        <i class="fas fa-search mr-2"></i>
                        Показать
                    </button>
                </div>
                <div>
                    <a href="{{ route('admin.module.panel-statistics.export-pdf', ['year' => $selectedYear, 'month' => $selectedMonth]) }}" 
                       class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                        <i class="fas fa-file-pdf mr-2"></i>
                        Экспорт в PDF
                    </a>
                </div>
            </form>
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
    </div>
@endsection


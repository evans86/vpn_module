@extends('layouts.admin')

@section('title', 'Мониторинг серверов')
@section('page-title', 'Статистика нагрузки серверов')

@section('content')
    <div class="space-y-6">
        @foreach($statistics as $panelId => $panelData)
            <x-admin.card>
                <x-slot name="title">
                    <a href="{{ $panelData['panel']->panel_adress }}" 
                       target="_blank" 
                       class="text-indigo-600 hover:text-indigo-800 flex items-center">
                        Статистика панели ID {{ $panelData['panel']->id }}
                        <i class="fas fa-external-link-alt ml-2 text-sm"></i>
                    </a>
                </x-slot>

                @php
                    $lastStats = $panelData['data']->last()['statistics'] ?? [];
                    $cpuUsage = $lastStats['cpu_usage'] ?? 0;
                    $memoryUsage = ($lastStats['mem_used'] ?? 0) / max($lastStats['mem_total'] ?? 1, 1) * 100;
                    $load = max($cpuUsage, $memoryUsage);
                    
                    if ($load < 30) {
                        $loadLevel = 'Низкая';
                        $loadColor = 'green';
                    } elseif ($load < 70) {
                        $loadLevel = 'Средняя';
                        $loadColor = 'yellow';
                    } else {
                        $loadLevel = 'Высокая';
                        $loadColor = 'red';
                    }
                @endphp

                <!-- Краткая статистика -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                    <!-- Пользователи -->
                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                        <h5 class="text-sm font-semibold text-gray-700 mb-2">Пользователи</h5>
                        <div class="text-sm text-gray-600 space-y-1">
                            <div>Активные/Всего: <strong>{{ $lastStats['users_active'] ?? 0 }} / {{ $lastStats['total_user'] ?? 0 }}</strong></div>
                            <div>Онлайн сейчас: <strong>{{ $lastStats['online_users'] ?? 0 }}</strong></div>
                            <div>Истекший срок: <strong>{{ $lastStats['users_expired'] ?? 0 }}</strong></div>
                            <div>Лимит трафика: <strong>{{ $lastStats['users_limited'] ?? 0 }}</strong></div>
                            <div>На удержании: <strong>{{ $lastStats['users_on_hold'] ?? 0 }}</strong></div>
                            <div>Отключены: <strong>{{ $lastStats['users_disabled'] ?? 0 }}</strong></div>
                        </div>
                    </div>

                    <!-- Входящий трафик -->
                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                        <h5 class="text-sm font-semibold text-gray-700 mb-2">Входящий трафик</h5>
                        <p class="text-2xl font-bold text-indigo-600">
                            {{ number_format(($lastStats['incoming_bandwidth'] ?? 0) / (1024 * 1024 * 1024), 2) }} GB
                        </p>
                    </div>

                    <!-- Исходящий трафик -->
                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                        <h5 class="text-sm font-semibold text-gray-700 mb-2">Исходящий трафик</h5>
                        <p class="text-2xl font-bold text-purple-600">
                            {{ number_format(($lastStats['outgoing_bandwidth'] ?? 0) / (1024 * 1024 * 1024), 2) }} GB
                        </p>
                    </div>

                    <!-- Память -->
                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                        <h5 class="text-sm font-semibold text-gray-700 mb-2">Память</h5>
                        <p class="text-lg font-semibold text-gray-900">
                            {{ number_format(($lastStats['mem_used'] ?? 0) / (1024 * 1024 * 1024), 2) }} GB / 
                            {{ number_format(($lastStats['mem_total'] ?? 0) / (1024 * 1024 * 1024), 2) }} GB
                        </p>
                        <div class="mt-2">
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-indigo-600 h-2 rounded-full" 
                                     style="width: {{ min($memoryUsage, 100) }}%"></div>
                            </div>
                            <span class="text-xs text-gray-500">{{ number_format($memoryUsage, 1) }}%</span>
                        </div>
                    </div>
                </div>

                <!-- Анализ нагрузки -->
                <div class="mb-6 bg-gray-50 rounded-lg p-4 border border-gray-200">
                    <h5 class="text-sm font-semibold text-gray-700 mb-2">Анализ нагрузки</h5>
                    <div class="flex items-center space-x-4">
                        <span class="text-sm text-gray-600">Нагрузка:</span>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                            {{ $loadColor === 'green' ? 'bg-green-100 text-green-800' : '' }}
                            {{ $loadColor === 'yellow' ? 'bg-yellow-100 text-yellow-800' : '' }}
                            {{ $loadColor === 'red' ? 'bg-red-100 text-red-800' : '' }}">
                            {{ $loadLevel }}
                        </span>
                        <span class="text-sm text-gray-500">({{ number_format($load, 2) }}%)</span>
                    </div>
                </div>

                <!-- Графики -->
                <div class="space-y-6">
                    <!-- График CPU -->
                    <div>
                        <h5 class="text-sm font-semibold text-gray-700 mb-2">Использование CPU (%)</h5>
                        <div class="chart-container bg-white rounded-lg border border-gray-200 p-4" id="chart-cpu-{{ $panelId }}"></div>
                    </div>

                    <!-- График памяти -->
                    <div>
                        <h5 class="text-sm font-semibold text-gray-700 mb-2">Использование памяти (ГБ)</h5>
                        <div class="chart-container bg-white rounded-lg border border-gray-200 p-4" id="chart-memory-{{ $panelId }}"></div>
                    </div>

                    <!-- График онлайн-пользователей -->
                    <div>
                        <h5 class="text-sm font-semibold text-gray-700 mb-2">Онлайн-пользователи</h5>
                        <div class="chart-container bg-white rounded-lg border border-gray-200 p-4" id="chart-users-{{ $panelId }}"></div>
                    </div>
                </div>
            </x-admin.card>
        @endforeach
    </div>
@endsection

@push('css')
    <style>
        .chart-container {
            height: 400px;
            position: relative;
        }
    </style>
@endpush

@push('js')
    <!-- Подключаем Plotly.js -->
    <script src="https://cdn.plot.ly/plotly-2.24.1.min.js"></script>

    <script>
        @foreach($statistics as $panelId => $panelData)
        // Данные для графиков
        const labels{{ $panelId }} = {!! json_encode($panelData['data']->pluck('created_at')) !!};
        const cpuData{{ $panelId }} = {!! json_encode($panelData['data']->pluck('statistics.cpu_usage')) !!};
        const memoryData{{ $panelId }} = {!! json_encode($panelData['data']->pluck('statistics.mem_used_gb')) !!};
        const usersData{{ $panelId }} = {!! json_encode($panelData['data']->pluck('statistics.online_users')) !!};

        // Вычисляем диапазон для последних 12 часов
        const lastTimestamp{{ $panelId }} = new Date(labels{{ $panelId }}[labels{{ $panelId }}.length - 1]);
        const startTimestamp{{ $panelId }} = new Date(lastTimestamp{{ $panelId }}.getTime() - 12 * 60 * 60 * 1000);

        // График CPU
        Plotly.newPlot(`chart-cpu-{{ $panelId }}`, [{
            x: labels{{ $panelId }},
            y: cpuData{{ $panelId }},
            type: 'scatter',
            mode: 'lines',
            name: 'Использование CPU (%)',
            line: { color: 'rgba(75, 192, 192, 1)' },
        }], {
            title: 'Использование CPU (%)',
            xaxis: {
                title: 'Время',
                range: [startTimestamp{{ $panelId }}, lastTimestamp{{ $panelId }}],
            },
            yaxis: { title: 'Значение' },
            showlegend: true,
        });

        // График памяти
        Plotly.newPlot(`chart-memory-{{ $panelId }}`, [{
            x: labels{{ $panelId }},
            y: memoryData{{ $panelId }},
            type: 'scatter',
            mode: 'lines',
            name: 'Использование памяти (ГБ)',
            line: { color: 'rgba(153, 102, 255, 1)' },
        }], {
            title: 'Использование памяти (ГБ)',
            xaxis: {
                title: 'Время',
                range: [startTimestamp{{ $panelId }}, lastTimestamp{{ $panelId }}],
            },
            yaxis: { title: 'Значение' },
            showlegend: true,
        });

        // График онлайн-пользователей
        Plotly.newPlot(`chart-users-{{ $panelId }}`, [{
            x: labels{{ $panelId }},
            y: usersData{{ $panelId }},
            type: 'scatter',
            mode: 'lines',
            name: 'Онлайн-пользователи',
            line: { color: 'rgba(255, 159, 64, 1)' },
        }], {
            title: 'Онлайн-пользователи',
            xaxis: {
                title: 'Время',
                range: [startTimestamp{{ $panelId }}, lastTimestamp{{ $panelId }}],
            },
            yaxis: { title: 'Значение' },
            showlegend: true,
        });
        @endforeach
    </script>
@endpush

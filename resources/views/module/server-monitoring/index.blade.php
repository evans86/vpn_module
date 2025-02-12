@extends('layouts.app', ['page' => __('Мониторинг серверов'), 'pageSlug' => 'server-monitoring'])

@section('content')
    <style>
        .chart-container {
            height: 300px; /* Фиксированная высота для графиков */
            position: relative; /* Для корректного отображения Chart.js */
        }
    </style>

    <div class="container-fluid">
        @foreach($statistics as $panelId => $panelData)
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title">
                                <a href="{{ $panelData['panel']->panel_adress }}" target="_blank" class="text-primary">
                                    Статистика панели ID {{ $panelData['panel']->id }}
                                    <i class="fas fa-external-link-alt fa-sm"></i>
                                </a>
                            </h4>
                        </div>
                        <div class="card-body">
                            <!-- Краткая статистика -->
                            <div class="row mb-4">
                                <!-- Пользователи -->
                                <div class="col-md-3">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h5 class="card-title">Пользователи</h5>
                                            <p class="card-text">
                                                {{ $panelData['data']->last()['statistics']['users_active'] ?? 0 }} / {{ $panelData['data']->last()['statistics']['total_user'] ?? 0 }}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Входящий трафик -->
                                <div class="col-md-3">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h5 class="card-title">Входящий трафик</h5>
                                            <p class="card-text">
                                                {{ number_format(($panelData['data']->last()['statistics']['incoming_bandwidth'] ?? 0) / (1024 * 1024 * 1024), 2) }} GB
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Исходящий трафик -->
                                <div class="col-md-3">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h5 class="card-title">Исходящий трафик</h5>
                                            <p class="card-text">
                                                {{ number_format(($panelData['data']->last()['statistics']['outgoing_bandwidth'] ?? 0) / (1024 * 1024 * 1024), 2) }} GB
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Память -->
                                <div class="col-md-3">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h5 class="card-title">Память</h5>
                                            <p class="card-text">
                                                {{ number_format(($panelData['data']->last()['statistics']['mem_used'] ?? 0) / (1024 * 1024 * 1024), 2) }} GB / {{ number_format(($panelData['data']->last()['statistics']['mem_total'] ?? 0) / (1024 * 1024 * 1024), 2) }} GB
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Анализ нагрузки -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h5 class="card-title">Анализ нагрузки</h5>
                                            @php
                                                // Расчёт нагрузки
                                                $cpuUsage = $panelData['data']->last()['statistics']['cpu_usage'] ?? 0;
                                                $memoryUsage = ($panelData['data']->last()['statistics']['mem_used'] ?? 0) / ($panelData['data']->last()['statistics']['mem_total'] ?? 1) * 100;
                                                $load = max($cpuUsage, $memoryUsage);

                                                // Определение уровня нагрузки
                                                if ($load < 30) {
                                                    $loadLevel = 'Низкая';
                                                    $loadColor = 'success';
                                                } elseif ($load < 70) {
                                                    $loadLevel = 'Средняя';
                                                    $loadColor = 'warning';
                                                } else {
                                                    $loadLevel = 'Высокая';
                                                    $loadColor = 'danger';
                                                }
                                            @endphp
                                            <p class="card-text">
                                                Нагрузка: <span class="text-{{ $loadColor }}">{{ $loadLevel }}</span> ({{ number_format($load, 2) }}%)
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- График CPU -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h5>Использование CPU (%)</h5>
                                    <div class="chart-container">
                                        <canvas id="chart-cpu-{{ $panelId }}"></canvas>
                                    </div>
                                </div>
                            </div>

                            <!-- График памяти -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h5>Использование памяти (ГБ)</h5>
                                    <div class="chart-container">
                                        <canvas id="chart-memory-{{ $panelId }}"></canvas>
                                    </div>
                                </div>
                            </div>

                            <!-- График онлайн-пользователей -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h5>Онлайн-пользователи</h5>
                                    <div class="chart-container">
                                        <canvas id="chart-users-{{ $panelId }}"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @push('js')
        <!-- Подключаем Chart.js и плагин zoom -->
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-zoom"></script>

        <script>
            // Настройки для всех графиков (выносим за пределы цикла)
            const commonOptions = {
                responsive: true,
                maintainAspectRatio: false, // Отключаем автоматическое соотношение сторон
                plugins: {
                    zoom: {
                        zoom: {
                            wheel: {
                                enabled: true, // Включаем масштабирование колесом мыши
                            },
                            pinch: {
                                enabled: true, // Включаем масштабирование на touch-устройствах
                            },
                            mode: 'x', // Масштабирование только по оси X
                        },
                        pan: {
                            enabled: true, // Включаем перемещение графика
                            mode: 'x', // Перемещение только по оси X
                        },
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                    },
                },
                scales: {
                    x: {
                        display: true,
                        title: {
                            display: true,
                            text: 'Время',
                        },
                    },
                    y: {
                        display: true,
                        title: {
                            display: true,
                            text: 'Значение',
                        },
                        min: 0,
                        max: 100, // Нагрузка от 0% до 100%
                    },
                },
            };

            @foreach($statistics as $panelId => $panelData)
            // Данные для графиков
            const labels{{ $panelId }} = {!! json_encode($panelData['data']->pluck('created_at')) !!};
            const cpuData{{ $panelId }} = {!! json_encode($panelData['data']->pluck('statistics.cpu_usage')) !!};
            const memoryData{{ $panelId }} = {!! json_encode($panelData['data']->pluck('statistics.mem_used_gb')) !!};
            const usersData{{ $panelId }} = {!! json_encode($panelData['data']->pluck('statistics.online_users')) !!};

            // График CPU
            new Chart(document.getElementById('chart-cpu-{{ $panelId }}'), {
                type: 'line',
                data: {
                    labels: labels{{ $panelId }},
                    datasets: [{
                        label: 'Использование CPU (%)',
                        data: cpuData{{ $panelId }},
                        borderColor: 'rgba(75, 192, 192, 1)',
                        fill: false,
                    }]
                },
                options: commonOptions,
            });

            // График памяти
            new Chart(document.getElementById('chart-memory-{{ $panelId }}'), {
                type: 'line',
                data: {
                    labels: labels{{ $panelId }},
                    datasets: [{
                        label: 'Использование памяти (ГБ)',
                        data: memoryData{{ $panelId }},
                        borderColor: 'rgba(153, 102, 255, 1)',
                        fill: false,
                    }]
                },
                options: commonOptions,
            });

            // График онлайн-пользователей
            new Chart(document.getElementById('chart-users-{{ $panelId }}'), {
                type: 'line',
                data: {
                    labels: labels{{ $panelId }},
                    datasets: [{
                        label: 'Онлайн-пользователи',
                        data: usersData{{ $panelId }},
                        borderColor: 'rgba(255, 159, 64, 1)',
                        fill: false,
                    }]
                },
                options: commonOptions,
            });
            @endforeach
        </script>
    @endpush
@endsection

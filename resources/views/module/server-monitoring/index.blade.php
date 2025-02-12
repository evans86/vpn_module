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
                            <h4 class="card-title">Статистика панели: {{ $panelData['panel']->name }}</h4>
                        </div>
                        <div class="card-body">
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

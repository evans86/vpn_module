@extends('layouts.app', ['page' => __('Мониторинг серверов'), 'pageSlug' => 'server-monitoring'])

@section('content')
    <style>
        .chart-container {
            height: 300px; /* Фиксированная высота для графиков */
            position: relative; /* Для корректного отображения D3.js */
            margin-bottom: 20px;
        }
        .chart-container svg {
            width: 100%;
            height: 100%;
        }
        .line {
            fill: none;
            stroke-width: 2px;
        }
        .axis path,
        .axis line {
            fill: none;
            stroke: #000;
            shape-rendering: crispEdges;
        }
        .axis text {
            font-size: 12px;
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
                                    <div class="chart-container" id="chart-cpu-{{ $panelId }}"></div>
                                </div>
                            </div>

                            <!-- График памяти -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h5>Использование памяти (ГБ)</h5>
                                    <div class="chart-container" id="chart-memory-{{ $panelId }}"></div>
                                </div>
                            </div>

                            <!-- График онлайн-пользователей -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h5>Онлайн-пользователи</h5>
                                    <div class="chart-container" id="chart-users-{{ $panelId }}"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @push('js')
        <!-- Подключаем D3.js -->
        <script src="https://d3js.org/d3.v7.min.js"></script>

        <script>
            // Функция для создания графика
            function createChart(containerId, data, label, color) {
                const margin = { top: 20, right: 30, bottom: 30, left: 40 };
                const width = document.getElementById(containerId).clientWidth - margin.left - margin.right;
                const height = 300 - margin.top - margin.bottom;

                // Очищаем контейнер
                d3.select(`#${containerId}`).html('');

                // Создаём SVG-элемент
                const svg = d3.select(`#${containerId}`)
                    .append('svg')
                    .attr('width', width + margin.left + margin.right)
                    .attr('height', height + margin.top + margin.bottom)
                    .append('g')
                    .attr('transform', `translate(${margin.left},${margin.top})`);

                // Преобразуем даты в объекты Date
                const parseDate = d3.timeParse('%Y-%m-%d %H:%M:%S');
                data.forEach(d => d.created_at = parseDate(d.created_at));

                // Шкала для оси X
                const x = d3.scaleTime()
                    .domain(d3.extent(data, d => d.created_at))
                    .range([0, width]);

                // Шкала для оси Y
                const y = d3.scaleLinear()
                    .domain([0, d3.max(data, d => d.value)])
                    .nice()
                    .range([height, 0]);

                // Линия графика
                const line = d3.line()
                    .x(d => x(d.created_at))
                    .y(d => y(d.value));

                // Ось X
                svg.append('g')
                    .attr('transform', `translate(0,${height})`)
                    .call(d3.axisBottom(x));

                // Ось Y
                svg.append('g')
                    .call(d3.axisLeft(y));

                // Добавляем линию графика
                svg.append('path')
                    .datum(data)
                    .attr('class', 'line')
                    .attr('d', line)
                    .attr('stroke', color)
                    .attr('stroke-width', 2)
                    .attr('fill', 'none');
            }

            @foreach($statistics as $panelId => $panelData)
            // Данные для графиков
            const cpuData{{ $panelId }} = {!! json_encode($panelData['data']->map(function ($stat) {
                    return [
                        'created_at' => $stat['created_at'],
                        'value' => $stat['statistics']['cpu_usage'] ?? 0,
                    ];
                })) !!};

            const memoryData{{ $panelId }} = {!! json_encode($panelData['data']->map(function ($stat) {
                    return [
                        'created_at' => $stat['created_at'],
                        'value' => ($stat['statistics']['mem_used'] ?? 0) / (1024 * 1024 * 1024),
                    ];
                })) !!};

            const usersData{{ $panelId }} = {!! json_encode($panelData['data']->map(function ($stat) {
                    return [
                        'created_at' => $stat['created_at'],
                        'value' => $stat['statistics']['online_users'] ?? 0,
                    ];
                })) !!};

            // Создаём графики
            createChart('chart-cpu-{{ $panelId }}', cpuData{{ $panelId }}, 'Использование CPU (%)', 'rgba(75, 192, 192, 1)');
            createChart('chart-memory-{{ $panelId }}', memoryData{{ $panelId }}, 'Использование памяти (ГБ)', 'rgba(153, 102, 255, 1)');
            createChart('chart-users-{{ $panelId }}', usersData{{ $panelId }}, 'Онлайн-пользователи', 'rgba(255, 159, 64, 1)');
            @endforeach
        </script>
    @endpush
@endsection

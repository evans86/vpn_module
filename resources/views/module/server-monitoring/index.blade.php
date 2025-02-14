@extends('layouts.app', ['page' => __('Мониторинг серверов'), 'pageSlug' => 'server-monitoring'])

@section('content')
    <style>
        .chart-container {
            height: 400px; /* Фиксированная высота для графиков */
            position: relative; /* Для корректного отображения Plotly */
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
                                                Активные/Всего: {{ $panelData['data']->last()['statistics']['users_active'] ?? 0 }} / {{ $panelData['data']->last()['statistics']['total_user'] ?? 0 }}<br>
                                                Онлайн сейчас: {{ $panelData['data']->last()['statistics']['online_users'] ?? 0 }}<br>
                                                Истекший срок: {{ $panelData['data']->last()['statistics']['users_expired'] ?? 0 }}<br>
                                                Лимит трафика: {{ $panelData['data']->last()['statistics']['users_limited'] ?? 0 }}<br>
                                                На удержании: {{ $panelData['data']->last()['statistics']['users_on_hold'] ?? 0 }}<br>
                                                Отключены: {{ $panelData['data']->last()['statistics']['users_disabled'] ?? 0 }}
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
            const lastTimestamp = new Date(labels{{ $panelId }}[labels{{ $panelId }}.length - 1]);
            const startTimestamp = new Date(lastTimestamp.getTime() - 12 * 60 * 60 * 1000); // 12 часов назад

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
                    range: [startTimestamp, lastTimestamp], // Устанавливаем диапазон для последних 12 часов
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
                    range: [startTimestamp, lastTimestamp], // Устанавливаем диапазон для последних 12 часов
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
                    range: [startTimestamp, lastTimestamp], // Устанавливаем диапазон для последних 12 часов
                },
                yaxis: { title: 'Значение' },
                showlegend: true,
            });
            @endforeach
        </script>
    @endpush
@endsection

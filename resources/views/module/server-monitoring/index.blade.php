@extends('layouts.app', ['page' => __('Мониторинг серверов'), 'pageSlug' => 'server-monitoring'])

@section('content')
    <div class="container-fluid">
        @foreach($statistics as $panelId => $panelData)
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title">Статистика панели: {{ $panelData['panel']->name }}</h4>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <canvas id="chart-cpu-{{ $panelId }}" height="150"></canvas>
                                </div>
                                <div class="col-md-4">
                                    <canvas id="chart-memory-{{ $panelId }}" height="150"></canvas>
                                </div>
                                <div class="col-md-4">
                                    <canvas id="chart-users-{{ $panelId }}" height="150"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @push('js')
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
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
                options: {
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Процент использования'
                            }
                        }
                    }
                }
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
                options: {
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Гигабайты (ГБ)'
                            }
                        }
                    }
                }
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
                options: {
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Количество пользователей'
                            }
                        }
                    }
                }
            });
            @endforeach
        </script>
    @endpush
@endsection

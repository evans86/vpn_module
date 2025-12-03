@extends('layouts.admin')

@section('title', 'Мониторинг серверов')
@section('page-title', 'Статистика нагрузки серверов')

@section('content')
    <div class="space-y-6">
        <!-- Фильтры -->
        <x-admin.card>
            <x-slot name="title">
                Фильтры и настройки
            </x-slot>
            <form action="{{ route('admin.module.server-monitoring.index') }}" method="GET" class="flex flex-wrap items-end gap-4">
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Период (дни)</label>
                    <select name="days" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 sm:text-sm">
                        <option value="1" {{ $days == 1 ? 'selected' : '' }}>1 день</option>
                        <option value="2" {{ $days == 2 ? 'selected' : '' }}>2 дня</option>
                        <option value="3" {{ $days == 3 ? 'selected' : '' }}>3 дня</option>
                        <option value="7" {{ $days == 7 ? 'selected' : '' }}>7 дней</option>
                    </select>
                </div>
                
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Панель</label>
                    <select name="panel_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 sm:text-sm">
                        <option value="">Все панели</option>
                        @foreach($allPanels as $panel)
                            <option value="{{ $panel->id }}" {{ $panelId == $panel->id ? 'selected' : '' }}>
                                Панель #{{ $panel->id }} ({{ parse_url($panel->panel_adress, PHP_URL_HOST) ?: $panel->panel_adress }})
                            </option>
                        @endforeach
                    </select>
                </div>
                
                <div class="flex-1 min-w-[150px]">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Лимит точек</label>
                    <select name="limit" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 sm:text-sm">
                        <option value="200" {{ $limit == 200 ? 'selected' : '' }}>200 точек</option>
                        <option value="500" {{ $limit == 500 ? 'selected' : '' }}>500 точек</option>
                        <option value="1000" {{ $limit == 1000 ? 'selected' : '' }}>1000 точек</option>
                    </select>
                </div>
                
                <div class="flex gap-2">
                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        <i class="fas fa-filter mr-2"></i> Применить
                    </button>
                    <a href="{{ route('admin.module.server-monitoring.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        <i class="fas fa-redo mr-2"></i> Сбросить
                    </a>
                </div>
            </form>
            
            @if(isset($statistics) && count($statistics) > 0)
                <div class="mt-4 text-sm text-gray-600">
                    <i class="fas fa-info-circle mr-1"></i>
                    Загружено записей: 
                    @foreach($statistics as $pId => $pData)
                        Панель #{{ $pId }}: {{ $pData['data']->count() }} из {{ $pData['total_records'] ?? 0 }}
                        @if(!$loop->last), @endif
                    @endforeach
                </div>
            @endif
        </x-admin.card>

        @if(empty($statistics))
            <x-admin.empty-state 
                icon="fa-chart-line" 
                title="Данные не найдены"
                description="Выберите другой период или панель" />
        @else
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
                    $onlineUsers = $lastStats['online_users'] ?? 0;
                    $totalUsers = $lastStats['total_user'] ?? 0;
                    
                    // Нормализуем нагрузку от пользователей
                    // Используем процент онлайн пользователей от общего количества
                    // Если онлайн > 80% от общего - это высокая нагрузка
                    $userLoad = 0;
                    if ($totalUsers > 0) {
                        $userPercentage = ($onlineUsers / $totalUsers) * 100;
                        // Если онлайн пользователей больше 80% от общего - это нагрузка
                        if ($userPercentage > 80) {
                            $userLoad = min(100, 60 + (($userPercentage - 80) * 2));
                        } elseif ($userPercentage > 60) {
                            $userLoad = 40 + (($userPercentage - 60) * 1);
                        } else {
                            $userLoad = ($userPercentage / 60) * 40;
                        }
                    }
                    
                    // Взвешенная формула нагрузки:
                    // CPU: 40%, Память: 40%, Пользователи: 20%
                    $load = ($cpuUsage * 0.4) + ($memoryUsage * 0.4) + ($userLoad * 0.2);
                    
                    // Определяем уровень нагрузки
                    if ($load < 30) {
                        $loadLevel = 'Низкая';
                        $loadColor = 'green';
                        $loadIcon = 'fa-check-circle';
                    } elseif ($load < 60) {
                        $loadLevel = 'Средняя';
                        $loadColor = 'yellow';
                        $loadIcon = 'fa-exclamation-triangle';
                    } elseif ($load < 80) {
                        $loadLevel = 'Высокая';
                        $loadColor = 'orange';
                        $loadIcon = 'fa-exclamation-circle';
                    } else {
                        $loadLevel = 'Критическая';
                        $loadColor = 'red';
                        $loadIcon = 'fa-times-circle';
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
                    <h5 class="text-sm font-semibold text-gray-700 mb-3">Анализ нагрузки сервера</h5>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm text-gray-600">Общая нагрузка:</span>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                                    {{ $loadColor === 'green' ? 'bg-green-100 text-green-800' : '' }}
                                    {{ $loadColor === 'yellow' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                    {{ $loadColor === 'orange' ? 'bg-orange-100 text-orange-800' : '' }}
                                    {{ $loadColor === 'red' ? 'bg-red-100 text-red-800' : '' }}">
                                    <i class="fas {{ $loadIcon }} mr-1"></i>
                                    {{ $loadLevel }}
                                </span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-3">
                                <div class="h-3 rounded-full transition-all duration-300
                                    {{ $loadColor === 'green' ? 'bg-green-500' : '' }}
                                    {{ $loadColor === 'yellow' ? 'bg-yellow-500' : '' }}
                                    {{ $loadColor === 'orange' ? 'bg-orange-500' : '' }}
                                    {{ $loadColor === 'red' ? 'bg-red-500' : '' }}"
                                     style="width: {{ min($load, 100) }}%"></div>
                            </div>
                            <div class="text-xs text-gray-500 mt-1">{{ number_format($load, 1) }}% (CPU: 40%, Память: 40%, Пользователи: 20%)</div>
                        </div>
                        <div class="text-sm text-gray-600 space-y-1">
                            <div class="flex justify-between">
                                <span>CPU:</span>
                                <span class="font-semibold {{ $cpuUsage > 80 ? 'text-red-600' : ($cpuUsage > 60 ? 'text-yellow-600' : 'text-gray-800') }}">
                                    {{ number_format($cpuUsage, 1) }}%
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span>Память:</span>
                                <span class="font-semibold {{ $memoryUsage > 80 ? 'text-red-600' : ($memoryUsage > 60 ? 'text-yellow-600' : 'text-gray-800') }}">
                                    {{ number_format($memoryUsage, 1) }}%
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span>Онлайн пользователей:</span>
                                <span class="font-semibold">{{ $onlineUsers }} / {{ $totalUsers }}</span>
                            </div>
                        </div>
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
        @endif
    </div>
@endsection

@push('css')
    <style>
        .chart-container {
            height: 400px;
            position: relative;
            width: 100%;
            overflow: hidden;
        }
        .chart-container .js-plotly-plot {
            width: 100% !important;
            height: 100% !important;
        }
    </style>
@endpush

@push('js')
    <!-- Подключаем Plotly.js -->
    <script src="https://cdn.plot.ly/plotly-2.24.1.min.js"></script>

    <script>
        @foreach($statistics as $panelId => $panelData)
        // Оптимизированная загрузка данных - извлекаем только нужные поля
        const chartData{{ $panelId }} = {!! json_encode($panelData['data']->map(function($item) {
            return [
                'time' => $item['created_at'],
                'cpu' => $item['statistics']['cpu_usage'] ?? 0,
                'memory' => $item['statistics']['mem_used_gb'] ?? 0,
                'users' => $item['statistics']['online_users'] ?? 0,
            ];
        })) !!};
        
        // Извлекаем данные для графиков
        const labels{{ $panelId }} = chartData{{ $panelId }}.map(item => item.time);
        const cpuData{{ $panelId }} = chartData{{ $panelId }}.map(item => item.cpu);
        const memoryData{{ $panelId }} = chartData{{ $panelId }}.map(item => item.memory);
        const usersData{{ $panelId }} = chartData{{ $panelId }}.map(item => item.users);

        // Проверяем наличие данных
        if (labels{{ $panelId }}.length === 0) {
            document.getElementById(`chart-cpu-{{ $panelId }}`).innerHTML = '<div class="text-center text-gray-500 py-8">Нет данных для отображения</div>';
            document.getElementById(`chart-memory-{{ $panelId }}`).innerHTML = '<div class="text-center text-gray-500 py-8">Нет данных для отображения</div>';
            document.getElementById(`chart-users-{{ $panelId }}`).innerHTML = '<div class="text-center text-gray-500 py-8">Нет данных для отображения</div>';
        } else {
            // График CPU
            Plotly.newPlot(`chart-cpu-{{ $panelId }}`, [{
                x: labels{{ $panelId }},
                y: cpuData{{ $panelId }},
                type: 'scatter',
                mode: 'lines+markers',
                name: 'Использование CPU (%)',
                line: { color: 'rgba(75, 192, 192, 1)', width: 2 },
                marker: { size: 4, opacity: 0.7 },
                hovertemplate: '<b>CPU: %{y:.1f}%</b><br>Время: %{x}<extra></extra>',
            }], {
                title: {
                    text: 'Использование CPU (%)',
                    font: { size: 14 }
                },
                xaxis: {
                    title: 'Время',
                    type: 'date',
                    showgrid: true,
                    gridcolor: 'rgba(0,0,0,0.1)',
                },
                yaxis: { 
                    title: 'Процент (%)',
                    range: [0, 100],
                    showgrid: true,
                    gridcolor: 'rgba(0,0,0,0.1)',
                },
                showlegend: true,
                hovermode: 'x unified',
                responsive: true,
                autosize: true,
                displayModeBar: true,
                displaylogo: false,
                modeBarButtonsToRemove: ['lasso2d', 'select2d'],
                layout: {
                    autosize: true,
                    margin: { l: 60, r: 30, t: 50, b: 60 },
                }
            }, {
                responsive: true,
                displayModeBar: true
            });

            // График памяти
            Plotly.newPlot(`chart-memory-{{ $panelId }}`, [{
                x: labels{{ $panelId }},
                y: memoryData{{ $panelId }},
                type: 'scatter',
                mode: 'lines+markers',
                name: 'Использование памяти (ГБ)',
                line: { color: 'rgba(153, 102, 255, 1)', width: 2 },
                marker: { size: 4, opacity: 0.7 },
                hovertemplate: '<b>Память: %{y:.2f} ГБ</b><br>Время: %{x}<extra></extra>',
            }], {
                title: {
                    text: 'Использование памяти (ГБ)',
                    font: { size: 14 }
                },
                xaxis: {
                    title: 'Время',
                    type: 'date',
                    showgrid: true,
                    gridcolor: 'rgba(0,0,0,0.1)',
                },
                yaxis: { 
                    title: 'Гигабайты (ГБ)',
                    showgrid: true,
                    gridcolor: 'rgba(0,0,0,0.1)',
                },
                showlegend: true,
                hovermode: 'x unified',
                responsive: true,
                autosize: true,
                displayModeBar: true,
                displaylogo: false,
                modeBarButtonsToRemove: ['lasso2d', 'select2d'],
                layout: {
                    autosize: true,
                    margin: { l: 60, r: 30, t: 50, b: 60 },
                }
            }, {
                responsive: true,
                displayModeBar: true
            });

            // График онлайн-пользователей
            Plotly.newPlot(`chart-users-{{ $panelId }}`, [{
                x: labels{{ $panelId }},
                y: usersData{{ $panelId }},
                type: 'scatter',
                mode: 'lines+markers',
                name: 'Онлайн-пользователи',
                line: { color: 'rgba(255, 159, 64, 1)', width: 2 },
                marker: { size: 4, opacity: 0.7 },
                hovertemplate: '<b>Онлайн: %{y} пользователей</b><br>Время: %{x}<extra></extra>',
            }], {
                title: {
                    text: 'Онлайн-пользователи',
                    font: { size: 14 }
                },
                xaxis: {
                    title: 'Время',
                    type: 'date',
                    showgrid: true,
                    gridcolor: 'rgba(0,0,0,0.1)',
                },
                yaxis: { 
                    title: 'Количество пользователей',
                    showgrid: true,
                    gridcolor: 'rgba(0,0,0,0.1)',
                },
                showlegend: true,
                hovermode: 'x unified',
                responsive: true,
                autosize: true,
                displayModeBar: true,
                displaylogo: false,
                modeBarButtonsToRemove: ['lasso2d', 'select2d'],
                layout: {
                    autosize: true,
                    margin: { l: 60, r: 30, t: 50, b: 60 },
                }
            }, {
                responsive: true,
                displayModeBar: true
            });
            
            // Обработка изменения размера окна
            window.addEventListener('resize', function() {
                Plotly.Plots.resize(`chart-cpu-{{ $panelId }}`);
                Plotly.Plots.resize(`chart-memory-{{ $panelId }}`);
                Plotly.Plots.resize(`chart-users-{{ $panelId }}`);
            });
        }
        @endforeach
    </script>
@endpush

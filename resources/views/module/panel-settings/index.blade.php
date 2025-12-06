@extends('layouts.admin')

@section('title', 'Настройки распределения панелей')
@section('page-title', 'Настройки распределения панелей')

@section('content')
    <div class="space-y-6">
        @if(session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline">{{ session('success') }}</span>
            </div>
        @endif

        @if(session('error'))
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline">{{ session('error') }}</span>
            </div>
        @endif

        <div class="bg-white shadow rounded-lg p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Выбор стратегии распределения</h3>
            <p class="text-sm text-gray-600 mb-6">
                Выберите систему распределения пользователей по панелям при создании нового пользователя.
            </p>

            <form action="{{ route('admin.module.panel-settings.update-strategy') }}" method="POST">
                @csrf
                @method('PUT')

                <div class="space-y-4">
                    @foreach($strategies as $key => $strategy)
                        <div class="border rounded-lg p-4 hover:bg-gray-50 transition-colors {{ $currentStrategy === $key ? 'border-blue-500 bg-blue-50' : 'border-gray-200' }}">
                            <label class="flex items-start cursor-pointer">
                                <input type="radio" 
                                       name="strategy" 
                                       value="{{ $key }}" 
                                       class="mt-1 mr-3"
                                       {{ $currentStrategy === $key ? 'checked' : '' }}
                                       required>
                                <div class="flex-1">
                                    <div class="flex items-center mb-2">
                                        <span class="text-2xl mr-2">{{ $strategy['icon'] }}</span>
                                        <span class="font-semibold text-gray-900">{{ $strategy['name'] }}</span>
                                        @if($currentStrategy === $key)
                                            <span class="ml-2 px-2 py-1 text-xs font-semibold bg-blue-500 text-white rounded">
                                                Активна
                                            </span>
                                        @endif
                                    </div>
                                    <p class="text-sm text-gray-600">{{ $strategy['description'] }}</p>
                                </div>
                            </label>
                        </div>
                    @endforeach
                </div>

                <div class="mt-6 flex items-center justify-end">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-semibold transition-colors">
                        <i class="fas fa-save mr-2"></i> Сохранить настройки
                    </button>
                </div>
            </form>
        </div>

        <!-- Сравнение стратегий -->
        @if(isset($comparison) && !isset($comparison['error']))
            <div class="bg-white shadow rounded-lg p-6">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-semibold text-gray-900">Сравнение стратегий</h3>
                    <button onclick="location.reload()" class="text-sm text-blue-600 hover:text-blue-800 flex items-center">
                        <i class="fas fa-sync-alt mr-2"></i> Обновить данные
                    </button>
                </div>

                <!-- Результаты выбора каждой стратегии -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    @foreach(['balanced' => '⚖️', 'traffic_based' => '📊', 'intelligent' => '🧠'] as $strategyKey => $icon)
                        @php
                            $strategyInfo = $comparison['strategies'][$strategyKey] ?? null;
                            $selectedPanel = $strategyInfo['selected_panel_info'] ?? null;
                        @endphp
                        <div class="border rounded-lg p-4 {{ $currentStrategy === $strategyKey ? 'border-blue-500 bg-blue-50' : 'border-gray-200' }}">
                            <div class="flex items-center mb-2">
                                <span class="text-2xl mr-2">{{ $icon }}</span>
                                <span class="font-semibold text-sm">{{ $strategies[$strategyKey]['name'] }}</span>
                            </div>
                            @if($selectedPanel)
                                <div class="space-y-1 text-sm">
                                    <div><strong>Панель ID:</strong> {{ $selectedPanel['id'] }}</div>
                                    <div><strong>Сервер:</strong> {{ $selectedPanel['server_name'] ?? 'N/A' }}</div>
                                    <div><strong>Пользователи:</strong> {{ $selectedPanel['total_users'] }} (активных: {{ $selectedPanel['active_users'] }})</div>
                                    @if($selectedPanel['traffic_used_percent'] !== null)
                                        <div class="flex items-center gap-2">
                                            <strong>Трафик:</strong> 
                                            <span class="px-2 py-0.5 rounded text-xs font-medium 
                                                {{ $selectedPanel['traffic_used_percent'] > 80 ? 'bg-red-100 text-red-800' : ($selectedPanel['traffic_used_percent'] > 60 ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800') }}">
                                                {{ number_format($selectedPanel['traffic_used_percent'], 1) }}%
                                            </span>
                                            @if($selectedPanel['traffic_used_gb'])
                                                <span class="text-gray-500 text-xs">({{ number_format($selectedPanel['traffic_used_gb'], 0) }} GB)</span>
                                            @endif
                                        </div>
                                    @else
                                        <div class="text-gray-400 text-xs">Трафик: нет данных</div>
                                    @endif
                                    @if($selectedPanel['cpu_usage'] > 0)
                                        <div><strong>CPU:</strong> {{ $selectedPanel['cpu_usage'] }}%</div>
                                    @endif
                                    @if($selectedPanel['memory_usage'] > 0)
                                        <div><strong>Память:</strong> {{ $selectedPanel['memory_usage'] }}%</div>
                                    @endif
                                    @if($selectedPanel['intelligent_score'] > 0)
                                        <div><strong>Score:</strong> {{ $selectedPanel['intelligent_score'] }}</div>
                                    @endif
                                </div>
                            @else
                                <p class="text-sm text-gray-500">Не выбрана</p>
                            @endif
                        </div>
                    @endforeach
                </div>

                <!-- Общая статистика -->
                <div class="bg-gray-50 rounded-lg p-4 mb-6">
                    <h4 class="text-md font-semibold text-gray-900 mb-3">Общая статистика</h4>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                        <div>
                            <div class="text-gray-600">Всего панелей</div>
                            <div class="text-lg font-semibold">{{ $comparison['summary']['total_panels'] }}</div>
                        </div>
                        <div>
                            <div class="text-gray-600">С актуальной статистикой</div>
                            <div class="text-lg font-semibold">{{ $comparison['summary']['panels_with_stats'] }}</div>
                        </div>
                        <div>
                            <div class="text-gray-600">С данными о трафике</div>
                            <div class="text-lg font-semibold">{{ $comparison['summary']['panels_with_traffic'] }}</div>
                        </div>
                        <div>
                            <div class="text-gray-600">Средний трафик</div>
                            <div class="text-lg font-semibold">{{ number_format($comparison['summary']['avg_traffic'], 1) }}%</div>
                        </div>
                    </div>
                </div>

                <!-- Детальная таблица панелей -->
                <div class="overflow-x-auto">
                    <h4 class="text-md font-semibold text-gray-900 mb-3">Детальная информация по панелям</h4>
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Сервер</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Пользователи</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Трафик</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">CPU</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Память</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Score</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Выбор</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($comparison['panels'] as $panel)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                        {{ $panel['id'] }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                                        {{ $panel['server_name'] }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                                        <div>{{ $panel['total_users'] }} всего</div>
                                        <div class="text-xs text-gray-500">{{ $panel['active_users'] }} активных</div>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm">
                                        @if($panel['traffic_used_percent'] !== null)
                                            <div class="flex items-center">
                                                <div class="flex-1">
                                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                                        <div class="bg-{{ $panel['traffic_used_percent'] > 80 ? 'red' : ($panel['traffic_used_percent'] > 60 ? 'yellow' : 'green') }}-600 h-2 rounded-full" 
                                                             style="width: {{ min(100, $panel['traffic_used_percent']) }}%"></div>
                                                    </div>
                                                    <div class="text-xs text-gray-600 mt-1">
                                                        {{ number_format($panel['traffic_used_percent'], 1) }}%
                                                        @if($panel['traffic_used_gb'])
                                                            ({{ number_format($panel['traffic_used_gb'], 0) }} GB)
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        @else
                                            <span class="text-gray-400">Нет данных</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm">
                                        @if($panel['cpu_usage'] > 0)
                                            <span class="px-2 py-1 rounded text-xs font-medium 
                                                {{ $panel['cpu_usage'] > 80 ? 'bg-red-100 text-red-800' : ($panel['cpu_usage'] > 60 ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800') }}">
                                                {{ $panel['cpu_usage'] }}%
                                            </span>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm">
                                        @if($panel['memory_usage'] > 0)
                                            <span class="px-2 py-1 rounded text-xs font-medium 
                                                {{ $panel['memory_usage'] > 80 ? 'bg-red-100 text-red-800' : ($panel['memory_usage'] > 60 ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800') }}">
                                                {{ $panel['memory_usage'] }}%
                                            </span>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm">
                                        @if($panel['intelligent_score'] > 0)
                                            <span class="font-semibold text-gray-900">{{ $panel['intelligent_score'] }}</span>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm">
                                        <div class="flex flex-col gap-1">
                                            @if($panel['is_balanced_selected'])
                                                <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs">⚖️ Balanced</span>
                                            @endif
                                            @if($panel['is_traffic_selected'])
                                                <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs">📊 Traffic</span>
                                            @endif
                                            @if($panel['is_intelligent_selected'])
                                                <span class="px-2 py-1 bg-purple-100 text-purple-800 rounded text-xs">🧠 Intelligent</span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 text-xs text-gray-500">
                    <p>Обновлено: {{ $comparison['timestamp'] }}</p>
                </div>
            </div>
        @elseif(isset($comparison['error']))
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <p class="text-yellow-800">{{ $comparison['error'] }}</p>
            </div>
        @endif

        <!-- Информация о текущей стратегии -->
        <div class="bg-gray-50 border border-gray-200 rounded-lg p-6">
            <h4 class="text-md font-semibold text-gray-900 mb-3">Текущая стратегия</h4>
            <div class="space-y-2 text-sm text-gray-700">
                @if($currentStrategy === 'balanced')
                    <p><strong>Равномерное распределение</strong> - система выбирает панель с минимальным количеством пользователей.</p>
                    <p class="text-gray-600">Подходит для равномерного распределения нагрузки между панелями.</p>
                @elseif($currentStrategy === 'traffic_based')
                    <p><strong>На основе трафика сервера</strong> - система выбирает панель на сервере с наименьшим процентом использования трафика.</p>
                    <p class="text-gray-600">Идеально для предотвращения перегрузки серверов по трафику (особенно в конце месяца).</p>
                @else
                    <p><strong>Интеллектуальная система</strong> - комплексный анализ: количество пользователей, нагрузка CPU/памяти и трафик.</p>
                    <p class="text-gray-600">Балансирует все факторы для оптимального распределения.</p>
                @endif
            </div>
        </div>

        <!-- Панели с ошибками -->
        @if($panelsWithErrors->isNotEmpty())
            <div class="bg-white shadow rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                    <span class="text-red-500 mr-2">⚠️</span>
                    Панели с ошибками (исключены из ротации)
                </h3>
                <p class="text-sm text-gray-600 mb-6">
                    Эти панели автоматически исключены из ротации из-за ошибок при создании пользователей. 
                    После устранения проблемы снимите пометку об ошибке, чтобы вернуть панель в ротацию.
                </p>

                <div class="space-y-4">
                    @foreach($panelsWithErrors as $panel)
                        <div class="border border-red-200 rounded-lg p-4 bg-red-50">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center mb-2">
                                        <span class="font-semibold text-gray-900">ID-{{ $panel->id }}</span>
                                        <span class="ml-2 px-2 py-1 text-xs font-semibold bg-red-500 text-white rounded">
                                            Ошибка
                                        </span>
                                    </div>
                                    <div class="text-sm text-gray-600 mb-2">
                                        <div><strong>Адрес:</strong> {{ $panel->panel_adress }}</div>
                                        @if($panel->server)
                                            <div><strong>Сервер:</strong> {{ $panel->server->name }}</div>
                                        @endif
                                        @if($panel->error_at)
                                            <div><strong>Дата ошибки:</strong> {{ $panel->error_at->format('d.m.Y H:i') }}</div>
                                        @endif
                                    </div>
                                    <div class="mt-3 p-3 bg-white rounded border border-red-200">
                                        <div class="text-sm font-medium text-gray-700 mb-1">Сообщение об ошибке:</div>
                                        <div class="text-sm text-gray-800 whitespace-pre-wrap">{{ $panel->error_message }}</div>
                                    </div>
                                    
                                    @if(isset($errorHistory[$panel->id]) && $errorHistory[$panel->id]->isNotEmpty())
                                        <div class="mt-3">
                                            <div class="text-sm font-medium text-gray-700 mb-2">История ошибок:</div>
                                            <div class="space-y-2">
                                                @foreach($errorHistory[$panel->id] as $history)
                                                    <div class="text-xs p-2 bg-gray-50 rounded border">
                                                        <div class="flex justify-between items-start mb-1">
                                                            <span class="font-medium text-gray-700">
                                                                {{ $history->error_occurred_at->format('d.m.Y H:i') }}
                                                            </span>
                                                            @if($history->resolved_at)
                                                                <span class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded">
                                                                    {{ $history->resolution_type === 'automatic' ? 'Автоматически' : 'Вручную' }}
                                                                </span>
                                                            @else
                                                                <span class="px-2 py-1 text-xs bg-red-100 text-red-800 rounded">
                                                                    Активна
                                                                </span>
                                                            @endif
                                                        </div>
                                                        <div class="text-gray-600 mb-1">{{ $history->error_message }}</div>
                                                        @if($history->resolved_at)
                                                            <div class="text-gray-500 text-xs">
                                                                Решено: {{ $history->resolved_at->format('d.m.Y H:i') }}
                                                                @if($history->resolution_note)
                                                                    - {{ $history->resolution_note }}
                                                                @endif
                                                            </div>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>
                                <div class="ml-4">
                                    <form action="{{ route('admin.module.panel-settings.clear-error') }}" method="POST" 
                                          onsubmit="return confirm('Вы уверены, что проблема решена и панель можно вернуть в ротацию?');">
                                        @csrf
                                        <input type="hidden" name="panel_id" value="{{ $panel->id }}">
                                        <button type="submit" 
                                                class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 transition-colors">
                                            Проблема решена
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            <div class="bg-white shadow rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                    <span class="text-green-500 mr-2">✅</span>
                    Статус панелей
                </h3>
                <p class="text-sm text-gray-600">
                    Все панели работают нормально. Ошибок не обнаружено.
                </p>
            </div>
        @endif
    </div>
@endsection


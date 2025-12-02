@extends('layouts.admin')

@section('title', 'Логи приложения')
@section('page-title', 'Логи приложения')

@section('content')
    <div class="space-y-6">
        <!-- Статистика по уровням -->
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
            <div class="bg-red-600 text-white rounded-lg p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-xs font-semibold uppercase mb-1 opacity-90">Ошибки</div>
                        <div class="text-2xl font-bold" id="stats-error">{{ $stats['error'] ?? 0 }}</div>
                    </div>
                    <i class="fas fa-exclamation-circle text-3xl opacity-75"></i>
                </div>
            </div>
            <div class="bg-yellow-600 text-white rounded-lg p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-xs font-semibold uppercase mb-1 opacity-90">Предупреждения</div>
                        <div class="text-2xl font-bold" id="stats-warning">{{ $stats['warning'] ?? 0 }}</div>
                    </div>
                    <i class="fas fa-exclamation-triangle text-3xl opacity-75"></i>
                </div>
            </div>
            <div class="bg-blue-600 text-white rounded-lg p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-xs font-semibold uppercase mb-1 opacity-90">Информация</div>
                        <div class="text-2xl font-bold" id="stats-info">{{ $stats['info'] ?? 0 }}</div>
                    </div>
                    <i class="fas fa-info-circle text-3xl opacity-75"></i>
                </div>
            </div>
            <div class="bg-gray-600 text-white rounded-lg p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-xs font-semibold uppercase mb-1 opacity-90">Отладка</div>
                        <div class="text-2xl font-bold" id="stats-debug">{{ $stats['debug'] ?? 0 }}</div>
                    </div>
                    <i class="fas fa-bug text-3xl opacity-75"></i>
                </div>
            </div>
            <div class="bg-indigo-600 text-white rounded-lg p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-xs font-semibold uppercase mb-1 opacity-90">Всего</div>
                        <div class="text-2xl font-bold" id="stats-total">{{ $stats['total'] ?? 0 }}</div>
                    </div>
                    <i class="fas fa-list text-3xl opacity-75"></i>
                </div>
            </div>
        </div>

        <x-admin.card>
            <x-slot name="title">
                Логи приложения
            </x-slot>
            <x-slot name="tools">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800" id="logsCount">
                    Показано: {{ $logs->count() }} из {{ $logs->total() }}
                </span>
            </x-slot>

            <!-- Быстрые фильтры -->
            <div class="mb-4 flex flex-wrap gap-2">
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="inline-flex items-center px-3 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 quick-filter" data-level="error">
                        <i class="fas fa-exclamation-circle mr-1"></i> Только ошибки
                    </button>
                    <button type="button" class="inline-flex items-center px-3 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-yellow-600 hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500 quick-filter" data-level="warning">
                        <i class="fas fa-exclamation-triangle mr-1"></i> Предупреждения
                    </button>
                    <button type="button" class="inline-flex items-center px-3 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 quick-filter" data-level="critical">
                        <i class="fas fa-skull mr-1"></i> Критические
                    </button>
                    <button type="button" class="inline-flex items-center px-3 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 quick-filter" data-level="">
                        <i class="fas fa-list mr-1"></i> Все логи
                    </button>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="inline-flex items-center px-3 py-2 border border-indigo-300 text-sm font-medium rounded-md shadow-sm text-indigo-700 bg-white hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 quick-date" data-days="1">
                        Сегодня
                    </button>
                    <button type="button" class="inline-flex items-center px-3 py-2 border border-indigo-300 text-sm font-medium rounded-md shadow-sm text-indigo-700 bg-white hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 quick-date" data-days="7">
                        7 дней
                    </button>
                    <button type="button" class="inline-flex items-center px-3 py-2 border border-indigo-300 text-sm font-medium rounded-md shadow-sm text-indigo-700 bg-white hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 quick-date" data-days="30">
                        30 дней
                    </button>
                </div>
            </div>

            <x-admin.filter-form action="{{ route('admin.logs.index') }}" id="logsFilterForm">
                <x-admin.filter-select 
                    name="level" 
                    label="Уровень"
                    :options="[
                        'error' => 'Ошибка',
                        'critical' => 'Критическая',
                        'warning' => 'Предупреждение',
                        'info' => 'Информация',
                        'debug' => 'Отладка'
                    ]"
                    value="{{ request('level') }}"
                    placeholder="Все уровни" />
                
                <x-admin.filter-select 
                    name="source" 
                    label="Источник"
                    :options="collect($sources)->mapWithKeys(function($source) {
                        return [$source => $source];
                    })->toArray()"
                    value="{{ request('source') }}"
                    placeholder="Все источники" />
                
                <x-admin.filter-input 
                    name="date_from" 
                    label="Дата от" 
                    value="{{ request('date_from', now()->subDays(7)->format('Y-m-d')) }}" 
                    type="date" />
                
                <x-admin.filter-input 
                    name="date_to" 
                    label="Дата до" 
                    value="{{ request('date_to', now()->format('Y-m-d')) }}" 
                    type="date" />
                
                <x-admin.filter-input 
                    name="search" 
                    label="Поиск (мин. 3 символа)" 
                    value="{{ request('search') }}" 
                    placeholder="Поиск по сообщению или источнику" />
            </x-admin.filter-form>

            <!-- Индикатор загрузки -->
            <div id="loadingIndicator" class="text-center py-8 hidden">
                <i class="fas fa-spinner fa-spin fa-2x text-indigo-600"></i>
                <p class="mt-2 text-gray-600">Загрузка логов...</p>
            </div>

            <!-- Таблица логов -->
            <div id="logsTableContainer">
                @if($logs->isEmpty())
                    <x-admin.empty-state 
                        icon="fa-file-alt" 
                        title="Логи не найдены"
                        description="Попробуйте изменить параметры фильтрации" />
                @else
                    <x-admin.table :headers="['Время', 'Уровень', 'Источник', 'Сообщение', 'Действия']" :responsive="true">
                        <tbody class="bg-white divide-y divide-gray-200" id="logsTableBody">
                            @include('logs.partials.table', ['logs' => $logs])
                        </tbody>
                    </x-admin.table>
                @endif
            </div>

            <!-- Пагинация -->
            <div id="paginationContainer" class="mt-4">
                @include('logs.partials.pagination')
            </div>
        </x-admin.card>
    </div>
@endsection

@push('styles')
    <style>
        .log-row.table-danger {
            background-color: #fee2e2 !important;
        }

        .log-row.table-warning {
            background-color: #fef3c7 !important;
        }

        .log-row:hover {
            background-color: #f9fafb !important;
        }

        .log-message {
            max-width: 500px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .quick-filter.active {
            box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.5);
        }
    </style>
@endpush

@push('js')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('logsFilterForm');
            const tableBody = document.getElementById('logsTableBody');
            const loadingIndicator = document.getElementById('loadingIndicator');
            const tableContainer = document.getElementById('logsTableContainer');
            let searchTimeout;

            // Быстрые фильтры по уровню
            document.querySelectorAll('.quick-filter').forEach(button => {
                button.addEventListener('click', function() {
                    document.querySelectorAll('.quick-filter').forEach(btn => btn.classList.remove('active'));
                    this.classList.add('active');
                    
                    const level = this.dataset.level;
                    document.getElementById('level').value = level;
                    submitForm();
                });
            });

            // Быстрые фильтры по дате
            document.querySelectorAll('.quick-date').forEach(button => {
                button.addEventListener('click', function() {
                    const days = parseInt(this.dataset.days);
                    const dateFrom = new Date();
                    dateFrom.setDate(dateFrom.getDate() - days);
                    
                    document.getElementById('date_from').value = dateFrom.toISOString().split('T')[0];
                    document.getElementById('date_to').value = new Date().toISOString().split('T')[0];
                    
                    submitForm();
                });
            });

            // Поиск с debounce (минимум 3 символа)
            const searchInput = document.getElementById('search');
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                const value = this.value.trim();
                
                if (value.length === 0 || value.length >= 3) {
                    searchTimeout = setTimeout(() => {
                        if (value.length >= 3 || value.length === 0) {
                            submitForm();
                        }
                    }, 500); // Задержка 500мс
                }
            });

            // Изменение других фильтров
            document.getElementById('level').addEventListener('change', submitForm);
            document.getElementById('source').addEventListener('change', submitForm);
            document.getElementById('date_from').addEventListener('change', submitForm);
            document.getElementById('date_to').addEventListener('change', submitForm);

            // Обработка формы
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                submitForm();
            });

            // AJAX загрузка
            function submitForm() {
                const formData = new FormData(form);
                const params = new URLSearchParams(formData);
                
                // Показываем индикатор загрузки
                loadingIndicator.classList.remove('hidden');
                tableContainer.style.opacity = '0.5';
                
                fetch(`${form.action}?${params.toString()}`, {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.html) {
                        // Обновляем tbody таблицы
                        if (tableBody) {
                            tableBody.innerHTML = data.html;
                        }
                        
                        // Обновляем пагинацию
                        if (data.pagination) {
                            const paginationContainer = document.getElementById('paginationContainer');
                            if (paginationContainer) {
                                paginationContainer.innerHTML = data.pagination;
                            }
                        }
                        
                        // Обновляем статистику
                        if (data.stats) {
                            const statsError = document.getElementById('stats-error');
                            const statsWarning = document.getElementById('stats-warning');
                            const statsInfo = document.getElementById('stats-info');
                            const statsDebug = document.getElementById('stats-debug');
                            const statsTotal = document.getElementById('stats-total');
                            
                            if (statsError) statsError.textContent = data.stats.error || 0;
                            if (statsWarning) statsWarning.textContent = data.stats.warning || 0;
                            if (statsInfo) statsInfo.textContent = data.stats.info || 0;
                            if (statsDebug) statsDebug.textContent = data.stats.debug || 0;
                            if (statsTotal) statsTotal.textContent = data.stats.total || 0;
                        }
                        
                        // Обновляем счетчик
                        if (data.count !== undefined && data.total !== undefined) {
                            const logsCount = document.getElementById('logsCount');
                            if (logsCount) {
                                logsCount.textContent = `Показано: ${data.count} из ${data.total}`;
                            }
                        }
                        
                        // Обновляем URL без перезагрузки
                        window.history.pushState({}, '', `${form.action}?${params.toString()}`);
                    } else if (data.error) {
                        showToast(data.error, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Ошибка загрузки логов', 'error');
                })
                .finally(() => {
                    loadingIndicator.classList.add('hidden');
                    tableContainer.style.opacity = '1';
                });
            }

            // Обработка пагинации через AJAX
            document.addEventListener('click', function(e) {
                const target = e.target.closest('a');
                if (target && target.closest('.pagination')) {
                    e.preventDefault();
                    const url = new URL(target.href);
                    const params = url.searchParams;
                    
                    // Загружаем страницу через AJAX
                    loadingIndicator.classList.remove('hidden');
                    tableContainer.style.opacity = '0.5';
                    
                    fetch(url.href, {
                        method: 'GET',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.html) {
                            if (tableBody) {
                                tableBody.innerHTML = data.html;
                            }
                            if (data.pagination) {
                                const paginationContainer = document.getElementById('paginationContainer');
                                if (paginationContainer) {
                                    paginationContainer.innerHTML = data.pagination;
                                }
                            }
                            if (data.stats) {
                                const statsError = document.getElementById('stats-error');
                                const statsWarning = document.getElementById('stats-warning');
                                const statsInfo = document.getElementById('stats-info');
                                const statsDebug = document.getElementById('stats-debug');
                                const statsTotal = document.getElementById('stats-total');
                                
                                if (statsError) statsError.textContent = data.stats.error || 0;
                                if (statsWarning) statsWarning.textContent = data.stats.warning || 0;
                                if (statsInfo) statsInfo.textContent = data.stats.info || 0;
                                if (statsDebug) statsDebug.textContent = data.stats.debug || 0;
                                if (statsTotal) statsTotal.textContent = data.stats.total || 0;
                            }
                            if (data.count !== undefined && data.total !== undefined) {
                                const logsCount = document.getElementById('logsCount');
                                if (logsCount) {
                                    logsCount.textContent = `Показано: ${data.count} из ${data.total}`;
                                }
                            }
                            window.history.pushState({}, '', url.href);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showToast('Ошибка загрузки страницы', 'error');
                    })
                    .finally(() => {
                        loadingIndicator.classList.add('hidden');
                        tableContainer.style.opacity = '1';
                    });
                }
            });

            // Функция для уведомлений (используем toastr)
            function showToast(message, type = 'info') {
                if (type === 'error') {
                    toastr.error(message);
                } else if (type === 'warning') {
                    toastr.warning(message);
                } else if (type === 'success') {
                    toastr.success(message);
                } else {
                    toastr.info(message);
                }
            }

            // Выделяем активный быстрый фильтр
            const currentLevel = document.getElementById('level').value;
            if (currentLevel) {
                document.querySelector(`.quick-filter[data-level="${currentLevel}"]`)?.classList.add('active');
            } else {
                document.querySelector('.quick-filter[data-level=""]')?.classList.add('active');
            }
        });
    </script>
@endpush

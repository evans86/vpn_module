@extends('layouts.app', ['page' => __('Логи приложения'), 'pageSlug' => 'logs'])

@section('content')
    <div class="container-fluid">
        <!-- Статистика по уровням -->
        <div class="row mb-4">
            <div class="col-xl-2 col-md-4">
                <div class="card bg-danger text-white mb-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="text-xs font-weight-bold text-uppercase mb-1">Ошибки</div>
                                <div class="h5 mb-0" id="stats-error">{{ $stats['error'] ?? 0 }}</div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-exclamation-circle fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4">
                <div class="card bg-warning text-white mb-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="text-xs font-weight-bold text-uppercase mb-1">Предупреждения</div>
                                <div class="h5 mb-0" id="stats-warning">{{ $stats['warning'] ?? 0 }}</div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-exclamation-triangle fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4">
                <div class="card bg-info text-white mb-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="text-xs font-weight-bold text-uppercase mb-1">Информация</div>
                                <div class="h5 mb-0" id="stats-info">{{ $stats['info'] ?? 0 }}</div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-info-circle fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4">
                <div class="card bg-secondary text-white mb-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="text-xs font-weight-bold text-uppercase mb-1">Отладка</div>
                                <div class="h5 mb-0" id="stats-debug">{{ $stats['debug'] ?? 0 }}</div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-bug fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4">
                <div class="card bg-primary text-white mb-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="text-xs font-weight-bold text-uppercase mb-1">Всего</div>
                                <div class="h5 mb-0" id="stats-total">{{ $stats['total'] ?? 0 }}</div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-list fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Логи приложения</h4>
                        <div class="card-tools">
                            <span class="badge badge-light" id="logsCount">Показано: {{ $logs->count() }} из {{ $logs->total() }}</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Быстрые фильтры -->
                        <div class="mb-3">
                            <div class="btn-group" role="group">
                                <button type="button" class="btn btn-sm btn-danger quick-filter" data-level="error">
                                    <i class="fas fa-exclamation-circle"></i> Только ошибки
                                </button>
                                <button type="button" class="btn btn-sm btn-warning quick-filter" data-level="warning">
                                    <i class="fas fa-exclamation-triangle"></i> Предупреждения
                                </button>
                                <button type="button" class="btn btn-sm btn-info quick-filter" data-level="critical">
                                    <i class="fas fa-skull"></i> Критические
                                </button>
                                <button type="button" class="btn btn-sm btn-secondary quick-filter" data-level="">
                                    <i class="fas fa-list"></i> Все логи
                                </button>
                            </div>
                            <div class="btn-group ml-2" role="group">
                                <button type="button" class="btn btn-sm btn-outline-primary quick-date" data-days="1">
                                    Сегодня
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-primary quick-date" data-days="7">
                                    7 дней
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-primary quick-date" data-days="30">
                                    30 дней
                                </button>
                            </div>
                        </div>

                        <form action="{{ route('admin.logs.index') }}" method="GET" id="logsFilterForm">
                            <div class="row mb-3">
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="level">Уровень</label>
                                        <select class="form-control" id="level" name="level">
                                            <option value="">Все уровни</option>
                                            <option value="error" {{ request('level') == 'error' ? 'selected' : '' }}>Ошибка</option>
                                            <option value="critical" {{ request('level') == 'critical' ? 'selected' : '' }}>Критическая</option>
                                            <option value="warning" {{ request('level') == 'warning' ? 'selected' : '' }}>Предупреждение</option>
                                            <option value="info" {{ request('level') == 'info' ? 'selected' : '' }}>Информация</option>
                                            <option value="debug" {{ request('level') == 'debug' ? 'selected' : '' }}>Отладка</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="source">Источник</label>
                                        <select class="form-control" id="source" name="source">
                                            <option value="">Все источники</option>
                                            @foreach($sources as $source)
                                                <option value="{{ $source }}" {{ request('source') == $source ? 'selected' : '' }}>
                                                    {{ $source }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="date_from">Дата от</label>
                                        <input type="date" class="form-control" id="date_from" name="date_from"
                                               value="{{ request('date_from', now()->subDays(7)->format('Y-m-d')) }}">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="date_to">Дата до</label>
                                        <input type="date" class="form-control" id="date_to" name="date_to"
                                               value="{{ request('date_to', now()->format('Y-m-d')) }}">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="search">Поиск (мин. 3 символа)</label>
                                        <input type="text" class="form-control" id="search" name="search"
                                               placeholder="Поиск по сообщению или источнику"
                                               value="{{ request('search') }}">
                                    </div>
                                </div>
                                <div class="col-md-1">
                                    <div class="form-group">
                                        <label>&nbsp;</label>
                                        <button type="submit" class="btn btn-primary btn-block">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>

                        <!-- Индикатор загрузки -->
                        <div id="loadingIndicator" class="text-center" style="display: none;">
                            <i class="fas fa-spinner fa-spin fa-2x text-primary"></i>
                            <p class="mt-2">Загрузка логов...</p>
                        </div>

                        <!-- Таблица логов -->
                        <div class="table-responsive" id="logsTableContainer">
                            <table class="table table-hover">
                                <thead>
                                <tr>
                                    <th width="160">Время</th>
                                    <th width="120">Уровень</th>
                                    <th width="150">Источник</th>
                                    <th>Сообщение</th>
                                    <th width="100">Действия</th>
                                </tr>
                                </thead>
                                <tbody id="logsTableBody">
                                @forelse($logs as $log)
                                    <tr class="log-row log-level-{{ $log->level }} {{ in_array($log->level, ['error', 'critical', 'emergency']) ? 'table-danger' : ($log->level === 'warning' ? 'table-warning' : '') }}">
                                        <td>
                                            <small>{{ $log->created_at->format('d.m.Y H:i:s') }}</small>
                                        </td>
                                        <td>
                                            <span class="badge badge-{{ $log->getLevelColorClass() }}">
                                                <i class="fas {{ $log->getLevelIcon() }} mr-1"></i>
                                                {{ ucfirst($log->level) }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-light">{{ $log->source }}</span>
                                        </td>
                                        <td>
                                            <div class="log-message" title="{{ $log->message }}">
                                                {{ $log->message_short }}
                                            </div>
                                        </td>
                                        <td>
                                            <a href="{{ route('admin.logs.show', $log) }}" 
                                               class="btn btn-sm btn-info"
                                               title="Подробнее">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">
                                            <i class="fas fa-info-circle fa-3x mb-3"></i>
                                            <p>Логи не найдены</p>
                                        </td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>

                        <!-- Пагинация -->
                        <div id="paginationContainer">
                            @include('logs.partials.pagination')
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('css')
    <style>
        .log-row.table-danger {
            background-color: #f8d7da !important;
        }

        .log-row.table-warning {
            background-color: #fff3cd !important;
        }

        .log-row:hover {
            background-color: #f8f9fa !important;
        }

        .log-message {
            max-width: 500px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .quick-filter.active {
            box-shadow: 0 0 0 2px rgba(0,123,255,.5);
        }

        .table th {
            background-color: #f8f9fa;
            font-weight: 600;
            border-bottom: 2px solid #dee2e6;
        }
    </style>
@endpush

@push('scripts')
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
                loadingIndicator.style.display = 'block';
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
                        tableBody.innerHTML = data.html;
                        
                        // Обновляем пагинацию
                        if (data.pagination) {
                            document.getElementById('paginationContainer').innerHTML = data.pagination;
                        }
                        
                        // Обновляем статистику
                        if (data.stats) {
                            document.getElementById('stats-error').textContent = data.stats.error || 0;
                            document.getElementById('stats-warning').textContent = data.stats.warning || 0;
                            document.getElementById('stats-info').textContent = data.stats.info || 0;
                            document.getElementById('stats-debug').textContent = data.stats.debug || 0;
                            document.getElementById('stats-total').textContent = data.stats.total || 0;
                        }
                        
                        // Обновляем счетчик
                        if (data.count !== undefined && data.total !== undefined) {
                            document.getElementById('logsCount').textContent = `Показано: ${data.count} из ${data.total}`;
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
                    loadingIndicator.style.display = 'none';
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
                    loadingIndicator.style.display = 'block';
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
                            tableBody.innerHTML = data.html;
                            if (data.pagination) {
                                document.getElementById('paginationContainer').innerHTML = data.pagination;
                            }
                            if (data.stats) {
                                document.getElementById('stats-error').textContent = data.stats.error || 0;
                                document.getElementById('stats-warning').textContent = data.stats.warning || 0;
                                document.getElementById('stats-info').textContent = data.stats.info || 0;
                                document.getElementById('stats-debug').textContent = data.stats.debug || 0;
                                document.getElementById('stats-total').textContent = data.stats.total || 0;
                            }
                            window.history.pushState({}, '', url.href);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showToast('Ошибка загрузки страницы', 'error');
                    })
                    .finally(() => {
                        loadingIndicator.style.display = 'none';
                        tableContainer.style.opacity = '1';
                    });
                }
            });

            // Функция для уведомлений
            function showToast(message, type = 'info') {
                const toast = document.createElement('div');
                toast.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
                toast.style.cssText = 'top: 20px; right: 20px; z-index: 99999; min-width: 300px;';
                toast.innerHTML = `
                    <i class="fas fa-${type === 'error' ? 'exclamation-circle' : 'info-circle'} mr-2"></i>
                    <strong>${message}</strong>
                    <button type="button" class="close" data-dismiss="alert">
                        <span>&times;</span>
                    </button>
                `;
                document.body.appendChild(toast);
                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.classList.remove('show');
                        setTimeout(() => toast.remove(), 300);
                    }
                }, 3000);
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

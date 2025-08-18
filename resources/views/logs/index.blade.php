@extends('layouts.app', ['page' => __('Логи приложения'), 'pageSlug' => 'logs'])

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Логи приложения</h4>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('admin.logs.index') }}" method="GET">
                            <div class="row mb-3">
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="level">Уровень</label>
                                        <select class="form-control" id="level" name="level">
                                            <option value="">Все уровни</option>
                                            <option value="info" {{ request('level') == 'info' ? 'selected' : '' }}>Информация</option>
                                            <option value="warning" {{ request('level') == 'warning' ? 'selected' : '' }}>Предупреждение</option>
                                            <option value="error" {{ request('level') == 'error' ? 'selected' : '' }}>Ошибка</option>
                                            <option value="critical" {{ request('level') == 'critical' ? 'selected' : '' }}>Критическая</option>
                                            <option value="debug" {{ request('level') == 'debug' ? 'selected' : '' }}>Отладка</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="time_from">Время от</label>
                                        <input type="time" class="form-control" id="time_from" name="time_from"
                                               value="{{ request('time_from') }}">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="time_to">Время до</label>
                                        <input type="time" class="form-control" id="time_to" name="time_to"
                                               value="{{ request('time_to') }}">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="date_from">Дата от</label>
                                        <input type="date" class="form-control" id="date_from" name="date_from"
                                               value="{{ request('date_from') }}">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="date_to">Дата до</label>
                                        <input type="date" class="form-control" id="date_to" name="date_to"
                                               value="{{ request('date_to') }}">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="search">Поиск</label>
                                        <input type="text" class="form-control" id="search" name="search"
                                               placeholder="Поиск по сообщению"
                                               value="{{ request('search') }}">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>&nbsp;</label>
                                        <div class="btn-group btn-block">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-search"></i> Поиск
                                            </button>
                                            <a href="{{ route('admin.logs.index') }}"
                                               class="btn btn-secondary">
                                                <i class="fas fa-times"></i> Сбросить
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>

                        <!-- Таблица логов -->
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                <tr>
                                    <th>ВРЕМЯ</th>
                                    <th>УРОВЕНЬ</th>
                                    <th>ИСТОЧНИК</th>
                                    <th>СООБЩЕНИЕ</th>
                                    <th>ДЕЙСТВИЯ</th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse($logs as $log)
                                    <tr>
                                        <td>{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                                        <td>
                                            <span class="badge badge-{{ $log->getLevelColorClass() }}">
                                                <i class="fas {{ $log->getLevelIcon() }} mr-1"></i>
                                                {{ $log->level }}
                                            </span>
                                        </td>
                                        <td>{{ $log->source }}</td>
                                        <td>{{ $log->message_short }}</td>
{{--                                        <td>{{ Str::limit($log->message, 100) }}</td>--}}
                                        <td>
                                            <a href="{{ route('admin.logs.show', $log) }}" class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i> Подробнее
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">
                                            <i class="fas fa-info-circle"></i> Логи не найдены
                                        </td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>

                        <!-- Пагинация -->
                        <div class="d-flex justify-content-center mt-3">
                            {{ $logs->appends(request()->query())->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.querySelector('form');
            const tableBody = document.querySelector('tbody');

            // Обработка пагинации
            document.addEventListener('click', function(e) {
                const target = e.target;

                // Проверяем, является ли клик по ссылке пагинации
                if (target.tagName === 'A' && target.closest('.pagination')) {
                    e.preventDefault();
                    const url = target.href;

                    // Загружаем страницу обычным способом
                    window.location.href = url;
                }
            });

            // Обработка формы фильтрации
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(form);
                const queryString = new URLSearchParams(formData).toString();
                const url = `${form.action}?${queryString}`;
                window.location.href = url;
            });
        });
    </script>
@endpush

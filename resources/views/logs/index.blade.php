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
                        <!-- Фильтры -->
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <form action="{{ route('admin.logs.index') }}" method="GET" class="form-inline">
                                    <div class="form-group mx-2">
                                        <select name="level" class="form-control">
                                            <option value="">Все уровни</option>
                                            <option value="info" {{ request('level') == 'info' ? 'selected' : '' }}>
                                                Информация
                                            </option>
                                            <option
                                                value="warning" {{ request('level') == 'warning' ? 'selected' : '' }}>
                                                Предупреждение
                                            </option>
                                            <option value="error" {{ request('level') == 'error' ? 'selected' : '' }}>
                                                Ошибка
                                            </option>
                                            <option
                                                value="critical" {{ request('level') == 'critical' ? 'selected' : '' }}>
                                                Критическая ошибка
                                            </option>
                                            <option value="debug" {{ request('level') == 'debug' ? 'selected' : '' }}>
                                                Отладка
                                            </option>
                                        </select>
                                    </div>
                                    <div class="form-group mx-2">
                                        <select name="source" class="form-control">
                                            <option value="">Все источники</option>
                                            @foreach($sources as $source)
                                                <option
                                                    value="{{ $source }}" {{ request('source') == $source ? 'selected' : '' }}>
                                                    {{ $source }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group mx-2">
                                        <input type="date" name="date_from" class="form-control" placeholder="Дата от"
                                               value="{{ request('date_from') }}">
                                    </div>
                                    <div class="form-group mx-2">
                                        <input type="date" name="date_to" class="form-control" placeholder="Дата до"
                                               value="{{ request('date_to') }}">
                                    </div>
                                    <div class="form-group mx-2">
                                        <input type="text" name="search" class="form-control"
                                               placeholder="Поиск по сообщению"
                                               value="{{ request('search') }}">
                                    </div>
                                    <button type="submit" class="btn btn-primary">Применить фильтры</button>
                                    <a href="{{ route('admin.logs.index') }}"
                                       class="btn btn-secondary ml-2">Сбросить</a>
                                </form>
                            </div>
                        </div>

                        <!-- Таблица логов -->
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                <tr>
                                    <th>Время</th>
                                    <th>Уровень</th>
                                    <th>Источник</th>
                                    <th>Сообщение</th>
                                    <th>Действия</th>
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
                                        <td>{{ Str::limit($log->message, 100) }}</td>
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

@extends('layouts.app', ['page' => __('Нарушения лимитов подключений'), 'pageSlug' => 'connection-limit-violations'])

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Нарушения лимитов подключений</h4>
                        <div class="btn-group">
                            <a href="{{ route('admin.module.connection-limit-violations.stats') }}"
                               class="btn btn-info btn-sm" target="_blank">
                                <i class="fas fa-chart-bar"></i> Статистика
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Виджеты статистики -->
                        <div class="row mb-4">
                            <div class="col-xl-3 col-md-6">
                                <div class="card bg-primary text-white mb-4">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <div class="text-xs font-weight-bold text-uppercase mb-1">
                                                    Всего нарушений
                                                </div>
                                                <div class="h5 mb-0">{{ $stats['total'] }}</div>
                                            </div>
                                            <div class="col-auto">
                                                <i class="fas fa-exclamation-triangle fa-2x"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6">
                                <div class="card bg-danger text-white mb-4">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <div class="text-xs font-weight-bold text-uppercase mb-1">
                                                    Активные нарушения
                                                </div>
                                                <div class="h5 mb-0">{{ $stats['active'] }}</div>
                                            </div>
                                            <div class="col-auto">
                                                <i class="fas fa-bell fa-2x"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6">
                                <div class="card bg-warning text-white mb-4">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <div class="text-xs font-weight-bold text-uppercase mb-1">
                                                    Сегодня
                                                </div>
                                                <div class="h5 mb-0">{{ $stats['today'] }}</div>
                                            </div>
                                            <div class="col-auto">
                                                <i class="fas fa-calendar-day fa-2x"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Фильтры -->
                        <form action="{{ route('admin.module.connection-limit-violations.index') }}" method="GET">
                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <select class="form-control" name="status">
                                        <option value="">Все статусы</option>
                                        <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>
                                            Активные
                                        </option>
                                        <option value="resolved" {{ request('status') == 'resolved' ? 'selected' : '' }}>
                                            Решенные
                                        </option>
                                        <option value="ignored" {{ request('status') == 'ignored' ? 'selected' : '' }}>
                                            Игнорированные
                                        </option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-primary">Применить</button>
                                    <a href="{{ route('admin.module.connection-limit-violations.index') }}"
                                       class="btn btn-secondary">Сбросить</a>
                                </div>
                            </div>
                        </form>

                        <!-- Таблица нарушений -->
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                <tr>
                                    <th>Время</th>
                                    <th>Ключ</th>
                                    <th>Пользователь</th>
                                    <th>Лимит / Факт</th>
                                    <th>IP адреса</th>
                                    <th>Повторений</th>
                                    <th>Статус</th>
                                    <th>Действия</th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse($violations as $violation)
                                    <tr>
                                        <td>{{ $violation->created_at->format('d.m.Y H:i') }}</td>
                                        <td>
                                            @if($violation->keyActivate)
                                                <code>{{ $violation->keyActivate->id }}</code>
                                            @else
                                                <span class="text-muted">Удален (ID: {{ $violation->key_activate_id }})</span>
                                            @endif
                                        </td>
                                        <td>{{ $violation->user_tg_id }}</td>
                                        <td>
                                            <span class="badge badge-success">{{ $violation->allowed_connections }}</span>
                                            <span class="text-muted">/</span>
                                            <span class="badge badge-danger">{{ $violation->actual_connections }}</span>
                                            <small class="text-muted">(+{{ $violation->excess_percentage }}%)</small>
                                        </td>
                                        <td>
                                            <div class="ip-addresses-container">
                                                @php
                                                    $ipAddresses = $violation->ip_addresses ?? [];
                                                    $totalIps = count($ipAddresses);
                                                    $maxVisible = 3; // Максимум IP до разворачивания
                                                @endphp

                                                {{-- Показываем первые несколько IP --}}
                                                <div class="ip-addresses-preview">
                                                    @foreach(array_slice($ipAddresses, 0, $maxVisible) as $ip)
                                                        <span class="badge badge-secondary ip-badge">{{ $ip }}</span>
                                                    @endforeach

                                                    {{-- Показываем количество скрытых IP --}}
                                                    @if($totalIps > $maxVisible)
                                                        <button type="button"
                                                                class="btn btn-xs btn-outline-primary toggle-ips"
                                                                data-target="ips-{{ $violation->id }}"
                                                                title="Показать все IP адреса">
                                                            +{{ $totalIps - $maxVisible }} ещё
                                                        </button>
                                                    @endif
                                                </div>

                                                {{-- Скрытый блок со всеми IP --}}
                                                @if($totalIps > $maxVisible)
                                                    <div class="ip-addresses-full mt-2" id="ips-{{ $violation->id }}" style="display: none;">
                                                        @foreach($ipAddresses as $ip)
                                                            <span class="badge badge-light ip-badge">{{ $ip }}</span>
                                                        @endforeach
                                                        <button type="button"
                                                                class="btn btn-xs btn-outline-secondary toggle-ips mt-1"
                                                                data-target="ips-{{ $violation->id }}"
                                                                title="Скрыть">
                                                            Скрыть
                                                        </button>
                                                    </div>
                                                @endif
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge badge-warning">{{ $violation->violation_count }}</span>
                                        </td>
                                        <td>
                                        <span class="badge badge-{{ $violation->status_color }}">
                                            <i class="{{ $violation->status_icon }} mr-1"></i>
                                            {{ $violation->status }}
                                        </span>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="{{ route('admin.module.connection-limit-violations.show', $violation) }}"
                                                   class="btn btn-info btn-sm" title="Подробнее">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                @if($violation->isActive())
                                                    <form action="{{ route('admin.module.connection-limit-violations.resolve', $violation) }}"
                                                          method="POST" class="d-inline">
                                                        @csrf
                                                        <button type="submit" class="btn btn-success btn-sm"
                                                                title="Пометить как решенное">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    </form>
                                                    <form action="{{ route('admin.module.connection-limit-violations.ignore', $violation) }}"
                                                          method="POST" class="d-inline">
                                                        @csrf
                                                        <button type="submit" class="btn btn-secondary btn-sm"
                                                                title="Игнорировать">
                                                            <i class="fas fa-eye-slash"></i>
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center text-muted">
                                            <i class="fas fa-info-circle"></i> Нарушения не найдены
                                        </td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>

                        <!-- Пагинация -->
                        <div class="d-flex justify-content-center mt-3">
                            {{ $violations->appends(request()->query())->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('js')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Обработчик для кнопок разворачивания/сворачивания IP адресов
            document.querySelectorAll('.toggle-ips').forEach(function(button) {
                button.addEventListener('click', function() {
                    const targetId = this.getAttribute('data-target');
                    const targetElement = document.getElementById(targetId);

                    if (targetElement) {
                        if (targetElement.style.display === 'none') {
                            targetElement.style.display = 'block';
                            this.textContent = 'Скрыть';
                            this.classList.remove('btn-outline-primary');
                            this.classList.add('btn-outline-secondary');
                        } else {
                            targetElement.style.display = 'none';
                            this.textContent = '+' + (targetElement.querySelectorAll('.ip-badge').length - 3) + ' ещё';
                            this.classList.remove('btn-outline-secondary');
                            this.classList.add('btn-outline-primary');
                        }
                    }
                });
            });
        });
    </script>
@endpush

@push('css')
    <style>
        .ip-addresses-container {
            max-width: 300px;
        }

        .ip-badge {
            margin: 2px;
            font-family: monospace;
            font-size: 0.8em;
            display: inline-block;
        }

        .ip-addresses-preview {
            line-height: 1.8;
        }

        .ip-addresses-full {
            background-color: #f8f9fa;
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #dee2e6;
        }

        .toggle-ips {
            font-size: 0.7em;
            padding: 1px 6px;
            margin-left: 4px;
        }
    </style>
@endpush

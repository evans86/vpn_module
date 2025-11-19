@extends('layouts.app', ['page' => __('Нарушения лимитов подключений'), 'pageSlug' => 'connection-limit-violations'])

@section('content')
    <div class="container-fluid">

        <!-- Панель быстрых действий -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h5>Быстрая проверка</h5>
                                <form action="{{ route('admin.module.connection-limit-violations.manual-check') }}"
                                      method="POST" class="form-inline">
                                    @csrf
                                    <div class="form-group mr-2">
                                        <label class="mr-2">Порог:</label>
                                        <input type="number" name="threshold" value="2" min="1" max="10"
                                               class="form-control form-control-sm" style="width: 80px;">
                                    </div>
                                    <div class="form-group mr-2">
                                        <label class="mr-2">Окно (мин):</label>
                                        <input type="number" name="window" value="60" min="1" max="1440"
                                               class="form-control form-control-sm" style="width: 100px;">
                                    </div>
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="fas fa-search"></i> Проверить
                                    </button>
                                </form>
                            </div>
                            <div class="col-md-6 text-right">
                                <button type="button" class="btn btn-success btn-sm" data-toggle="modal"
                                        data-target="#bulkActionsModal">
                                    <i class="fas fa-tasks"></i> Массовые действия
                                </button>
                                <a href="{{ route('admin.module.connection-limit-violations.index') }}"
                                   class="btn btn-secondary btn-sm">
                                    <i class="fas fa-sync"></i> Обновить
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Виджеты статистики -->
        <div class="row mb-4">
            <div class="col-xl-2 col-md-4">
                <div class="card bg-primary text-white mb-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="text-xs font-weight-bold text-uppercase mb-1">Всего нарушений</div>
                                <div class="h5 mb-0">{{ $stats['total'] }}</div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-exclamation-triangle fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4">
                <div class="card bg-danger text-white mb-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="text-xs font-weight-bold text-uppercase mb-1">Активные</div>
                                <div class="h5 mb-0">{{ $stats['active'] }}</div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-bell fa-2x"></i>
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
                                <div class="text-xs font-weight-bold text-uppercase mb-1">Сегодня</div>
                                <div class="h5 mb-0">{{ $stats['today'] }}</div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-calendar-day fa-2x"></i>
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
                                <div class="text-xs font-weight-bold text-uppercase mb-1">Критические (≥3)</div>
                                <div class="h5 mb-0">{{ $stats['critical'] ?? 0 }}</div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-skull-crossbones fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4">
                <div class="card bg-success text-white mb-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="text-xs font-weight-bold text-uppercase mb-1">Решено</div>
                                <div class="h5 mb-0">{{ $stats['resolved'] ?? 0 }}</div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-check-circle fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Фильтры -->
        <div class="card mb-4">
            <div class="card-body">
                <form action="{{ route('admin.module.connection-limit-violations.index') }}" method="GET">
                    <div class="row">
                        <div class="col-md-2">
                            <label>Статус</label>
                            <select class="form-control" name="status">
                                <option value="">Все</option>
                                <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Активные
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
                            <label>Нарушений ≥</label>
                            <select class="form-control" name="violation_count">
                                <option value="">Все</option>
                                <option value="1" {{ request('violation_count') == '1' ? 'selected' : '' }}>1+</option>
                                <option value="2" {{ request('violation_count') == '2' ? 'selected' : '' }}>2+</option>
                                <option value="3" {{ request('violation_count') == '3' ? 'selected' : '' }}>3+</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label>Панель</label>
                            <select class="form-control" name="panel_id">
                                <option value="">Все панели</option>
                                @foreach($panels as $panel)
                                    <option
                                        value="{{ $panel->id }}" {{ request('panel_id') == $panel->id ? 'selected' : '' }}>
                                        {{ $panel->host }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label>Дата с</label>
                            <input type="date" class="form-control" name="date_from" value="{{ request('date_from') }}">
                        </div>
                        <div class="col-md-2">
                            <label>Дата по</label>
                            <input type="date" class="form-control" name="date_to" value="{{ request('date_to') }}">
                        </div>
                        <div class="col-md-2">
                            <label>Поиск</label>
                            <input type="text" class="form-control" name="search" value="{{ request('search') }}"
                                   placeholder="ID ключа или пользователя">
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-md-12">
                            <button type="submit" class="btn btn-primary">Применить фильтры</button>
                            <a href="{{ route('admin.module.connection-limit-violations.index') }}"
                               class="btn btn-secondary">Сбросить</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Таблица нарушений -->
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">Список нарушений</h4>
                <div class="card-tools">
                    <span
                        class="badge badge-light">Показано: {{ $violations->count() }} из {{ $violations->total() }}</span>
                </div>
            </div>
            <div class="card-body">
                @if($violations->isEmpty())
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-info-circle fa-3x mb-3"></i>
                        <p>Нарушения не найдены</p>
                    </div>
                @else
                    <form id="bulkForm">
                        @csrf
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                <tr>
                                    <th width="30">
                                        <input type="checkbox" id="selectAll">
                                    </th>
                                    <th>Время</th>
                                    <th>Ключ</th>
                                    <th>Пользователь</th>
                                    <th>Лимит / Факт</th>
                                    <th>Уведомления</th>
                                    <th>IP адреса</th>
                                    <th>Повторений</th>
                                    <th>Статус</th>
                                    <th width="250">Действия</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($violations as $violation)
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="violation_ids[]" value="{{ $violation->id }}"
                                                   class="violation-checkbox">
                                        </td>
                                        <td>
                                            <small>{{ $violation->created_at->format('d.m.Y H:i') }}</small>
                                        </td>
                                        <td>
                                            @if($violation->keyActivate)
                                                <div class="d-flex align-items-center">
                                                    <a href="{{ route('admin.module.key-activate.index', ['id' => $violation->keyActivate->id]) }}"
                                                       class="text-primary font-weight-bold"
                                                       title="Перейти к ключу">
                                                        {{ substr($violation->keyActivate->id, 0, 8) }}...
                                                    </a>
                                                    <button class="btn btn-sm btn-link ml-2 copy-key-btn"
                                                            data-clipboard-text="{{ $violation->keyActivate->id }}"
                                                            title="Копировать ID ключа">
                                                        <i class="fas fa-copy"></i>
                                                    </button>
                                                </div>
                                                <div>
                                                    <small class="text-muted">
                                                        @if($violation->panel)
                                                            <a href="{{ route('admin.module.panel.index', ['id' => $violation->panel_id]) }}"
                                                               class="text-muted"
                                                               title="Перейти к панели">
                                                                {{ $violation->panel->host }}
                                                            </a>
                                                        @else
                                                            N/A
                                                        @endif
                                                    </small>
                                                </div>
                                                @if($violation->isKeyReplaced())
                                                    <div>
                                                        <small class="text-success">
                                                            <i class="fas fa-key"></i> Ключ заменен
                                                            @if($violation->getReplacedKeyId())
                                                                <a href="{{ route('admin.module.key-activate.index', ['id' => $violation->getReplacedKeyId()]) }}"
                                                                   class="text-success ml-1">
                                                                    (новый)
                                                                </a>
                                                            @endif
                                                        </small>
                                                    </div>
                                                @endif
                                            @else
                                                <span class="text-danger">Ключ удален</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($violation->user_tg_id)
                                                <div class="d-flex align-items-center">
                                                    <a href="{{ route('admin.module.telegram-users.index', ['telegram_id' => $violation->user_tg_id]) }}"
                                                       class="font-weight-bold text-primary"
                                                       title="Перейти к пользователю">
                                                        {{ $violation->user_tg_id }}
                                                    </a>
                                                </div>
                                                @if($violation->keyActivate && $violation->keyActivate->user)
                                                    <div>
                                                        <small class="text-muted">
                                                            {{ $violation->keyActivate->user->username ?? $violation->keyActivate->user->first_name }}
                                                        </small>
                                                    </div>
                                                @endif
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span
                                                class="badge badge-success">{{ $violation->allowed_connections }}</span>
                                            <span class="text-muted">/</span>
                                            <span class="badge badge-danger">{{ $violation->actual_connections }}</span>
                                            <br>
                                            <small class="text-muted">(+{{ $violation->excess_percentage }}%)</small>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center" title="Отправлено уведомлений: {{ $violation->getNotificationsSentCount() }}
@if($violation->getLastNotificationTime())
Последнее: {{ $violation->getLastNotificationTime() }}
@endif">
                                                <i class="{{ $violation->notification_icon }} mr-1"></i>
                                                <span
                                                    class="badge badge-light">{{ $violation->getNotificationsSentCount() }}</span>
                                                @if($violation->getLastNotificationTime())
                                                    <small class="text-muted ml-1">
                                                        {{ $violation->getLastNotificationTime() }}
                                                    </small>
                                                @endif
                                            </div>
                                        </td>
                                        <td>
                                            <div class="ip-addresses">
                                                @foreach(array_slice($violation->ip_addresses ?? [], 0, 3) as $ip)
                                                    <span class="badge badge-light ip-badge"
                                                          title="{{ $ip }}">{{ $ip }}</span>
                                                @endforeach
                                                @if(count($violation->ip_addresses ?? []) > 3)
                                                    <span class="badge badge-secondary"
                                                          title="{{ implode(', ', array_slice($violation->ip_addresses ?? [], 3)) }}">
                                                        +{{ count($violation->ip_addresses ?? []) - 3 }}
                                                    </span>
                                                @endif
                                            </div>
                                        </td>
                                        <td>
                                            <span
                                                class="badge badge-{{ $violation->violation_count >= 3 ? 'danger' : ($violation->violation_count >= 2 ? 'warning' : 'info') }}">
                                                {{ $violation->violation_count }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-{{ $violation->status_color }}"
                                                  id="status-{{ $violation->id }}">
                                                <i class="{{ $violation->status_icon }} mr-1"></i>
                                                {{ $violation->status }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <!-- Быстрые действия -->
                                                <button type="button" class="btn btn-outline-primary quick-action"
                                                        data-violation-id="{{ $violation->id }}"
                                                        data-action="toggle_status"
                                                        title="Переключить статус">
                                                    <i class="fas fa-sync"></i>
                                                </button>

                                                <!-- Игнорировать -->
                                                @if($violation->status === \App\Models\VPN\ConnectionLimitViolation::STATUS_ACTIVE)
                                                    <button type="button" class="btn btn-outline-secondary quick-action"
                                                            data-violation-id="{{ $violation->id }}"
                                                            data-action="ignore"
                                                            title="Игнорировать нарушение">
                                                        <i class="fas fa-eye-slash"></i>
                                                    </button>
                                                @endif

                                                <!-- Уведомление -->
                                                <button type="button" class="btn btn-outline-warning"
                                                        onclick="sendNotification({{ $violation->id }})"
                                                        title="Отправить уведомление (отправлено: {{ $violation->getNotificationsSentCount() }})">
                                                    <i class="fas fa-bell"></i>
                                                </button>

                                                <!-- Перевыпуск ключа -->
                                                @if($violation->keyActivate && $violation->status === \App\Models\VPN\ConnectionLimitViolation::STATUS_ACTIVE)
                                                    <button type="button" class="btn btn-outline-danger"
                                                            onclick="reissueKey({{ $violation->id }})"
                                                            title="Перевыпустить ключ">
                                                        <i class="fas fa-redo-alt"></i>
                                                    </button>
                                                @endif

                                                <!-- Детали -->
                                                <a href="{{ route('admin.module.connection-limit-violations.show', $violation) }}"
                                                   class="btn btn-outline-info" title="Подробнее">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </form>

                    <!-- Пагинация -->
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <div>
                            <span class="text-muted">
                                Показано с {{ $violations->firstItem() }} по {{ $violations->lastItem() }} из {{ $violations->total() }}
                            </span>
                        </div>
                        <div>
                            {{ $violations->appends(request()->query())->links() }}
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Модальное окно массовых действий -->
    <div class="modal fade" id="bulkActionsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Массовые действия</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>Выбрано: <span id="selectedCount">0</span> нарушений</p>

                    <div class="form-group">
                        <label>Действие:</label>
                        <select class="form-control" id="bulkActionSelect">
                            <option value="">-- Выберите действие --</option>
                            <option value="resolve">Пометить как решенные</option>
                            <option value="ignore">Пометить как игнорированные</option>
                            <option value="notify">Отправить уведомления</option>
                            <option value="reissue_keys">Перевыпустить ключи</option>
                            <option value="delete">Удалить нарушения</option>
                        </select>
                    </div>

                    <div id="actionWarning" class="alert alert-warning mt-3" style="display: none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Отмена</button>
                    <button type="button" class="btn btn-primary" id="executeBulkAction">Выполнить</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('css')
    <style>
        .copy-key-btn {
            padding: 0 5px;
            color: #6c757d;
        }

        .copy-key-btn:hover {
            color: #007bff;
            text-decoration: none;
        }

        .ip-badge {
            font-family: monospace;
            font-size: 0.75rem;
            margin: 1px;
            cursor: help;
        }

        .table td {
            vertical-align: middle;
        }

        .key-link {
            font-family: 'Courier New', monospace;
            font-weight: bold;
        }

        .user-link {
            font-weight: bold;
        }
    </style>
@endpush

@push('js')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/clipboard.js/2.0.8/clipboard.min.js"></script>
    <script>
        // Инициализация Clipboard.js для кнопок копирования
        document.addEventListener('DOMContentLoaded', function () {
            const clipboard = new ClipboardJS('.copy-key-btn');

            clipboard.on('success', function (e) {
                const originalTitle = e.trigger.getAttribute('title');
                e.trigger.innerHTML = '<i class="fas fa-check text-success"></i>';
                e.trigger.setAttribute('title', 'Скопировано!');

                setTimeout(() => {
                    e.trigger.innerHTML = '<i class="fas fa-copy"></i>';
                    e.trigger.setAttribute('title', originalTitle);
                }, 2000);

                e.clearSelection();
            });

            clipboard.on('error', function (e) {
                console.error('Ошибка копирования:', e);
            });
        });

        // Выделение всех чекбоксов
        document.getElementById('selectAll').addEventListener('change', function () {
            const checkboxes = document.querySelectorAll('.violation-checkbox');
            checkboxes.forEach(checkbox => checkbox.checked = this.checked);
            updateSelectedCount();
        });

        // Обновление счетчика выбранных
        document.querySelectorAll('.violation-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', updateSelectedCount);
        });

        function updateSelectedCount() {
            const selected = document.querySelectorAll('.violation-checkbox:checked').length;
            document.getElementById('selectedCount').textContent = selected;
        }

        // Быстрые действия
        document.querySelectorAll('.quick-action').forEach(button => {
            button.addEventListener('click', function () {
                const violationId = this.dataset.violationId;
                const action = this.dataset.action;

                fetch(`/admin/module/connection-limit-violations/${violationId}/quick-action`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({action: action})
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Обновляем статус в таблице
                            const statusBadge = document.getElementById(`status-${violationId}`);
                            statusBadge.className = `badge badge-${data.status_color}`;
                            statusBadge.innerHTML = `<i class="${data.status_icon} mr-1"></i>${data.new_status}`;

                            showToast('Статус обновлен', 'success');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showToast('Ошибка при обновлении', 'error');
                    });
            });
        });

        // Массовые действия
        document.getElementById('executeBulkAction').addEventListener('click', function () {
            const selectedIds = Array.from(document.querySelectorAll('.violation-checkbox:checked'))
                .map(checkbox => checkbox.value);

            const action = document.getElementById('bulkActionSelect').value;

            if (!action) {
                showToast('Выберите действие', 'warning');
                return;
            }

            if (selectedIds.length === 0) {
                showToast('Выберите нарушения', 'warning');
                return;
            }

            const form = document.getElementById('bulkForm');
            const formData = new FormData(form);
            formData.append('action', action);
            selectedIds.forEach(id => formData.append('violation_ids[]', id));

            fetch('{{ route("admin.module.connection-limit-violations.bulk-actions") }}', {
                method: 'POST',
                body: formData
            })
                .then(response => {
                    if (response.redirected) {
                        window.location.href = response.url;
                        return;
                    }
                    return response.json();
                })
                .then(data => {
                    if (data && data.success) {
                        showToast(data.message, 'success');
                        $('#bulkActionsModal').modal('hide');
                        location.reload();
                    } else if (data) {
                        showToast(data.message || 'Ошибка при выполнении действия', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Ошибка при выполнении действия', 'error');
                });
        });

        // Функции для отдельных действий
        function sendNotification(violationId) {
            if (!confirm('Отправить уведомление пользователю?')) return;

            fetch(`/admin/module/connection-limit-violations/${violationId}/manage`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({action: 'send_notification'})
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Уведомление отправлено', 'success');
                    } else {
                        showToast('Ошибка отправки: ' + (data.message || 'неизвестная ошибка'), 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Ошибка отправки уведомления', 'error');
                });
        }

        function replaceKey(violationId) {
            if (!confirm('Заменить ключ пользователя? Старый ключ будет деактивирован.')) return;

            fetch(`/admin/module/connection-limit-violations/${violationId}/manage`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({action: 'replace_key'})
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Ключ заменен: ' + data.new_key_id, 'success');
                        location.reload();
                    } else {
                        showToast('Ошибка замены ключа: ' + (data.message || 'неизвестная ошибка'), 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Ошибка замены ключа', 'error');
                });
        }

        // Вспомогательная функция для уведомлений
        function showToast(message, type = 'info') {
            // Используем существующие toast-уведомления или создаем простые
            const toast = document.createElement('div');
            toast.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
            toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            toast.innerHTML = `
                ${message}
                <button type="button" class="close" data-dismiss="alert">
                    <span>&times;</span>
                </button>
            `;

            document.body.appendChild(toast);

            setTimeout(() => {
                if (toast.parentNode) {
                    toast.remove();
                }
            }, 5000);
        }

        function reissueKey(violationId) {
            if (!confirm('Перевыпустить ключ? Старый ключ будет деактивирован, а пользователь получит новый ключ.')) return;

            fetch(`/admin/module/connection-limit-violations/${violationId}/manage`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({action: 'reissue_key'})
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Ключ перевыпущен: ' + data.new_key_id, 'success');
                        location.reload();
                    } else {
                        showToast('Ошибка перевыпуска ключа: ' + (data.message || 'неизвестная ошибка'), 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Ошибка перевыпуска ключа', 'error');
                });
        }

        // Обновляем быстрые действия для поддержки игнорирования
        document.querySelectorAll('.quick-action').forEach(button => {
            button.addEventListener('click', function () {
                const violationId = this.dataset.violationId;
                const action = this.dataset.action;

                fetch(`/admin/module/connection-limit-violations/${violationId}/quick-action`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({action: action})
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            if (action === 'ignore') {
                                showToast('Нарушение игнорировано', 'success');
                            } else {
                                showToast('Статус обновлен', 'success');
                            }
                            location.reload();
                        } else {
                            showToast('Ошибка: ' + (data.message || 'неизвестная ошибка'), 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showToast('Ошибка при выполнении действия', 'error');
                    });
            });
        });
    </script>
@endpush

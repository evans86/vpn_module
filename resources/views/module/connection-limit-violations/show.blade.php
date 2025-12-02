@extends('layouts.admin')

@section('title', 'Детали нарушения лимита подключений')
@section('page-title', 'Детали нарушения')

@section('content')
    <div class="space-y-6">
        <!-- Кнопка назад -->
        <div>
            <a href="{{ route('admin.module.connection-limit-violations.index') }}"
               class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                <i class="fas fa-arrow-left mr-2"></i> Назад к списку
            </a>
        </div>

        <!-- Основная информация -->
        <x-admin.card title="Основная информация">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ID нарушения</label>
                    <p class="text-sm text-gray-900 font-mono">{{ $violation->id }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Статус</label>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium
                        @if($violation->status === \App\Models\VPN\ConnectionLimitViolation::STATUS_ACTIVE)
                            bg-red-100 text-red-800
                        @elseif($violation->status === \App\Models\VPN\ConnectionLimitViolation::STATUS_RESOLVED)
                            bg-green-100 text-green-800
                        @else
                            bg-gray-100 text-gray-800
                        @endif">
                        <i class="{{ $violation->status_icon }} mr-1"></i>
                        @if($violation->status === \App\Models\VPN\ConnectionLimitViolation::STATUS_ACTIVE)
                            Активное
                        @elseif($violation->status === \App\Models\VPN\ConnectionLimitViolation::STATUS_RESOLVED)
                            Решено
                        @else
                            Игнорировано
                        @endif
                    </span>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Дата создания</label>
                    <p class="text-sm text-gray-900">{{ $violation->created_at->format('d.m.Y H:i:s') }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Telegram ID пользователя</label>
                    <p class="text-sm text-gray-900 font-mono">{{ $violation->user_tg_id ?? 'Не указан' }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Количество нарушений</label>
                    <p class="text-sm text-gray-900 font-semibold">{{ $violation->violation_count }}</p>
                </div>

                @if($violation->resolved_at)
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Дата решения</label>
                    <p class="text-sm text-gray-900">{{ $violation->resolved_at->format('d.m.Y H:i:s') }}</p>
                </div>
                @endif
            </div>
        </x-admin.card>

        <!-- Информация о подключениях -->
        <x-admin.card title="Информация о подключениях">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Разрешено подключений</label>
                    <p class="text-2xl font-bold text-gray-900">{{ $violation->allowed_connections }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Фактически подключений</label>
                    <p class="text-2xl font-bold text-red-600">{{ $violation->actual_connections }}</p>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Превышение</label>
                    <div class="flex items-center">
                        <div class="flex-1 bg-gray-200 rounded-full h-4 mr-4">
                            <div class="bg-red-600 h-4 rounded-full" 
                                 style="width: {{ min(100, ($violation->actual_connections / max($violation->allowed_connections, 1)) * 100) }}%"></div>
                        </div>
                        <span class="text-sm font-semibold text-red-600">
                            +{{ $violation->actual_connections - $violation->allowed_connections }} 
                            ({{ $violation->excess_percentage }}%)
                        </span>
                    </div>
                </div>
            </div>
        </x-admin.card>

        <!-- IP-адреса -->
        <x-admin.card title="IP-адреса подключений">
            @if(!empty($violation->ip_addresses) && is_array($violation->ip_addresses))
                <div class="space-y-2">
                    <div class="flex items-center justify-between mb-4">
                        <p class="text-sm text-gray-600">Всего уникальных IP: <strong>{{ count($violation->ip_addresses) }}</strong></p>
                        <button onclick="copyAllIPs()" 
                                class="inline-flex items-center px-3 py-1 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            <i class="fas fa-copy mr-1"></i> Копировать все
                        </button>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2" id="ip-list">
                        @foreach($violation->ip_addresses as $ip)
                            <div class="flex items-center justify-between p-2 bg-gray-50 rounded border border-gray-200">
                                <span class="text-sm font-mono text-gray-900">{{ $ip }}</span>
                                <button onclick="copyIP('{{ $ip }}')" 
                                        class="ml-2 text-gray-400 hover:text-indigo-600" 
                                        title="Копировать">
                                    <i class="fas fa-copy text-xs"></i>
                                </button>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <p class="text-sm text-gray-500">IP-адреса не зафиксированы</p>
            @endif
        </x-admin.card>

        <!-- Информация о ключе -->
        @if($violation->keyActivate)
        <x-admin.card title="Информация о ключе">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ID ключа</label>
                    <p class="text-sm text-gray-900 font-mono break-all">{{ $violation->keyActivate->id }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Статус ключа</label>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium
                        @if($violation->keyActivate->status === \App\Models\KeyActivate\KeyActivate::ACTIVE)
                            bg-green-100 text-green-800
                        @elseif($violation->keyActivate->status === \App\Models\KeyActivate\KeyActivate::EXPIRED)
                            bg-red-100 text-red-800
                        @else
                            bg-gray-100 text-gray-800
                        @endif">
                        @if($violation->keyActivate->status === \App\Models\KeyActivate\KeyActivate::ACTIVE)
                            Активен
                        @elseif($violation->keyActivate->status === \App\Models\KeyActivate\KeyActivate::EXPIRED)
                            Истек
                        @else
                            Неизвестно
                        @endif
                    </span>
                </div>

                @if($violation->keyActivate->finish_at)
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Срок действия</label>
                    <p class="text-sm text-gray-900">{{ \Carbon\Carbon::createFromTimestamp($violation->keyActivate->finish_at)->format('d.m.Y H:i:s') }}</p>
                </div>
                @endif

                @if($violation->keyActivate->traffic_limit)
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Лимит трафика</label>
                    <p class="text-sm text-gray-900">{{ number_format($violation->keyActivate->traffic_limit / 1024 / 1024 / 1024, 2) }} GB</p>
                </div>
                @endif

                @if($violation->keyActivate->packSalesman && $violation->keyActivate->packSalesman->salesman)
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Продавец</label>
                    <p class="text-sm text-gray-900">{{ $violation->keyActivate->packSalesman->salesman->name ?? 'Не указан' }}</p>
                </div>
                @endif
            </div>
        </x-admin.card>
        @endif

        <!-- Информация о панели и сервере -->
        @if($violation->panel)
        <x-admin.card title="Информация о панели и сервере">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Панель</label>
                    <p class="text-sm text-gray-900">{{ $violation->panel->panel_name ?? 'Не указана' }}</p>
                </div>

                @if($violation->panel->server)
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Сервер</label>
                    <p class="text-sm text-gray-900">{{ $violation->panel->server->server_name ?? 'Не указан' }}</p>
                </div>
                @endif

                @if($violation->serverUser)
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Пользователь сервера</label>
                    <p class="text-sm text-gray-900 font-mono">{{ $violation->serverUser->id ?? 'Не указан' }}</p>
                </div>
                @endif
            </div>
        </x-admin.card>
        @endif

        <!-- История действий -->
        <x-admin.card title="История действий">
            <div class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Отправлено уведомлений</label>
                        <p class="text-sm text-gray-900">
                            <i class="{{ $violation->notification_icon }} mr-1"></i>
                            {{ $violation->getNotificationsSentCount() }}
                        </p>
                    </div>

                    @if($violation->last_notification_sent_at)
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Последнее уведомление</label>
                        <p class="text-sm text-gray-900">{{ $violation->getLastNotificationTimeFormatted() }}</p>
                    </div>
                    @endif

                    @if($violation->isKeyReplaced())
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Ключ заменен</label>
                        <p class="text-sm text-gray-900">
                            <i class="fas fa-check-circle text-green-600 mr-1"></i>
                            {{ $violation->key_replaced_at->format('d.m.Y H:i:s') }}
                        </p>
                    </div>

                    @if($violation->getReplacedKeyId())
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Новый ключ</label>
                        <p class="text-sm text-gray-900 font-mono break-all">{{ $violation->getReplacedKeyId() }}</p>
                    </div>
                    @endif
                    @endif
                </div>
            </div>
        </x-admin.card>

        <!-- Действия -->
        <x-admin.card title="Действия">
            <div class="flex flex-wrap gap-3">
                @if($violation->status === \App\Models\VPN\ConnectionLimitViolation::STATUS_ACTIVE)
                <button onclick="sendNotification({{ $violation->id }})"
                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <i class="fas fa-bell mr-2"></i> Отправить уведомление
                </button>

                <button onclick="reissueKey({{ $violation->id }})"
                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-yellow-600 hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500">
                    <i class="fas fa-key mr-2"></i> Перевыпустить ключ
                </button>

                <button onclick="resolveViolation({{ $violation->id }})"
                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                    <i class="fas fa-check mr-2"></i> Пометить как решенное
                </button>

                <button onclick="ignoreViolation({{ $violation->id }})"
                        class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <i class="fas fa-eye-slash mr-2"></i> Игнорировать
                </button>
                @else
                <button onclick="activateViolation({{ $violation->id }})"
                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <i class="fas fa-undo mr-2"></i> Активировать снова
                </button>
                @endif
            </div>
        </x-admin.card>
    </div>

    <script>
        function copyIP(ip) {
            navigator.clipboard.writeText(ip).then(() => {
                toastr.success('IP-адрес скопирован: ' + ip);
            }).catch(() => {
                toastr.error('Ошибка копирования');
            });
        }

        function copyAllIPs() {
            const ips = @json($violation->ip_addresses ?? []);
            const text = ips.join('\n');
            navigator.clipboard.writeText(text).then(() => {
                toastr.success('Все IP-адреса скопированы');
            }).catch(() => {
                toastr.error('Ошибка копирования');
            });
        }

        function sendNotification(violationId) {
            if (!confirm('Отправить уведомление пользователю?')) return;

            fetch(`/admin/module/connection-limit-violations/${violationId}/manage`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ action: 'send_notification' })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    toastr.success('Уведомление отправлено');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    toastr.error('Ошибка: ' + (data.message || 'неизвестная ошибка'));
                }
            })
            .catch(() => toastr.error('Ошибка отправки уведомления'));
        }

        function reissueKey(violationId) {
            if (!confirm('Перевыпустить ключ? Старый ключ будет деактивирован, новый будет создан с учетом оставшегося времени и трафика.')) return;

            fetch(`/admin/module/connection-limit-violations/${violationId}/manage`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ action: 'reissue_key' })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    toastr.success('Ключ перевыпущен: ' + (data.new_key_id || ''));
                    setTimeout(() => location.reload(), 1000);
                } else {
                    toastr.error('Ошибка: ' + (data.message || 'неизвестная ошибка'));
                }
            })
            .catch(() => toastr.error('Ошибка перевыпуска ключа'));
        }

        function resolveViolation(violationId) {
            if (!confirm('Пометить нарушение как решенное?')) return;

            fetch(`/admin/module/connection-limit-violations/${violationId}/quick-action`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ action: 'resolve' })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    toastr.success('Нарушение помечено как решенное');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    toastr.error('Ошибка: ' + (data.error || 'неизвестная ошибка'));
                }
            })
            .catch(() => toastr.error('Ошибка при выполнении действия'));
        }

        function ignoreViolation(violationId) {
            if (!confirm('Игнорировать это нарушение?')) return;

            fetch(`/admin/module/connection-limit-violations/${violationId}/quick-action`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ action: 'ignore' })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    toastr.success('Нарушение помечено как игнорированное');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    toastr.error('Ошибка: ' + (data.error || 'неизвестная ошибка'));
                }
            })
            .catch(() => toastr.error('Ошибка при выполнении действия'));
        }

        function activateViolation(violationId) {
            if (!confirm('Активировать нарушение снова?')) return;

            fetch(`/admin/module/connection-limit-violations/${violationId}/quick-action`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ action: 'toggle_status' })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    toastr.success('Нарушение активировано');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    toastr.error('Ошибка: ' + (data.error || 'неизвестная ошибка'));
                }
            })
            .catch(() => toastr.error('Ошибка при выполнении действия'));
        }
    </script>
@endsection


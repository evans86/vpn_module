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

        <!-- Информация о логике работы -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <h5 class="font-semibold text-blue-900 mb-2 flex items-center">
                <i class="fas fa-info-circle mr-2"></i> Как работает система уведомлений
            </h5>
            <div class="text-sm text-blue-800 space-y-1">
                <p><strong>1-е нарушение:</strong> Отправляется уведомление-предупреждение</p>
                <p><strong>2-е нарушение:</strong> Отправляется повторное уведомление-предупреждение</p>
                <p><strong>3-е нарушение:</strong> Отправляется уведомление и автоматически перевыпускается ключ</p>
                <p class="mt-2 text-xs text-blue-700">
                    <i class="fas fa-shield-alt mr-1"></i>
                    <strong>Важно:</strong> Если пользователь заблокировал бота, уведомление все равно засчитывается как отправленное, и система продолжает работать по той же логике.
                </p>
            </div>
        </div>

        <!-- Детальная информация об уведомлениях -->
        <x-admin.card title="Детальная информация об уведомлениях">
            <div class="space-y-6">
                <!-- Общая статистика -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <h5 class="text-sm font-semibold text-blue-900 mb-3">Общая статистика</h5>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-blue-700 mb-1">Отправлено уведомлений</label>
                            <p class="text-lg font-bold text-blue-900">
                                <i class="{{ $violation->notification_icon }} mr-1"></i>
                                @php
                                    $sentCount = $violation->getNotificationsSentCount();
                                    $violationCount = max($violation->violation_count, $sentCount); // Используем максимум чтобы избежать "1 из 0"
                                @endphp
                                {{ $sentCount }} из {{ $violationCount }}
                            </p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-blue-700 mb-1">Ожидается уведомлений</label>
                            <p class="text-lg font-bold text-blue-900">
                                @php
                                    $expected = $violation->violation_count - $violation->getNotificationsSentCount();
                                @endphp
                                @if($expected > 0)
                                    <span class="text-orange-600">{{ $expected }}</span>
                                @else
                                    <span class="text-green-600">0 (все отправлены)</span>
                                @endif
                            </p>
                        </div>
                        @if(($violation->notification_retry_count ?? 0) > 0)
                        <div>
                            <label class="block text-xs font-medium text-blue-700 mb-1">Попыток повторной отправки</label>
                            <p class="text-lg font-bold text-yellow-600">
                                <i class="fas fa-redo mr-1"></i>
                                {{ $violation->notification_retry_count }}
                            </p>
                        </div>
                        @endif
                    </div>
                </div>

                <!-- Статус последней отправки -->
                @if($violation->last_notification_sent_at || $violation->last_notification_status)
                <div class="border border-gray-200 rounded-lg p-4">
                    <h5 class="text-sm font-semibold text-gray-900 mb-3">Последняя попытка отправки</h5>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @if($violation->last_notification_sent_at)
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Время отправки</label>
                            <p class="text-sm text-gray-900 font-semibold">
                                {{ $violation->last_notification_sent_at->format('d.m.Y H:i:s') }}
                                <span class="text-gray-500 ml-2">({{ $violation->last_notification_sent_at->diffForHumans() }})</span>
                            </p>
                        </div>
                        @endif

                        @if($violation->last_notification_status)
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Статус отправки</label>
                            @php
                                $statusConfig = [
                                    'success' => [
                                        'label' => 'Успешно отправлено',
                                        'color' => 'green',
                                        'icon' => 'fa-check-circle',
                                        'bg' => 'bg-green-100',
                                        'text' => 'text-green-800'
                                    ],
                                    'blocked' => [
                                        'label' => 'Пользователь заблокировал бота',
                                        'color' => 'orange',
                                        'icon' => 'fa-ban',
                                        'bg' => 'bg-orange-100',
                                        'text' => 'text-orange-800',
                                        'note' => 'Уведомление засчитано как отправленное'
                                    ],
                                    'technical_error' => [
                                        'label' => 'Техническая ошибка',
                                        'color' => 'red',
                                        'icon' => 'fa-exclamation-triangle',
                                        'bg' => 'bg-red-100',
                                        'text' => 'text-red-800',
                                        'note' => 'Будет повторная попытка'
                                    ],
                                    'user_not_found' => [
                                        'label' => 'Пользователь не найден',
                                        'color' => 'gray',
                                        'icon' => 'fa-user-slash',
                                        'bg' => 'bg-gray-100',
                                        'text' => 'text-gray-800'
                                    ]
                                ];
                                $status = $statusConfig[$violation->last_notification_status] ?? [
                                    'label' => $violation->last_notification_status,
                                    'color' => 'gray',
                                    'icon' => 'fa-info-circle',
                                    'bg' => 'bg-gray-100',
                                    'text' => 'text-gray-800'
                                ];
                            @endphp
                            <div>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium {{ $status['bg'] }} {{ $status['text'] }}">
                                    <i class="fas {{ $status['icon'] }} mr-1"></i>
                                    {{ $status['label'] }}
                                </span>
                                @if(isset($status['note']))
                                <p class="text-xs text-gray-500 mt-1">{{ $status['note'] }}</p>
                                @endif
                            </div>
                        </div>
                        @endif

                        @if($violation->last_notification_error)
                        <div class="md:col-span-2">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Текст ошибки</label>
                            <div class="bg-red-50 border border-red-200 rounded p-3">
                                <p class="text-xs font-mono text-red-800 break-all">{{ $violation->last_notification_error }}</p>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
                @else
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                    <p class="text-sm text-gray-600">
                        <i class="fas fa-info-circle mr-2"></i>
                        Уведомления еще не отправлялись
                    </p>
                </div>
                @endif

                <!-- История нарушений и уведомлений -->
                <div class="border border-gray-200 rounded-lg p-4">
                    <h5 class="text-sm font-semibold text-gray-900 mb-3">История нарушений</h5>
                    <div class="space-y-3">
                        @for($i = 1; $i <= $violation->violation_count; $i++)
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded border border-gray-200">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center
                                    @if($i <= $violation->getNotificationsSentCount())
                                        bg-green-100 text-green-800
                                    @elseif($i == $violation->violation_count && $violation->last_notification_status === 'technical_error')
                                        bg-yellow-100 text-yellow-800
                                    @else
                                        bg-gray-100 text-gray-400
                                    @endif">
                                    <i class="fas 
                                        @if($i <= $violation->getNotificationsSentCount())
                                            fa-check
                                        @elseif($i == $violation->violation_count && $violation->last_notification_status === 'technical_error')
                                            fa-clock
                                        @else
                                            fa-hourglass-half
                                        @endif text-xs"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm font-medium text-gray-900">
                                        Нарушение #{{ $i }}
                                        @if($i == 1)
                                            <span class="text-xs text-gray-500">(Первое предупреждение)</span>
                                        @elseif($i == 2)
                                            <span class="text-xs text-gray-500">(Второе предупреждение)</span>
                                        @elseif($i == 3)
                                            <span class="text-xs text-gray-500">(Третье - перевыпуск ключа)</span>
                                        @endif
                                    </p>
                                    @if($i <= $violation->getNotificationsSentCount())
                                        <div class="mt-1">
                                            <p class="text-xs text-green-600 mb-2">
                                                <i class="fas fa-check-circle mr-1"></i>
                                                <strong>Уведомление отправлено:</strong>
                                                @if($i == $violation->getNotificationsSentCount() && $violation->last_notification_status === 'blocked')
                                                    <span class="text-orange-600">(пользователь заблокировал бота)</span>
                                                @endif
                                            </p>
                                            @php
                                                // Получаем текст уведомления для конкретного нарушения
                                                $notificationText = $violation->getNotificationMessageText($i);
                                                // Форматируем для отображения
                                                $notificationText = str_replace('<b>', '<strong class="font-bold">', $notificationText);
                                                $notificationText = str_replace('</b>', '</strong>', $notificationText);
                                                $notificationText = str_replace('<code>', '<code class="bg-gray-200 px-1 py-0.5 rounded text-xs font-mono">', $notificationText);
                                                $notificationText = str_replace('</code>', '</code>', $notificationText);
                                                // Получаем первые 2 строки для предпросмотра (убираем HTML теги для подсчета строк)
                                                $plainText = strip_tags($notificationText);
                                                $lines = explode("\n", $plainText);
                                                $previewLines = array_slice($lines, 0, 2);
                                                $previewText = implode("\n", $previewLines);
                                                // Если текст длиннее 2 строк, добавляем многоточие
                                                if (count($lines) > 2) {
                                                    $previewText .= "\n...";
                                                }
                                                $fullText = $notificationText;
                                            @endphp
                                            <div class="p-2 bg-blue-50 border border-blue-200 rounded text-xs text-gray-800 notification-text-container" data-violation-id="{{ $violation->id }}" data-violation-number="{{ $i }}">
                                                <div class="notification-text-preview whitespace-pre-wrap leading-relaxed">{!! nl2br(htmlspecialchars($previewText)) !!}</div>
                                                <div class="notification-text-full whitespace-pre-wrap leading-relaxed hidden">{!! $fullText !!}</div>
                                                @if(count($lines) > 2)
                                                <button type="button" class="mt-2 text-blue-600 hover:text-blue-800 text-xs font-medium notification-toggle-btn flex items-center" onclick="toggleNotificationText({{ $violation->id }}, {{ $i }})">
                                                    <i class="fas fa-chevron-down notification-icon mr-1"></i>
                                                    <span class="notification-toggle-text">Развернуть</span>
                                                </button>
                                                @endif
                                            </div>
                                        </div>
                                    @elseif($i == $violation->violation_count && $violation->last_notification_status === 'technical_error')
                                        <p class="text-xs text-yellow-600">
                                            <i class="fas fa-exclamation-triangle mr-1"></i>
                                            Техническая ошибка - ожидается повторная попытка
                                        </p>
                                    @else
                                        <p class="text-xs text-gray-500">
                                            <i class="fas fa-clock mr-1"></i>
                                            Ожидается отправка уведомления
                                        </p>
                                    @endif
                                </div>
                            </div>
                            @if($i == 3 && $violation->isKeyReplaced())
                            <div class="ml-4">
                                <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-green-100 text-green-800">
                                    <i class="fas fa-key mr-1"></i>
                                    Ключ перевыпущен
                                </span>
                            </div>
                            @endif
                        </div>
                        @endfor
                    </div>
                </div>

                <!-- Информация о замене ключа -->
                @if($violation->isKeyReplaced())
                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <h5 class="text-sm font-semibold text-green-900 mb-3">
                        <i class="fas fa-key mr-2"></i>
                        Информация о замене ключа
                    </h5>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-green-700 mb-1">Дата замены</label>
                            <p class="text-sm text-green-900 font-semibold">
                                {{ $violation->key_replaced_at->format('d.m.Y H:i:s') }}
                            </p>
                        </div>
                        @if($violation->getReplacedKeyId())
                        <div>
                            <label class="block text-xs font-medium text-green-700 mb-1">Новый ключ</label>
                            <p class="text-sm text-green-900 font-mono break-all">
                                <a href="{{ route('admin.module.key-activate.index', ['id' => $violation->getReplacedKeyId()]) }}" 
                                   class="text-green-700 hover:text-green-900 underline">
                                    {{ $violation->getReplacedKeyId() }}
                                </a>
                            </p>
                        </div>
                        @endif
                    </div>
                </div>
                @endif
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

        function toggleNotificationText(violationId, violationNumber) {
            const container = document.querySelector(`.notification-text-container[data-violation-id="${violationId}"][data-violation-number="${violationNumber}"]`);
            if (!container) return;

            const preview = container.querySelector('.notification-text-preview');
            const full = container.querySelector('.notification-text-full');
            const btn = container.querySelector('.notification-toggle-btn');
            const icon = container.querySelector('.notification-icon');
            const text = container.querySelector('.notification-toggle-text');

            if (full.classList.contains('hidden')) {
                // Разворачиваем
                preview.classList.add('hidden');
                full.classList.remove('hidden');
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-up');
                text.textContent = 'Свернуть';
            } else {
                // Сворачиваем
                preview.classList.remove('hidden');
                full.classList.add('hidden');
                icon.classList.remove('fa-chevron-up');
                icon.classList.add('fa-chevron-down');
                text.textContent = 'Развернуть';
            }
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


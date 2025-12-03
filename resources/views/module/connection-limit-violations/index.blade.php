@extends('layouts.admin')

@section('title', 'Нарушения лимитов подключений')
@section('page-title', 'Лимиты подключений')

@section('content')
    <div class="space-y-6">

        <!-- Панель быстрых действий -->
        <x-admin.card>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h5 class="text-sm font-semibold text-gray-700 mb-2">Быстрая проверка</h5>
                    <form action="{{ route('admin.module.connection-limit-violations.manual-check') }}"
                          method="POST" 
                          class="flex items-center gap-2 flex-wrap">
                        @csrf
                        <div class="flex items-center gap-2">
                            <label class="text-sm text-gray-700">Порог:</label>
                            <input type="number" 
                                   name="threshold" 
                                   value="3" 
                                   min="1" 
                                   max="10"
                                   class="mt-1 block w-20 rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 sm:text-sm"
                                   title="Нарушение при 4+ IP (порог 3 означает 3 и менее - не нарушение)">
                        </div>
                        <div class="flex items-center gap-2">
                            <label class="text-sm text-gray-700">Окно (мин):</label>
                            <input type="number" 
                                   name="window" 
                                   value="15" 
                                   min="1" 
                                   max="1440"
                                   class="mt-1 block w-24 rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 sm:text-sm"
                                   title="Окно проверки в минутах (по умолчанию 15 минут)">
                        </div>
                        <button type="submit" class="inline-flex items-center px-3 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            <i class="fas fa-search mr-1"></i> Проверить
                        </button>
                    </form>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" 
                            class="inline-flex items-center px-3 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500" 
                            onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: { id: 'bulkActionsModal' } }))">
                        <i class="fas fa-tasks mr-1"></i> Массовые действия
                    </button>
                    <a href="{{ route('admin.module.connection-limit-violations.index') }}"
                       class="inline-flex items-center px-3 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        <i class="fas fa-sync mr-1"></i> Обновить
                    </a>
                </div>
            </div>
        </x-admin.card>

        <!-- Информационная панель о логике подсчета -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <h5 class="font-semibold text-blue-900 mb-3 flex items-center">
                <i class="fas fa-info-circle mr-2"></i> Как работает система нарушений
            </h5>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                <div>
                    <strong class="text-blue-900">Логика обнаружения:</strong>
                    <ul class="mt-1 space-y-1 text-blue-800">
                        <li>• Нарушение фиксируется при подключении с <strong>разных сетей</strong> (не из одной /24 подсети)</li>
                        <li>• Порог: <strong>4+ уникальных IP</strong> из разных сетей (3 IP - еще не нарушение)</li>
                        <li>• Окно проверки: <strong>15 минут</strong></li>
                    </ul>
                </div>
                <div>
                    <strong class="text-blue-900">Подсчет нарушений:</strong>
                    <ul class="mt-1 space-y-1 text-blue-800">
                        <li>• <strong>1 нарушение:</strong> Предупреждение пользователю</li>
                        <li>• <strong>2 нарушения:</strong> Повторное предупреждение</li>
                        <li>• <strong>3+ нарушений:</strong> Автоматический перевыпуск ключа</li>
                    </ul>
                </div>
                <div>
                    <strong class="text-blue-900">Автоматизация:</strong>
                    <ul class="mt-1 space-y-1 text-blue-800">
                        <li>• Проверка нарушений: <strong>каждые 10 минут</strong> (cron)</li>
                        <li>• Обработка нарушений: <strong>каждый час</strong> (cron)</li>
                        <li>• Авто-решение старых: <strong>через 72 часа</strong></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Виджеты статистики -->
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
            <div class="bg-indigo-600 text-white rounded-lg p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-xs font-semibold uppercase mb-1 opacity-90">Всего нарушений</div>
                        <div class="text-2xl font-bold">{{ $stats['total'] }}</div>
                    </div>
                    <i class="fas fa-exclamation-triangle text-3xl opacity-75"></i>
                </div>
            </div>
            <div class="bg-red-600 text-white rounded-lg p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-xs font-semibold uppercase mb-1 opacity-90">Активные</div>
                        <div class="text-2xl font-bold">{{ $stats['active'] }}</div>
                    </div>
                    <i class="fas fa-bell text-3xl opacity-75"></i>
                </div>
            </div>
            <div class="bg-yellow-600 text-white rounded-lg p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-xs font-semibold uppercase mb-1 opacity-90">Сегодня</div>
                        <div class="text-2xl font-bold">{{ $stats['today'] }}</div>
                    </div>
                    <i class="fas fa-calendar-day text-3xl opacity-75"></i>
                </div>
            </div>
            <div class="bg-blue-600 text-white rounded-lg p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-xs font-semibold uppercase mb-1 opacity-90">Критические (≥3)</div>
                        <div class="text-2xl font-bold">{{ $stats['critical'] ?? 0 }}</div>
                    </div>
                    <i class="fas fa-skull-crossbones text-3xl opacity-75"></i>
                </div>
            </div>
            <div class="bg-green-600 text-white rounded-lg p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-xs font-semibold uppercase mb-1 opacity-90">Решено</div>
                        <div class="text-2xl font-bold">{{ $stats['resolved'] ?? 0 }}</div>
                    </div>
                    <i class="fas fa-check-circle text-3xl opacity-75"></i>
                </div>
            </div>
        </div>

        <!-- Фильтры -->
        <x-admin.filter-form action="{{ route('admin.module.connection-limit-violations.index') }}">
            <x-admin.filter-select 
                name="status" 
                label="Статус"
                :options="[
                    'active' => 'Активные',
                    'resolved' => 'Решенные',
                    'ignored' => 'Игнорированные'
                ]"
                value="{{ request('status') }}" />
            
            <x-admin.filter-select 
                name="violation_count" 
                label="Нарушений ≥"
                :options="['1' => '1+', '2' => '2+', '3' => '3+']"
                value="{{ request('violation_count') }}" />
            
            <x-admin.filter-select 
                name="panel_id" 
                label="Панель"
                :options="collect($panels)->mapWithKeys(function($panel) {
                    return [$panel->id => $panel->host ?? $panel->panel_adress];
                })->toArray()"
                value="{{ request('panel_id') }}"
                placeholder="Все панели" />
            
            <x-admin.filter-input 
                name="date_from" 
                label="Дата с" 
                value="{{ request('date_from') }}" 
                type="date" />
            
            <x-admin.filter-input 
                name="date_to" 
                label="Дата по" 
                value="{{ request('date_to') }}" 
                type="date" />
            
            <x-admin.filter-input 
                name="search" 
                label="Поиск" 
                value="{{ request('search') }}" 
                placeholder="ID ключа или пользователя" />
        </x-admin.filter-form>

        <!-- Таблица нарушений -->
        <x-admin.card>
            <x-slot name="title">
                Список нарушений
            </x-slot>
            <x-slot name="tools">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                    Показано: {{ $violations->count() }} из {{ $violations->total() }}
                </span>
            </x-slot>

            @if($violations->isEmpty())
                <x-admin.empty-state 
                    icon="fa-shield-alt" 
                    title="Нарушения не найдены"
                    description="Попробуйте изменить параметры фильтрации" />
            @else
                <form id="bulkForm">
                    @csrf
                    <div class="overflow-x-auto w-full">
                        <table class="w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" width="30">
                                        <input type="checkbox" id="selectAll" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Время</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ключ</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Пользователь</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Лимит / Факт</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Уведомления</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP адреса</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Повторений</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Статус</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Действия</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                            @php
                                $totalViolations = $violations->count();
                                $currentIndex = 0;
                            @endphp
                            @foreach($violations as $violation)
                                @php
                                    $currentIndex++;
                                    // Если записей 3 или меньше, все меню открываются сверху
                                    // Если записей больше 3, последние 3 открываются сверху
                                    $isLastRows = $totalViolations <= 3 || $currentIndex > ($totalViolations - 3);
                                @endphp
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <input type="checkbox" 
                                               name="violation_ids[]" 
                                               value="{{ $violation->id }}"
                                               class="violation-checkbox rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $violation->created_at->format('d.m.Y H:i') }}
                                    </td>
                                    <td class="px-6 py-4 text-sm">
                                        @if($violation->keyActivate)
                                            <div class="flex items-center">
                                                <a href="{{ route('admin.module.key-activate.index', ['id' => $violation->keyActivate->id]) }}"
                                                   class="font-mono text-xs text-indigo-600 hover:text-indigo-800 font-semibold"
                                                   title="Перейти к ключу">
                                                    {{ substr($violation->keyActivate->id, 0, 8) }}...
                                                </a>
                                                <button class="ml-2 text-gray-400 hover:text-gray-600 copy-key-btn"
                                                        data-clipboard-text="{{ $violation->keyActivate->id }}"
                                                        title="Копировать ID ключа">
                                                    <i class="fas fa-copy text-xs"></i>
                                                </button>
                                            </div>
                                            <div class="mt-1">
                                                <small class="text-gray-500 text-xs">
                                                    @if($violation->panel && isset($violation->panel->host))
                                                        <a href="{{ route('admin.module.panel.index', ['id' => $violation->panel_id]) }}"
                                                           class="text-gray-500 hover:text-gray-700"
                                                           title="Перейти к панели">
                                                            {{ $violation->panel->host }}
                                                        </a>
                                                    @else
                                                        N/A
                                                    @endif
                                                </small>
                                            </div>
                                            @if($violation->isKeyReplaced())
                                                <div class="mt-1">
                                                    <small class="text-green-600 text-xs">
                                                        <i class="fas fa-key"></i> Ключ заменен
                                                        @if($violation->getReplacedKeyId())
                                                            <a href="{{ route('admin.module.key-activate.index', ['id' => $violation->getReplacedKeyId()]) }}"
                                                               class="text-green-600 hover:text-green-800 ml-1">
                                                                (новый)
                                                            </a>
                                                        @endif
                                                    </small>
                                                </div>
                                            @endif
                                        @else
                                            <span class="text-red-600 text-sm">Ключ удален</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-sm">
                                        @if($violation->user_tg_id)
                                            <div>
                                                <a href="{{ route('admin.module.telegram-users.index', ['telegram_id' => $violation->user_tg_id]) }}"
                                                   class="font-semibold text-indigo-600 hover:text-indigo-800"
                                                   title="Перейти к пользователю">
                                                    {{ $violation->user_tg_id }}
                                                </a>
                                            </div>
                                            @if($violation->keyActivate && $violation->keyActivate->user)
                                                <div class="mt-1">
                                                    <small class="text-gray-500 text-xs">
                                                        {{ $violation->keyActivate->user->username ?? $violation->keyActivate->user->first_name ?? 'N/A' }}
                                                    </small>
                                                </div>
                                            @endif
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-sm">
                                        <div class="flex items-center gap-1">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                                                {{ $violation->allowed_connections }}
                                            </span>
                                            <span class="text-gray-400">/</span>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">
                                                {{ $violation->actual_connections }}
                                            </span>
                                        </div>
                                        <div class="mt-1">
                                            <small class="text-gray-500 text-xs">
                                                Превышение: <strong>+{{ $violation->excess_percentage }}%</strong>
                                            </small>
                                        </div>
                                        @if(count($violation->ip_addresses ?? []) > 0)
                                            <div class="mt-1">
                                                <small class="text-gray-500 text-xs">
                                                    Сетей: <strong>{{ count(array_unique(array_map(function($ip) {
                                                        $parts = explode('.', $ip);
                                                        return count($parts) === 4 ? $parts[0].'.'.$parts[1].'.'.$parts[2].'.0/24' : $ip;
                                                    }, $violation->ip_addresses ?? []))) }}</strong>
                                                </small>
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <div class="flex items-center"
                                             title="Отправлено уведомлений: {{ $violation->getNotificationsSentCount() }}@if($violation->getLastNotificationTime())&#10;Последнее: {{ $violation->getLastNotificationTimeFormatted() }}@endif">
                                            <i class="{{ $violation->notification_icon }} mr-1 text-gray-400"></i>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">
                                                {{ $violation->getNotificationsSentCount() }}
                                            </span>
                                            @if($violation->getLastNotificationTime())
                                                <small class="text-gray-500 ml-1 text-xs">
                                                    {{ $violation->getLastNotificationTimeFormatted() }}
                                                </small>
                                            @else
                                                <small class="text-gray-400 ml-1 text-xs">—</small>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm">
                                        <div class="ip-addresses flex flex-wrap gap-1">
                                            @php
                                                $ipAddresses = $violation->ip_addresses ?? [];
                                                $displayedIps = array_slice($ipAddresses, 0, 3);
                                                $totalIps = count($ipAddresses);
                                            @endphp
                                            
                                            @foreach($displayedIps as $ip)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-mono bg-gray-100 text-gray-800"
                                                      title="{{ $ip }}">{{ $ip }}</span>
                                            @endforeach
                                            
                                                @if($totalIps > 3)
                                                    <button type="button" 
                                                            class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-200 text-gray-700 hover:bg-gray-300 ip-show-more-btn"
                                                            onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: { id: 'ipModal{{ $violation->id }}' } }))"
                                                            title="Показать все IP-адреса">
                                                        +{{ $totalIps - 3 }}
                                                    </button>
                                                @endif
                                            
                                            @if($totalIps === 0)
                                                <span class="text-gray-400 text-xs">—</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <div>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                {{ $violation->violation_count >= 3 ? 'bg-red-100 text-red-800' : '' }}
                                                {{ $violation->violation_count >= 2 && $violation->violation_count < 3 ? 'bg-yellow-100 text-yellow-800' : '' }}
                                                {{ $violation->violation_count < 2 ? 'bg-blue-100 text-blue-800' : '' }}">
                                                {{ $violation->violation_count }}
                                            </span>
                                            <div class="mt-1">
                                                @if($violation->violation_count >= 3)
                                                    <small class="text-red-600 text-xs"><i class="fas fa-exclamation-circle"></i> Критично</small>
                                                @elseif($violation->violation_count >= 2)
                                                    <small class="text-yellow-600 text-xs"><i class="fas fa-exclamation-triangle"></i> Повторное</small>
                                                @else
                                                    <small class="text-blue-600 text-xs"><i class="fas fa-info-circle"></i> Первое</small>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            {{ $violation->status_color === 'success' ? 'bg-green-100 text-green-800' : '' }}
                                            {{ $violation->status_color === 'danger' ? 'bg-red-100 text-red-800' : '' }}
                                            {{ $violation->status_color === 'warning' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                            {{ $violation->status_color === 'info' ? 'bg-blue-100 text-blue-800' : '' }}
                                            {{ $violation->status_color === 'secondary' ? 'bg-gray-100 text-gray-800' : '' }}"
                                            id="status-{{ $violation->id }}">
                                            <i class="{{ $violation->status_icon }} mr-1"></i>
                                            {{ $violation->status }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="relative inline-block text-left" x-data="{ open: false }">
                                            <button type="button" 
                                                    @click="open = !open"
                                                    @click.away="open = false"
                                                    class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            
                                            <div x-show="open"
                                                 x-cloak
                                                 x-transition:enter="transition ease-out duration-100"
                                                 x-transition:enter-start="transform opacity-0 scale-95"
                                                 x-transition:enter-end="transform opacity-100 scale-100"
                                                 x-transition:leave="transition ease-in duration-75"
                                                 x-transition:leave-start="transform opacity-100 scale-100"
                                                 x-transition:leave-end="transform opacity-0 scale-95"
                                                 @click.away="open = false"
                                                 class="absolute right-0 w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 focus:outline-none z-50 @if($isLastRows)origin-bottom-right bottom-full mb-2 @else origin-top-right top-full mt-2 @endif"
                                                 style="display: none;">
                                                <div class="py-1" role="menu">
                                                    <button type="button" 
                                                            class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center quick-action"
                                                            data-violation-id="{{ $violation->id }}"
                                                            data-action="toggle_status"
                                                            @click="open = false"
                                                            role="menuitem">
                                                        <i class="fas fa-sync mr-2 text-indigo-600"></i>
                                                        Переключить статус
                                                    </button>

                                                    @if($violation->status === \App\Models\VPN\ConnectionLimitViolation::STATUS_ACTIVE)
                                                    <button type="button" 
                                                            class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center quick-action"
                                                            data-violation-id="{{ $violation->id }}"
                                                            data-action="ignore"
                                                            @click="open = false"
                                                            role="menuitem">
                                                        <i class="fas fa-eye-slash mr-2 text-gray-600"></i>
                                                        Игнорировать
                                                    </button>
                                                    @endif

                                                    <button type="button" 
                                                            class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center"
                                                            onclick="sendNotification({{ $violation->id }}); open = false;"
                                                            @click="open = false"
                                                            role="menuitem">
                                                        <i class="fas fa-bell mr-2 text-yellow-600"></i>
                                                        Отправить уведомление
                                                        @if($violation->getNotificationsSentCount() > 0)
                                                            <span class="ml-auto text-xs text-gray-500">({{ $violation->getNotificationsSentCount() }})</span>
                                                        @endif
                                                    </button>

                                                    @if($violation->keyActivate && $violation->status === \App\Models\VPN\ConnectionLimitViolation::STATUS_ACTIVE)
                                                    <button type="button" 
                                                            class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center"
                                                            onclick="reissueKey({{ $violation->id }}); open = false;"
                                                            @click="open = false"
                                                            role="menuitem">
                                                        <i class="fas fa-redo-alt mr-2 text-red-600"></i>
                                                        Перевыпустить ключ
                                                    </button>
                                                    @endif

                                                    <a href="{{ route('admin.module.connection-limit-violations.show', $violation) }}"
                                                       class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center"
                                                       @click="open = false"
                                                       role="menuitem">
                                                        <i class="fas fa-eye mr-2 text-blue-600"></i>
                                                        Подробнее
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </form>

                <!-- Pagination -->
                <x-admin.pagination-wrapper :paginator="$violations" />
            @endif
        </x-admin.card>
    </div>

    <!-- Модальное окно массовых действий -->
    <x-admin.modal id="bulkActionsModal" title="Массовые действия">
        <div>
            <p class="text-sm text-gray-700 mb-4">Выбрано: <span id="selectedCount" class="font-semibold">0</span> нарушений</p>

            <div class="mb-4">
                <label for="bulkActionSelect" class="block text-sm font-medium text-gray-700 mb-1">
                    Действие:
                </label>
                <select id="bulkActionSelect" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 sm:text-sm">
                    <option value="">-- Выберите действие --</option>
                    <option value="resolve">Пометить как решенные</option>
                    <option value="ignore">Пометить как игнорированные</option>
                    <option value="notify">Отправить уведомления</option>
                    <option value="reissue_keys">Перевыпустить ключи (автоматически при 3+)</option>
                    <option value="delete">Удалить нарушения</option>
                </select>
            </div>

            <div id="actionWarning" class="bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-3 rounded-lg text-sm hidden"></div>
        </div>
        <x-slot name="footer">
            <button type="button" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" id="executeBulkAction">Выполнить</button>
            <button type="button" 
                    class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" 
                    onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: { id: 'bulkActionsModal' } }))">
                Отмена
            </button>
        </x-slot>
    </x-admin.modal>

    <!-- Модальные окна для отображения всех IP-адресов -->
    @foreach($violations as $violation)
        @if(count($violation->ip_addresses ?? []) > 3)
            <x-admin.modal id="ipModal{{ $violation->id }}" title="Все IP-адреса нарушения #{{ $violation->id }}" size="lg">
                <div>
                    <div class="mb-4">
                        <strong class="text-gray-700">Всего IP-адресов:</strong> 
                        <span class="font-semibold">{{ count($violation->ip_addresses ?? []) }}</span>
                    </div>
                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 max-h-96 overflow-y-auto">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2">
                            @foreach($violation->ip_addresses ?? [] as $index => $ip)
                                <div class="flex items-center justify-between bg-white rounded px-3 py-2 border border-gray-200">
                                    <span class="font-mono text-sm text-gray-800">{{ $ip }}</span>
                                    <button type="button" 
                                            class="ml-2 text-gray-400 hover:text-indigo-600 copy-ip-btn" 
                                            data-clipboard-text="{{ $ip }}"
                                            title="Копировать IP">
                                        <i class="fas fa-copy text-xs"></i>
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    
                    @if(count($violation->ip_addresses ?? []) > 0)
                        <div class="mt-4">
                            <button type="button" 
                                    class="inline-flex items-center px-3 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" 
                                    id="copyAllIps{{ $violation->id }}"
                                    data-ips="{{ implode(', ', $violation->ip_addresses ?? []) }}">
                                <i class="fas fa-copy mr-1"></i> Копировать все IP
                            </button>
                        </div>
                    @endif
                </div>
                <x-slot name="footer">
                    <button type="button" 
                            class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" 
                            onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: { id: 'ipModal{{ $violation->id }}' } }))">
                        Закрыть
                    </button>
                </x-slot>
            </x-admin.modal>
        @endif
    @endforeach
@endsection


@push('js')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/clipboard.js/2.0.8/clipboard.min.js"></script>
    <script>
        // Инициализация Clipboard.js для кнопок копирования и других обработчиков
        document.addEventListener('DOMContentLoaded', function () {
            // Копирование ID ключа
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

            // Копирование IP-адресов
            const clipboardIp = new ClipboardJS('.copy-ip-btn');
            clipboardIp.on('success', function (e) {
                const icon = e.trigger.querySelector('i');
                const originalClass = icon.className;
                icon.className = 'fas fa-check text-success';
                setTimeout(() => {
                    icon.className = originalClass;
                }, 2000);
                e.clearSelection();
                showToast('IP-адрес скопирован', 'success');
            });

            // Копирование всех IP-адресов
            document.querySelectorAll('[id^="copyAllIps"]').forEach(button => {
                button.addEventListener('click', function() {
                    const ips = this.getAttribute('data-ips');
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(ips).then(() => {
                            showToast('Все IP-адреса скопированы', 'success');
                        }).catch(() => {
                            // Fallback для старых браузеров
                            const textarea = document.createElement('textarea');
                            textarea.value = ips;
                            document.body.appendChild(textarea);
                            textarea.select();
                            document.execCommand('copy');
                            document.body.removeChild(textarea);
                            showToast('Все IP-адреса скопированы', 'success');
                        });
                    } else {
                        // Fallback для старых браузеров
                        const textarea = document.createElement('textarea');
                        textarea.value = ips;
                        document.body.appendChild(textarea);
                        textarea.select();
                        document.execCommand('copy');
                        document.body.removeChild(textarea);
                        showToast('Все IP-адреса скопированы', 'success');
                    }
                });
            });

            // Функция для обновления счетчика
            function updateSelectedCount() {
                const checkboxes = document.querySelectorAll('.violation-checkbox');
                const checked = document.querySelectorAll('.violation-checkbox:checked');
                const count = checked.length;
                
                const selectedCountEl = document.getElementById('selectedCount');
                if (selectedCountEl) {
                    selectedCountEl.textContent = count;
                }
                
                const selectAllCheckbox = document.getElementById('selectAll');
                if (selectAllCheckbox) {
                    const total = checkboxes.length;
                    selectAllCheckbox.checked = total > 0 && count === total;
                    selectAllCheckbox.indeterminate = count > 0 && count < total;
                }
            }

            // Выделение всех чекбоксов - используем простой подход
            const selectAllCheckbox = document.getElementById('selectAll');
            if (selectAllCheckbox) {
                selectAllCheckbox.onchange = function() {
                    const checkboxes = document.querySelectorAll('.violation-checkbox');
                    const isChecked = this.checked;
                    checkboxes.forEach(function(checkbox) {
                        checkbox.checked = isChecked;
                    });
                    updateSelectedCount();
                };
            }

            // Обновление счетчика при изменении чекбоксов
            const violationCheckboxes = document.querySelectorAll('.violation-checkbox');
            violationCheckboxes.forEach(function(checkbox) {
                checkbox.onchange = function() {
                    updateSelectedCount();
                };
            });

            // Инициализация
            setTimeout(function() {
                updateSelectedCount();
            }, 200);
            
            // Обновление при открытии модального окна
            window.addEventListener('open-modal', function(event) {
                if (event.detail && event.detail.id === 'bulkActionsModal') {
                    setTimeout(function() {
                        updateSelectedCount();
                    }, 300);
                }
            });
        });

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
                        if (statusBadge) {
                            statusBadge.className = `inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                ${data.status_color === 'success' ? 'bg-green-100 text-green-800' : ''}
                                ${data.status_color === 'danger' ? 'bg-red-100 text-red-800' : ''}
                                ${data.status_color === 'warning' ? 'bg-yellow-100 text-yellow-800' : ''}
                                ${data.status_color === 'info' ? 'bg-blue-100 text-blue-800' : ''}
                                ${data.status_color === 'secondary' ? 'bg-gray-100 text-gray-800' : ''}`;
                            statusBadge.innerHTML = `<i class="${data.status_icon} mr-1"></i>${data.new_status}`;
                        }

                        toastr.success('Статус обновлен');
                    }
                })
                    .catch(error => {
                        console.error('Error:', error);
                        showToast('Ошибка при обновлении', 'error');
                    });
            });
        });

        // Массовые действия
        const executeBulkActionBtn = document.getElementById('executeBulkAction');
        if (executeBulkActionBtn) {
            executeBulkActionBtn.addEventListener('click', function () {
                const selectedIds = Array.from(document.querySelectorAll('.violation-checkbox:checked'))
                    .map(checkbox => checkbox.value);

                const action = document.getElementById('bulkActionSelect');
                const actionValue = action ? action.value : '';
                const button = this;

                if (!actionValue) {
                    toastr.warning('Выберите действие');
                    return;
                }

                if (selectedIds.length === 0) {
                    toastr.warning('Выберите нарушения');
                    return;
                }

                // Показываем индикатор загрузки
                const originalText = button.innerHTML;
                button.disabled = true;
                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Обработка...';

                const formData = new FormData();
                formData.append('action', actionValue);
                formData.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
                selectedIds.forEach(id => formData.append('violation_ids[]', id));

            fetch('{{ route("admin.module.connection-limit-violations.bulk-actions") }}', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    button.disabled = false;
                    button.innerHTML = originalText;

                    if (data && data.success) {
                        toastr.success(data.message);
                        window.dispatchEvent(new CustomEvent('close-modal', { detail: { id: 'bulkActionsModal' } }));
                        
                        // Обновляем только таблицу без полной перезагрузки
                        setTimeout(() => {
                            location.reload();
                        }, 1000);
                    } else {
                        toastr.error(data?.message || 'Ошибка при выполнении действия');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    button.disabled = false;
                    button.innerHTML = originalText;
                    showToast('Ошибка при выполнении действия', 'error');
                });
            });
        }

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

        // Вспомогательная функция для уведомлений (используем toastr)
        function showToast(message, type = 'info') {
            if (type === 'success') {
                toastr.success(message);
            } else if (type === 'error' || type === 'danger') {
                toastr.error(message);
            } else if (type === 'warning') {
                toastr.warning(message);
            } else {
                toastr.info(message);
            }
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
                                toastr.success('Нарушение игнорировано');
                            } else {
                                toastr.success('Статус обновлен');
                            }
                            location.reload();
                        } else {
                            toastr.error('Ошибка: ' + (data.message || 'неизвестная ошибка'));
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

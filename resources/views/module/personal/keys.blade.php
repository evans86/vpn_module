@extends('module.personal.layouts.app')

@section('title', 'Управление ключами')

@section('content')
    <div class="px-4 py-6 sm:px-0">
        <!-- Форма фильтрации - В ОДНУ СТРОКУ -->
        <div class="bg-white shadow rounded-lg mb-6">
            <div class="px-4 py-5 sm:p-6">
                <h4 class="text-lg font-medium text-gray-900 mb-4">Фильтры поиска</h4>
                <form method="GET" action="{{ route('personal.keys') }}">
                    <div class="flex flex-wrap items-end gap-4">
                        <!-- Поиск по ключу -->
                        <div class="flex-1 min-w-[200px]">
                            <label for="key_search" class="block text-sm font-medium text-gray-700 mb-1">
                                Поиск по ключу
                            </label>
                            <input type="text" name="key_search" id="key_search"
                                   value="{{ request('key_search') }}"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                   placeholder="Введите ключ">
                        </div>

                        <!-- Поиск по Telegram ID -->
                        <div class="flex-1 min-w-[200px]">
                            <label for="telegram_search" class="block text-sm font-medium text-gray-700 mb-1">
                                Поиск по Telegram ID
                            </label>
                            <input type="text" name="telegram_search" id="telegram_search"
                                   value="{{ request('telegram_search') }}"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                   placeholder="Введите Telegram ID">
                        </div>

                        <!-- Фильтр по статусу -->
                        <div class="flex-1 min-w-[200px]">
                            <label for="status_filter" class="block text-sm font-medium text-gray-700 mb-1">
                                Статус ключа
                            </label>
                            <select name="status_filter" id="status_filter"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                @foreach($statuses as $value => $label)
                                    <option value="{{ $value }}" {{ request('status_filter') === (string) $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Фильтр по сроку действия -->
                        <div class="flex-1 min-w-[200px]">
                            <label for="expiry_filter" class="block text-sm font-medium text-gray-700 mb-1">
                                Срок действия
                            </label>
                            <select name="expiry_filter" id="expiry_filter"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="">Все ключи</option>
                                <option value="active" {{ request('expiry_filter') == 'active' ? 'selected' : '' }}>Активные</option>
                                <option value="expired" {{ request('expiry_filter') == 'expired' ? 'selected' : '' }}>Просроченные</option>
                            </select>
                        </div>

                        <!-- Кнопки -->
                        <div class="flex gap-2">
                            <button type="submit"
                                    class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 whitespace-nowrap">
                                Применить
                            </button>
                            <a href="{{ route('personal.keys') }}"
                               class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500 whitespace-nowrap">
                                Сбросить
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Таблица ключей -->
        <div class="bg-white shadow rounded-lg overflow-hidden">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                <h3 class="text-lg leading-6 font-medium text-gray-900">
                    Список ключей
                </h3>
                <p class="mt-1 text-sm text-gray-500">
                    Всего ключей: {{ $keys->total() }}
                </p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ключ</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Трафик</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Период</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Статус</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Пользователь</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Срок действия</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Действия</th>
                    </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($keys as $key)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center group cursor-pointer" onclick="copyKey('{{ $key->id }}')" title="Кликните чтобы скопировать">
                                    <div class="text-sm text-gray-500 font-mono">
                                        {{ $key->id }}
                                    </div>
                                    <svg class="w-4 h-4 ml-2 text-gray-400 opacity-0 group-hover:opacity-100 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                    </svg>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $key->getTrafficInfo() }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $key->getPeriodInfo() }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $key->getStatusBadgeClassSalesman() }}">
                                    {{ $key->getStatusText() }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">
                                    {{ $key->user_nickname }}
                                </div>
                                @if($key->user_tg_id)
                                    <div class="text-sm text-gray-500">
                                        TG ID: {{ $key->user_tg_id }}
                                    </div>
                                @endif
                                @if($key->user_name)
                                    <div class="text-sm text-blue-600 font-medium">
                                        Имя: {{ $key->user_name }}
                                    </div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $key->expiry_date_formatted }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <a href="https://vpn-telegram.com/config/{{ $key->id }}"
                                   target="_blank"
                                   class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                   title="Открыть конфигурацию">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                    </svg>
                                    Конфигурация
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">
                                Нет доступных ключей
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if($keys->hasPages())
                <div class="px-4 py-4 bg-gray-50 border-t border-gray-200">
                    {{ $keys->appends(request()->query())->onEachSide(1)->links('pagination::tailwind') }}
                </div>
            @endif
        </div>
    </div>

    <!-- Скрипт для копирования ключа -->
    <script>
        function copyKey(key) {
            // Проверяем доступность Clipboard API
            if (navigator.clipboard && window.isSecureContext) {
                // Современный способ с Clipboard API
                navigator.clipboard.writeText(key).then(() => {
                    showNotification('Ключ скопирован в буфер обмена: ' + key);
                }).catch(err => {
                    console.error('Ошибка при копировании: ', err);
                    useFallbackCopy(key);
                });
            } else {
                // Fallback для старых браузеров или HTTP
                useFallbackCopy(key);
            }
        }

        function useFallbackCopy(key) {
            const textArea = document.createElement('textarea');
            textArea.value = key;
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            textArea.style.top = '-999999px';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();

            try {
                const successful = document.execCommand('copy');
                if (successful) {
                    showNotification('Ключ скопирован в буфер обмена: ' + key);
                } else {
                    showNotification('Не удалось скопировать ключ', 'error');
                }
            } catch (err) {
                console.error('Ошибка при копировании: ', err);
                showNotification('Ошибка при копировании', 'error');
            } finally {
                document.body.removeChild(textArea);
            }
        }

        function showNotification(message, type = 'success') {
            // Создаем элемент уведомления
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 px-4 py-2 rounded-md shadow-lg z-50 transition-all duration-300 transform translate-x-full ${type === 'success' ? 'bg-green-500 text-white' : 'bg-red-500 text-white'}`;
            notification.textContent = message;
            document.body.appendChild(notification);

            // Показываем уведомление
            setTimeout(() => {
                notification.classList.remove('translate-x-full');
                notification.classList.add('translate-x-0');
            }, 10);

            // Скрываем уведомление через 3 секунды
            setTimeout(() => {
                notification.classList.remove('translate-x-0');
                notification.classList.add('translate-x-full');
                setTimeout(() => {
                    document.body.removeChild(notification);
                }, 300);
            }, 3000);
        }
    </script>

    <style>
        .group:hover {
            background-color: #f9fafb;
        }
        .cursor-pointer {
            cursor: pointer;
        }
    </style>
@endsection

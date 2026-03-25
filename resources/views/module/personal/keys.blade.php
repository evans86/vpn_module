@extends('module.personal.layouts.app')

@section('title', 'Управление ключами')

@section('content')
    <div class="px-4 py-6 sm:px-0 max-w-full min-w-0">
        <!-- Фильтры -->
        <div class="bg-white shadow rounded-lg mb-6 overflow-hidden">
            <div class="px-4 py-5 sm:p-6">
                <h4 class="text-lg font-medium text-gray-900 mb-4">Фильтры поиска</h4>
                <form method="GET" action="{{ \App\Helpers\UrlHelper::personalRoute('personal.keys') }}">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4 items-end">
                        <div class="min-w-0">
                            <label for="key_search" class="block text-sm font-medium text-gray-700 mb-1">
                                Поиск по ключу
                            </label>
                            <input type="text" name="key_search" id="key_search"
                                   value="{{ request('key_search') }}"
                                   class="w-full min-w-0 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                   placeholder="Введите ключ">
                        </div>

                        <div class="min-w-0">
                            <label for="telegram_search" class="block text-sm font-medium text-gray-700 mb-1">
                                Поиск по Telegram ID
                            </label>
                            <input type="text" name="telegram_search" id="telegram_search"
                                   value="{{ request('telegram_search') }}"
                                   class="w-full min-w-0 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                   placeholder="Введите Telegram ID">
                        </div>

                        <div class="min-w-0">
                            <label for="status_filter" class="block text-sm font-medium text-gray-700 mb-1">
                                Статус ключа
                            </label>
                            <select name="status_filter" id="status_filter"
                                    class="w-full min-w-0 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                @foreach($statuses as $value => $label)
                                    <option value="{{ $value }}" {{ request('status_filter') === (string) $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="min-w-0">
                            <label for="source_filter" class="block text-sm font-medium text-gray-700 mb-1">
                                Источник ключа
                            </label>
                            <select name="source_filter" id="source_filter"
                                    class="w-full min-w-0 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                @foreach($sources as $value => $label)
                                    <option value="{{ $value }}" {{ request('source_filter') === $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="min-w-0">
                            <label for="expiry_filter" class="block text-sm font-medium text-gray-700 mb-1">
                                Срок действия
                            </label>
                            <select name="expiry_filter" id="expiry_filter"
                                    class="w-full min-w-0 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="">Все ключи</option>
                                <option value="active" {{ request('expiry_filter') == 'active' ? 'selected' : '' }}>
                                    Активные
                                </option>
                                <option value="expired" {{ request('expiry_filter') == 'expired' ? 'selected' : '' }}>
                                    Просроченные
                                </option>
                            </select>
                        </div>

                        <div class="flex flex-wrap gap-2 sm:col-span-2 lg:col-span-3 xl:col-span-1 xl:justify-end">
                            <button type="submit"
                                    class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 whitespace-nowrap">
                                Применить
                            </button>
                            <a href="{{ \App\Helpers\UrlHelper::personalRoute('personal.keys') }}"
                               class="inline-flex items-center px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500 whitespace-nowrap">
                                Сбросить
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Таблица ключей -->
        <div class="bg-white shadow rounded-lg overflow-hidden max-w-full">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                <h3 class="text-lg leading-6 font-medium text-gray-900">
                    Список ключей
                </h3>
                <p class="mt-1 text-sm text-gray-500">
                    Всего ключей: {{ $keys->total() }}
                </p>
            </div>
            <div class="overflow-x-auto max-w-full -mx-px">
                <table class="min-w-[920px] w-full divide-y divide-gray-200 table-fixed">
                    <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="w-[200px] px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Ключ
                        </th>
                        <th scope="col" class="w-[100px] px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Период
                        </th>
                        <th scope="col" class="w-[110px] px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Статус
                        </th>
                        <th scope="col" class="w-[100px] px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Источник
                        </th>
                        <th scope="col" class="w-[160px] px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Пользователь
                        </th>
                        <th scope="col" class="w-[120px] px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Срок действия
                        </th>
                        <th scope="col" class="w-[min(320px,30%)] min-w-[260px] px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Действия
                        </th>
                    </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($keys as $key)
                        <tr class="align-top">
                            <td class="px-3 py-3 min-w-0">
                                <div class="flex items-start gap-2 min-w-0">
                                    <button type="button"
                                            class="flex-shrink-0 p-1 rounded text-gray-400 hover:text-indigo-600 hover:bg-gray-100 border border-transparent hover:border-gray-200"
                                            data-copy="{{ $key->id }}"
                                            title="Скопировать ID ключа"
                                            aria-label="Скопировать ID ключа">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                        </svg>
                                    </button>
                                    <span class="text-xs text-gray-800 font-mono break-all leading-snug" title="{{ $key->id }}">{{ $key->id }}</span>
                                </div>
                            </td>
                            <td class="px-3 py-3 text-sm text-gray-600 break-words">
                                {{ $key->getPeriodInfo() }}
                            </td>
                            <td class="px-3 py-3">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $key->getStatusBadgeClassSalesman() }}">
                                    {{ $key->getStatusText() }}
                                </span>
                            </td>

                            <td class="px-3 py-3 text-sm text-gray-600">
                                @if($key->module_salesman_id)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                        Модуль VPN
                                    </span>
                                @elseif($key->pack_salesman_id)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        Telegram бот
                                    </span>
                                @endif
                            </td>

                            <td class="px-3 py-3 min-w-0 text-sm">
                                <div class="text-gray-900 break-words line-clamp-3" title="{{ $key->user_nickname }}">
                                    {{ $key->user_nickname }}
                                </div>
                                @if($key->user_tg_id)
                                    <div class="text-xs text-gray-500 mt-0.5 break-all">
                                        TG: {{ $key->user_tg_id }}
                                    </div>
                                @endif
                                @if($key->user_name)
                                    <div class="text-xs text-indigo-600 font-medium mt-0.5 break-words">
                                        {{ $key->user_name }}
                                    </div>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-sm text-gray-600 whitespace-normal break-words">
                                {{ $key->expiry_date_formatted }}
                            </td>
                            <td class="px-3 py-3 text-sm min-w-0">
                                @if($key->status == \App\Models\KeyActivate\KeyActivate::ACTIVE)
                                    @php
                                        $configMainUrl = \App\Helpers\UrlHelper::configUrl($key->id);
                                        $mirrorUrls = \App\Helpers\UrlHelper::configMirrorUrls($key->id);
                                        $refreshUrl = route('vpn.config.refresh', ['token' => $key->id], false);
                                    @endphp
                                    <div class="flex flex-col gap-2 min-w-0">
                                        <div class="flex flex-wrap items-center gap-1.5">
                                            <a href="{{ $configMainUrl }}"
                                               target="_blank" rel="noopener noreferrer"
                                               class="inline-flex items-center px-2.5 py-1.5 border border-transparent text-xs font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-indigo-500 flex-shrink-0">
                                                <svg class="w-3.5 h-3.5 mr-1 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                          d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                                </svg>
                                                Конфигурация
                                            </a>
                                            <button type="button"
                                                    class="inline-flex items-center px-2 py-1 text-xs font-medium rounded border border-gray-300 bg-white text-gray-700 hover:bg-gray-50"
                                                    data-copy="{{ $configMainUrl }}"
                                                    title="Копировать ссылку на конфигурацию"
                                                    aria-label="Копировать ссылку на конфигурацию">
                                                <svg class="w-3.5 h-3.5 mr-1 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                          d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                                </svg>
                                                Копировать ссылку
                                            </button>
                                        </div>
                                        @foreach($mirrorUrls as $idx => $mirrorUrl)
                                            <div class="flex flex-wrap items-center gap-1.5 min-w-0 border-t border-gray-100 pt-2">
                                                <a href="{{ $mirrorUrl }}" target="_blank" rel="noopener noreferrer"
                                                   class="text-xs text-amber-800 hover:text-amber-950 underline truncate min-w-0 max-w-[200px] sm:max-w-[240px]"
                                                   title="{{ $mirrorUrl }}">Зеркало {{ $idx + 1 }}</a>
                                                <button type="button"
                                                        class="inline-flex items-center px-2 py-0.5 text-xs rounded border border-amber-200 bg-amber-50 text-amber-900 hover:bg-amber-100 flex-shrink-0"
                                                        data-copy="{{ $mirrorUrl }}"
                                                        title="Копировать URL зеркала"
                                                        aria-label="Копировать URL зеркала {{ $idx + 1 }}">
                                                    Копировать
                                                </button>
                                            </div>
                                        @endforeach
                                        <button type="button"
                                                class="key-refresh-btn inline-flex items-center justify-center gap-1.5 px-2.5 py-1.5 mt-1 text-xs font-medium rounded-md border border-indigo-200 bg-indigo-50 text-indigo-800 hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-indigo-400 disabled:opacity-50 disabled:cursor-not-allowed w-fit max-w-full"
                                                data-refresh-url="{{ $refreshUrl }}"
                                                title="Подтянуть актуальные данные с панелей (как «Обновить» на странице конфига)">
                                            <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357 2m15.357-2H15"/>
                                            </svg>
                                            Обновить данные
                                        </button>
                                    </div>
                                @else
                                    <span class="text-xs text-gray-500"
                                          title="Конфигурация доступна только для активированных ключей">
                                        Недоступно
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-8 text-center text-sm text-gray-500">
                                Нет доступных ключей
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if($keys->hasPages())
                <div class="px-4 py-4 bg-gray-50 border-t border-gray-200 overflow-x-auto">
                    {{ $keys->appends(request()->query())->onEachSide(1)->links('pagination::tailwind') }}
                </div>
            @endif
        </div>
    </div>

    <script>
        (function () {
            function copyText(text) {
                if (!text) return Promise.reject();
                if (navigator.clipboard && window.isSecureContext) {
                    return navigator.clipboard.writeText(text);
                }
                return new Promise(function (resolve, reject) {
                    var ta = document.createElement('textarea');
                    ta.value = text;
                    ta.style.position = 'fixed';
                    ta.style.left = '-9999px';
                    document.body.appendChild(ta);
                    ta.select();
                    try {
                        document.execCommand('copy') ? resolve() : reject();
                    } catch (e) {
                        reject(e);
                    } finally {
                        document.body.removeChild(ta);
                    }
                });
            }

            function showNotification(message, type) {
                type = type || 'success';
                var el = document.createElement('div');
                el.className = 'fixed top-4 right-4 px-4 py-2 rounded-md shadow-lg z-50 transition-all duration-300 transform translate-x-full max-w-[min(100vw-2rem,24rem)] text-sm break-words ' +
                    (type === 'success' ? 'bg-green-500 text-white' : 'bg-red-500 text-white');
                el.textContent = message;
                document.body.appendChild(el);
                setTimeout(function () {
                    el.classList.remove('translate-x-full');
                    el.classList.add('translate-x-0');
                }, 10);
                setTimeout(function () {
                    el.classList.remove('translate-x-0');
                    el.classList.add('translate-x-full');
                    setTimeout(function () {
                        if (el.parentNode) el.parentNode.removeChild(el);
                    }, 300);
                }, 3000);
            }

            document.addEventListener('click', function (e) {
                var btn = e.target.closest('[data-copy]');
                if (!btn || !btn.getAttribute('data-copy')) return;
                e.preventDefault();
                var v = btn.getAttribute('data-copy');
                copyText(v).then(function () {
                    showNotification('Скопировано в буфер обмена', 'success');
                }).catch(function () {
                    showNotification('Не удалось скопировать', 'error');
                });
            });

            document.addEventListener('click', function (e) {
                var btn = e.target.closest('.key-refresh-btn');
                if (!btn) return;
                e.preventDefault();
                var url = btn.getAttribute('data-refresh-url');
                if (!url) return;
                btn.disabled = true;
                fetch(url, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                })
                    .then(function (r) {
                        return r.json().then(function (j) { return { ok: r.ok, j: j }; }).catch(function () {
                            return { ok: false, j: { success: false, message: 'Некорректный ответ сервера' } };
                        });
                    })
                    .then(function (_ref) {
                        var j = _ref.j;
                        if (j && j.success) {
                            showNotification('Данные обновлены. Синхронизация с панелями может занять несколько секунд. Страница перезагрузится…', 'success');
                            setTimeout(function () { window.location.reload(); }, 2200);
                        } else {
                            var msg = (j && j.message) ? j.message : 'Не удалось обновить данные';
                            showNotification(msg, 'error');
                            btn.disabled = false;
                        }
                    })
                    .catch(function () {
                        showNotification('Ошибка сети при обновлении', 'error');
                        btn.disabled = false;
                    });
            });
        })();
    </script>

    <style>
        .line-clamp-3 {
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
    </style>
@endsection

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

                        <div class="min-w-0">
                            <label for="violation_filter" class="block text-sm font-medium text-gray-700 mb-1">
                                Лимит подключений
                            </label>
                            <select name="violation_filter" id="violation_filter"
                                    class="w-full min-w-0 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="">Все ключи</option>
                                <option value="only" {{ request('violation_filter') === 'only' ? 'selected' : '' }}>
                                    Только с нарушениями
                                </option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-5 flex flex-wrap items-center justify-end gap-3 border-t border-gray-100 pt-4">
                        <button type="submit"
                                class="px-5 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 whitespace-nowrap text-sm font-medium shadow-sm">
                            Применить
                        </button>
                        <a href="{{ \App\Helpers\UrlHelper::personalRoute('personal.keys') }}"
                           class="inline-flex items-center px-5 py-2 bg-white border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-400 whitespace-nowrap text-sm font-medium">
                            Сбросить
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Таблица ключей -->
        <div class="bg-white shadow rounded-lg overflow-hidden max-w-full border border-gray-100">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                <h3 class="text-lg leading-6 font-medium text-gray-900">
                    Список ключей
                </h3>
                <p class="mt-1 text-sm text-gray-500">
                    Всего ключей: {{ $keys->total() }}
                </p>
            </div>
            <div class="keys-table-scroll overflow-x-auto max-w-full">
                <table class="min-w-[960px] w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-[148px]">
                            Ключ
                        </th>
                        <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-[88px]">
                            Период
                        </th>
                        <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-[112px]">
                            Статус
                        </th>
                        <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-[104px]">
                            Источник
                        </th>
                        <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[140px]">
                            Пользователь
                        </th>
                        <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-[128px]">
                            Срок
                        </th>
                        <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[200px]">
                            Примечание
                        </th>
                        <th scope="col" class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider w-[132px]">
                            Действия
                        </th>
                    </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-100">
                    @forelse($keys as $key)
                        @php
                            $kid = (string) $key->id;
                            $kidShort = strlen($kid) > 24
                                ? substr($kid, 0, 13) . '…' . substr($kid, -8)
                                : $kid;
                        @endphp
                        <tr class="hover:bg-gray-50/80 align-middle">
                            <td class="px-3 py-2.5 min-w-0">
                                <div class="flex items-center gap-1.5 min-w-0">
                                    <button type="button"
                                            class="keys-icon-btn flex-shrink-0"
                                            data-copy="{{ $kid }}"
                                            title="Скопировать полный ID"
                                            aria-label="Скопировать ID ключа">
                                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                        </svg>
                                    </button>
                                    <span class="font-mono text-xs text-gray-800 truncate block max-w-[7.5rem] sm:max-w-[9rem]" title="{{ $kid }}">{{ $kidShort }}</span>
                                </div>
                            </td>
                            <td class="px-3 py-2.5 text-sm text-gray-600 whitespace-nowrap">
                                {{ $key->getPeriodInfo() }}
                            </td>
                            <td class="px-3 py-2.5 whitespace-nowrap">
                                <span class="px-2 py-0.5 inline-flex text-xs leading-5 font-medium rounded-full {{ $key->getStatusBadgeClassSalesman() }}">
                                    {{ $key->getStatusText() }}
                                </span>
                            </td>

                            <td class="px-3 py-2.5 whitespace-nowrap">
                                @if($key->module_salesman_id)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                        Модуль
                                    </span>
                                @elseif($key->pack_salesman_id)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        Бот
                                    </span>
                                @endif
                            </td>

                            <td class="px-3 py-2.5 text-sm min-w-0 max-w-[200px]">
                                <div class="text-gray-900 text-sm truncate" title="{{ $key->user_nickname }}">
                                    {{ $key->user_nickname }}
                                </div>
                                @if($key->user_tg_id)
                                    <div class="text-xs text-gray-500 truncate">TG {{ $key->user_tg_id }}</div>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-xs text-gray-600 leading-snug">
                                {{ $key->expiry_date_formatted }}
                            </td>
                            <td class="px-3 py-2.5 text-xs text-gray-700 align-top min-w-0 max-w-[280px]">
                                @if($key->replacedViolation)
                                    <p class="text-gray-800 leading-snug mb-1.5">
                                        Неактивен из‑за нарушения лимита подключений.
                                    </p>
                                    @if($key->replacedViolation->replaced_key_id)
                                        @php
                                            $newKeyLinkParams = ['key_search' => $key->replacedViolation->replaced_key_id];
                                            if (request('violation_filter') === 'only') {
                                                $newKeyLinkParams['violation_filter'] = 'only';
                                            }
                                        @endphp
                                        <p class="text-gray-600 leading-snug">
                                            <span class="text-gray-500">Новый ключ:</span>
                                            <a href="{{ \App\Helpers\UrlHelper::personalRoute('personal.keys', $newKeyLinkParams) }}"
                                               class="font-mono text-indigo-600 hover:text-indigo-800 break-all"
                                               title="{{ $key->replacedViolation->replaced_key_id }}">
                                                {{ $key->replacedViolation->replaced_key_id }}
                                            </a>
                                        </p>
                                    @endif
                                @elseif($key->replacementSourceViolation)
                                    <p class="text-gray-800 leading-snug">
                                        Активный ключ, выдан взамен после нарушения лимита подключений.
                                    </p>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-right whitespace-nowrap">
                                @if($key->status == \App\Models\KeyActivate\KeyActivate::ACTIVE)
                                    @php
                                        $configMainUrl = \App\Helpers\UrlHelper::configUrl($key->id);
                                        $mirrorUrls = \App\Helpers\UrlHelper::configMirrorUrls($key->id);
                                        $refreshUrl = route('vpn.config.refresh', ['token' => $key->id], false);
                                    @endphp
                                    <details class="keys-actions-dropdown relative inline-block text-left">
                                        <summary class="keys-actions-summary cursor-pointer select-none inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-700 shadow-sm hover:bg-gray-50 hover:border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-400">
                                            Меню
                                            <svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                            </svg>
                                        </summary>
                                        <div class="keys-actions-panel fixed z-[9999] w-56 max-h-[min(70vh,24rem)] overflow-y-auto rounded-lg border border-gray-200 bg-white py-1.5 shadow-2xl ring-1 ring-black/10">
                                            <a href="{{ $configMainUrl }}" target="_blank" rel="noopener noreferrer"
                                               class="flex items-center gap-2 px-3 py-2 text-xs text-gray-700 hover:bg-indigo-50">
                                                <svg class="w-4 h-4 text-indigo-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                                </svg>
                                                Открыть конфигурацию
                                            </a>
                                            <button type="button"
                                                    class="flex w-full items-center gap-2 px-3 py-2 text-xs text-left text-gray-700 hover:bg-gray-50"
                                                    data-copy="{{ $configMainUrl }}">
                                                <svg class="w-4 h-4 text-gray-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                                </svg>
                                                Копировать ссылку
                                            </button>
                                            @if(count($mirrorUrls))
                                                <div class="mx-2 my-2 border-t border-gray-100"></div>
                                            @endif
                                            @foreach($mirrorUrls as $idx => $mirrorUrl)
                                                <div class="mx-2 mb-2">
                                                    <div class="px-1 mb-1 text-[10px] font-semibold uppercase tracking-wider text-gray-500">Зеркало {{ $idx + 1 }}</div>
                                                    <div class="flex items-center gap-1 rounded-md bg-amber-50/80 px-1.5 py-1">
                                                        <a href="{{ $mirrorUrl }}" target="_blank" rel="noopener noreferrer"
                                                           class="flex-1 min-w-0 truncate text-xs font-medium text-amber-900 hover:underline"
                                                           title="{{ $mirrorUrl }}">Открыть</a>
                                                        <button type="button" class="keys-icon-btn flex-shrink-0 rounded p-1 text-amber-800 hover:bg-amber-100"
                                                                data-copy="{{ $mirrorUrl }}" title="Копировать URL">
                                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                                            </svg>
                                                        </button>
                                                    </div>
                                                </div>
                                            @endforeach
                                            <div class="mx-2 my-1 border-t border-gray-100"></div>
                                            <button type="button"
                                                    class="key-refresh-btn flex w-full items-center gap-2 px-3 py-2 text-xs text-left text-indigo-800 hover:bg-indigo-50 disabled:opacity-50"
                                                    data-refresh-url="{{ $refreshUrl }}">
                                                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357 2m15.357-2H15"/>
                                                </svg>
                                                Обновить данные
                                            </button>
                                        </div>
                                    </details>
                                @else
                                    <span class="text-xs text-gray-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-10 text-center text-sm text-gray-500">
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
                el.className = 'fixed top-4 right-4 px-4 py-2 rounded-md shadow-lg z-[100] transition-all duration-300 transform translate-x-full max-w-[min(100vw-2rem,24rem)] text-sm break-words ' +
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
                e.stopPropagation();
                var v = btn.getAttribute('data-copy');
                copyText(v).then(function () {
                    showNotification('Скопировано', 'success');
                }).catch(function () {
                    showNotification('Не удалось скопировать', 'error');
                });
            });

            document.addEventListener('click', function (e) {
                var btn = e.target.closest('.key-refresh-btn');
                if (!btn) return;
                e.preventDefault();
                e.stopPropagation();
                var url = btn.getAttribute('data-refresh-url');
                if (!url) return;
                var details = btn.closest('details');
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
                            if (details) details.open = false;
                            showNotification('Данные обновлены. Страница перезагрузится…', 'success');
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

            function positionKeysMenu(d) {
                var panel = d.querySelector('.keys-actions-panel');
                var summary = d.querySelector('.keys-actions-summary');
                if (!panel || !summary) return;
                var r = summary.getBoundingClientRect();
                var pw = Math.min(panel.offsetWidth || 224, window.innerWidth - 16);
                var ph = panel.offsetHeight;
                var margin = 8;
                var left = r.right - pw;
                if (left < margin) left = margin;
                if (left + pw > window.innerWidth - margin) left = window.innerWidth - pw - margin;
                var top = r.bottom + 6;
                if (top + ph > window.innerHeight - margin) {
                    top = r.top - ph - 6;
                }
                if (top < margin) top = margin;
                panel.style.left = left + 'px';
                panel.style.top = top + 'px';
            }

            document.querySelectorAll('.keys-actions-dropdown').forEach(function (d) {
                d.addEventListener('toggle', function () {
                    var td = d.closest('td');
                    if (td) {
                        td.classList.toggle('keys-menu-open-cell', d.open);
                    }
                    if (!d.open) return;
                    document.querySelectorAll('.keys-actions-dropdown').forEach(function (other) {
                        if (other !== d) other.open = false;
                    });
                    document.querySelectorAll('td.keys-menu-open-cell').forEach(function (cell) {
                        if (cell !== td) cell.classList.remove('keys-menu-open-cell');
                    });
                    requestAnimationFrame(function () {
                        positionKeysMenu(d);
                        requestAnimationFrame(function () { positionKeysMenu(d); });
                    });
                });
            });

            window.addEventListener('resize', function () {
                document.querySelectorAll('.keys-actions-dropdown[open]').forEach(function (d) {
                    positionKeysMenu(d);
                });
            });

            document.querySelectorAll('.keys-table-scroll').forEach(function (el) {
                el.addEventListener('scroll', function () {
                    document.querySelectorAll('.keys-actions-dropdown[open]').forEach(function (d) {
                        d.open = false;
                    });
                });
            });

            document.addEventListener('scroll', function (e) {
                var t = e.target;
                if (t && t.closest && t.closest('.keys-actions-panel')) return;
                document.querySelectorAll('.keys-actions-dropdown[open]').forEach(function (d) {
                    d.open = false;
                });
            }, true);

            document.addEventListener('click', function (e) {
                if (e.target.closest('.keys-actions-dropdown')) return;
                document.querySelectorAll('.keys-actions-dropdown[open]').forEach(function (d) {
                    d.open = false;
                });
            });
        })();
    </script>

    <style>
        .keys-actions-dropdown > summary::-webkit-details-marker { display: none; }
        .keys-actions-dropdown > summary { list-style: none; }
        .keys-icon-btn { background: transparent; border: none; padding: 0.125rem; border-radius: 0.25rem; cursor: pointer; }
        .keys-icon-btn:hover { background: rgba(99, 102, 241, 0.08); }
        .keys-icon-btn:focus { outline: 2px solid rgba(99, 102, 241, 0.4); outline-offset: 1px; }
        /* Иначе следующие строки таблицы рисуются поверх fixed-панели */
        td.keys-menu-open-cell {
            position: relative;
            z-index: 10000;
            isolation: isolate;
        }
    </style>
@endsection

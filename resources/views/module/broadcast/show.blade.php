@extends('layouts.admin')

@section('title', $campaign->name)
@section('page-title', 'Рассылка: ' . Str::limit($campaign->name, 40))

@section('content')
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <a href="{{ route('admin.module.broadcast.index') }}"
               class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900">
                <i class="fas fa-arrow-left mr-2"></i> К списку рассылок
            </a>
            @if($campaign->isDraft())
                <form action="{{ route('admin.module.broadcast.start', $campaign) }}" method="POST" class="inline">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                        <i class="fas fa-paper-plane mr-2"></i> Запустить рассылку
                    </button>
                </form>
            @elseif($campaign->isRunning())
                <form action="{{ route('admin.module.broadcast.cancel', $campaign) }}" method="POST" class="inline" onsubmit="return confirm('Остановить рассылку? Уже отправленные сообщения останутся доставленными.');">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center px-4 py-2 border border-red-300 text-sm font-medium rounded-md shadow-sm text-red-700 bg-white hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                        <i class="fas fa-stop mr-2"></i> Остановить рассылку
                    </button>
                </form>
            @endif
        </div>

        <x-admin.card title="{{ $campaign->name }}">
            <div class="space-y-4">
                <div class="flex flex-wrap items-center gap-4">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-medium
                        @if($campaign->status === 'draft') bg-gray-100 text-gray-800
                        @elseif($campaign->status === 'queued' || $campaign->status === 'running') bg-blue-100 text-blue-800
                        @elseif($campaign->status === 'completed') bg-green-100 text-green-800
                        @elseif($campaign->status === 'cancelled') bg-red-100 text-red-800
                        @else bg-gray-100 text-gray-800
                        @endif">
                        {{ $campaign->getStatusLabel() }}
                    </span>
                    <span class="text-sm text-gray-500">
                        Создана: {{ $campaign->created_at->format('d.m.Y H:i') }}
                    </span>
                    @if($campaign->started_at)
                        <span class="text-sm text-gray-500">
                            Запуск: {{ $campaign->started_at->format('d.m.Y H:i') }}
                        </span>
                    @endif
                    @if($campaign->completed_at)
                        <span class="text-sm text-gray-500">
                            Завершена: {{ $campaign->completed_at->format('d.m.Y H:i') }}
                        </span>
                    @endif
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="text-xs font-medium text-gray-500 uppercase">Всего получателей</div>
                        <div class="text-xl font-semibold text-gray-900">{{ $campaign->total_recipients }}</div>
                    </div>
                    <div class="bg-green-50 rounded-lg p-4">
                        <div class="text-xs font-medium text-green-700 uppercase">Доставлено</div>
                        <div class="text-xl font-semibold text-green-800">{{ $campaign->delivered_count }}</div>
                    </div>
                    <div class="bg-red-50 rounded-lg p-4">
                        <div class="text-xs font-medium text-red-700 uppercase">Не доставлено</div>
                        <div class="text-xl font-semibold text-red-800">{{ $campaign->failed_count }}</div>
                    </div>
                    <div class="bg-blue-50 rounded-lg p-4">
                        <div class="text-xs font-medium text-blue-700 uppercase">Ожидают</div>
                        <div class="text-xl font-semibold text-blue-800">{{ $campaign->getPendingCount() }}</div>
                    </div>
                </div>

                <div>
                    <h4 class="text-sm font-medium text-gray-700 mb-2">Текст сообщения</h4>
                    <div class="bg-gray-50 rounded-lg p-4 text-sm text-gray-800 whitespace-pre-wrap border border-gray-200">{{ $campaign->message }}</div>
                </div>
            </div>
        </x-admin.card>

        @if($campaign->isDraft())
            <x-admin.card title="Тестовая рассылка">
                <p class="text-sm text-gray-600 mb-4">Найдите пользователей по Telegram ID или username и добавьте их в список. Затем отправьте тест выбранным (не более 20).</p>

                <div class="mb-4">
                    <label for="test-user-search" class="block text-sm font-medium text-gray-700 mb-1">Поиск</label>
                    <input type="text" id="test-user-search" placeholder="Telegram ID или @username..." class="block w-full max-w-md rounded-md border-gray-300 shadow-sm text-sm" autocomplete="off">
                    <div id="test-search-results" class="mt-2 border border-gray-200 rounded-lg bg-white max-h-48 overflow-y-auto hidden"></div>
                    <div id="test-search-loading" class="mt-2 text-sm text-gray-500 hidden">Поиск...</div>
                    <div id="test-search-empty" class="mt-2 text-sm text-gray-500 hidden">Никого не найдено</div>
                </div>

                <div class="mb-4">
                    <h4 class="text-sm font-medium text-gray-700 mb-2">Кому будет отправлено <span id="test-selected-count">0</span></h4>
                    <ul id="test-selected-list" class="border border-gray-200 rounded-lg divide-y divide-gray-200 max-h-48 overflow-y-auto"></ul>
                    <p id="test-selected-empty" class="text-sm text-gray-400 py-2">Список пуст. Выберите получателей из результатов поиска.</p>
                </div>

                <form id="test-send-form" action="{{ route('admin.module.broadcast.test-send', $campaign) }}" method="POST">
                    @csrf
                    <input type="hidden" name="key_activate_ids" id="test-key-activate-ids" value="[]">
                    <button type="submit" id="test-send-btn" disabled class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed">
                        Отправить тест выбранным
                    </button>
                </form>

                <script>
                    (function() {
                        var searchUrl = '{{ route('admin.module.broadcast.search-users') }}';
                        var searchInput = document.getElementById('test-user-search');
                        var searchResults = document.getElementById('test-search-results');
                        var searchLoading = document.getElementById('test-search-loading');
                        var searchEmpty = document.getElementById('test-search-empty');
                        var selectedList = document.getElementById('test-selected-list');
                        var selectedCount = document.getElementById('test-selected-count');
                        var selectedEmpty = document.getElementById('test-selected-empty');
                        var keyIdsInput = document.getElementById('test-key-activate-ids');
                        var sendBtn = document.getElementById('test-send-btn');

                        var selected = [];
                        var debounceTimer = null;
                        var maxSelected = 20;

                        function displayLabel(user) {
                            var parts = [];
                            if (user.username) parts.push('@' + user.username);
                            parts.push('ID: ' + user.user_tg_id);
                            return parts.join(' · ');
                        }

                        function updateSelectedUi() {
                            selectedCount.textContent = selected.length;
                            selectedEmpty.style.display = selected.length ? 'none' : 'block';
                            selectedList.innerHTML = selected.map(function(u) {
                                return '<li class="flex items-center justify-between px-3 py-2 hover:bg-gray-50">' +
                                    '<span class="text-sm">' + displayLabel(u).replace(/</g, '&lt;') + '</span>' +
                                    '<button type="button" class="text-red-600 hover:text-red-800 text-sm" data-id="' + u.key_activate_id.replace(/"/g, '&quot;') + '">Убрать</button></li>';
                            }).join('');
                            selectedList.querySelectorAll('button').forEach(function(btn) {
                                btn.addEventListener('click', function() {
                                    var id = this.getAttribute('data-id');
                                    selected = selected.filter(function(u) { return u.key_activate_id !== id; });
                                    updateSelectedUi();
                                    syncHiddenInput();
                                });
                            });
                            syncHiddenInput();
                        }

                        function syncHiddenInput() {
                            var ids = selected.map(function(u) { return u.key_activate_id; });
                            keyIdsInput.value = JSON.stringify(ids);
                            sendBtn.disabled = ids.length === 0;
                        }

                        function addSelected(user) {
                            if (selected.some(function(u) { return u.key_activate_id === user.key_activate_id; })) return;
                            if (selected.length >= maxSelected) return;
                            selected.push(user);
                            updateSelectedUi();
                        }

                        function doSearch() {
                            var q = (searchInput && searchInput.value || '').trim();
                            searchResults.classList.add('hidden');
                            searchEmpty.classList.add('hidden');
                            if (q.length < 1) {
                                searchLoading.classList.add('hidden');
                                return;
                            }
                            searchLoading.classList.remove('hidden');
                            fetch(searchUrl + '?q=' + encodeURIComponent(q))
                                .then(function(r) { return r.json(); })
                                .then(function(data) {
                                    searchLoading.classList.add('hidden');
                                    if (!data || data.length === 0) {
                                        searchEmpty.classList.remove('hidden');
                                        searchResults.classList.add('hidden');
                                        return;
                                    }
                                    searchEmpty.classList.add('hidden');
                                    searchResults.classList.remove('hidden');
                                    searchResults.innerHTML = '<ul class="divide-y divide-gray-200">' + data.map(function(u) {
                                        return '<li class="px-3 py-2 hover:bg-gray-50 cursor-pointer text-sm" data-id="' + (u.key_activate_id || '').replace(/"/g, '&quot;') + '" data-tg="' + (u.user_tg_id || '') + '" data-username="' + (u.username || '').replace(/"/g, '&quot;') + '">' + displayLabel(u).replace(/</g, '&lt;') + '</li>';
                                    }).join('') + '</ul>';
                                    searchResults.querySelectorAll('li').forEach(function(li) {
                                        li.addEventListener('click', function() {
                                            addSelected({
                                                key_activate_id: this.getAttribute('data-id'),
                                                user_tg_id: this.getAttribute('data-tg'),
                                                username: this.getAttribute('data-username') || null
                                            });
                                        });
                                    });
                                })
                                .catch(function() {
                                    searchLoading.classList.add('hidden');
                                    searchEmpty.textContent = 'Ошибка поиска';
                                    searchEmpty.classList.remove('hidden');
                                });
                        }

                        if (searchInput) {
                            searchInput.addEventListener('input', function() {
                                clearTimeout(debounceTimer);
                                debounceTimer = setTimeout(doSearch, 300);
                            });
                            searchInput.addEventListener('focus', function() {
                                if ((searchInput.value || '').trim().length >= 1) doSearch();
                            });
                        }
                        updateSelectedUi();
                    })();
                </script>
            </x-admin.card>
        @endif
    </div>
@endsection

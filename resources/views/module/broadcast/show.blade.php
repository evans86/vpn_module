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

        @if($campaign->isDraft() && $recipientsForTest->isNotEmpty())
            <x-admin.card title="Тестовая рассылка — выбор получателей">
                <p class="text-sm text-gray-600 mb-4">Отправьте тест первым N получателям или найдите и отметьте нужных в списке (не более 20).</p>
                <div class="mb-3">
                    <label for="test-recipient-search" class="block text-sm font-medium text-gray-700 mb-1">Поиск</label>
                    <input type="text" id="test-recipient-search" placeholder="Telegram ID или № получателя..." class="block w-full max-w-xs rounded-md border-gray-300 shadow-sm text-sm" autocomplete="off">
                </div>
                <div class="overflow-x-auto max-h-80 overflow-y-auto border border-gray-200 rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50 sticky top-0">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase w-10">
                                    <input type="checkbox" id="test-select-all" title="Выбрать все видимые">
                                </th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Telegram ID</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($recipientsForTest as $r)
                                @php
                                    $tgId = $r->keyActivate && $r->keyActivate->user_tg_id ? (string) $r->keyActivate->user_tg_id : '';
                                @endphp
                                <tr class="hover:bg-gray-50 test-recipient-row" data-search="{{ $r->id }} {{ $tgId }}">
                                    <td class="px-3 py-2">
                                        @if($r->keyActivate && $r->keyActivate->user_tg_id)
                                            <input type="checkbox" name="recipient_ids[]" value="{{ $r->id }}" form="test-send-selected-form" class="test-recipient-cb rounded border-gray-300">
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-sm text-gray-500">{{ $r->id }}</td>
                                    <td class="px-3 py-2 text-sm text-gray-900">{{ $tgId ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="mt-2 text-xs text-gray-500"><span id="test-recipient-visible-count">{{ $recipientsForTest->count() }}</span> из {{ $recipientsForTest->count() }} получателей.</p>
                <form id="test-send-selected-form" action="{{ route('admin.module.broadcast.test-send', $campaign) }}" method="POST" class="mt-4">
                    @csrf
                    <button type="submit" class="inline-flex items-center px-3 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50">
                        Отправить тест выбранным
                    </button>
                </form>
                <script>
                    (function() {
                        var searchEl = document.getElementById('test-recipient-search');
                        var rows = document.querySelectorAll('.test-recipient-row');
                        var totalCount = rows.length;
                        var countEl = document.getElementById('test-recipient-visible-count');
                        var selectAllEl = document.getElementById('test-select-all');

                        function filter() {
                            var q = (searchEl && searchEl.value || '').trim().toLowerCase();
                            var visible = 0;
                            rows.forEach(function(tr) {
                                var show = !q || (tr.getAttribute('data-search') || '').toLowerCase().indexOf(q) !== -1;
                                tr.style.display = show ? '' : 'none';
                                tr.classList.toggle('test-row-visible', show);
                                if (show) visible++;
                            });
                            if (countEl) countEl.textContent = visible;
                            if (selectAllEl) selectAllEl.checked = false;
                        }

                        if (searchEl) searchEl.addEventListener('input', filter);
                        filter();

                        if (selectAllEl) selectAllEl.addEventListener('change', function() {
                            document.querySelectorAll('.test-recipient-row.test-row-visible .test-recipient-cb').forEach(function(cb) { cb.checked = selectAllEl.checked; });
                        });
                    })();
                </script>
            </x-admin.card>
        @endif
    </div>
@endsection

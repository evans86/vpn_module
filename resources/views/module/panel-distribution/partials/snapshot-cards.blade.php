{{-- Горизонтальная лента: ~4 карточки во viewport, мини-сводка по трафику за текущий месяц (данные как в «Статистика панелей»). --}}
<div class="bg-white shadow rounded-lg p-4 md:p-6">
    <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">Сводка по панелям</h3>
            <p class="text-xs text-gray-500 mt-0.5">Провайдер, тариф сервера, лимит и трафик за период (Marzban / API). Листайте вправо.</p>
        </div>
        <a href="{{ route('admin.module.panel-statistics.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800 whitespace-nowrap">
            Подробная статистика панелей →
        </a>
    </div>
    @if(empty($snapshotPanels))
        <p class="text-sm text-gray-500">Нет данных для сводки.</p>
    @else
        <div class="flex gap-4 overflow-x-auto pb-2 snap-x snap-mandatory scroll-smooth -mx-1 px-1" style="scrollbar-width: thin;">
            @foreach($snapshotPanels as $snap)
                <div class="snap-start shrink-0 w-[calc(25%-12px)] min-w-[220px] max-w-[280px] border border-gray-200 rounded-xl p-4 bg-gradient-to-b from-slate-50 to-white shadow-sm">
                    <div class="text-xs font-semibold text-indigo-700 uppercase tracking-wide">Панель #{{ $snap['panel_id'] }}</div>
                    <div class="text-sm font-medium text-gray-900 mt-1 truncate" title="{{ $snap['server_name'] }}">{{ $snap['server_name'] }}</div>
                    <dl class="mt-3 space-y-1.5 text-xs text-gray-600">
                        <div class="flex justify-between gap-2"><dt>Провайдер</dt><dd class="text-gray-800 font-medium truncate">{{ $snap['provider'] }}</dd></div>
                        <div class="flex justify-between gap-2"><dt>Тариф</dt><dd class="text-gray-800">{{ $snap['tariff_label'] }}</dd></div>
                        <div class="flex justify-between gap-2"><dt>Период</dt><dd class="text-gray-800">{{ $snap['period_label'] }}</dd></div>
                    </dl>
                    <div class="mt-3 pt-3 border-t border-gray-100">
                        @if($snap['used_tb'] !== null || $snap['limit_tb'] !== null)
                            <div class="text-xs text-gray-500 mb-1">Трафик</div>
                            <div class="text-sm font-semibold text-gray-900">
                                {{ $snap['used_tb'] !== null ? number_format((float) $snap['used_tb'], 2, '.', ' ') : '—' }}
                                /
                                {{ $snap['limit_tb'] !== null ? number_format((float) $snap['limit_tb'], 2, '.', ' ') : '—' }}
                                <span class="text-gray-500 font-normal">ТиБ</span>
                            </div>
                            @if($snap['used_percent'] !== null)
                                <div class="mt-2 w-full bg-gray-200 rounded-full h-1.5">
                                    <div class="h-1.5 rounded-full {{ ($snap['used_percent'] > 80) ? 'bg-red-500' : (($snap['used_percent'] > 60) ? 'bg-amber-400' : 'bg-emerald-500') }}"
                                         style="width: {{ min(100, (float) $snap['used_percent']) }}%"></div>
                                </div>
                                <div class="text-xs text-gray-500 mt-1">{{ number_format((float) $snap['used_percent'], 1) }}% от лимита</div>
                            @endif
                        @else
                            <span class="text-xs text-gray-400">Нет данных о трафике за период</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

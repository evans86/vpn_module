<div id="rotation" class="scroll-mt-24 space-y-6">
    @if($panelsWithErrors->isNotEmpty())
        <div class="bg-white shadow rounded-lg p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                <span class="text-red-500 mr-2">⚠️</span>
                Панели с ошибками (исключены из ротации)
            </h3>
            <p class="text-sm text-gray-600 mb-6">
                Эти панели автоматически исключены из ротации из-за ошибок при создании пользователей.
                После устранения проблемы снимите пометку об ошибке, чтобы вернуть панель в ротацию.
            </p>

            <div class="space-y-4">
                @foreach($panelsWithErrors as $panel)
                    <div class="border border-red-200 rounded-lg p-4 bg-red-50">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center mb-2">
                                    <span class="font-semibold text-gray-900">ID-{{ $panel->id }}</span>
                                    <span class="ml-2 px-2 py-1 text-xs font-semibold bg-red-500 text-white rounded">Ошибка</span>
                                </div>
                                <div class="text-sm text-gray-600 mb-2">
                                    <div><strong>Адрес:</strong> {{ $panel->panel_adress }}</div>
                                    @if($panel->server)
                                        <div><strong>Сервер:</strong> {{ $panel->server->name }}</div>
                                    @endif
                                    @if($panel->error_at)
                                        <div><strong>Дата ошибки:</strong> {{ $panel->error_at->format('d.m.Y H:i') }}</div>
                                    @endif
                                </div>
                                <div class="mt-3 p-3 bg-white rounded border border-red-200">
                                    <div class="text-sm font-medium text-gray-700 mb-1">Сообщение об ошибке:</div>
                                    <div class="text-sm text-gray-800 whitespace-pre-wrap">{{ $panel->error_message }}</div>
                                </div>

                                @if(isset($errorHistory[$panel->id]) && $errorHistory[$panel->id]->isNotEmpty())
                                    <div class="mt-3">
                                        <div class="text-sm font-medium text-gray-700 mb-2">История ошибок:</div>
                                        <div class="space-y-2">
                                            @foreach($errorHistory[$panel->id] as $history)
                                                <div class="text-xs p-2 bg-gray-50 rounded border">
                                                    <div class="flex justify-between items-start mb-1">
                                                        <span class="font-medium text-gray-700">{{ $history->error_occurred_at->format('d.m.Y H:i') }}</span>
                                                        @if($history->resolved_at)
                                                            <span class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded">{{ $history->resolution_type === 'automatic' ? 'Автоматически' : 'Вручную' }}</span>
                                                        @else
                                                            <span class="px-2 py-1 text-xs bg-red-100 text-red-800 rounded">Активна</span>
                                                        @endif
                                                    </div>
                                                    <div class="text-gray-600 mb-1">{{ $history->error_message }}</div>
                                                    @if($history->resolved_at)
                                                        <div class="text-gray-500 text-xs">
                                                            Решено: {{ $history->resolved_at->format('d.m.Y H:i') }}
                                                            @if($history->resolution_note) — {{ $history->resolution_note }} @endif
                                                        </div>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                            <div class="ml-4">
                                <form action="{{ route('admin.module.panel-settings.clear-error') }}" method="POST"
                                      onsubmit="return confirm('Вы уверены, что проблема решена и панель можно вернуть в ротацию?');">
                                    @csrf
                                    <input type="hidden" name="panel_id" value="{{ $panel->id }}">
                                    <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 transition-colors">Проблема решена</button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @else
        <div class="bg-white shadow rounded-lg p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                <span class="text-green-500 mr-2">✅</span>
                Статус панелей
            </h3>
            <p class="text-sm text-gray-600">Все панели работают нормально. Ошибок не обнаружено.</p>
        </div>
    @endif

    @if($excludedPanels->isNotEmpty())
        <div class="bg-white shadow rounded-lg p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                <span class="text-yellow-500 mr-2">🚫</span>
                Панели, исключенные из ротации
            </h3>
            <p class="text-sm text-gray-600 mb-6">
                Вручную исключены из ротации (тесты / обслуживание). Новые пользователи на этих панелях не создаются.
            </p>

            <div class="space-y-4">
                @foreach($excludedPanels as $panel)
                    <div class="border border-yellow-200 rounded-lg p-4 bg-yellow-50">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center mb-2">
                                    <span class="font-semibold text-gray-900">ID-{{ $panel->id }}</span>
                                    <span class="ml-2 px-2 py-1 text-xs font-semibold bg-yellow-500 text-white rounded">Исключена</span>
                                </div>
                                <div class="text-sm text-gray-600">
                                    <div><strong>Адрес:</strong> {{ $panel->panel_adress }}</div>
                                    @if($panel->server)
                                        <div><strong>Сервер:</strong> {{ $panel->server->name }}</div>
                                    @endif
                                    @if($panel->config_type)
                                        <div><strong>Тип конфига:</strong>
                                            @if($panel->config_type === 'reality_stable')
                                                <span class="px-2 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-800">REALITY (только)</span>
                                            @elseif($panel->config_type === 'reality')
                                                <span class="px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">REALITY</span>
                                            @elseif($panel->config_type === 'mixed')
                                                <span class="px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800">Смешанный</span>
                                            @else
                                                <span class="px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">Стабильный</span>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </div>
                            <div class="ml-4">
                                <a href="{{ route('admin.module.panel.index') }}" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors inline-block">
                                    <i class="fas fa-arrow-right mr-2"></i>Управление панелями
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>

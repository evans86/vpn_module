@extends('layouts.admin')

@section('title', 'Домены без VPN')
@section('page-title', 'Домены без VPN (Direct)')

@section('content')
    <div class="space-y-6">
        <x-admin.card title="Как это работает">
            <p class="text-sm text-gray-700 mb-3">
                Здесь задаётся <strong>единый список</strong> доменов и <strong>зон</strong> (например <code class="bg-gray-100 px-1 rounded">.ru</code>,
                <code class="bg-gray-100 px-1 rounded">ru</code> или <code class="bg-gray-100 px-1 rounded">*.nl</code>), для которых клиенты должны ходить
                в интернет <strong>напрямую</strong>, минуя VPN. Список отдаётся публичным JSON — его могут подхватывать приложения
                с поддержкой удалённых правил (sing-box, Clash Meta, кастомные клиенты).
            </p>
            <p class="text-sm text-gray-600 mb-2">
                <strong>JSON списка доменов:</strong>
            </p>
            <code class="block text-xs sm:text-sm bg-gray-100 border border-gray-200 rounded px-3 py-2 break-all">{{ $publicJsonUrl }}</code>
            <p class="text-sm text-gray-600 mb-2 mt-4">
                <strong>sing-box rule-set (source, remote):</strong>
            </p>
            <code class="block text-xs sm:text-sm bg-gray-100 border border-gray-200 rounded px-3 py-2 break-all">{{ $publicRuleSetUrl }}</code>
            <p class="text-xs text-gray-500 mt-3">
                <strong>Ссылка на подписку</strong> (<code class="bg-gray-100 px-1 rounded">/config/…</code>):
                <strong>Clash / Mihomo / Stash</strong> — добавьте <code class="bg-gray-100 px-1 rounded">?format=clash</code> для YAML с правилами DIRECT и proxy-providers;
                <strong>v2rayNG, NekoBox, Hiddify</strong> и др. обычно импортируют plain text (узлы); в начале ответа добавляются строки-комментарии с ссылками на rule-set и JSON (не используются при <code class="bg-gray-100 px-1 rounded">?format=raw</code> — он нужен только внутри Clash-профиля).
            </p>
        </x-admin.card>

        <x-admin.card title="Добавить домен">
            <form action="{{ route('admin.module.vpn-direct-domains.store') }}" method="POST" class="space-y-4">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
                    <div class="md:col-span-5">
                        <label for="domain" class="block text-sm font-medium text-gray-700 mb-1">Домен, зона или ссылка</label>
                        <input type="text" name="domain" id="domain" value="{{ old('domain') }}"
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                               placeholder="bank.ru, .ru, *.com или https://…"
                               required>
                        @error('domain')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="md:col-span-2">
                        <label for="sort_order" class="block text-sm font-medium text-gray-700 mb-1">Порядок</label>
                        <input type="number" name="sort_order" id="sort_order" value="{{ old('sort_order', 0) }}" min="0"
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    </div>
                    <div class="md:col-span-4">
                        <label for="note" class="block text-sm font-medium text-gray-700 mb-1">Заметка (необязательно)</label>
                        <input type="text" name="note" id="note" value="{{ old('note') }}"
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                               placeholder="банк, госуслуги…">
                    </div>
                    <div class="md:col-span-1 flex justify-end">
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                            <i class="fas fa-plus mr-2"></i> Добавить
                        </button>
                    </div>
                </div>
            </form>
        </x-admin.card>

        <x-admin.card title="Список">
            @if($items->isEmpty())
                <x-admin.empty-state
                    icon="fa-route"
                    title="Список пуст"
                    description="Добавьте домены выше — они появятся в публичном JSON для поддерживаемых клиентов."
                />
            @else
                <x-admin.table :headers="['Домен', 'Порядок', 'Заметка', 'В JSON', 'Действия']">
                    @foreach($items as $item)
                        <tr class="hover:bg-gray-50">
                            <td class="px-3 sm:px-6 py-3 text-sm font-mono text-gray-900 break-all">{{ $item->domain }}</td>
                            <td class="px-3 sm:px-6 py-3 text-sm text-gray-600">{{ $item->sort_order }}</td>
                            <td class="px-3 sm:px-6 py-3 text-sm text-gray-500">{{ $item->note ?: '—' }}</td>
                            <td class="px-3 sm:px-6 py-3 whitespace-nowrap">
                                <form action="{{ route('admin.module.vpn-direct-domains.toggle', $item) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit"
                                            class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-md {{ $item->is_enabled ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-600' }}">
                                        {{ $item->is_enabled ? 'Да' : 'Нет' }}
                                    </button>
                                </form>
                            </td>
                            <td class="px-3 sm:px-6 py-3 whitespace-nowrap text-sm space-x-2">
                                <a href="{{ route('admin.module.vpn-direct-domains.edit', $item) }}"
                                   class="text-indigo-600 hover:text-indigo-800">Изменить</a>
                                <form action="{{ route('admin.module.vpn-direct-domains.destroy', $item) }}" method="POST" class="inline"
                                      onsubmit="return confirm('Удалить этот домен из списка?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-800">Удалить</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </x-admin.table>
            @endif
        </x-admin.card>
    </div>
@endsection

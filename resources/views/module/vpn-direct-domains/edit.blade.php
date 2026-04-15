@extends('layouts.admin')

@section('title', 'Редактирование домена')
@section('page-title', 'Домен без VPN')

@section('content')
    <div class="space-y-6 max-w-3xl">
        <x-admin.card title="Редактировать запись">
            <form action="{{ route('admin.module.vpn-direct-domains.update', $item) }}" method="POST" class="space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <label for="domain" class="block text-sm font-medium text-gray-700 mb-1">Домен или зона</label>
                    <input type="text" name="domain" id="domain" value="{{ old('domain', $item->domain) }}"
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm font-mono"
                           placeholder="bank.ru, .ru, *.com"
                           required>
                    <p class="mt-1 text-xs text-gray-500">Зона целиком: <span class="font-mono">ru</span>, <span class="font-mono">.ru</span> или <span class="font-mono">*.ru</span> (весь <span class="font-mono">.ru</span>).</p>
                    @error('domain')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="sort_order" class="block text-sm font-medium text-gray-700 mb-1">Порядок сортировки</label>
                    <input type="number" name="sort_order" id="sort_order" value="{{ old('sort_order', $item->sort_order) }}" min="0"
                           class="w-full max-w-xs rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                </div>
                <div>
                    <label for="note" class="block text-sm font-medium text-gray-700 mb-1">Заметка</label>
                    <input type="text" name="note" id="note" value="{{ old('note', $item->note) }}"
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                </div>
                <div class="flex items-center gap-2">
                    <input type="hidden" name="is_enabled" value="0">
                    <input type="checkbox" name="is_enabled" id="is_enabled" value="1"
                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                           {{ (string) old('is_enabled', $item->is_enabled ? '1' : '0') === '1' ? 'checked' : '' }}>
                    <label for="is_enabled" class="text-sm text-gray-700">Учитывать в публичном JSON</label>
                </div>
                <div class="flex items-center gap-3 pt-2">
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                        Сохранить
                    </button>
                    <a href="{{ route('admin.module.vpn-direct-domains.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Отмена</a>
                </div>
            </form>
        </x-admin.card>
    </div>
@endsection

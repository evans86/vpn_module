@extends('layouts.admin')

@section('title', 'Новая рассылка')
@section('page-title', 'Новая рассылка')

@section('content')
    <div class="space-y-6">
        <x-admin.card title="Параметры рассылки">
            <p class="text-sm text-gray-600 mb-4">
                Сообщение будет отправлено всем пользователям с активным ключом (по одному сообщению на пользователя).
                Сейчас получателей: <strong>{{ $eligibleCount }}</strong>.
            </p>

            <form action="{{ route('admin.module.broadcast.store') }}" method="POST" class="space-y-4">
                @csrf

                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700">Название рассылки</label>
                    <input type="text"
                           name="name"
                           id="name"
                           value="{{ old('name') }}"
                           required
                           maxlength="255"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                           placeholder="Например: Обновление тарифов">
                    @error('name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="message" class="block text-sm font-medium text-gray-700">Текст сообщения</label>
                    <textarea name="message"
                              id="message"
                              rows="6"
                              required
                              maxlength="4096"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                              placeholder="Текст будет отправлен в Telegram (поддерживается HTML)">{{ old('message') }}</textarea>
                    <p class="mt-1 text-xs text-gray-500">До 4096 символов. Поддерживается HTML (например &lt;b&gt;, &lt;i&gt;, &lt;a href="..."&gt;).</p>
                    @error('message')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center gap-3">
                    <button type="submit"
                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        <i class="fas fa-save mr-2"></i> Создать рассылку
                    </button>
                    <a href="{{ route('admin.module.broadcast.index') }}"
                       class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50">
                        Отмена
                    </a>
                </div>
            </form>
        </x-admin.card>
    </div>
@endsection

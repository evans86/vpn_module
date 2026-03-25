@extends('module.personal.layouts.app')

@section('title', 'Резервный вход в кабинет')

@section('content')
    <div class="px-4 py-6 sm:px-0 max-w-2xl">
        <div class="mb-6">
            <h2 class="text-xl font-semibold text-gray-900">Резервный вход (email и пароль)</h2>
            <p class="mt-1 text-sm text-gray-600">
                Если Telegram недоступен, вы можете войти на страницу входа, указав email и пароль ниже.
                Пароль хранится в зашифрованном виде; администратор видит только email и факт, что пароль задан.
            </p>
        </div>

        @if(session('success'))
            <div class="mb-4 rounded-lg bg-green-50 text-green-800 text-sm px-4 py-3 border border-green-200">
                {{ session('success') }}
            </div>
        @endif

        <div class="bg-white shadow rounded-lg border border-gray-100 p-6">
            <form method="POST" action="{{ \App\Helpers\UrlHelper::personalRoute('personal.cabinet-login.update') }}">
                @csrf
                <div class="space-y-4">
                    <div>
                        <label for="cabinet_email" class="block text-sm font-medium text-gray-700">Email (логин)</label>
                        <input type="email" name="cabinet_email" id="cabinet_email"
                               value="{{ old('cabinet_email', $salesman->email) }}"
                               autocomplete="email"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('cabinet_email') border-red-500 @enderror"
                               placeholder="your@email.com">
                        @error('cabinet_email')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-xs text-gray-500">Очистите поле и сохраните, чтобы отключить вход по email.</p>
                    </div>
                    <div>
                        <label for="cabinet_password" class="block text-sm font-medium text-gray-700">
                            @if($salesman->hasCabinetEmailLoginEnabled())
                                Новый пароль
                            @else
                                Пароль
                            @endif
                        </label>
                        <input type="password" name="cabinet_password" id="cabinet_password"
                               autocomplete="new-password"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('cabinet_password') border-red-500 @enderror"
                               placeholder="{{ $salesman->hasCabinetEmailLoginEnabled() ? 'Оставьте пустым, чтобы не менять' : 'Минимум 8 символов' }}">
                        @error('cabinet_password')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="cabinet_password_confirmation" class="block text-sm font-medium text-gray-700">Повтор пароля</label>
                        <input type="password" name="cabinet_password_confirmation" id="cabinet_password_confirmation"
                               autocomplete="new-password"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    </div>
                </div>
                <div class="mt-6 flex flex-wrap gap-3">
                    <button type="submit"
                            class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Сохранить
                    </button>
                </div>
            </form>
        </div>

        @if($salesman->hasCabinetEmailLoginEnabled())
            <p class="mt-4 text-xs text-gray-500">
                Резервный вход <strong>включён</strong>. Логин: <strong>{{ $salesman->email }}</strong>
            </p>
        @else
            <p class="mt-4 text-xs text-gray-500">Резервный вход <strong>не настроен</strong> или отключён.</p>
        @endif
    </div>
@endsection

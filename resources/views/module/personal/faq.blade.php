@extends('module.personal.layouts.app')

@section('title', 'База знаний FAQ')

@section('content')

    <div class="px-4 py-6 sm:px-0">
        <div class="flex flex-col items-center justify-center min-h-[60vh]">
            <div class="text-center max-w-2xl mx-auto">
                <!-- Большая надпись "Скоро!" -->
                <h1 class="text-5xl md:text-6xl font-bold text-indigo-600 mb-6 animate-pulse">
                    Скоро!
                </h1>

                <!-- Описание -->
                <p class="text-xl text-gray-600 mb-8">
                    Раздел FAQ находится в разработке и будет доступен в ближайшее время
                </p>

                <!-- Кнопка возврата (опционально) -->
                <a href="{{ url()->previous() }}"
                   class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Вернуться назад
                </a>
            </div>
        </div>
    </div>

{{--    <div class="px-4 py-6 sm:px-0">--}}
{{--        <div class="mb-6">--}}
{{--            <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">--}}
{{--                Настройки аккаунта--}}
{{--            </h2>--}}
{{--            <p class="mt-2 text-sm text-gray-500">--}}
{{--                Управление вашим профилем и настройками безопасности--}}
{{--            </p>--}}
{{--        </div>--}}

{{--        <div class="bg-white shadow rounded-lg overflow-hidden">--}}
{{--            <div class="px-4 py-5 sm:px-6 border-b border-gray-200">--}}
{{--                <h3 class="text-lg leading-6 font-medium text-gray-900">--}}
{{--                    Профиль--}}
{{--                </h3>--}}
{{--                <p class="mt-1 max-w-2xl text-sm text-gray-500">--}}
{{--                    Основная информация о вашем аккаунте--}}
{{--                </p>--}}
{{--            </div>--}}
{{--            <div class="px-4 py-5 sm:p-6">--}}
{{--                <form>--}}
{{--                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">--}}
{{--                        <div>--}}
{{--                            <label for="name" class="block text-sm font-medium text-gray-700">Имя</label>--}}
{{--                            <input type="text" id="name" name="name" value="{{ $salesman->name }}" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">--}}
{{--                        </div>--}}

{{--                        <div>--}}
{{--                            <label for="telegram_id" class="block text-sm font-medium text-gray-700">Telegram ID</label>--}}
{{--                            <input type="text" id="telegram_id" name="telegram_id" value="{{ $salesman->telegram_id }}" disabled class="mt-1 bg-gray-100 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">--}}
{{--                        </div>--}}

{{--                        <div>--}}
{{--                            <label for="username" class="block text-sm font-medium text-gray-700">Username</label>--}}
{{--                            <input type="text" id="username" name="username" value="{{ $salesman->username }}" disabled class="mt-1 bg-gray-100 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">--}}
{{--                        </div>--}}

{{--                        <div>--}}
{{--                            <label for="phone" class="block text-sm font-medium text-gray-700">Телефон</label>--}}
{{--                            <input type="tel" id="phone" name="phone" value="{{ $salesman->phone }}" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">--}}
{{--                        </div>--}}

{{--                        <div class="sm:col-span-2">--}}
{{--                            <label for="email" class="block text-sm font-medium text-gray-700">Email</label>--}}
{{--                            <input type="email" id="email" name="email" value="{{ $salesman->email }}" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">--}}
{{--                        </div>--}}
{{--                    </div>--}}

{{--                    <div class="mt-6">--}}
{{--                        <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">--}}
{{--                            Сохранить изменения--}}
{{--                        </button>--}}
{{--                    </div>--}}
{{--                </form>--}}
{{--            </div>--}}
{{--        </div>--}}

{{--        <div class="mt-8 bg-white shadow rounded-lg overflow-hidden">--}}
{{--            <div class="px-4 py-5 sm:px-6 border-b border-gray-200">--}}
{{--                <h3 class="text-lg leading-6 font-medium text-gray-900">--}}
{{--                    Безопасность--}}
{{--                </h3>--}}
{{--                <p class="mt-1 max-w-2xl text-sm text-gray-500">--}}
{{--                    Настройки безопасности вашего аккаунта--}}
{{--                </p>--}}
{{--            </div>--}}
{{--            <div class="px-4 py-5 sm:p-6">--}}
{{--                <div class="space-y-6">--}}
{{--                    <div>--}}
{{--                        <h4 class="text-md font-medium text-gray-900 mb-2">Двухфакторная аутентификация</h4>--}}
{{--                        <div class="flex items-center">--}}
{{--                            <span class="relative inline-block">--}}
{{--                                <input type="checkbox" id="2fa_enabled" name="2fa_enabled" class="sr-only" {{ $salesman->two_factor_enabled ? 'checked' : '' }}>--}}
{{--                                <div class="block bg-gray-200 w-14 h-8 rounded-full"></div>--}}
{{--                                <div class="dot absolute left-1 top-1 bg-white w-6 h-6 rounded-full transition"></div>--}}
{{--                            </span>--}}
{{--                            <label for="2fa_enabled" class="ml-3 text-sm font-medium text-gray-700">--}}
{{--                                {{ $salesman->two_factor_enabled ? 'Включена' : 'Выключена' }}--}}
{{--                            </label>--}}
{{--                        </div>--}}
{{--                        <p class="mt-1 text-sm text-gray-500">--}}
{{--                            Добавьте дополнительный уровень безопасности к вашему аккаунту--}}
{{--                        </p>--}}
{{--                    </div>--}}

{{--                    <div class="border-t border-gray-200 pt-6">--}}
{{--                        <h4 class="text-md font-medium text-gray-900 mb-2">Смена пароля</h4>--}}
{{--                        <form>--}}
{{--                            <div class="space-y-4">--}}
{{--                                <div>--}}
{{--                                    <label for="current_password" class="block text-sm font-medium text-gray-700">Текущий пароль</label>--}}
{{--                                    <input type="password" id="current_password" name="current_password" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">--}}
{{--                                </div>--}}

{{--                                <div>--}}
{{--                                    <label for="new_password" class="block text-sm font-medium text-gray-700">Новый пароль</label>--}}
{{--                                    <input type="password" id="new_password" name="new_password" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">--}}
{{--                                </div>--}}

{{--                                <div>--}}
{{--                                    <label for="confirm_password" class="block text-sm font-medium text-gray-700">Подтвердите пароль</label>--}}
{{--                                    <input type="password" id="confirm_password" name="confirm_password" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">--}}
{{--                                </div>--}}
{{--                            </div>--}}

{{--                            <div class="mt-4">--}}
{{--                                <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">--}}
{{--                                    Изменить пароль--}}
{{--                                </button>--}}
{{--                            </div>--}}
{{--                        </form>--}}
{{--                    </div>--}}
{{--                </div>--}}
{{--            </div>--}}
{{--        </div>--}}

{{--        <div class="mt-8 bg-white shadow rounded-lg overflow-hidden">--}}
{{--            <div class="px-4 py-5 sm:px-6 border-b border-gray-200">--}}
{{--                <h3 class="text-lg leading-6 font-medium text-gray-900">--}}
{{--                    Уведомления--}}
{{--                </h3>--}}
{{--                <p class="mt-1 max-w-2xl text-sm text-gray-500">--}}
{{--                    Настройте, как вы хотите получать уведомления--}}
{{--                </p>--}}
{{--            </div>--}}
{{--            <div class="px-4 py-5 sm:p-6">--}}
{{--                <form>--}}
{{--                    <div class="space-y-4">--}}
{{--                        <div>--}}
{{--                            <h4 class="text-sm font-medium text-gray-900 mb-2">Email уведомления</h4>--}}
{{--                            <div class="space-y-2">--}}
{{--                                <div class="flex items-start">--}}
{{--                                    <div class="flex items-center h-5">--}}
{{--                                        <input id="email_sales" name="email_sales" type="checkbox" checked class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 rounded">--}}
{{--                                    </div>--}}
{{--                                    <div class="ml-3 text-sm">--}}
{{--                                        <label for="email_sales" class="font-medium text-gray-700">Новые продажи</label>--}}
{{--                                        <p class="text-gray-500">Получать уведомления о новых продажах</p>--}}
{{--                                    </div>--}}
{{--                                </div>--}}

{{--                                <div class="flex items-start">--}}
{{--                                    <div class="flex items-center h-5">--}}
{{--                                        <input id="email_payments" name="email_payments" type="checkbox" checked class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 rounded">--}}
{{--                                    </div>--}}
{{--                                    <div class="ml-3 text-sm">--}}
{{--                                        <label for="email_payments" class="font-medium text-gray-700">Платежи</label>--}}
{{--                                        <p class="text-gray-500">Получать уведомления о платежах</p>--}}
{{--                                    </div>--}}
{{--                                </div>--}}
{{--                            </div>--}}
{{--                        </div>--}}

{{--                        <div class="border-t border-gray-200 pt-4">--}}
{{--                            <h4 class="text-sm font-medium text-gray-900 mb-2">Telegram уведомления</h4>--}}
{{--                            <div class="space-y-2">--}}
{{--                                <div class="flex items-start">--}}
{{--                                    <div class="flex items-center h-5">--}}
{{--                                        <input id="telegram_sales" name="telegram_sales" type="checkbox" checked class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 rounded">--}}
{{--                                    </div>--}}
{{--                                    <div class="ml-3 text-sm">--}}
{{--                                        <label for="telegram_sales" class="font-medium text-gray-700">Новые продажи</label>--}}
{{--                                        <p class="text-gray-500">Получать уведомления о новых продажах</p>--}}
{{--                                    </div>--}}
{{--                                </div>--}}

{{--                                <div class="flex items-start">--}}
{{--                                    <div class="flex items-center h-5">--}}
{{--                                        <input id="telegram_payments" name="telegram_payments" type="checkbox" checked class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 rounded">--}}
{{--                                    </div>--}}
{{--                                    <div class="ml-3 text-sm">--}}
{{--                                        <label for="telegram_payments" class="font-medium text-gray-700">Платежи</label>--}}
{{--                                        <p class="text-gray-500">Получать уведомления о платежах</p>--}}
{{--                                    </div>--}}
{{--                                </div>--}}
{{--                            </div>--}}
{{--                        </div>--}}
{{--                    </div>--}}

{{--                    <div class="mt-6">--}}
{{--                        <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">--}}
{{--                            Сохранить настройки--}}
{{--                        </button>--}}
{{--                    </div>--}}
{{--                </form>--}}
{{--            </div>--}}
{{--        </div>--}}
{{--    </div>--}}
@endsection

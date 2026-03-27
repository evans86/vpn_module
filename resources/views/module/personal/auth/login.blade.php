<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Вход в личный кабинет — продавец</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-100 to-indigo-50 flex items-center justify-center p-4">
<div class="w-full max-w-md">
    <div class="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
        <div class="px-6 pt-8 pb-6 text-center border-b border-gray-100">
            <h1 class="text-xl font-semibold text-gray-900">Личный кабинет продавца</h1>
            <p class="mt-2 text-sm text-gray-500">Выберите способ входа</p>
        </div>

        <div class="p-6 space-y-6">
            @if(session('success'))
                <div class="rounded-lg bg-green-50 text-green-800 text-sm px-4 py-3 border border-green-200">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="rounded-lg bg-red-50 text-red-800 text-sm px-4 py-3 border border-red-200">{{ session('error') }}</div>
            @endif

            <!-- Telegram -->
            <div>
                <h2 class="text-sm font-medium text-gray-700 mb-3">Через Telegram</h2>
                <a href="{{ \App\Helpers\UrlHelper::personalRoute('personal.auth.telegram') }}"
                   class="flex items-center justify-center gap-2 w-full py-3 px-4 rounded-xl bg-blue-500 hover:bg-blue-600 text-white font-medium shadow-md transition-colors">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69a.2.2 0 00-.05-.18c-.06-.05-.14-.03-.21-.02-.09.02-1.49.95-4.22 2.79-.4.27-.76.41-1.08.4-.36-.01-1.04-.2-1.55-.37-.63-.2-1.12-.31-1.08-.66.02-.18.27-.36.74-.55 2.92-1.27 4.86-2.11 5.83-2.55 2.76-1.17 3.35-1.36 3.73-1.36.08 0 .27.02.39.12.1.08.13.21.14.27-.01.06.01.24 0 .38z"/>
                    </svg>
                    Войти через Telegram
                </a>
            </div>

            <div class="relative">
                <div class="absolute inset-0 flex items-center" aria-hidden="true">
                    <div class="w-full border-t border-gray-200"></div>
                </div>
                <div class="relative flex justify-center text-xs uppercase tracking-wide">
                    <span class="px-3 bg-white text-gray-400">или резервный вход</span>
                </div>
            </div>

            <!-- Email + password -->
            <div>
                <h2 class="text-sm font-medium text-gray-700 mb-3">По email и паролю</h2>
                <p class="text-xs text-gray-500 mb-4">Используйте, если Telegram недоступен. Email и пароль задаются в личном кабинете в разделе «Резервный вход».</p>

                <form method="GET" action="{{ \App\Helpers\UrlHelper::personalRoute('personal.auth.email') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label for="email" class="block text-xs font-medium text-gray-600 mb-1">Email</label>
                        <input type="email" name="email" id="email" value="{{ old('email') }}" required autocomplete="email"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 @error('email') border-red-400 @enderror"
                               placeholder="name@example.com">
                        @error('email')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="password" class="block text-xs font-medium text-gray-600 mb-1">Пароль</label>
                        <input type="password" name="password" id="password" required autocomplete="current-password"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <label class="flex items-center gap-2 text-sm text-gray-600">
                        <input type="checkbox" name="remember" value="1" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" {{ old('remember') ? 'checked' : '' }}>
                        Запомнить меня
                    </label>
                    <button type="submit"
                            class="w-full py-3 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium shadow transition-colors">
                        Войти
                    </button>
                </form>
            </div>
        </div>
    </div>
    <p class="text-center text-xs text-gray-400 mt-6">© {{ date('Y') }} VPN Service</p>
</div>
</body>
</html>

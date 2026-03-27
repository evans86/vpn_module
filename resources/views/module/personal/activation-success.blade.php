@extends('module.personal.layouts.app')

@section('title', 'Сообщение после активации ключа')

@section('content')
    @if(session('success'))
        <div class="mb-4 rounded-md bg-green-50 p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-green-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-green-800">{{ session('success') }}</p>
                </div>
            </div>
        </div>
    @endif

    <div class="px-4 py-6 sm:px-0">
        @if($hasBot)
            <div class="bg-white shadow rounded-lg mb-8">
                <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">
                        Сообщение после успешной активации ключа
                    </h3>
                    <p class="mt-1 max-w-2xl text-sm text-gray-500">
                        Текст, который получает клиент в боте активации сразу после успешной активации VPN. Поддерживается HTML (как в Telegram: &lt;b&gt;, &lt;a href&gt; и т.д.).
                    </p>
                </div>

                <div class="px-4 py-5 sm:p-6 space-y-6">
                    <div class="rounded-md bg-gray-50 border border-gray-200 p-4 text-sm text-gray-700">
                        <p class="font-medium text-gray-900 mb-2">Плейсхолдеры (подставляются при отправке):</p>
                        <ul class="list-disc list-inside space-y-1 font-mono text-xs">
                            <li><code>{EXPIRY_DATE}</code> — срок действия, дата вида 23.06.2026</li>
                            <li><code>{KEY_ID}</code> — UUID ключа активации</li>
                            <li><code>{CONFIG_URL}</code> — одна ссылка на страницу конфигурации (основной сайт)</li>
                            <li><code>{CONFIG_LINKS}</code> — блок ссылок: основной сайт + зеркала (HTML, как раньше по умолчанию)</li>
                            <li><code>{MIRROR_1}</code>, <code>{MIRROR_2}</code>, … — URL зеркал по порядку (если зеркала не заданы — пусто). Допускается опечатка <code>{MIRRO_1}</code></li>
                        </ul>
                        <p class="mt-3 text-amber-800 text-xs">Лимит Telegram на одно сообщение ~4096 символов.</p>
                    </div>

                    <form action="{{ \App\Helpers\UrlHelper::personalRoute('personal.activation-success.update') }}" method="GET">
                        @csrf
                        <div>
                            <label for="activation_success_text" class="block text-sm font-medium text-gray-700 mb-2">Шаблон сообщения</label>
                            <textarea id="activation_success_text" name="activation_success_text" rows="18"
                                      class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md font-mono text-xs"
                                      placeholder="Оставьте пустым — будет использоваться типовой шаблон с плейсхолдерами">{{ old('activation_success_text', $salesman->custom_activation_success_text) }}</textarea>
                            <p class="mt-2 text-sm text-gray-500">Пустое поле при сохранении — снова типовой текст из системы. Ниже — образец по умолчанию (можно скопировать и править).</p>
                        </div>

                        <div class="mt-8 border-t border-gray-200 pt-6">
                            <h4 class="text-md font-medium text-gray-900 mb-2">Кнопки со ссылками под сообщением</h4>
                            <p class="text-sm text-gray-500 mb-4">Каждая строка — одна кнопка в Telegram (подпись и URL). Пустые строки игнорируются. Если не задать ни одной кнопки — подставятся типовые ссылки на инструкции.</p>
                            @error('activation_links')
                                <p class="mb-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            <div id="activation-link-rows" class="space-y-2">
                                @foreach($keyboardRows as $i => $row)
                                    <div class="flex flex-col sm:flex-row gap-2 items-start sm:items-center activation-link-row">
                                        <input type="text" name="activation_links[{{ $i }}][text]" value="{{ $row['text'] ?? '' }}"
                                               maxlength="64"
                                               placeholder="Подпись кнопки"
                                               class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:max-w-xs sm:text-sm border-gray-300 rounded-md">
                                        <input type="text" name="activation_links[{{ $i }}][url]" value="{{ $row['url'] ?? '' }}"
                                               placeholder="https://…"
                                               class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full flex-1 sm:text-sm border-gray-300 rounded-md">
                                    </div>
                                @endforeach
                            </div>
                            <button type="button" id="add-activation-link-row"
                                    class="mt-3 inline-flex items-center px-3 py-1.5 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                + Добавить кнопку
                            </button>
                        </div>

                        <div class="mt-6 flex flex-wrap items-center gap-3">
                            <button type="submit"
                                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                Сохранить
                            </button>
                            <button type="button" onclick="if(confirm('Сбросить к типовому шаблону?')) document.getElementById('resetActivationForm').submit();"
                                    class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50">
                                Сбросить к типовому
                            </button>
                        </div>
                    </form>

                    <form id="resetActivationForm" action="{{ \App\Helpers\UrlHelper::personalRoute('personal.activation-success.reset') }}" method="GET" class="hidden">
                        @csrf
                    </form>

                    <div class="border-t border-gray-200 pt-6">
                        <h4 class="text-md font-medium text-gray-900 mb-2">Типовой шаблон (для копирования)</h4>
                        <pre class="bg-gray-50 p-4 rounded-lg border border-gray-200 text-xs overflow-x-auto whitespace-pre-wrap font-mono text-gray-800">{{ $defaultTemplate }}</pre>
                    </div>

                    <div class="border-t border-gray-200 pt-6">
                        <h4 class="text-md font-medium text-gray-900 mb-2">Предпросмотр (пример: срок +30 дней, тестовый UUID ключа)</h4>
                        <div class="bg-gray-50 p-4 rounded-lg border border-gray-200 prose prose-sm max-w-none activation-preview">
                            {!! $preview !!}
                        </div>
                        <p class="mt-3 text-xs text-gray-500">Кнопки в Telegram совпадут с сохранённым списком выше (или с типовыми, если список пустой).</p>
                    </div>
                </div>
            </div>
        @else
            <div class="bg-white shadow rounded-lg overflow-hidden">
                <div class="px-4 py-5 sm:px-6 bg-indigo-600 text-white">
                    <h3 class="text-lg leading-6 font-medium">
                        <i class="fas fa-robot mr-2"></i> Нет бота активации
                    </h3>
                </div>
                <div class="px-4 py-5 sm:p-6">
                    <p class="text-sm text-gray-700">
                        Чтобы настраивать это сообщение, привяжите бота активации к аккаунту (как для раздела «Помощь»).
                    </p>
                </div>
            </div>
        @endif
    </div>
    @if($hasBot)
    <script>
        (function () {
            var container = document.getElementById('activation-link-rows');
            var btn = document.getElementById('add-activation-link-row');
            if (!container || !btn) return;
            var idx = container.querySelectorAll('.activation-link-row').length;
            btn.addEventListener('click', function () {
                var wrap = document.createElement('div');
                wrap.className = 'flex flex-col sm:flex-row gap-2 items-start sm:items-center activation-link-row';
                wrap.innerHTML =
                    '<input type="text" name="activation_links[' + idx + '][text]" value="" maxlength="64" placeholder="Подпись кнопки" class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:max-w-xs sm:text-sm border-gray-300 rounded-md">' +
                    '<input type="text" name="activation_links[' + idx + '][url]" value="" placeholder="https://…" class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full flex-1 sm:text-sm border-gray-300 rounded-md">';
                container.appendChild(wrap);
                idx++;
            });
        })();
    </script>
    @endif
@endsection

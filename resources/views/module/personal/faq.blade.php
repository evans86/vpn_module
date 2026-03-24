@extends('module.personal.layouts.app')

@section('title', 'База знаний FAQ')

@section('content')
    @if(session('success'))
        <div class="mb-4 rounded-md bg-green-50 p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-green-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"
                         fill="currentColor">
                        <path fill-rule="evenodd"
                              d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                              clip-rule="evenodd"/>
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-green-800">
                        {{ session('success') }}
                    </p>
                </div>
            </div>
        </div>
    @endif


    <div class="px-4 py-6 sm:px-0">
        @if($hasBot)
            <!-- Редактор FAQ для бота -->
            <div class="bg-white shadow rounded-lg mb-8">
                <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">
                        Редактирование раздела "Помощь"
                    </h3>
                    <p class="mt-1 max-w-2xl text-sm text-gray-500">
                        Настройте текст, который видят ваши клиенты в разделе "Помощь" вашего бота
                    </p>
                </div>

                <div class="px-4 py-5 sm:p-6">
                    {{-- Явный путь: не зависит от route cache / генерации URL; POST на тот же URI, что и страница --}}
                    <form action="/personal/faq" method="POST">
                        @csrf

                        <div class="mb-6">
                            <label for="help_text" class="block text-sm font-medium text-gray-700 mb-2">
                                Текст раздела "Помощь"
                            </label>
                            <textarea id="help_text" name="help_text" rows="12"
                                      class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md"
                                      placeholder="Введите текст помощи для ваших клиентов..."><blockquote><b>❓ Помощь</b></blockquote>

🔹 <b>Активация VPN:</b>

1️⃣ Нажмите '🔑 Активировать'
2️⃣ Введите полученный ключ
3️⃣ Скопируйте конфигурацию и следуйте инструкциям для подключения на различных устройствах, представленным ниже

🔹 <b>Проверка статуса:</b>

1️⃣ Нажмите кнопку '📊 Статус'
2️⃣ Просмотрите информацию о вашем доступе и конфигурации

📁 <b>Инструкции по настройке VPN:</b>

- <a href="https://teletype.in/@bott_manager/C0WFg-Bsren">Android</a> 🤖
- <a href="https://teletype.in/@bott_manager/8jEexiKqjlEWQ">iOS</a> 🍏
- <a href="https://teletype.in/@bott_manager/kJaChoXUqmZ">Windows</a> 🪟
- <a href="https://teletype.in/@bott_manager/Q8vOQ-_lnQ_">MacOS</a> 💻
- <a href="https://teletype.in/@bott_manager/OIc2Dwer6jV">AndroidTV</a> 📺

👨🏻‍💻 По всем вопросам обращайтесь к <a href="ссылка на аккаунт поддержки">администратору</a> бота.</textarea>
                            <p class="mt-2 text-sm text-gray-500">
                                Можно использовать HTML-разметку. Максимальная длина: 4000 символов.
                            </p>
                        </div>

                        <div class="flex items-center justify-between">
                            <button type="submit"
                                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                Сохранить изменения
                            </button>

                            <button type="button" onclick="confirmReset()"
                                    class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                Сбросить к стандартному
                            </button>
                        </div>
                    </form>

                    <form id="resetForm" action="{{ \App\Helpers\UrlHelper::personalRoute('personal.faq.reset') }}" method="POST" class="hidden">
                        @csrf
                    </form>

                    <div class="mt-8 border-t border-gray-200 pt-6">
                        <h4 class="text-md font-medium text-gray-900 mb-2">Предпросмотр</h4>
                        <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                            <div class="vpn-instructions-preview">
                                <blockquote><b>❓ Помощь</b></blockquote>

                                🔹 <b>Активация VPN:</b>

                                1️⃣ Нажмите '🔑 Активировать'
                                2️⃣ Введите полученный ключ
                                3️⃣ Скопируйте конфигурацию и следуйте инструкциям для подключения на различных устройствах, представленным ниже

                                🔹 <b>Проверка статуса:</b>

                                1️⃣ Нажмите кнопку '📊 Статус'
                                2️⃣ Просмотрите информацию о вашем доступе и конфигурации

                                📁 <b>Инструкции по настройке VPN:</b>

                                - <a href="https://teletype.in/@bott_manager/C0WFg-Bsren">Android</a> 🤖
                                - <a href="https://teletype.in/@bott_manager/8jEexiKqjlEWQ">iOS</a> 🍏
                                - <a href="https://teletype.in/@bott_manager/kJaChoXUqmZ">Windows</a> 🪟
                                - <a href="https://teletype.in/@bott_manager/Q8vOQ-_lnQ_">MacOS</a> 💻
                                - <a href="https://teletype.in/@bott_manager/OIc2Dwer6jV">AndroidTV</a> 📺

                                👨🏻‍💻 По всем вопросам обращайтесь к <a href="ссылка на аккаунт поддержки">администратору</a> бота.
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        @else
            <!-- Блок, если нет привязанного бота -->
            <div class="bg-white shadow rounded-lg mb-8 overflow-hidden">
                <div class="px-4 py-5 sm:px-6 bg-indigo-600 text-white">
                    <h3 class="text-lg leading-6 font-medium">
                        <i class="fas fa-robot mr-2"></i> У вас нет привязанного бота
                    </h3>
                </div>
                <div class="px-4 py-5 sm:p-6">
                    <div class="flex items-start">
                        <div class="flex-shrink-0 text-indigo-500">
                            <i class="fas fa-info-circle text-2xl"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-gray-700">
                                Для редактирования FAQ вам нужно привязать бота к вашему аккаунту.
                            </p>
                            <div class="mt-4">
                                <a href="https://t.me/father_vpn_bot_t_bot"
                                   target="_blank"
                                   class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700">
                                    <i class="fab fa-telegram mr-2"></i> Перейти к Father VPN BOT-T
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if($hasModule)
            <!-- Редактор инструкций для модуля VPN -->
            <div class="bg-white shadow rounded-lg overflow-hidden">
                <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">
                        Редактирование инструкций для модуля VPN
                    </h3>
                    <p class="mt-1 max-w-2xl text-sm text-gray-500">
                        Настройте текст инструкций по настройке VPN для ваших клиентов
                    </p>
                </div>

                <div class="px-4 py-5 sm:p-6">
                    <form id="vpnInstructionsForm" action="{{ \App\Helpers\UrlHelper::personalRoute('personal.faq.vpn-instructions.update') }}"
                          method="POST">
                        @csrf

                        <div class="mb-6">
                            <label for="vpn_instructions" class="block text-sm font-medium text-gray-700 mb-2">
                                Текст инструкций VPN
                            </label>
                            <textarea id="vpn_instructions" name="instructions" rows="12"
                                      class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md"
                                      placeholder="Введите текст инструкций для VPN...">{{ $currentInstructions }}</textarea>
                            <p class="mt-2 text-sm text-gray-500">
                                Можно использовать HTML-разметку. Максимальная длина: 4000 символов.
                            </p>
                        </div>

                        <div class="flex items-center justify-between">
                            <button type="submit"
                                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                Сохранить изменения
                            </button>

                            <button type="button" onclick="confirmVpnReset()"
                                    class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                Сбросить к стандартному
                            </button>
                        </div>
                    </form>

                    <form id="resetVpnForm" action="{{ \App\Helpers\UrlHelper::personalRoute('personal.faq.vpn-instructions.reset') }}" method="POST"
                          class="hidden">
                        @csrf
                    </form>

                    <div class="mt-8 border-t border-gray-200 pt-6">
                        <h4 class="text-md font-medium text-gray-900 mb-2">Предпросмотр</h4>
                        <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                            <div class="vpn-instructions-preview">
                                {!! $currentInstructions !!}
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        @else
            <!-- Блок, если нет модуля -->
            <div class="bg-white shadow rounded-lg mb-8 overflow-hidden">
                <div class="px-4 py-5 sm:px-6 bg-blue-600 text-white">
                    <h3 class="text-lg leading-6 font-medium">
                        <i class="fas fa-puzzle-piece mr-2"></i> Модуль VPN не подключен
                    </h3>
                </div>
                <div class="px-4 py-5 sm:p-6">
                    <div class="flex items-start">
                        <div class="flex-shrink-0 text-blue-500">
                            <i class="fas fa-cube text-2xl"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-gray-700">
                                Для редактирования инструкций VPN вам нужно подключить модуль к вашему боту.
                            </p>
                            <div class="mt-4">
                                <a href="https://bot-t.com"
                                   target="_blank"
                                   class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700">
                                    <i class="fas fa-external-link-alt mr-2"></i> Перейти на BOT-T
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <script>
        function confirmReset() {
            if (confirm('Вы уверены, что хотите сбросить текст помощи к стандартному?')) {
                document.getElementById('resetForm').submit();
            }
        }

        function confirmVpnReset() {
            if (confirm('Вы уверены, что хотите сбросить инструкции VPN к стандартным?')) {
                document.getElementById('resetVpnForm').submit();
            }
        }
    </script>
@endsection

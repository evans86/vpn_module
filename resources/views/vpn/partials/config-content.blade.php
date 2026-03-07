    <div class="container mx-auto px-4 py-8 max-w-6xl">
        @php
            // Вычисляем все ссылки протоколов в начале, чтобы кнопка «Скопировать конфигурации» и data-атрибуты были корректны
            $allConfigLinks = [];
            $keysSource = (isset($newKeyFormattedKeys) && $newKeyFormattedKeys) ? $newKeyFormattedKeys : (isset($formattedKeys) ? $formattedKeys : []);
            $groupsSource = (!isset($newKeyFormattedKeys) && isset($formattedKeysGrouped) && is_array($formattedKeysGrouped) && !empty($formattedKeysGrouped)) ? $formattedKeysGrouped : [];
            if (!empty($groupsSource)) {
                foreach ($groupsSource as $g) {
                    foreach ($g['keys'] ?? [] as $k) {
                        $allConfigLinks[] = stripslashes($k['link'] ?? '');
                    }
                }
            } elseif (!empty($keysSource)) {
                foreach ($keysSource as $k) {
                    $allConfigLinks[] = stripslashes($k['link'] ?? '');
                }
            }
        @endphp
        @if(isset($isDemoMode) && $isDemoMode && app()->environment('local'))
            <!-- Demo Mode Banner -->
            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6 rounded-r-lg">
                <div class="flex items-start justify-between">
                    <div class="flex items-start flex-1">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-yellow-800">
                                <strong>Демо-режим:</strong> Вы просматриваете демо-версию страницы конфигурации. Данные не являются реальными.
                            </p>
                        </div>
                    </div>
                    <a href="{{ route('vpn.config.error') }}"
                       class="ml-4 inline-flex items-center px-3 py-1.5 border border-yellow-300 rounded-md text-xs font-medium text-yellow-800 bg-yellow-100 hover:bg-yellow-200 transition-colors whitespace-nowrap">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        Посмотреть ошибку
                    </a>
                </div>
            </div>
        @endif

        <!-- Key Replaced Notification -->
        @if(isset($replacedViolation) && $replacedViolation && isset($newKeyActivate) && $newKeyActivate && isset($newKeyFormattedKeys) && $newKeyFormattedKeys)
            <div class="bg-gradient-to-r from-green-600 to-emerald-700 rounded-2xl shadow-xl p-6 md:p-8 mb-8 text-white">
                <div class="flex items-start justify-between">
                    <div class="flex items-start flex-1">
                        <div class="flex-shrink-0">
                            <svg class="h-6 w-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="ml-4 flex-1">
                            <h3 class="text-xl font-bold mb-2">✅ Ключ был перевыпущен</h3>
                            <p class="text-white/90 mb-3">
                                Ваш ключ доступа был автоматически перевыпущен из-за превышения лимита подключений.
                                Ниже отображается новый ключ. Пожалуйста, обновите конфигурацию в вашем VPN-клиенте.
                            </p>
                            @if($replacedViolation->key_replaced_at)
                                <div class="text-sm text-white/80">
                                    Перевыпущен: {{ $replacedViolation->key_replaced_at->format('d.m.Y H:i') }}
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- Hero Section -->
        <div class="bg-gradient-to-r from-blue-600 to-indigo-700 rounded-2xl shadow-xl p-6 md:p-8 mb-8 text-white">
            <div class="flex flex-col md:flex-row items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl md:text-3xl font-bold mb-2">Конфигурация VPN</h1>
                    <p class="text-blue-100 text-sm md:text-base">Управление подключением и проверка качества сети</p>
                </div>
                <a href="{{ $netcheckUrl ?? route('netcheck.index') }}"
                   class="inline-flex items-center px-4 py-2 bg-white text-indigo-700 rounded-lg font-semibold hover:bg-blue-50 transition-colors shadow-lg">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Проверить качество сети
                </a>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-6 md:p-8" id="config-content-wrapper" data-all-config-links="{{ !empty($allConfigLinks) ? base64_encode(json_encode($allConfigLinks)) : '' }}">
            <!-- Action Buttons -->
            <div class="mb-8 flex flex-col sm:flex-row gap-3 flex-wrap">
                <button onclick="copyCurrentUrl()"
                        class="inline-flex items-center justify-center px-4 py-3 border-2 border-indigo-200 text-indigo-700 rounded-xl font-medium bg-indigo-50 hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all shadow-sm hover:shadow">
                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/>
                    </svg>
                    Скопировать ссылку
                </button>
                @if(!empty($allConfigLinks))
                <button onclick="copyAllConfigurations()"
                        class="inline-flex items-center justify-center px-4 py-3 border-2 border-green-200 text-green-700 rounded-xl font-medium bg-green-50 hover:bg-green-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-all shadow-sm hover:shadow"
                        title="Скопировать все протоколы в текстовом виде (по одному на строку)">
                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Скопировать конфигурации
                </button>
                @endif

                <button onclick="showUrlQR()"
                        class="inline-flex items-center justify-center px-4 py-3 border-2 border-gray-200 text-gray-700 rounded-xl font-medium bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-all shadow-sm hover:shadow">
                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9h14a2 2 0 012 2v2m0 0H3a2 2 0 01-2-2V9a2 2 0 012-2h14a2 2 0 012 2v2zm0 0h2a2 2 0 012 2v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4a2 2 0 012-2h2z"/>
                    </svg>
                    QR-код конфигурации
                </button>
            </div>

            <!-- Violations Section -->
            @if(isset($violations) && $violations->count() > 0)
                @php
                    $activeViolation = $violations->first();
                    $violationCount = $activeViolation->violation_count ?? 0;
                    $notificationsSent = $activeViolation->getNotificationsSentCount();
                @endphp
                <div class="mb-8">
                    <div class="bg-gradient-to-r {{ $violationCount >= 3 ? 'from-red-600 to-red-700' : ($violationCount >= 2 ? 'from-orange-600 to-orange-700' : 'from-yellow-600 to-yellow-700') }} rounded-2xl shadow-xl p-6 mb-8 text-white">
                        <div class="flex items-start justify-between">
                            <div class="flex items-start flex-1">
                                <div class="flex-shrink-0">
                                    <svg class="h-6 w-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                                <div class="ml-4 flex-1">
                                    <h3 class="text-xl font-bold mb-2">
                                        @if($violationCount >= 3)
                                            ⚠️ Критическое нарушение
                                        @elseif($violationCount >= 2)
                                            ⚠️ Повторное нарушение
                                        @else
                                            ⚠️ Обнаружено нарушение
                                        @endif
                                    </h3>
                                    <p class="text-white/90">
                                        @if($violationCount >= 3)
                                            Обнаружено нарушение лимита подключений. Ключ был автоматически перевыпущен.
                                        @elseif($violationCount >= 2)
                                            Обнаружено повторное нарушение лимита подключений. При следующем нарушении ключ будет перевыпущен.
                                        @else
                                            Обнаружено нарушение лимита подключений. Пожалуйста, используйте не более 3 одновременных подключений.
                                        @endif
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- User Information Section -->
            @php
                // Используем данные нового ключа, если он был перевыпущен
                $displayUserInfo = (isset($newKeyUserInfo) && $newKeyUserInfo) ? $newKeyUserInfo : $userInfo;
                $displayFormattedKeys = (isset($newKeyFormattedKeys) && $newKeyFormattedKeys) ? $newKeyFormattedKeys : $formattedKeys;
                // Группировка по локации: массив [ ['label' => ..., 'flag' => ..., 'keys' => [...]], ... ]
                $displayFormattedKeysGrouped = (isset($formattedKeysGrouped) && is_array($formattedKeysGrouped) && !empty($formattedKeysGrouped) && !isset($newKeyFormattedKeys)) ? $formattedKeysGrouped : [];

                // Все ссылки протоколов для кнопки «Скопировать конфигурации» (по одной на строку)
                $allConfigLinks = [];
                if (!empty($displayFormattedKeysGrouped)) {
                    foreach ($displayFormattedKeysGrouped as $g) {
                        foreach ($g['keys'] ?? [] as $k) {
                            $allConfigLinks[] = $k['link'];
                        }
                    }
                } elseif (!empty($displayFormattedKeys)) {
                    foreach ($displayFormattedKeys as $k) {
                        $allConfigLinks[] = $k['link'];
                    }
                }

                // Определяем какой ключ отображается (новый или старый)
                $displayedKey = (isset($newKeyActivate) && $newKeyActivate) ? $newKeyActivate : $keyActivate;

                // ВАЖНО: Проверяем статус ключа из БАЗЫ ДАННЫХ, а не из Marzban API!
                // Marzban может вернуть status='active' даже если ключ просрочен в Laravel БД
                $isKeyExpired = $displayedKey->status === \App\Models\KeyActivate\KeyActivate::EXPIRED;
                $isKeyActive = $displayedKey->status === \App\Models\KeyActivate\KeyActivate::ACTIVE;
                $isKeyPaid = $displayedKey->status === \App\Models\KeyActivate\KeyActivate::PAID;
            @endphp
            <div class="mb-8">
                <h2 class="text-2xl font-bold mb-6 text-gray-900 flex items-center">
                    <svg class="w-6 h-6 mr-2 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Информация о подключении
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-gradient-to-br from-gray-50 to-gray-100 p-6 rounded-xl border border-gray-200">
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <span class="text-gray-600 font-medium">Статус:</span>
                                @php
                                    // Определяем статус на основе БД Laravel, а не Marzban API
                                    $statusText = 'Неизвестен';
                                    $statusClass = 'bg-gray-100 text-gray-800 border border-gray-200';

                                    if ($isKeyExpired) {
                                        $statusText = '✗ Просрочен';
                                        $statusClass = 'bg-red-100 text-red-800 border border-red-200';
                                    } elseif ($isKeyActive) {
                                        $statusText = '✓ Активен';
                                        $statusClass = 'bg-green-100 text-green-800 border border-green-200';
                                    } elseif ($isKeyPaid) {
                                        $statusText = '⏳ Оплачен';
                                        $statusClass = 'bg-blue-100 text-blue-800 border border-blue-200';
                                    }
                                @endphp
                                <span class="px-3 py-1.5 rounded-full text-sm font-semibold {{ $statusClass }}">
                                    {{ $statusText }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-600 font-medium">Использовано:</span>
                                <span class="font-bold text-gray-900">{{ number_format($displayUserInfo['data_used'] / (1024*1024*1024), 2) }} GB</span>
                            </div>
                        </div>
                    </div>

                    <div class="bg-gradient-to-br from-blue-50 to-indigo-50 p-6 rounded-xl border border-blue-200">
                        <div class="space-y-4">
                            <div>
                                <span class="text-gray-600 font-medium block mb-2">Действует до:</span>
                                @if($displayUserInfo['expiration_date'])
                                    <span class="text-lg font-bold text-gray-900">{{ date('d.m.Y H:i', $displayUserInfo['expiration_date']) }}</span>
                                    @php
                                        $days = $displayUserInfo['days_remaining'];
                                        if ($days !== null) {
                                            $lastDigit = $days % 10;
                                            $lastTwoDigits = $days % 100;

                                            if ($lastTwoDigits >= 11 && $lastTwoDigits <= 19) {
                                                $daysText = 'дней';
                                            } elseif ($lastDigit === 1) {
                                                $daysText = 'день';
                                            } elseif ($lastDigit >= 2 && $lastDigit <= 4) {
                                                $daysText = 'дня';
                                            } else {
                                                $daysText = 'дней';
                                            }
                                        }
                                    @endphp
                                    @if($displayUserInfo['days_remaining'] !== null)
                                        <div class="text-sm text-indigo-600 font-medium mt-2">
                                            ⏱ Осталось {{ $displayUserInfo['days_remaining'] }} {{ $daysText }}
                                        </div>
                                    @endif
                                @else
                                    <span class="text-lg font-bold text-gray-500">Не указано</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Connection Keys Section -->
            @if($isKeyExpired)
                <!-- Ключ просрочен - показываем сообщение -->
                <div class="bg-gradient-to-r from-red-50 to-orange-50 border-2 border-red-200 rounded-2xl shadow-lg p-8 text-center">
                    <svg class="w-20 h-20 mx-auto text-red-500 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <h3 class="text-2xl font-bold text-red-700 mb-3">Срок действия ключа истек</h3>
                    <p class="text-red-600 text-lg mb-6">
                        Ключ доступа больше не активен. Для продолжения использования VPN необходимо приобрести новый ключ.
                    </p>
                    @if(isset($displayUserInfo['expiration_date']) && $displayUserInfo['expiration_date'])
                        <div class="text-sm text-red-500 mb-6">
                            Срок действия истек: <strong>{{ date('d.m.Y H:i', $displayUserInfo['expiration_date']) }}</strong>
                        </div>
                    @endif
                </div>
            @elseif($isKeyActive && $displayUserInfo['status'] === 'active')
                <!-- Ключ активен И Marzban тоже активен - показываем протоколы (по локации/серверу или плоский список) -->
                <div>
                    <h2 class="text-2xl font-bold mb-6 text-gray-900 flex items-center">
                        <svg class="w-6 h-6 mr-2 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                        Доступные протоколы
                    </h2>
                    @if(!empty($displayFormattedKeysGrouped))
                        {{-- Группировка по локации: флаг + полное название (Нидерланды, Россия и т.д.) --}}
                        <div class="space-y-4">
                            @foreach($displayFormattedKeysGrouped as $index => $group)
                                @php
                                    $groupLabel = $group['label'] ?? 'Сервер';
                                    $groupFlagCode = $group['flag_code'] ?? '';
                                    $groupKeys = $group['keys'] ?? [];
                                    $groupTargetId = 'config-location-' . $index;
                                @endphp
                                <div class="border-2 border-gray-200 rounded-xl overflow-hidden bg-white config-location-group"
                                     data-group-links="{{ base64_encode(json_encode(array_map('stripslashes', array_column($groupKeys, 'link')))) }}"
                                     data-group-label="{{ e($groupLabel) }}">
                                    <button type="button"
                                            class="config-location-toggle w-full flex items-center justify-between px-5 py-4 text-left bg-gradient-to-r from-gray-50 to-indigo-50 hover:from-indigo-50 hover:to-indigo-100 transition-colors"
                                            aria-expanded="true"
                                            data-target="{{ $groupTargetId }}">
                                        <span class="font-bold text-gray-900 flex items-center gap-2">
                                            <svg class="w-5 h-5 text-indigo-600 config-location-chevron flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                            </svg>
                                            @if($groupFlagCode)
                                                <img src="https://flagcdn.com/w40/{{ strtolower($groupFlagCode) }}.png" srcset="https://flagcdn.com/w80/{{ strtolower($groupFlagCode) }}.png 2x" width="28" height="21" alt="" class="rounded flex-shrink-0" loading="lazy">
                                            @endif
                                            <span>{{ $groupLabel }}</span>
                                        </span>
                                        <span class="flex items-center gap-2">
                                            <button type="button" onclick="event.stopPropagation(); copyGroupConfigurations(this);"
                                                    class="inline-flex items-center px-2.5 py-1.5 text-xs font-medium rounded-lg border border-green-200 text-green-700 bg-green-50 hover:bg-green-100 focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-green-500"
                                                    title="Скопировать протоколы этой группы в текстовом виде">
                                                <svg class="h-3.5 w-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                                Скопировать конфигурации
                                            </button>
                                            <span class="text-sm text-gray-500">{{ count($groupKeys) }} протокол(ов)</span>
                                        </span>
                                    </button>
                                    <div id="{{ $groupTargetId }}" class="config-location-body border-t border-gray-200">
                                        <div class="p-4 space-y-3">
                                            @foreach($groupKeys as $key)
                                                <div class="border border-gray-200 rounded-lg p-4 hover:border-indigo-300 hover:shadow transition-all bg-white">
                                                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                                                        <div class="flex items-center flex-grow">
                                                            <div class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-gradient-to-br from-indigo-500 to-blue-600 text-white font-bold text-sm mr-3 shadow">
                                                                {{ $key['icon'] }}
                                                            </div>
                                                            <div class="flex-grow min-w-0">
                                                                <div class="font-bold text-gray-900">{{ $key['protocol'] }}</div>
                                                                @if(!empty($key['connection_type']))
                                                                    <div class="text-sm text-indigo-600 font-medium mt-0.5">{{ $key['connection_type'] }}</div>
                                                                @endif
                                                            </div>
                                                        </div>
                                                        <div class="flex flex-wrap gap-2 md:ml-4">
                                                            <button onclick="copyToClipboard('{{ $key['link'] }}', '{{ $key['protocol'] }}')"
                                                                    class="inline-flex items-center justify-center px-3 py-2 border-2 border-indigo-200 text-indigo-700 rounded-lg font-semibold text-sm bg-indigo-50 hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all"
                                                                    title="Скопировать {{ $key['protocol'] }}">
                                                                <svg class="h-4 w-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/></svg>
                                                                Копировать
                                                            </button>
                                                            <button onclick="showQR('{{ $key['link'] }}', '{{ $key['protocol'] }}')"
                                                                    class="inline-flex items-center justify-center px-3 py-2 border-2 border-gray-200 text-gray-700 rounded-lg font-semibold text-sm bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-all">
                                                                <svg class="h-4 w-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9h14a2 2 0 012 2v2m0 0H3a2 2 0 01-2-2V9a2 2 0 012-2h14a2 2 0 012 2v2zm0 0h2a2 2 0 012 2v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4a2 2 0 012-2h2z"/></svg>
                                                                QR-код
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        {{-- Плоский список (один слот или перевыпущенный ключ) --}}
                        <div class="space-y-4">
                            @foreach($displayFormattedKeys as $key)
                                <div class="border-2 border-gray-200 rounded-xl p-5 hover:border-indigo-300 hover:shadow-lg transition-all bg-white">
                                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                                        <div class="flex items-center flex-grow">
                                            <div class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-gradient-to-br from-indigo-500 to-blue-600 text-white font-bold text-lg mr-4 shadow-md">
                                                {{ $key['icon'] }}
                                            </div>
                                            <div class="flex-grow min-w-0">
                                                <div class="font-bold text-lg text-gray-900">{{ $key['protocol'] }}</div>
                                                <div class="text-sm text-indigo-600 font-medium mt-1">{{ $key['connection_type'] }}</div>
                                            </div>
                                        </div>
                                        <div class="flex flex-col sm:flex-row gap-3 md:ml-4 w-full md:w-auto">
                                            <button onclick="copyToClipboard('{{ $key['link'] }}', '{{ $key['protocol'] }}')"
                                                    class="inline-flex items-center justify-center px-4 py-2.5 border-2 border-indigo-200 text-indigo-700 rounded-lg font-semibold bg-indigo-50 hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all shadow-sm hover:shadow"
                                                    title="Нажмите, чтобы скопировать конфигурацию {{ $key['protocol'] }}">
                                                <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                          d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/>
                                                </svg>
                                                Копировать
                                            </button>
                                            <button onclick="showQR('{{ $key['link'] }}', '{{ $key['protocol'] }}')"
                                                    class="inline-flex items-center justify-center px-4 py-2.5 border-2 border-gray-200 text-gray-700 rounded-lg font-semibold bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-all shadow-sm hover:shadow">
                                                <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9h14a2 2 0 012 2v2m0 0H3a2 2 0 01-2-2V9a2 2 0 012-2h14a2 2 0 012 2v2zm0 0h2a2 2 0 012 2v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4a2 2 0 012-2h2z"/>
                                                </svg>
                                                QR-код
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @else
                <div class="text-center py-12 bg-gray-50 rounded-xl border-2 border-dashed border-gray-300">
                    <svg class="w-16 h-16 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <p class="text-gray-600 font-medium text-lg">Подписка неактивна</p>
                    <p class="text-gray-500 text-sm mt-2">Ключи подключения недоступны</p>
                </div>
            @endif
        </div>
    </div>

    <!-- QR Code Modal -->
    <div id="qrModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
        <div class="bg-white p-6 md:p-8 rounded-2xl max-w-lg w-full mx-auto shadow-2xl">
            <div class="text-center mb-6">
                <h3 id="qrTitle" class="text-xl font-bold mb-2 text-gray-900">QR-код для подключения</h3>
                <p id="qrDescription" class="text-sm text-gray-500">Отсканируйте этот код в вашем VPN-клиенте</p>
            </div>
            <div id="qrcode" class="flex flex-col items-center justify-center mb-6 bg-gray-50 p-4 rounded-xl">
                <!-- QR код будет добавлен сюда -->
            </div>
            <div class="flex justify-end">
                <button onclick="closeQR()"
                        class="px-6 py-3 bg-gray-100 text-gray-700 rounded-lg font-semibold hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-colors">
                    Закрыть
                </button>
            </div>
        </div>
    </div>

    <div id="copy-notification" class="notification hidden"></div>

    @push('scripts')
        <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
        <script src="https://unpkg.com/qr-code-styling@1.5.0/lib/qr-code-styling.js"></script>
        <script>
            let copyNotificationTimeout;
            let currentQR = null;

            function showCopyNotification(message) {
                const notification = document.getElementById('copy-notification');
                notification.textContent = message;
                notification.classList.remove('hidden');

                if (copyNotificationTimeout) {
                    clearTimeout(copyNotificationTimeout);
                }

                copyNotificationTimeout = setTimeout(() => {
                    notification.classList.add('hidden');
                }, 3000);
            }

            function copyCurrentUrl() {
                const url = window.location.href;
                navigator.clipboard.writeText(url).then(() => {
                    showCopyNotification('✓ Ссылка скопирована в буфер обмена!');
                }).catch(() => {
                    alert('Не удалось скопировать ссылку.');
                });
            }

            function copyToClipboard(text, protocol) {
                navigator.clipboard.writeText(text).then(() => {
                    showCopyNotification(`✓ Конфигурация ${protocol} скопирована!`);
                }).catch(() => {
                    alert('Не удалось скопировать конфигурацию.');
                });
            }

            /** Скопировать все протоколы в буфер (по одному на строку). */
            function copyAllConfigurations() {
                const wrapper = document.getElementById('config-content-wrapper');
                if (!wrapper) return;
                const raw = wrapper.getAttribute('data-all-config-links');
                let links = [];
                try {
                    if (raw) links = JSON.parse(atob(raw));
                } catch (e) {
                    console.warn('copyAllConfigurations: invalid data', e);
                }
                if (links.length === 0) {
                    showCopyNotification('Нет протоколов для копирования.');
                    return;
                }
                const text = links.join('\n');
                navigator.clipboard.writeText(text).then(() => {
                    showCopyNotification('✓ Все конфигурации скопированы (' + links.length + ' протокол(ов))!');
                }).catch(() => {
                    alert('Не удалось скопировать конфигурации.');
                });
            }

            /** Скопировать протоколы одной группы (выпадающего списка) в буфер. */
            function copyGroupConfigurations(buttonEl) {
                const group = buttonEl.closest('.config-location-group');
                if (!group) return;
                const raw = group.getAttribute('data-group-links');
                const label = group.getAttribute('data-group-label') || 'Группа';
                let links = [];
                try {
                    if (raw) links = JSON.parse(atob(raw));
                } catch (e) {
                    console.warn('copyGroupConfigurations: invalid data', e);
                }
                if (links.length === 0) {
                    showCopyNotification('В этой группе нет протоколов.');
                    return;
                }
                const text = links.join('\n');
                navigator.clipboard.writeText(text).then(() => {
                    showCopyNotification('✓ Конфигурации «' + label + '» скопированы (' + links.length + ')!');
                }).catch(() => {
                    alert('Не удалось скопировать конфигурации.');
                });
            }

            function showQR(link, protocol = '') {
                if (!link) {
                    alert('Ссылка для QR-кода отсутствует или некорректна.');
                    return;
                }

                const qrcodeElement = document.getElementById('qrcode');
                const qrTitle = document.getElementById('qrTitle');
                const qrDescription = document.getElementById('qrDescription');

                qrcodeElement.innerHTML = '';

                qrTitle.textContent = protocol ? `QR-код для ${protocol}` : 'QR-код';
                qrDescription.textContent = protocol
                    ? 'Отсканируйте этот код в вашем VPN-клиенте'
                    : 'Отсканируйте этот код для быстрого доступа';

                const qrCode = new QRCodeStyling({
                    width: 300,
                    height: 300,
                    type: "svg",
                    data: link,
                    dotsOptions: {
                        color: "#4f46e5",
                        type: "rounded"
                    },
                    backgroundOptions: {
                        color: "#ffffff",
                    },
                    image: "",
                    imageOptions: {
                        crossOrigin: "anonymous",
                        margin: 10
                    }
                });

                qrCode.append(qrcodeElement);
                currentQR = qrCode;

                const qrModal = document.getElementById('qrModal');
                qrModal.classList.remove('hidden');
                qrModal.classList.add('flex');
            }

            function showUrlQR(url) {
                showQR(url, 'конфигурации');
            }

            function closeQR() {
                const qrModal = document.getElementById('qrModal');
                qrModal.classList.add('hidden');
                qrModal.classList.remove('flex');

                if (currentQR) {
                    const qrcodeElement = document.getElementById('qrcode');
                    qrcodeElement.innerHTML = '';
                    currentQR = null;
                }
            }
        </script>
    @endpush
    <style>
        .notification {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 16px 24px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
            font-size: 15px;
            font-weight: 500;
            z-index: 1000;
            opacity: 0;
            transform: translateY(20px) scale(0.95);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .notification.hidden {
            opacity: 0;
            transform: translateY(20px) scale(0.95);
        }

        .notification:not(.hidden) {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    </style>
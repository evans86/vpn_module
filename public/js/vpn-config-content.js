/**
 * Клиентская отрисовка блока конфигурации VPN (данные с GET /config/{token}/content в поле page).
 */
(function (global) {
    'use strict';

    function escapeHtml(s) {
        if (s == null || s === '') return '';
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function escapeAttr(s) {
        return escapeHtml(s).replace(/"/g, '&quot;');
    }

    function utf8ToB64(str) {
        try {
            return btoa(unescape(encodeURIComponent(String(str || ''))));
        } catch (e) {
            return '';
        }
    }

    function fmtDateTime(ts) {
        if (ts == null || ts === '') return '';
        var d = new Date(Number(ts) * 1000);
        if (isNaN(d.getTime())) return '';
        var pad = function (n) { return n < 10 ? '0' + n : String(n); };
        return pad(d.getDate()) + '.' + pad(d.getMonth() + 1) + '.' + d.getFullYear() + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
    }

    function daysWordRu(days) {
        var n = Math.abs(Number(days)) % 100;
        var n1 = n % 10;
        if (n > 10 && n < 20) return 'дней';
        if (n1 === 1) return 'день';
        if (n1 >= 2 && n1 <= 4) return 'дня';
        return 'дней';
    }

    function gbFromBytes(b) {
        var n = Number(b) || 0;
        return (n / (1024 * 1024 * 1024)).toFixed(2);
    }

    /** Панель действий: симметричная сетка 2×3 (на md+), сразу под блоком «Проверить качество сети». */
    function buildVpnActionToolbarHtml() {
        var c = 'inline-flex w-full h-full min-h-[4.25rem] items-center justify-center text-center px-2 py-2.5 sm:px-3 border-2 rounded-xl font-medium text-xs sm:text-sm leading-tight focus:outline-none focus:ring-2 focus:ring-offset-2 transition-all shadow-sm hover:shadow';
        return (
            '<div id="config-action-buttons" class="mb-8">' +
            '<div class="bg-white rounded-2xl shadow-lg p-4 sm:p-6 md:p-8 border border-gray-100">' +
            '<div class="grid grid-cols-2 md:grid-cols-3 gap-3 auto-rows-fr">' +
            '<button type="button" onclick="copyCurrentUrl()" class="' + c + ' border-indigo-200 text-indigo-700 bg-indigo-50 hover:bg-indigo-100 focus:ring-indigo-500">' +
            '<span class="inline-flex flex-col sm:flex-row items-center justify-center gap-1.5"><svg class="h-5 w-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/></svg><span>Скопировать ссылку</span></span></button>' +
            '<button type="button" onclick="showVpnPageLinkQr()" class="' + c + ' border-violet-200 text-violet-800 bg-violet-50 hover:bg-violet-100 focus:ring-violet-500">' +
            '<span class="inline-flex flex-col sm:flex-row items-center justify-center gap-1.5"><svg class="h-5 w-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9h14a2 2 0 012 2v2m0 0H3a2 2 0 01-2-2V9a2 2 0 012-2h14a2 2 0 012 2v2zm0 0h2a2 2 0 012 2v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4a2 2 0 012-2h2z"/></svg><span>QR ссылки</span></span></button>' +
            '<button type="button" id="vpn-btn-copy-plain" disabled onclick="copyAllConfigurations()" class="' + c + ' border-green-200 text-green-700 bg-green-50 hover:bg-green-100 focus:ring-green-500 opacity-50 cursor-not-allowed" title="Нет протоколов подключения">' +
            '<span class="inline-flex flex-col sm:flex-row items-center justify-center gap-1.5"><svg class="h-5 w-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg><span>Скопировать конфигурацию</span></span></button>' +
            '<button type="button" id="vpn-btn-qr-plain" disabled onclick="showQrPlainAllConfigs()" class="' + c + ' border-emerald-200 text-emerald-800 bg-emerald-50 hover:bg-emerald-100 focus:ring-emerald-500 opacity-50 cursor-not-allowed" title="Нет протоколов подключения">' +
            '<span class="inline-flex flex-col sm:flex-row items-center justify-center gap-1.5"><svg class="h-5 w-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg><span>QR-код конфигурации</span></span></button>' +
            '<button type="button" onclick="copyVpnConfigJson()" class="' + c + ' border-amber-200 text-amber-900 bg-amber-50 hover:bg-amber-100 focus:ring-amber-500" title="Профиль sing-box для Hiddify / sing-box (или ссылка подписки)">' +
            '<span class="inline-flex flex-col sm:flex-row items-center justify-center gap-1.5"><svg class="h-5 w-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg><span>Скопировать конфигурацию (JSON)</span></span></button>' +
            '<button type="button" onclick="showQrVpnConfigJson()" class="' + c + ' border-orange-200 text-orange-900 bg-orange-50 hover:bg-orange-100 focus:ring-orange-500" title="QR профиля sing-box или ссылки подписки">' +
            '<span class="inline-flex flex-col sm:flex-row items-center justify-center gap-1.5"><svg class="h-5 w-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/></svg><span>QR-код конфигурации (JSON)</span></span></button>' +
            '</div></div></div>'
        );
    }

    /**
     * @param {HTMLElement} container
     * @param {object} page — ответ API (поле page)
     */
    function renderVpnConfigPage(container, page) {
        if (!container || !page) return;

        global.__vpnConfigPage = page;

        var KS = (page.meta && page.meta.keyStatus) ? page.meta.keyStatus : { EXPIRED: 0, ACTIVE: 1, PAID: 2 };
        var keyActivate = page.keyActivate || {};
        var userInfo = page.userInfo || {};
        var formattedKeys = page.formattedKeys || [];
        var formattedKeysGrouped = page.formattedKeysGrouped || [];
        var netcheckUrl = page.netcheckUrl || '#';
        var violations = page.violations || [];
        var replacedViolation = page.replacedViolation;
        var newKeyActivate = page.newKeyActivate;
        var newKeyFormattedKeys = page.newKeyFormattedKeys;
        var newKeyUserInfo = page.newKeyUserInfo;

        var displayUserInfo = newKeyUserInfo || userInfo;
        var displayFormattedKeys = (newKeyFormattedKeys && newKeyFormattedKeys.length) ? newKeyFormattedKeys : formattedKeys;
        var displayFormattedKeysGrouped = (newKeyFormattedKeys && newKeyFormattedKeys.length) ? [] : formattedKeysGrouped;

        var displayedKey = newKeyActivate || keyActivate;
        var st = displayedKey && typeof displayedKey.status === 'number' ? displayedKey.status : -1;
        var isKeyExpired = st === KS.EXPIRED;
        var isKeyActive = st === KS.ACTIVE;
        var isKeyPaid = st === KS.PAID;

        var allLinks = [];
        if (displayFormattedKeysGrouped && displayFormattedKeysGrouped.length) {
            displayFormattedKeysGrouped.forEach(function (g) {
                (g.keys || []).forEach(function (k) {
                    if (k.link) allLinks.push(k.link);
                });
            });
        } else {
            (displayFormattedKeys || []).forEach(function (k) {
                if (k.link) allLinks.push(k.link);
            });
        }
        var allConfigAttr = allLinks.length ? btoa(unescape(encodeURIComponent(JSON.stringify(allLinks)))) : '';

        var parts = [];

        if (replacedViolation && newKeyActivate && newKeyFormattedKeys && newKeyFormattedKeys.length) {
            var repTime = replacedViolation.key_replaced_at
                ? fmtDateTime(replacedViolation.key_replaced_at)
                : '';
            parts.push(
                '<div class="bg-gradient-to-r from-green-600 to-emerald-700 rounded-2xl shadow-xl p-6 md:p-8 mb-8 text-white">' +
                '<div class="flex items-start justify-between">' +
                '<div class="flex items-start flex-1">' +
                '<div class="flex-shrink-0"><svg class="h-6 w-6 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg></div>' +
                '<div class="ml-4 flex-1">' +
                '<h3 class="text-xl font-bold mb-2">✅ Ключ был перевыпущен</h3>' +
                '<p class="text-white/90 mb-3">Ваш ключ доступа был автоматически перевыпущен из-за превышения лимита подключений. Ниже отображается новый ключ.</p>' +
                (repTime ? '<div class="text-sm text-white/80">Перевыпущен: ' + escapeHtml(repTime) + '</div>' : '') +
                '</div></div></div></div>'
            );
        }

        parts.push(
            '<div class="bg-gradient-to-r from-blue-600 to-indigo-700 rounded-2xl shadow-xl p-6 md:p-8 mb-8 text-white">' +
            '<div class="flex flex-col md:flex-row items-center justify-between gap-4">' +
            '<div><h1 class="text-2xl md:text-3xl font-bold mb-2">Конфигурация VPN</h1>' +
            '<p class="text-blue-100 text-sm md:text-base">Управление подключением и проверка качества сети</p></div>' +
            '<a href="' + escapeAttr(netcheckUrl) + '" class="inline-flex items-center px-4 py-2 bg-white text-indigo-700 rounded-lg font-semibold hover:bg-blue-50 transition-colors shadow-lg">' +
            '<svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Проверить качество сети</a>' +
            '</div></div>'
        );

        parts.push(buildVpnActionToolbarHtml());

        parts.push('<div class="bg-white rounded-2xl shadow-lg p-6 md:p-8" id="config-content-wrapper" data-all-config-links="' + escapeAttr(allConfigAttr) + '">');

        if (violations.length > 0) {
            var activeViolation = violations[0];
            var violationCount = activeViolation.violation_count != null ? Number(activeViolation.violation_count) : 0;
            var grad = violationCount >= 3 ? 'from-red-600 to-red-700' : (violationCount >= 2 ? 'from-orange-600 to-orange-700' : 'from-yellow-600 to-yellow-700');
            var title = violationCount >= 3 ? '⚠️ Критическое нарушение' : (violationCount >= 2 ? '⚠️ Повторное нарушение' : '⚠️ Обнаружено нарушение');
            var desc = violationCount >= 3
                ? 'Обнаружено нарушение лимита подключений. Ключ был автоматически перевыпущен.'
                : (violationCount >= 2
                    ? 'Обнаружено повторное нарушение лимита подключений. При следующем нарушении ключ будет перевыпущен.'
                    : 'Обнаружено нарушение лимита подключений. Пожалуйста, используйте не более 3 одновременных подключений.');
            parts.push(
                '<div class="mb-8"><div class="bg-gradient-to-r ' + grad + ' rounded-2xl shadow-xl p-6 mb-8 text-white">' +
                '<div class="flex items-start justify-between"><div class="flex items-start flex-1">' +
                '<div class="flex-shrink-0"><svg class="h-6 w-6 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg></div>' +
                '<div class="ml-4 flex-1"><h3 class="text-xl font-bold mb-2">' + title + '</h3><p class="text-white/90">' + escapeHtml(desc) + '</p></div></div></div></div></div>'
            );
        }

        var statusText = 'Неизвестен';
        var statusClass = 'bg-gray-100 text-gray-800 border border-gray-200';
        if (isKeyExpired) {
            statusText = '✗ Просрочен';
            statusClass = 'bg-red-100 text-red-800 border border-red-200';
        } else if (isKeyActive) {
            statusText = '✓ Активен';
            statusClass = 'bg-green-100 text-green-800 border border-green-200';
        } else if (isKeyPaid) {
            statusText = '⏳ Оплачен';
            statusClass = 'bg-blue-100 text-blue-800 border border-blue-200';
        }

        var exp = displayUserInfo.expiration_date;
        var daysRem = displayUserInfo.days_remaining;
        var daysLine = '';
        if (daysRem !== null && daysRem !== undefined) {
            daysLine = '<div class="text-sm text-indigo-600 font-medium mt-2">⏱ Осталось ' + escapeHtml(String(daysRem)) + ' ' + daysWordRu(daysRem) + '</div>';
        }

        parts.push(
            '<div class="mb-8">' +
            '<h2 class="text-2xl font-bold mb-6 text-gray-900 flex items-center">' +
            '<svg class="w-6 h-6 mr-2 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Информация о подключении</h2>' +
            '<div class="grid grid-cols-1 md:grid-cols-2 gap-6">' +
            '<div class="bg-gradient-to-br from-gray-50 to-gray-100 p-6 rounded-xl border border-gray-200">' +
            '<div class="space-y-4">' +
            '<div class="flex items-center justify-between"><span class="text-gray-600 font-medium">Статус:</span>' +
            '<span class="px-3 py-1.5 rounded-full text-sm font-semibold ' + statusClass + '">' + escapeHtml(statusText) + '</span></div>' +
            '<div class="flex items-center justify-between"><span class="text-gray-600 font-medium">Использовано:</span>' +
            '<span class="font-bold text-gray-900">' + escapeHtml(gbFromBytes(displayUserInfo.data_used)) + ' GB</span></div>' +
            '</div></div>' +
            '<div class="bg-gradient-to-br from-blue-50 to-indigo-50 p-6 rounded-xl border border-blue-200">' +
            '<div class="space-y-4"><div>' +
            '<span class="text-gray-600 font-medium block mb-2">Действует до:</span>'
        );
        if (exp) {
            parts.push(
                '<span class="text-lg font-bold text-gray-900">' + escapeHtml(fmtDateTime(exp)) + '</span>' + daysLine
            );
        } else {
            parts.push('<span class="text-lg font-bold text-gray-500">Не указано</span>');
        }
        parts.push('</div></div></div></div></div>');

        if (isKeyExpired) {
            parts.push(
                '<div class="bg-gradient-to-r from-red-50 to-orange-50 border-2 border-red-200 rounded-2xl shadow-lg p-8 text-center">' +
                '<svg class="w-20 h-20 mx-auto text-red-500 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>' +
                '<h3 class="text-2xl font-bold text-red-700 mb-3">Срок действия ключа истек</h3>' +
                '<p class="text-red-600 text-lg mb-6">Ключ доступа больше не активен. Для продолжения использования VPN необходимо приобрести новый ключ.</p>' +
                (displayUserInfo.expiration_date
                    ? '<div class="text-sm text-red-500 mb-6">Срок действия истек: <strong>' + escapeHtml(fmtDateTime(displayUserInfo.expiration_date)) + '</strong></div>'
                    : '') +
                '</div>'
            );
        } else if (isKeyActive && displayUserInfo.status === 'active') {
            parts.push(
                '<div><h2 class="text-2xl font-bold mb-6 text-gray-900 flex items-center">' +
                '<svg class="w-6 h-6 mr-2 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>Доступные протоколы</h2>'
            );

            if (displayFormattedKeysGrouped && displayFormattedKeysGrouped.length) {
                parts.push('<div class="space-y-4">');
                displayFormattedKeysGrouped.forEach(function (group, index) {
                    var groupLabel = group.label || 'Сервер';
                    var flagCode = (group.flag_code || '').toLowerCase();
                    var groupKeys = group.keys || [];
                    var targetId = 'config-location-' + index;
                    var linksArr = groupKeys.map(function (k) { return k.link || ''; });
                    var groupData = btoa(unescape(encodeURIComponent(JSON.stringify(linksArr))));

                    parts.push(
                        '<div class="border-2 border-gray-200 rounded-xl overflow-hidden bg-white config-location-group" data-group-links="' + escapeAttr(groupData) + '" data-group-label="' + escapeAttr(groupLabel) + '">' +
                        '<button type="button" class="config-location-toggle w-full flex items-center justify-between px-5 py-4 text-left bg-gradient-to-r from-gray-50 to-indigo-50 hover:from-indigo-50 hover:to-indigo-100 transition-colors" aria-expanded="true" data-target="' + escapeAttr(targetId) + '">' +
                        '<span class="font-bold text-gray-900 flex items-center gap-2">' +
                        '<svg class="w-5 h-5 text-indigo-600 config-location-chevron flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>'
                    );
                    if (flagCode) {
                        parts.push(
                            '<img src="https://flagcdn.com/w40/' + escapeAttr(flagCode) + '.png" srcset="https://flagcdn.com/w80/' + escapeAttr(flagCode) + '.png 2x" width="28" height="21" alt="" class="rounded flex-shrink-0" loading="lazy">'
                        );
                    }
                    parts.push('<span>' + escapeHtml(groupLabel) + '</span></span>');
                    parts.push(
                        '<span class="flex items-center gap-2">' +
                        '<span role="button" tabindex="0" class="config-group-copy-btn inline-flex items-center px-2.5 py-1.5 text-xs font-medium rounded-lg border border-green-200 text-green-700 bg-green-50 hover:bg-green-100 focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-green-500" title="Скопировать протоколы этой группы в текстовом виде">' +
                        '<svg class="h-3.5 w-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>Скопировать конфигурации</span>' +
                        '<span class="text-sm text-gray-500">' + groupKeys.length + ' протокол(ов)</span></span></button>' +
                        '<div id="' + escapeAttr(targetId) + '" class="config-location-body border-t border-gray-200">' +
                        '<div class="p-4 space-y-3">'
                    );
                    groupKeys.forEach(function (key) {
                        var linkB64 = utf8ToB64(key.link);
                        var prot = key.protocol || '';
                        parts.push(
                            '<div class="border border-gray-200 rounded-lg p-4 hover:border-indigo-300 hover:shadow transition-all bg-white">' +
                            '<div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">' +
                            '<div class="flex items-center flex-grow">' +
                            '<div class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-gradient-to-br from-indigo-500 to-blue-600 text-white font-bold text-sm mr-3 shadow">' + escapeHtml(key.icon || '') + '</div>' +
                            '<div class="flex-grow min-w-0">' +
                            '<div class="font-bold text-gray-900">' + escapeHtml(prot) + '</div>' +
                            (key.connection_type ? '<div class="text-sm text-indigo-600 font-medium mt-0.5">' + escapeHtml(key.connection_type) + '</div>' : '') +
                            '</div></div>' +
                            '<div class="flex flex-wrap gap-2 md:ml-4">' +
                            '<button type="button" data-copy-link-b64="' + escapeAttr(linkB64) + '" data-protocol="' + escapeAttr(prot) + '" class="inline-flex items-center justify-center px-3 py-2 border-2 border-indigo-200 text-indigo-700 rounded-lg font-semibold text-sm bg-indigo-50 hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all" title="Скопировать ' + escapeAttr(prot) + '">' +
                            '<svg class="h-4 w-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/></svg>Копировать</button>' +
                            '<button type="button" data-qr-link-b64="' + escapeAttr(linkB64) + '" data-protocol="' + escapeAttr(prot) + '" class="inline-flex items-center justify-center px-3 py-2 border-2 border-gray-200 text-gray-700 rounded-lg font-semibold text-sm bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-all">' +
                            '<svg class="h-4 w-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9h14a2 2 0 012 2v2m0 0H3a2 2 0 01-2-2V9a2 2 0 012-2h14a2 2 0 012 2v2zm0 0h2a2 2 0 012 2v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4a2 2 0 012-2h2z"/></svg>QR-код</button>' +
                            '</div></div></div>'
                        );
                    });
                    parts.push('</div></div></div>');
                });
                parts.push('</div>');
            } else {
                parts.push('<div class="space-y-4">');
                (displayFormattedKeys || []).forEach(function (key) {
                    var linkB64 = utf8ToB64(key.link);
                    var prot = key.protocol || '';
                    parts.push(
                        '<div class="border-2 border-gray-200 rounded-xl p-5 hover:border-indigo-300 hover:shadow-lg transition-all bg-white">' +
                        '<div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">' +
                        '<div class="flex items-center flex-grow">' +
                        '<div class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-gradient-to-br from-indigo-500 to-blue-600 text-white font-bold text-lg mr-4 shadow-md">' + escapeHtml(key.icon || '') + '</div>' +
                        '<div class="flex-grow min-w-0">' +
                        '<div class="font-bold text-lg text-gray-900">' + escapeHtml(prot) + '</div>' +
                        '<div class="text-sm text-indigo-600 font-medium mt-1">' + escapeHtml(key.connection_type || '') + '</div>' +
                        '</div></div>' +
                        '<div class="flex flex-col sm:flex-row gap-3 md:ml-4 w-full md:w-auto">' +
                        '<button type="button" data-copy-link-b64="' + escapeAttr(linkB64) + '" data-protocol="' + escapeAttr(prot) + '" class="inline-flex items-center justify-center px-4 py-2.5 border-2 border-indigo-200 text-indigo-700 rounded-lg font-semibold bg-indigo-50 hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all shadow-sm hover:shadow">' +
                        '<svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/></svg>Копировать</button>' +
                        '<button type="button" data-qr-link-b64="' + escapeAttr(linkB64) + '" data-protocol="' + escapeAttr(prot) + '" class="inline-flex items-center justify-center px-4 py-2.5 border-2 border-gray-200 text-gray-700 rounded-lg font-semibold bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-all shadow-sm hover:shadow">' +
                        '<svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9h14a2 2 0 012 2v2m0 0H3a2 2 0 01-2-2V9a2 2 0 012-2h14a2 2 0 012 2v2zm0 0h2a2 2 0 012 2v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4a2 2 0 012-2h2z"/></svg>QR-код</button>' +
                        '</div></div></div>'
                    );
                });
                parts.push('</div>');
            }
            parts.push('</div>');
        } else {
            parts.push(
                '<div class="text-center py-12 bg-gray-50 rounded-xl border-2 border-dashed border-gray-300">' +
                '<svg class="w-16 h-16 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>' +
                '<p class="text-gray-600 font-medium text-lg">Подписка неактивна</p>' +
                '<p class="text-gray-500 text-sm mt-2">Ключи подключения недоступны</p></div>'
            );
        }

        parts.push('</div>');

        parts.push(
            '<div id="qrModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">' +
            '<div class="bg-white p-6 md:p-8 rounded-2xl max-w-lg w-full mx-auto shadow-2xl">' +
            '<div class="text-center mb-6">' +
            '<h3 id="qrTitle" class="text-xl font-bold mb-2 text-gray-900">QR-код для подключения</h3>' +
            '<p id="qrDescription" class="text-sm text-gray-500">Отсканируйте этот код в вашем VPN-клиенте</p></div>' +
            '<div id="qrcode" class="flex flex-col items-center justify-center mb-6 bg-gray-50 p-4 rounded-xl"></div>' +
            '<div class="flex justify-end">' +
            '<button type="button" onclick="closeQR()" class="px-6 py-3 bg-gray-100 text-gray-700 rounded-lg font-semibold hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-colors">Закрыть</button>' +
            '</div></div></div>' +
            '<div id="copy-notification" class="notification hidden"></div>'
        );

        container.innerHTML =
            '<div class="container mx-auto px-4 py-8 max-w-6xl">' + parts.join('') + '</div>';

        if (typeof global.syncVpnToolbarProtoButtons === 'function') {
            global.syncVpnToolbarProtoButtons();
        }
    }

    global.renderVpnConfigPage = renderVpnConfigPage;
})(window);

@extends('layouts.public')

@section('title', 'Конфигурация VPN — VPN Service')
@section('header-subtitle', 'Профиль и ключи подключения')

@section('content')
    <div id="config-refresh-bar" class="container mx-auto px-4 pt-4 max-w-6xl">
        <div class="bg-indigo-100 border-2 border-indigo-300 rounded-xl p-4 flex flex-wrap items-center justify-between gap-4 shadow-sm">
            <div class="flex items-center gap-3">
                <span class="flex items-center justify-center w-10 h-10 rounded-full bg-indigo-600 text-white flex-shrink-0" title="Время обновления">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
                <div>
                    <div class="text-xs font-semibold uppercase tracking-wide text-indigo-600">Конфигурация обновлена</div>
                    <div id="config-last-updated" class="text-lg font-bold text-indigo-900">—</div>
                </div>
            </div>
            <button type="button" id="config-btn-refresh" class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 transition-colors shadow">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Обновить
            </button>
        </div>
    </div>
    <div id="config-progress-bar" class="container mx-auto px-4 pt-4 max-w-6xl hidden">
        <div class="bg-gradient-to-br from-indigo-50 via-white to-violet-50 border-2 border-indigo-200/90 rounded-2xl p-5 sm:p-6 shadow-md">
            <div id="config-progress-spinner" class="flex flex-col gap-3 w-full max-w-xl mx-auto" role="status" aria-live="polite" aria-label="Идёт обновление конфигурации">
                <div class="vpn-config-progress-track" aria-hidden="true">
                    <div id="config-progress-fill" class="vpn-config-progress-fill"></div>
                </div>
                <div class="text-center min-h-[3.25rem] flex items-start justify-center px-1">
                    <p id="config-progress-status" class="vpn-refresh-status-text text-sm sm:text-[0.9375rem] font-medium text-indigo-900/85 leading-relaxed max-w-md"></p>
                </div>
            </div>
            <div id="config-progress-error" class="hidden w-full max-w-xl mx-auto pt-4 border-t border-red-100 mt-2">
                <p class="text-sm font-medium text-red-600">Не удалось обновить данные.</p>
                <p id="config-progress-error-detail" class="text-xs text-gray-600 mt-1 hidden"></p>
                <button type="button" id="config-progress-retry" class="mt-3 px-4 py-2 text-sm font-medium bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 shadow-sm">Повторить</button>
            </div>
        </div>
    </div>
    <div id="config-content">
        <div class="container mx-auto px-4 py-8 max-w-6xl flex items-center justify-center min-h-[200px]">
            <div class="flex flex-col items-center gap-4 text-indigo-600">
                <svg class="animate-spin h-10 w-10" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <p class="text-sm font-medium">Загрузка конфигурации…</p>
            </div>
        </div>
    </div>
    <style>
        .notification { position: fixed; bottom: 24px; right: 24px; padding: 16px 24px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; border-radius: 12px; box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15); font-size: 15px; font-weight: 500; z-index: 1000; opacity: 0; transform: translateY(20px) scale(0.95); transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .notification.hidden { opacity: 0; transform: translateY(20px) scale(0.95); }
        .notification:not(.hidden) { opacity: 1; transform: translateY(0) scale(1); }
        /* Прогресс «Обновить»: indigo/violet/teal в стиле страницы */
        .vpn-config-progress-track {
            height: 8px;
            border-radius: 9999px;
            background: rgba(79, 70, 229, 0.12);
            overflow: hidden;
            box-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.08);
        }
        .vpn-config-progress-fill {
            height: 100%;
            width: 42%;
            border-radius: 9999px;
            background: linear-gradient(90deg, #4f46e5, #7c3aed 42%, #14b8a6);
            background-size: 200% 100%;
            animation: vpnConfigProgressSlide 1.35s ease-in-out infinite;
        }
        @keyframes vpnConfigProgressSlide {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(320%); }
        }
        .vpn-refresh-status-text {
            transition: opacity 0.35s ease, transform 0.35s ease;
            opacity: 1;
            transform: translateY(0);
        }
        .vpn-refresh-status-text.vpn-refresh-status--hidden {
            opacity: 0;
            transform: translateY(4px);
        }
    </style>
    <script src="https://unpkg.com/qr-code-styling@1.5.0/lib/qr-code-styling.js"></script>
    @php
        $_vpnCfgJsPath = public_path('js/vpn-config-content.js');
        $_vpnCfgJsVer = is_file($_vpnCfgJsPath) ? filemtime($_vpnCfgJsPath) : 1;
        $_vpnRefreshUiPath = public_path('js/vpn-config-refresh-ui.js');
        $_vpnRefreshUiVer = is_file($_vpnRefreshUiPath) ? filemtime($_vpnRefreshUiPath) : 1;
    @endphp
    <script src="{{ asset('js/vpn-config-refresh-ui.js') }}?v={{ $_vpnRefreshUiVer }}"></script>
    <script src="{{ asset('js/vpn-config-content.js') }}?v={{ $_vpnCfgJsVer }}"></script>
    <script>
    (function(){
        var copyNotificationTimeout, currentQR = null;
        function showCopyNotification(message) {
            var notification = document.getElementById('copy-notification');
            if (!notification) return;
            notification.textContent = message;
            notification.classList.remove('hidden');
            if (copyNotificationTimeout) clearTimeout(copyNotificationTimeout);
            copyNotificationTimeout = setTimeout(function() { notification.classList.add('hidden'); }, 3000);
        }
        window.getVpnCleanConfigCanonicalUrl = function() {
            try {
                var u = new URL(window.location.href);
                u.searchParams.delete('format');
                u.searchParams.delete('sub');
                return u.toString();
            } catch (e) {
                return window.location.href;
            }
        };
        window.copyCurrentUrl = function() {
            var url = window.getVpnCleanConfigCanonicalUrl();
            navigator.clipboard.writeText(url).then(function() { showCopyNotification('✓ Ссылка скопирована в буфер обмена!'); }).catch(function() { alert('Не удалось скопировать ссылку.'); });
        };
        window.__vpnConfigPage = null;
        window.getVpnConfigAllLinks = function() {
            var p = window.__vpnConfigPage;
            if (p && typeof p === 'object') {
                var newKeys = p.newKeyFormattedKeys;
                var fk = p.formattedKeys || [];
                var fg = p.formattedKeysGrouped || [];
                var useNew = newKeys && newKeys.length;
                var links = [];
                if (useNew) {
                    (newKeys || []).forEach(function(k) { if (k && k.link) links.push(k.link); });
                } else if (fg.length) {
                    fg.forEach(function(g) {
                        (g.keys || []).forEach(function(k) { if (k && k.link) links.push(k.link); });
                    });
                } else {
                    (fk || []).forEach(function(k) { if (k && k.link) links.push(k.link); });
                }
                return links;
            }
            var wrapper = document.getElementById('config-content-wrapper');
            if (!wrapper) return [];
            var raw = wrapper.getAttribute('data-all-config-links');
            var links = [];
            try { if (raw) links = JSON.parse(atob(raw)); } catch (e) { console.warn('getVpnConfigAllLinks: invalid data', e); }
            return links;
        };
        window.syncVpnToolbarProtoButtons = function() {
            var links = window.getVpnConfigAllLinks();
            var has = links.length > 0;
            var copyBtn = document.getElementById('vpn-btn-copy-plain');
            if (!copyBtn) return;
            copyBtn.disabled = !has;
            copyBtn.classList.toggle('opacity-50', !has);
            copyBtn.classList.toggle('cursor-not-allowed', !has);
            copyBtn.setAttribute('title', has ? 'Все строки конфигурации, по одной на строку' : 'Нет протоколов подключения');
        };
        window.copyAllConfigurations = function() {
            var links = window.getVpnConfigAllLinks();
            if (links.length === 0) { showCopyNotification('Нет протоколов для копирования.'); return; }
            navigator.clipboard.writeText(links.join('\n')).then(function() {
                showCopyNotification('✓ Конфигурация скопирована (' + links.length + ' протокол(ов))!');
            }).catch(function() { alert('Не удалось скопировать конфигурацию.'); });
        };
        window.showVpnPageLinkQr = function() {
            var u = typeof window.getVpnCleanConfigCanonicalUrl === 'function' ? window.getVpnCleanConfigCanonicalUrl() : window.location.href;
            window.showQR(u, 'ссылки');
        };
        window.copyToClipboard = function(text, protocol) {
            navigator.clipboard.writeText(text).then(function() { showCopyNotification('✓ Конфигурация ' + (protocol || '') + ' скопирована!'); }).catch(function() { alert('Не удалось скопировать конфигурацию.'); });
        };
        window.copyGroupConfigurations = function(buttonEl) {
            var group = buttonEl.closest('.config-location-group');
            if (!group) return;
            var raw = group.getAttribute('data-group-links');
            var label = group.getAttribute('data-group-label') || 'Группа';
            var links = [];
            try { if (raw) links = JSON.parse(atob(raw)); } catch (e) { console.warn('copyGroupConfigurations: invalid data', e); }
            if (links.length === 0) { showCopyNotification('В этой группе нет протоколов.'); return; }
            navigator.clipboard.writeText(links.join('\n')).then(function() { showCopyNotification('✓ Конфигурации «' + label + '» скопированы (' + links.length + ')!'); }).catch(function() { alert('Не удалось скопировать конфигурации.'); });
        };
        window.showUrlQR = function(url) {
            var u = url || (typeof window.getVpnCleanConfigCanonicalUrl === 'function' ? window.getVpnCleanConfigCanonicalUrl() : window.location.href);
            window.showQR(u, 'ссылки');
        };
        window.showQR = function(link, protocol) {
            if (!link) { alert('Ссылка для QR-кода отсутствует или некорректна.'); return; }
            var qrcodeElement = document.getElementById('qrcode');
            var qrTitle = document.getElementById('qrTitle');
            var qrDescription = document.getElementById('qrDescription');
            if (!qrcodeElement || typeof QRCodeStyling === 'undefined') { alert('QR-библиотека не загружена.'); return; }
            qrcodeElement.innerHTML = '';
            if (qrTitle) qrTitle.textContent = protocol ? 'QR-код: ' + protocol : 'QR-код';
            if (qrDescription) {
                qrDescription.textContent = 'Отсканируйте в телефоне или VPN-клиенте';
            }
            var dataStr = String(link);
            var len = dataStr.length;
            var side = len > 800 ? 400 : 300;
            var opts = {
                width: side,
                height: side,
                type: len > 600 ? 'canvas' : 'svg',
                data: dataStr,
                dotsOptions: { color: '#4f46e5', type: 'rounded' },
                backgroundOptions: { color: '#ffffff' },
                image: '',
                imageOptions: { crossOrigin: 'anonymous', margin: 10 },
                qrOptions: { errorCorrectionLevel: len > 400 ? 'L' : 'M' }
            };
            try {
                var qrCode = new QRCodeStyling(opts);
                qrCode.append(qrcodeElement);
                currentQR = qrCode;
            } catch (err) {
                console.error(err);
                alert('Не удалось построить QR-код.');
                return;
            }
            var qrModal = document.getElementById('qrModal');
            if (qrModal) { qrModal.classList.remove('hidden'); qrModal.classList.add('flex'); }
        };
        window.closeQR = function() {
            var qrModal = document.getElementById('qrModal');
            if (qrModal) { qrModal.classList.add('hidden'); qrModal.classList.remove('flex'); }
            if (currentQR) {
                var qrcodeElement = document.getElementById('qrcode');
                if (qrcodeElement) qrcodeElement.innerHTML = '';
                currentQR = null;
            }
        };
    })();
    </script>
    <script>
    (function(){
        window.__vpnConfigDebug = @json((bool) config('app.debug'));
        var contentUrl = @json($contentUrl);
        var refreshUrl = @json($refreshUrl);
        var contentEl = document.getElementById('config-content');
        var refreshBar = document.getElementById('config-refresh-bar');
        var progressBar = document.getElementById('config-progress-bar');
        var lastUpdatedEl = document.getElementById('config-last-updated');
        var btnRefresh = document.getElementById('config-btn-refresh');
        var statusTextEl = document.getElementById('config-progress-status');
        var spinnerBlock = document.getElementById('config-progress-spinner');
        var errBlock = document.getElementById('config-progress-error');
        var retryBtn = document.getElementById('config-progress-retry');

        /** Короткий текст для пользователя. Подробности — storage/logs и консоль браузера при APP_DEBUG=true. */
        function humanizeRefreshError(raw, httpStatus) {
            if (window.__vpnConfigDebug) {
                try { console.warn('[VPN конфиг: обновить]', { httpStatus: httpStatus, raw: raw != null ? String(raw).slice(0, 1200) : '' }); } catch (e) {}
            }
            var s = String(raw || '').trim();
            if (httpStatus === 504 || httpStatus === 502 || httpStatus === 503) {
                return 'Сервер долго не отвечал. Попробуйте через минуту или обновите страницу.';
            }
            if (httpStatus === 0) {
                return 'Проверьте подключение к интернету и попробуйте снова.';
            }
            if (!s) {
                if (httpStatus >= 500) return 'Сервис временно недоступен. Попробуйте позже.';
                if (httpStatus === 404) return 'Данные не найдены. Обновите страницу.';
                return 'Попробуйте позже или обновите страницу.';
            }
            if (/504|502|503|Gateway|Time-out|timeout/i.test(s)) {
                return 'Сервер долго не отвечал. Попробуйте через минуту или обновите страницу.';
            }
            if (/^\s*</.test(s) || /<html/i.test(s)) {
                return 'Сервис временно недоступен. Попробуйте позже.';
            }
            if (/nginx|php-fpm|proxy_|fastcgi|socket/i.test(s)) {
                return 'Попробуйте позже или обновите страницу.';
            }
            if (s.length > 220) return s.slice(0, 220) + '…';
            return s;
        }

        function showErrorState(msg, httpStatus) {
            if (spinnerBlock) spinnerBlock.classList.add('hidden');
            if (errBlock) errBlock.classList.remove('hidden');
            setRefreshErrorMessage(msg ? humanizeRefreshError(msg, httpStatus) : '');
        }
        function showSpinnerState() {
            if (spinnerBlock) spinnerBlock.classList.remove('hidden');
            if (errBlock) errBlock.classList.add('hidden');
            setRefreshErrorMessage('');
        }

        function setContentError(msg) {
            if (!contentEl) return;
            contentEl.innerHTML = '<div class="container mx-auto px-4 py-8 max-w-6xl"><div class="bg-red-50 border border-red-200 rounded-xl p-6 text-center"><p class="text-red-700">' + (msg || 'Не удалось загрузить конфигурацию.') + '</p></div></div>';
        }

        function parseJsonResponse(r) {
            var st = r.status;
            var ct = (r.headers.get('Content-Type') || '').toLowerCase();
            if (ct.indexOf('application/json') !== -1 || ct.indexOf('+json') !== -1) {
                return r.json().then(function(d) { return { ok: r.ok, status: st, data: d }; }).catch(function() { return { ok: false, status: st, data: {} }; });
            }
            return r.text().then(function(t) { return { ok: false, status: st, data: { message: t ? String(t).slice(0, 200) : '' } }; });
        }

        function setRefreshErrorMessage(msg) {
            var el = document.getElementById('config-progress-error-detail');
            if (!el) return;
            if (msg) {
                el.textContent = msg;
                el.classList.remove('hidden');
            } else {
                el.textContent = '';
                el.classList.add('hidden');
            }
        }

        function vpnB64ToUtf8(b64) {
            if (!b64) return '';
            try {
                return decodeURIComponent(Array.prototype.map.call(atob(b64), function(c) {
                    return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2);
                }).join(''));
            } catch (err) { return ''; }
        }

        // Один раз вешаем делегирование кликов (работает и для подгруженного контента)
        if (contentEl) contentEl.addEventListener('click', delegateConfigContent);

        // Первая загрузка контента (данные page — JSON, разметка на клиенте)
        fetch(contentUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(parseJsonResponse)
            .then(function(res) {
                if (res.ok && res.data && res.data.success) {
                    window.__vpnConfigPage = res.data.page || null;
                    if (contentEl && res.data.page && typeof window.renderVpnConfigPage === 'function') {
                        window.renderVpnConfigPage(contentEl, res.data.page);
                    } else if (contentEl && res.data.html) {
                        contentEl.innerHTML = res.data.html;
                    }
                    if (typeof window.syncVpnToolbarProtoButtons === 'function') window.syncVpnToolbarProtoButtons();
                    if (res.data.lastUpdated && lastUpdatedEl) lastUpdatedEl.textContent = res.data.lastUpdated;
                    if (res.data.lastUpdated) window.__vpnLastConfigUpdatedLabel = res.data.lastUpdated;
                    if (typeof res.data.lastUpdatedEpoch === 'number') window.__vpnLastConfigEpoch = res.data.lastUpdatedEpoch;
                } else {
                    setContentError(res.data && res.data.message ? res.data.message : null);
                }
            })
            .catch(function() { setContentError(); });

        function delegateConfigContent(e) {
            var groupCopy = e.target.closest('.config-group-copy-btn');
            if (groupCopy) {
                e.preventDefault();
                e.stopPropagation();
                if (typeof copyGroupConfigurations === 'function') copyGroupConfigurations(groupCopy);
                return;
            }
            var copyBtn = e.target.closest('[data-copy-link-b64]');
            if (copyBtn) {
                e.preventDefault();
                var link = vpnB64ToUtf8(copyBtn.getAttribute('data-copy-link-b64'));
                var protocol = copyBtn.getAttribute('data-protocol') || '';
                if (link && typeof copyToClipboard === 'function') copyToClipboard(link, protocol);
                return;
            }
            var qrBtn = e.target.closest('[data-qr-link-b64]');
            if (qrBtn) {
                e.preventDefault();
                var linkQr = vpnB64ToUtf8(qrBtn.getAttribute('data-qr-link-b64'));
                var prot = qrBtn.getAttribute('data-protocol') || '';
                if (linkQr && typeof showQR === 'function') showQR(linkQr, prot);
                return;
            }
            delegateLocationToggle(e);
        }

        function delegateLocationToggle(e) {
            var btn = e.target.closest('.config-location-toggle');
            if (!btn) return;
            e.preventDefault();
            var targetId = btn.getAttribute('data-target');
            var body = document.getElementById(targetId);
            var chevron = btn.querySelector('.config-location-chevron');
            if (body && body.classList.contains('hidden')) {
                body.classList.remove('hidden');
                if (chevron) chevron.style.transform = 'rotate(0deg)';
                btn.setAttribute('aria-expanded', 'true');
            } else if (body) {
                body.classList.add('hidden');
                if (chevron) chevron.style.transform = 'rotate(-90deg)';
                btn.setAttribute('aria-expanded', 'false');
            }
        }

        /**
         * Тихая подгрузка /content после фоновой синхронизации Marzban.
         * Если lastUpdated изменился относительно ответа «Обновить» — БД уже перезаписана с панели.
         */
        function refetchVpnConfigContentSilent() {
            fetch(contentUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(parseJsonResponse)
                .then(function(res) {
                    if (res.data && res.data.success && res.data.page && contentEl && typeof window.renderVpnConfigPage === 'function') {
                        var newLabel = res.data.lastUpdated ? String(res.data.lastUpdated) : '';
                        var newEpoch = res.data.lastUpdatedEpoch;
                        var awaiting = window.__vpnAwaitingSyncRefresh === true;
                        var dataChanged = false;
                        if (awaiting) {
                            var bEp = window.__vpnLastUpdatedEpochBeforeSync;
                            if (typeof newEpoch === 'number' && typeof bEp === 'number') {
                                dataChanged = newEpoch !== bEp;
                            } else {
                                var baseline = window.__vpnLastUpdatedBeforeSync;
                                dataChanged = baseline !== undefined && newLabel && newLabel !== String(baseline);
                            }
                        }
                        if (dataChanged) {
                            showCopyNotification('✓ Конфигурация обновлена с сервера панели');
                            window.__vpnAwaitingSyncRefresh = false;
                        }
                        window.__vpnConfigPage = res.data.page;
                        window.renderVpnConfigPage(contentEl, res.data.page);
                        if (typeof window.syncVpnToolbarProtoButtons === 'function') window.syncVpnToolbarProtoButtons();
                        if (res.data.lastUpdated && lastUpdatedEl) lastUpdatedEl.textContent = res.data.lastUpdated;
                        if (newLabel) window.__vpnLastConfigUpdatedLabel = newLabel;
                        if (typeof newEpoch === 'number') window.__vpnLastConfigEpoch = newEpoch;
                    }
                })
                .catch(function() { /* игнорируем: пользователь уже видит данные с первого ответа */ });
        }

        var _vpnRefreshFollowupTimers = [];
        function clearVpnRefreshFollowups() {
            _vpnRefreshFollowupTimers.forEach(function(id) { clearTimeout(id); });
            _vpnRefreshFollowupTimers = [];
        }

        function runRefresh() {
            if (!progressBar || !refreshBar) return;
            clearVpnRefreshFollowups();
            window.__vpnAwaitingSyncRefresh = false;
            window.__vpnLastUpdatedBeforeSync = undefined;
            window.__vpnLastUpdatedEpochBeforeSync = undefined;
            progressBar.classList.remove('hidden');
            refreshBar.classList.add('hidden');
            showSpinnerState();

            var statusRotation = null;
            if (typeof window.vpnConfigRefreshStartStatusRotation === 'function' && statusTextEl) {
                statusRotation = window.vpnConfigRefreshStartStatusRotation(statusTextEl);
            }

            function stopStatusRotation() {
                if (statusRotation && typeof statusRotation.stop === 'function') {
                    statusRotation.stop();
                }
                statusRotation = null;
            }

            fetch(refreshUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(parseJsonResponse)
                .then(function(res) {
                    stopStatusRotation();
                    if (res.data && res.data.success) {
                        window.__vpnConfigPage = res.data.page || null;
                        if (contentEl && res.data.page && typeof window.renderVpnConfigPage === 'function') {
                            window.renderVpnConfigPage(contentEl, res.data.page);
                        } else if (contentEl && res.data.html) {
                            contentEl.innerHTML = res.data.html;
                        }
                        if (typeof window.syncVpnToolbarProtoButtons === 'function') window.syncVpnToolbarProtoButtons();
                        if (res.data.lastUpdated && lastUpdatedEl) lastUpdatedEl.textContent = res.data.lastUpdated;
                        if (res.data.lastUpdated) window.__vpnLastConfigUpdatedLabel = res.data.lastUpdated;
                        if (typeof res.data.lastUpdatedEpoch === 'number') window.__vpnLastConfigEpoch = res.data.lastUpdatedEpoch;
                        progressBar.classList.add('hidden');
                        refreshBar.classList.remove('hidden');
                        if (res.data.syncPending) {
                            window.__vpnAwaitingSyncRefresh = true;
                            window.__vpnLastUpdatedBeforeSync = res.data.lastUpdated ? String(res.data.lastUpdated) : '';
                            window.__vpnLastUpdatedEpochBeforeSync = (typeof res.data.lastUpdatedEpoch === 'number')
                                ? res.data.lastUpdatedEpoch
                                : undefined;
                            _vpnRefreshFollowupTimers.push(setTimeout(refetchVpnConfigContentSilent, 4500));
                            _vpnRefreshFollowupTimers.push(setTimeout(refetchVpnConfigContentSilent, 11000));
                            _vpnRefreshFollowupTimers.push(setTimeout(refetchVpnConfigContentSilent, 25000));
                        }
                    } else {
                        var errMsg = (res.data && res.data.message) ? String(res.data.message) : '';
                        if (!errMsg && !res.ok) errMsg = '';
                        showErrorState(errMsg, res.status);
                    }
                })
                .catch(function() {
                    stopStatusRotation();
                    showErrorState('', 0);
                });
        }

        if (btnRefresh) btnRefresh.addEventListener('click', runRefresh);
        if (retryBtn) retryBtn.addEventListener('click', runRefresh);
    })();
    </script>
@endsection

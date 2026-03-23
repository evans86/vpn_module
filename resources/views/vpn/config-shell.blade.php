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
        <div class="vpn-config-progress-card relative overflow-hidden bg-gradient-to-br from-indigo-50 via-white to-violet-50 border-2 border-indigo-200/90 rounded-2xl p-5 sm:p-6 shadow-md">
            <div class="pointer-events-none absolute inset-0 overflow-hidden rounded-2xl" aria-hidden="true">
                <div class="vpn-config-blob vpn-config-blob--1"></div>
                <div class="vpn-config-blob vpn-config-blob--2"></div>
            </div>
            <div id="config-progress-spinner" class="relative z-10 flex flex-col gap-4 w-full max-w-xl mx-auto" role="status" aria-live="polite" aria-busy="true" aria-label="Идёт обновление конфигурации">
                <div class="vpn-config-orb-visual flex justify-center pt-1 pb-0" aria-hidden="true">
                    <div class="vpn-config-orb-ring">
                        <span class="vpn-config-orb-wave"></span>
                        <span class="vpn-config-orb-wave vpn-config-orb-wave--b"></span>
                        <div class="vpn-config-orb-core">
                            <svg class="w-7 h-7 text-white drop-shadow-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                            </svg>
                        </div>
                    </div>
                </div>
                <div class="vpn-config-progress-track" aria-hidden="true">
                    <div id="config-progress-fill" class="vpn-config-progress-fill"></div>
                </div>
                <div class="text-center min-h-[3.25rem] flex flex-col items-center justify-start gap-3 px-1">
                    <p id="config-progress-status" class="vpn-refresh-status-text text-sm sm:text-[0.9375rem] font-medium text-indigo-900/85 leading-relaxed max-w-md"></p>
                    <div class="vpn-config-dots" aria-hidden="true">
                        <span></span><span></span><span></span>
                    </div>
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
        /* Декоративные «облака» на фоне карточки */
        .vpn-config-blob {
            position: absolute;
            border-radius: 50%;
            filter: blur(40px);
            opacity: 0.55;
        }
        .vpn-config-blob--1 {
            width: 200px;
            height: 200px;
            top: -60px;
            right: -40px;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.45) 0%, transparent 72%);
            animation: vpnBlobDrift 12s ease-in-out infinite;
        }
        .vpn-config-blob--2 {
            width: 180px;
            height: 180px;
            bottom: -70px;
            left: -50px;
            background: radial-gradient(circle, rgba(20, 184, 166, 0.35) 0%, transparent 72%);
            animation: vpnBlobDrift 14s ease-in-out infinite reverse;
        }
        @keyframes vpnBlobDrift {
            0%, 100% { transform: translate(0, 0) scale(1); }
            33% { transform: translate(-12px, 8px) scale(1.05); }
            66% { transform: translate(10px, -6px) scale(0.96); }
        }
        /* Центральный «орб» с пульсирующими кольцами */
        .vpn-config-orb-ring {
            position: relative;
            width: 76px;
            height: 76px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .vpn-config-orb-wave {
            position: absolute;
            inset: 0;
            border-radius: 50%;
            border: 2px solid rgba(99, 102, 241, 0.45);
            animation: vpnOrbWave 2.4s ease-out infinite;
        }
        .vpn-config-orb-wave--b {
            animation-delay: 1.1s;
            border-color: rgba(20, 184, 166, 0.4);
        }
        @keyframes vpnOrbWave {
            0% { transform: scale(0.65); opacity: 0.85; }
            100% { transform: scale(1.35); opacity: 0; }
        }
        .vpn-config-orb-core {
            position: relative;
            width: 52px;
            height: 52px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #6366f1 0%, #7c3aed 50%, #14b8a6 100%);
            background-size: 200% 200%;
            box-shadow:
                0 6px 24px rgba(99, 102, 241, 0.45),
                inset 0 1px 0 rgba(255, 255, 255, 0.25);
            animation: vpnOrbGlow 3.5s ease-in-out infinite;
        }
        @keyframes vpnOrbGlow {
            0%, 100% { background-position: 0% 50%; transform: scale(1) rotate(0deg); }
            50% { background-position: 100% 50%; transform: scale(1.06) rotate(4deg); }
        }
        /* Три прыгающие точки под текстом */
        .vpn-config-dots {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            height: 10px;
        }
        .vpn-config-dots span {
            display: block;
            width: 7px;
            height: 7px;
            border-radius: 9999px;
            background: linear-gradient(180deg, #6366f1, #8b5cf6);
            animation: vpnDotBounce 1.15s ease-in-out infinite;
        }
        .vpn-config-dots span:nth-child(2) { animation-delay: 0.14s; }
        .vpn-config-dots span:nth-child(3) { animation-delay: 0.28s; }
        @keyframes vpnDotBounce {
            0%, 80%, 100% { transform: translateY(0); opacity: 0.55; }
            40% { transform: translateY(-6px); opacity: 1; }
        }
        @media (prefers-reduced-motion: reduce) {
            .vpn-config-blob--1,
            .vpn-config-blob--2,
            .vpn-config-orb-wave,
            .vpn-config-orb-wave--b,
            .vpn-config-orb-core,
            .vpn-config-dots span,
            .vpn-config-progress-fill {
                animation: none !important;
            }
            .vpn-config-orb-core { transform: none; }
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

        var _vpnSyncPollTimer = null;
        function clearVpnSyncPolling() {
            if (_vpnSyncPollTimer) {
                clearInterval(_vpnSyncPollTimer);
                _vpnSyncPollTimer = null;
            }
        }

        function runRefresh() {
            if (!progressBar || !refreshBar) return;
            if (btnRefresh && btnRefresh.disabled) return;

            clearVpnSyncPolling();
            var refreshSessionClosed = false;
            var syncPollAttempts = 0;
            var MAX_SYNC_POLLS = 45;
            var POLL_INTERVAL_MS = 2000;

            function closeRefreshUi(notifyMsg) {
                if (refreshSessionClosed) return;
                refreshSessionClosed = true;
                clearVpnSyncPolling();
                if (statusRotation && typeof statusRotation.stop === 'function') {
                    statusRotation.stop();
                }
                statusRotation = null;
                if (btnRefresh) btnRefresh.disabled = false;
                if (spinnerBlock) spinnerBlock.setAttribute('aria-busy', 'false');
                progressBar.classList.add('hidden');
                refreshBar.classList.remove('hidden');
                if (notifyMsg) showCopyNotification(notifyMsg);
            }

            function resetUiBeforeRequest() {
                window.__vpnLastUpdatedBeforeSync = undefined;
                window.__vpnLastUpdatedEpochBeforeSync = undefined;
                progressBar.classList.remove('hidden');
                refreshBar.classList.add('hidden');
                showSpinnerState();
                if (spinnerBlock) spinnerBlock.setAttribute('aria-busy', 'true');
                if (btnRefresh) btnRefresh.disabled = true;
            }

            resetUiBeforeRequest();

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

            function onRefreshHttpError(errMsg, httpStatus) {
                stopStatusRotation();
                clearVpnSyncPolling();
                refreshSessionClosed = true;
                if (btnRefresh) btnRefresh.disabled = false;
                if (spinnerBlock) spinnerBlock.setAttribute('aria-busy', 'false');
                showErrorState(errMsg, httpStatus);
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

                        if (!res.data.syncPending) {
                            closeRefreshUi(null);
                            return;
                        }

                        var baselineEpoch = (typeof res.data.lastUpdatedEpoch === 'number') ? res.data.lastUpdatedEpoch : undefined;
                        var baselineLabel = res.data.lastUpdated ? String(res.data.lastUpdated) : '';

                        if (typeof window.vpnConfigRefreshStartSyncPollingRotation === 'function' && statusTextEl) {
                            statusRotation = window.vpnConfigRefreshStartSyncPollingRotation(statusTextEl);
                        }

                        function applyContentPayload(payload) {
                            if (!payload || !payload.page || !contentEl || typeof window.renderVpnConfigPage !== 'function') return;
                            window.__vpnConfigPage = payload.page;
                            window.renderVpnConfigPage(contentEl, payload.page);
                            if (typeof window.syncVpnToolbarProtoButtons === 'function') window.syncVpnToolbarProtoButtons();
                            if (payload.lastUpdated && lastUpdatedEl) lastUpdatedEl.textContent = payload.lastUpdated;
                            if (payload.lastUpdated) window.__vpnLastConfigUpdatedLabel = payload.lastUpdated;
                            if (typeof payload.lastUpdatedEpoch === 'number') window.__vpnLastConfigEpoch = payload.lastUpdatedEpoch;
                        }

                        function isDataNewerThanBaseline(payload) {
                            if (!payload) return false;
                            var newEpoch = payload.lastUpdatedEpoch;
                            var newLabel = payload.lastUpdated ? String(payload.lastUpdated) : '';
                            if (typeof newEpoch === 'number' && typeof baselineEpoch === 'number') {
                                return newEpoch !== baselineEpoch;
                            }
                            return baselineLabel !== undefined && newLabel && newLabel !== String(baselineLabel);
                        }

                        function pollOnce() {
                            if (refreshSessionClosed) return;
                            syncPollAttempts++;
                            if (syncPollAttempts > MAX_SYNC_POLLS) {
                                closeRefreshUi('Пока не удалось подтвердить обновление. Попробуйте позже или обновите страницу.');
                                return;
                            }
                            fetch(contentUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                                .then(parseJsonResponse)
                                .then(function(pollRes) {
                                    if (refreshSessionClosed) return;
                                    if (!pollRes.data || !pollRes.data.success || !pollRes.data.page) return;
                                    if (isDataNewerThanBaseline(pollRes.data)) {
                                        applyContentPayload(pollRes.data);
                                        closeRefreshUi('✓ Готово, конфигурация обновлена');
                                    }
                                })
                                .catch(function() { /* сеть: следующий интервал попробует снова */ });
                        }

                        pollOnce();
                        _vpnSyncPollTimer = setInterval(pollOnce, POLL_INTERVAL_MS);
                    } else {
                        var errMsg = (res.data && res.data.message) ? String(res.data.message) : '';
                        if (!errMsg && !res.ok) errMsg = '';
                        onRefreshHttpError(errMsg, res.status);
                    }
                })
                .catch(function() {
                    onRefreshHttpError('', 0);
                });
        }

        if (btnRefresh) btnRefresh.addEventListener('click', runRefresh);
        if (retryBtn) retryBtn.addEventListener('click', runRefresh);
    })();
    </script>
@endsection

<?php

namespace App\Http\Controllers;

use App\Models\KeyActivate\KeyActivate;
use App\Models\KeyActivateUser\KeyActivateUser;
use App\Models\ServerUser\ServerUser;
use App\Models\VPN\ConnectionLimitViolation;
use App\Jobs\AddMissingSlotsForKeyJob;
use App\Repositories\KeyActivate\KeyActivateRepository;
use App\Repositories\KeyActivateUser\KeyActivateUserRepository;
use App\Repositories\ServerUser\ServerUserRepository;
use App\Support\VpnConfigPageTrace;
use App\Services\External\MarzbanAPI;
use App\Services\Panel\marzban\MarzbanService;
use App\Services\Panel\PanelStrategy;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class VpnConfigController extends Controller
{
    /** Кэш только фрагмента HTML для /content (сек). Обновление с панели — только «Обновить» (refresh). */
    private const CONFIG_CONTENT_CACHE_TTL_SECONDS = 180;

    /**
     * @var KeyActivateRepository
     */
    private KeyActivateRepository $keyActivateRepository;
    /**
     * @var KeyActivateUserRepository
     */
    private KeyActivateUserRepository $keyActivateUserRepository;
    /**
     * @var ServerUserRepository
     */
    private ServerUserRepository $serverUserRepository;
    /**
     * @var \App\Services\Key\KeyActivateService
     */
    private $keyActivateService;

    public function __construct(
        KeyActivateRepository $keyActivateRepository,
        KeyActivateUserRepository $keyActivateUserRepository,
        ServerUserRepository $serverUserRepository,
        \App\Services\Key\KeyActivateService $keyActivateService
    )
    {
        $this->keyActivateRepository = $keyActivateRepository;
        $this->keyActivateUserRepository = $keyActivateUserRepository;
        $this->serverUserRepository = $serverUserRepository;
        $this->keyActivateService = $keyActivateService;
    }

    public function show(string $key_activate_id): Response
    {
        $key_activate_id = trim($key_activate_id);
        // Лимит памяти и времени: тяжёлая сборка по слотам и запросы к панелям (VPN-клиент и refresh)
        if ((int) ini_get('memory_limit') < 1024) {
            @ini_set('memory_limit', '1024M');
        }
        if (function_exists('set_time_limit')) {
            @set_time_limit(180);
        }

        $traceShow = VpnConfigPageTrace::shouldTrace($key_activate_id);
        if ($traceShow) {
            VpnConfigPageTrace::begin($key_activate_id, 'show');
        }

        try {
            // Если запрошен роут /config/error, перенаправляем на метод showError
            if ($key_activate_id === 'error') {
                if ($traceShow) {
                    VpnConfigPageTrace::checkpoint('show_error_route');
                }

                return $this->showError();
            }

            // Быстрая проверка существования ключа (один лёгкий запрос) — для браузера не тянем слоты и связи.
            $keyActivate = $this->keyActivateRepository->findById($key_activate_id);
            if ($traceShow) {
                VpnConfigPageTrace::checkpoint('show_after_findById', ['found' => (bool) $keyActivate]);
            }

            if (!$keyActivate) {
                $showDemo = app()->environment('local') && config('app.debug', false);
                if ($showDemo) {
                    if ($traceShow) {
                        VpnConfigPageTrace::checkpoint('show_not_found_demo');
                    }

                    return $this->showDemoPage($key_activate_id);
                }
                if (request()->wantsJson()) {
                    if ($traceShow) {
                        VpnConfigPageTrace::checkpoint('show_not_found_json');
                    }

                    return response()->json(['status' => 'error', 'message' => 'Configuration not found'], 404);
                }
                if ($traceShow) {
                    VpnConfigPageTrace::checkpoint('show_not_found_view');
                }

                return response()->view('vpn.error', [
                    'message' => 'Конфигурация VPN не найдена. Пожалуйста, проверьте правильность ссылки или обратитесь в поддержку.'
                ]);
            }

            $userAgent = request()->header('User-Agent') ?? 'Unknown';
            // Явный параметр для подписки (обход подмены заголовков прокси/зеркалом): ?format=subscription или ?sub=1
            $forceSubscription = in_array(request()->query('format'), ['subscription', 'sub', 'txt'], true)
                || request()->query('sub') === '1';
            $isSubscriptionRequest = $forceSubscription
                || $this->isVpnClient($userAgent)
                || !$this->requestAcceptsHtml()
                || !$this->hasVersionedBrowserInUserAgent($userAgent);

            if ($isSubscriptionRequest) {
                if ($traceShow) {
                    VpnConfigPageTrace::subscription($key_activate_id, 'SUB_A0_entry', [
                        'force_subscription_query' => $forceSubscription,
                        'is_vpn_client_ua' => $this->isVpnClient($userAgent),
                        'accepts_html' => $this->requestAcceptsHtml(),
                        'has_versioned_browser_ua' => $this->hasVersionedBrowserInUserAgent($userAgent),
                        'accept_header' => mb_substr((string) request()->header('Accept', ''), 0, 200),
                        'ua_short' => mb_substr($userAgent, 0, 160),
                        'note' => 'Дальше: findAllByKeyActivateIdForSubscription → collectConnectionKeysFromKeyActivateUsers',
                    ]);
                }

                $tFind = microtime(true);
                if ($traceShow) {
                    VpnConfigPageTrace::subscription($key_activate_id, 'SUB_A1_before_findAllByKeyActivateIdForSubscription');
                }
                $keyActivateUsers = $this->keyActivateUserRepository->findAllByKeyActivateIdForSubscription($key_activate_id);
                $findMs = round((microtime(true) - $tFind) * 1000, 2);
                if ($traceShow) {
                    $firstKau = $keyActivateUsers->first();
                    VpnConfigPageTrace::subscription($key_activate_id, 'SUB_A2_after_findAllByKeyActivateIdForSubscription', [
                        'segment_ms' => $findMs,
                        'find_ms' => $findMs,
                        'repo' => 'KeyActivateUserRepository::findAllByKeyActivateIdForSubscription (with serverUser.panel.server.location)',
                        'key_activate_users_count' => $keyActivateUsers->count(),
                        'kau_ids_sample' => $keyActivateUsers->take(10)->pluck('id')->values()->all(),
                        'first_kau_has_server_user' => $firstKau ? $firstKau->relationLoaded('serverUser') : null,
                        'first_kau_server_user_id' => $firstKau ? $firstKau->server_user_id : null,
                    ]);
                }

                if ($keyActivateUsers->isEmpty()) {
                    Log::warning('KeyActivateUser not found for KeyActivate', ['key_activate_id' => $key_activate_id, 'source' => 'vpn']);
                    if ($traceShow) {
                        VpnConfigPageTrace::subscription($key_activate_id, 'SUB_B1_empty_users_before_replacedViolation_query');
                    }
                    $tRv = microtime(true);
                    $replacedViolation = ConnectionLimitViolation::where('key_activate_id', $key_activate_id)
                        ->whereNotNull('key_replaced_at')->whereNotNull('replaced_key_id')->orderBy('key_replaced_at', 'desc')->first();
                    $rvMs = round((microtime(true) - $tRv) * 1000, 2);
                    if ($traceShow) {
                        VpnConfigPageTrace::subscription($key_activate_id, 'SUB_B2_after_replacedViolation_query', [
                            'segment_ms' => $rvMs,
                            'replaced_violation_query_ms' => $rvMs,
                            'found_replaced_key' => (bool) ($replacedViolation && $replacedViolation->replaced_key_id),
                            'replaced_key_id' => $replacedViolation && $replacedViolation->replaced_key_id ? $replacedViolation->replaced_key_id : null,
                        ]);
                    }
                    if ($replacedViolation && $replacedViolation->replaced_key_id) {
                        if ($traceShow) {
                            VpnConfigPageTrace::subscription($key_activate_id, 'SUB_B3_return_error_view_replaced');
                        }

                        return response()->view('vpn.error', [
                            'message' => 'Ваш ключ доступа был заменен из-за нарушения лимита подключений. Пожалуйста, используйте новый ключ.',
                            'replacedKeyId' => $replacedViolation->replaced_key_id
                        ]);
                    }
                    if (app()->environment('local') && config('app.debug', false)) {
                        if ($traceShow) {
                            VpnConfigPageTrace::subscription($key_activate_id, 'SUB_B3_return_showDemoPage');
                        }

                        return $this->showDemoPage($key_activate_id);
                    }
                    if ($traceShow) {
                        VpnConfigPageTrace::subscription($key_activate_id, 'SUB_B3_return_error_not_found');
                    }

                    return response()->view('vpn.error', ['message' => 'Конфигурация VPN не найдена.']);
                }

                $tCollect = microtime(true);
                if ($traceShow) {
                    VpnConfigPageTrace::subscription($key_activate_id, 'SUB_C1_before_collectConnectionKeysFromKeyActivateUsers', [
                        'hint' => 'Внутри: foreach слотов, load panel.server.location при необходимости, formatConnectionKeys / json_decode keys',
                    ]);
                }
                $connectionKeys = $this->collectConnectionKeysFromKeyActivateUsers($keyActivateUsers);
                $collectMs = round((microtime(true) - $tCollect) * 1000, 2);
                $bodyPreview = implode("\n", $connectionKeys);
                if ($traceShow) {
                    VpnConfigPageTrace::subscription($key_activate_id, 'SUB_C2_after_collectConnectionKeysFromKeyActivateUsers', [
                        'segment_ms' => $collectMs,
                        'collect_ms' => $collectMs,
                        'connection_keys_count' => count($connectionKeys),
                        'response_body_bytes' => strlen($bodyPreview),
                        'first_link_preview' => mb_substr($connectionKeys[0] ?? '', 0, 120),
                    ]);
                }

                if ($traceShow) {
                    VpnConfigPageTrace::subscription($key_activate_id, 'SUB_C3_return_plain_text_200');
                }

                return response($bodyPreview)
                    ->header('Content-Type', 'text/plain; charset=utf-8');
            }

            // Браузер: отдаём из кэша при повторном открытии (90 сек) — страница открывается мгновенно.
            $configPageCacheKey = 'vpn_config_html_' . $key_activate_id;
            $cachedHtml = Cache::get($configPageCacheKey);
            if ($cachedHtml !== null) {
                if ($traceShow) {
                    VpnConfigPageTrace::checkpoint('show_shell_full_html_cache_hit');
                }

                return response($cachedHtml)->header('Content-Type', 'text/html; charset=utf-8');
            }

            // Быстрый ответ: отдаём лёгкую оболочку (shell), контент подгрузится по /config/{token}/content — DOMContentLoaded будет ~0.5 с вместо 15+ с.
            if ($traceShow) {
                VpnConfigPageTrace::checkpoint('show_return_config_shell_view');
            }

            return response()->view('vpn.config-shell', [
                'token' => $key_activate_id,
                'contentUrl' => route('vpn.config.content', ['token' => $key_activate_id], false),
                'refreshUrl' => route('vpn.config.refresh', ['token' => $key_activate_id], false),
            ]);

        } catch (\App\Exceptions\KeyReplacedException $e) {
            if ($traceShow) {
                VpnConfigPageTrace::checkpoint('show_KeyReplacedException', ['new_key_id' => $e->getNewKeyId()]);
            }
            // Ключ был перевыпущен - показываем страницу ошибки с информацией о новом ключе
            $newKeyId = $e->getNewKeyId();

            Log::info('Key was replaced, showing error page with new key link', [
                'old_key_id' => $key_activate_id,
                'new_key_id' => $newKeyId,
                'source' => 'vpn'
            ]);

            if (request()->wantsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Key was replaced',
                    'new_key_id' => $newKeyId
                ], 404);
            }

            return response()->view('vpn.error', [
                'message' => 'Конфигурация VPN не найдена. Пожалуйста, проверьте правильность ссылки или обратитесь в поддержку.',
                'replacedKeyId' => $newKeyId
            ]);
        } catch (Exception $e) {
            if ($traceShow) {
                VpnConfigPageTrace::checkpoint('show_Exception', [
                    'error' => $e->getMessage(),
                    'class' => get_class($e),
                ]);
            }
            Log::error('Error showing VPN config', [
                'key_activate_id' => $key_activate_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Проверяем, может быть это ошибка 404 из-за перевыпуска ключа
            if (str_contains($e->getMessage(), '404') || str_contains($e->getMessage(), 'User not found')) {
                // Ищем KeyActivate по ID
                $keyActivate = $this->keyActivateRepository->findById($key_activate_id);

                if ($keyActivate) {
                    // Проверяем, был ли ключ перевыпущен
                    $replacedViolation = \App\Models\VPN\ConnectionLimitViolation::where('key_activate_id', $keyActivate->id)
                        ->whereNotNull('key_replaced_at')
                        ->whereNotNull('replaced_key_id')
                        ->orderBy('key_replaced_at', 'desc')
                        ->first();

                    if ($replacedViolation && $replacedViolation->replaced_key_id) {
                        Log::info('Key was replaced, showing error page with new key link', [
                            'old_key_id' => $key_activate_id,
                            'new_key_id' => $replacedViolation->replaced_key_id,
                            'source' => 'vpn'
                        ]);

                        if (request()->wantsJson()) {
                            return response()->json([
                                'status' => 'error',
                                'message' => 'Key was replaced',
                                'new_key_id' => $replacedViolation->replaced_key_id
                            ], 404);
                        }

                        return response()->view('vpn.error', [
                            'message' => 'Конфигурация VPN не найдена. Пожалуйста, проверьте правильность ссылки или обратитесь в поддержку.',
                            'replacedKeyId' => $replacedViolation->replaced_key_id
                        ]);
                    }
                }
            }

            if (request()->wantsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Configuration not found'
                ], 404);
            }

            return response()->view('vpn.error', [
                'message' => 'Конфигурация VPN не найдена. Пожалуйста, проверьте правильность ссылки или обратитесь в поддержку.'
            ]);
        } finally {
            if ($traceShow) {
                VpnConfigPageTrace::end();
            }
        }
    }

    /**
     * Контент страницы конфига (только из БД, без панелей). Для быстрой отрисовки: сначала отдаётся shell, потом fetch этого URL.
     * Возвращает JSON { success, html?, lastUpdated?, message? }.
     */
    public function showConfigContent(string $token): Response
    {
        $key_activate_id = trim($token);
        if ((int) ini_get('memory_limit') < 512) {
            @ini_set('memory_limit', '512M');
        }
        if (function_exists('set_time_limit')) {
            @set_time_limit(120);
        }

        $traceContent = VpnConfigPageTrace::shouldTrace($key_activate_id);
        if ($traceContent) {
            VpnConfigPageTrace::begin($key_activate_id, 'content');
        }

        $cacheKey = 'vpn_config_content_' . $key_activate_id;
        $cached = Cache::get($cacheKey);
        if ($cached !== null && is_array($cached)) {
            if ($traceContent) {
                VpnConfigPageTrace::checkpoint('content_json_cache_hit');
                VpnConfigPageTrace::end(['cached' => true]);
            }

            return response()->json(['success' => true, 'html' => $cached['html'] ?? '', 'lastUpdated' => $cached['lastUpdated'] ?? null]);
        }

        try {
            $keyActivate = $this->keyActivateRepository->findWithConfigRelationsForContent($key_activate_id);
            if ($traceContent) {
                VpnConfigPageTrace::checkpoint('content_after_findWithConfigRelationsForContent', ['found' => (bool) $keyActivate]);
            }
            if (!$keyActivate) {
                return response()->json(['success' => false, 'message' => 'Ключ не найден'], 404);
            }
            $keyActivateUsers = $keyActivate->keyActivateUsers;
            if ($keyActivateUsers->isEmpty()) {
                $keyActivateUsers = $this->keyActivateUserRepository->findAllByKeyActivateId($key_activate_id);
                if ($keyActivateUsers->isNotEmpty()) {
                    $keyActivate->setRelation('keyActivateUsers', $keyActivateUsers);
                }
            }
            if ($traceContent) {
                VpnConfigPageTrace::checkpoint('content_key_activate_users', ['count' => $keyActivateUsers->count()]);
            }
            if ($keyActivateUsers->isEmpty()) {
                $replacedViolation = $keyActivate->replacedViolation;
                if ($replacedViolation && $replacedViolation->replaced_key_id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Ваш ключ доступа был заменен из-за нарушения лимита подключений. Пожалуйста, используйте новый ключ.',
                        'replacedKeyId' => $replacedViolation->replaced_key_id,
                    ], 404);
                }
                return response()->json(['success' => false, 'message' => 'Конфигурация не найдена.'], 404);
            }
            $data = $this->buildConnectionDataFromStored($keyActivate, $key_activate_id, $keyActivateUsers);
            if ($traceContent) {
                VpnConfigPageTrace::checkpoint('content_after_buildConnectionDataFromStored', [
                    'slots' => count($data['slotsWithLinks'] ?? []),
                ]);
            }
            $response = $this->showBrowserPage(
                $keyActivate,
                $data['firstKeyActivateUser'],
                $data['firstServerUser'],
                $data['connectionKeys'],
                $data['slotsWithLinks'],
                true,
                true,
                null,
                null,
                $data['lastUpdated'] ?? null
            );
            if ($traceContent) {
                VpnConfigPageTrace::checkpoint('content_after_showBrowserPage_before_getContent');
            }
            $html = $response->getContent();
            if ($traceContent) {
                VpnConfigPageTrace::checkpoint('content_after_getContent', ['html_bytes' => strlen($html ?? '')]);
            }
            $lastUpdated = isset($data['lastUpdated']) && $data['lastUpdated']
                ? $data['lastUpdated']->format('d.m.Y H:i')
                : null;
            Cache::put($cacheKey, ['html' => $html, 'lastUpdated' => $lastUpdated], now()->addSeconds(self::CONFIG_CONTENT_CACHE_TTL_SECONDS));
            if ($traceContent) {
                VpnConfigPageTrace::checkpoint('content_cache_put_done');
            }

            return response()->json(['success' => true, 'html' => $html, 'lastUpdated' => $lastUpdated]);
        } catch (\Throwable $e) {
            if ($traceContent) {
                VpnConfigPageTrace::checkpoint('content_throw', [
                    'error' => $e->getMessage(),
                    'class' => get_class($e),
                ]);
            }
            Log::warning('VpnConfig content failed', [
                'key_activate_id' => $key_activate_id,
                'error' => $e->getMessage(),
                'source' => 'vpn',
            ]);
            return response()->json(['success' => false, 'message' => 'Не удалось загрузить конфигурацию.'], 500);
        } finally {
            if ($traceContent) {
                VpnConfigPageTrace::end();
            }
        }
    }

    /**
     * Фоновое обновление конфига для браузера: полная сборка данных и HTML страницы.
     * Вызывается JS со страницы config-loading.
     */
    public function showConfigRefresh(string $token): Response
    {
        $key_activate_id = trim($token);

        if ((int) ini_get('memory_limit') < 1024) {
            @ini_set('memory_limit', '1024M');
        }
        if (function_exists('set_time_limit')) {
            @set_time_limit(180);
        }

        $traceRefresh = VpnConfigPageTrace::shouldTrace($key_activate_id);
        if ($traceRefresh) {
            VpnConfigPageTrace::begin($key_activate_id, 'refresh');
        }

        Cache::forget('vpn_config_html_' . $key_activate_id);
        Cache::forget('vpn_config_content_' . $key_activate_id);
        try {
            $keyActivate = $this->keyActivateRepository->findById($key_activate_id);
            if ($traceRefresh) {
                VpnConfigPageTrace::checkpoint('refresh_after_findById', ['found' => (bool) $keyActivate]);
            }
            if (!$keyActivate) {
                return response()->json(['success' => false, 'message' => 'Ключ не найден'], 404);
            }
            $keyActivate->load([
                'packSalesman' => fn ($q) => $q->select('id', 'salesman_id', 'pack_id'),
                'packSalesman.salesman' => fn ($q) => $q->select('id', 'telegram_id', 'bot_link', 'panel_id', 'module_bot_id'),
            ]);
            if ($traceRefresh) {
                VpnConfigPageTrace::checkpoint('refresh_after_packSalesman_load');
            }
            $keyActivateUsers = $this->keyActivateUserRepository->findAllByKeyActivateId($key_activate_id);
            if ($keyActivateUsers->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'Нет слотов для ключа'], 404);
            }
            if ($traceRefresh) {
                VpnConfigPageTrace::checkpoint('refresh_before_buildConnectionData', ['slots' => $keyActivateUsers->count()]);
            }
            $data = $this->buildConnectionData($keyActivate, $key_activate_id, true);
            if ($traceRefresh) {
                VpnConfigPageTrace::checkpoint('refresh_after_buildConnectionData', [
                    'slots_with_links' => count($data['slotsWithLinks'] ?? []),
                ]);
            }
            $response = $this->showBrowserPage(
                $keyActivate,
                $data['firstKeyActivateUser'],
                $data['firstServerUser'],
                $data['connectionKeys'],
                $data['slotsWithLinks'],
                false,
                true,
                null,
                null,
                null
            );
            if ($traceRefresh) {
                VpnConfigPageTrace::checkpoint('refresh_after_showBrowserPage_before_getContent');
            }
            $lastUpdated = isset($data['lastUpdated']) && $data['lastUpdated']
                ? $data['lastUpdated']->format('d.m.Y H:i')
                : null;
            $html = $response->getContent();
            if ($traceRefresh) {
                VpnConfigPageTrace::checkpoint('refresh_after_getContent', ['html_bytes' => strlen($html ?: '')]);
            }
            Cache::put('vpn_config_content_' . $key_activate_id, ['html' => $html ?: '', 'lastUpdated' => $lastUpdated], now()->addSeconds(self::CONFIG_CONTENT_CACHE_TTL_SECONDS));
            if ($traceRefresh) {
                VpnConfigPageTrace::checkpoint('refresh_cache_put_done');
            }

            return response()->json(['success' => true, 'html' => $html ?: '', 'lastUpdated' => $lastUpdated]);
        } catch (\Throwable $e) {
            if ($traceRefresh) {
                VpnConfigPageTrace::checkpoint('refresh_throw', [
                    'error' => $e->getMessage(),
                    'class' => get_class($e),
                ]);
            }
            Log::warning('VpnConfig refresh failed', [
                'key_activate_id' => $key_activate_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'source' => 'vpn',
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Не удалось обновить конфигурацию. Попробуйте позже.',
            ], 500);
        } finally {
            if ($traceRefresh) {
                VpnConfigPageTrace::end();
            }
        }
    }

    /**
     * Быстрая сборка только из БД: сохранённые ссылки (server_user.keys), без запросов к панелям.
     * Для первого отображения страницы в браузере.
     * @param \Illuminate\Support\Collection|null $keyActivateUsers уже загруженные слоты — если переданы, повторный запрос не выполняется.
     */
    private function buildConnectionDataFromStored(KeyActivate $keyActivate, string $key_activate_id, ?\Illuminate\Support\Collection $keyActivateUsers = null): array
    {
        if ($keyActivateUsers === null) {
            $keyActivateUsers = $this->keyActivateUserRepository->findAllByKeyActivateId($key_activate_id);
        }
        $connectionKeys = [];
        $slotsWithLinks = [];
        $firstKeyActivateUser = null;
        $firstServerUser = null;
        $lastUpdated = null;

        foreach ($keyActivateUsers as $kau) {
            $serverUser = $kau->serverUser;
            if (!$serverUser && $kau->server_user_id) {
                $serverUser = ServerUser::with(['panel.server.location'])->find($kau->server_user_id);
                if ($serverUser) {
                    $kau->setRelation('serverUser', $serverUser);
                }
            }
            if (!$serverUser && $kau->server_user_id) {
                $serverUser = $this->serverUserRepository->findById($kau->server_user_id);
                if ($serverUser && !$serverUser->relationLoaded('panel')) {
                    $serverUser->load(['panel.server.location']);
                }
                if ($serverUser) {
                    $kau->setRelation('serverUser', $serverUser);
                }
            }
            if (!$serverUser) {
                continue;
            }
            if ($firstKeyActivateUser === null) {
                $firstKeyActivateUser = $kau;
                $firstServerUser = $serverUser;
            }
            if ($serverUser->panel && !$serverUser->panel->relationLoaded('server')) {
                $serverUser->panel->load('server.location');
            }
            $stored = json_decode($serverUser->keys ?? '[]', true);
            $slotLinks = is_array($stored) ? $stored : [];
            if (!empty($slotLinks)) {
                if ($serverUser->updated_at && (!$lastUpdated || $serverUser->updated_at > $lastUpdated)) {
                    $lastUpdated = $serverUser->updated_at;
                }
                $connectionKeys = array_merge($connectionKeys, $slotLinks);
                $server = $serverUser->panel && $serverUser->panel->server ? $serverUser->panel->server : null;
                $location = $server && $server->relationLoaded('location') ? $server->location : null;
                $locationCode = '';
                $name = 'Сервер';
                if ($location) {
                    $locationCode = strtolower(trim($location->code ?? ''));
                    $name = $this->locationCodeToFullName($location->code ?: '');
                    if ($name === '') {
                        $name = $location->code ?: 'Сервер';
                    }
                } elseif ($server && $server->name) {
                    $name = $server->name;
                }
                $name = $this->normalizeLocationLabelName($name);
                $sectionNumber = count($slotsWithLinks) + 1;
                $locationLabel = $this->locationLabelWithEmoji($location, $name . ' #' . $sectionNumber);
                $slotsWithLinks[] = [
                    'location_label'  => $locationLabel,
                    'location_code'   => $locationCode,
                    'connection_keys' => $slotLinks,
                ];
            }
        }

        if ($firstKeyActivateUser === null) {
            $firstKeyActivateUser = $keyActivateUsers->first();
            $firstServerUser = $firstKeyActivateUser && $firstKeyActivateUser->server_user_id
                ? $this->serverUserRepository->findById($firstKeyActivateUser->server_user_id)
                : null;
        }

        return [
            'connectionKeys' => $connectionKeys,
            'slotsWithLinks' => $slotsWithLinks,
            'firstKeyActivateUser' => $firstKeyActivateUser,
            'firstServerUser' => $firstServerUser,
            'lastUpdated' => $lastUpdated,
        ];
    }

    /**
     * Собрать connectionKeys и slotsWithLinks: добавить недостающие слоты, обновить ссылки с панелей.
     * @param bool $syncMultiProvider true при вызове из refresh — добавить слоты синхронно и вернуть полные данные
     */
    private function buildConnectionData(KeyActivate $keyActivate, string $key_activate_id, bool $syncMultiProvider = false): array
    {
        $keyActivateUsers = $this->keyActivateUserRepository->findAllByKeyActivateId($key_activate_id);

        $multiProviderSlots = config('panel.multi_provider_slots', []);
        if (!empty($multiProviderSlots) && is_array($multiProviderSlots) && $keyActivate->status === KeyActivate::ACTIVE) {
            $slotCount = $keyActivateUsers->count();
            $providerCount = count($multiProviderSlots);
            if ($providerCount > 0 && $slotCount < $providerCount) {
                $cacheKey = 'vpn_config_multi_provider_checked_' . $key_activate_id;
                $doAdd = $syncMultiProvider || !Cache::has($cacheKey);
                if ($doAdd) {
                    if ($syncMultiProvider) {
                        try {
                            $added = $this->keyActivateService->addMissingProviderSlots($keyActivate, false);
                            if ($added > 0) {
                                $keyActivateUsers = $this->keyActivateUserRepository->findAllByKeyActivateId($key_activate_id);
                            }
                        } catch (Exception $e) {
                            Log::warning('VpnConfig: addMissingProviderSlots failed', [
                                'key_activate_id' => $key_activate_id,
                                'error' => $e->getMessage(),
                                'source' => 'vpn',
                            ]);
                        }
                    } else {
                        if (config('queue.default') !== 'sync') {
                            AddMissingSlotsForKeyJob::dispatch($key_activate_id);
                        } else {
                            try {
                                $added = $this->keyActivateService->addMissingProviderSlots($keyActivate, false);
                                if ($added > 0) {
                                    $keyActivateUsers = $this->keyActivateUserRepository->findAllByKeyActivateId($key_activate_id);
                                }
                            } catch (Exception $e) {
                                Log::warning('VpnConfig: addMissingProviderSlots failed', [
                                    'key_activate_id' => $key_activate_id,
                                    'error' => $e->getMessage(),
                                    'source' => 'vpn',
                                ]);
                            }
                        }
                    }
                    if (!$syncMultiProvider) {
                        Cache::put($cacheKey, 1, now()->addMinutes(10));
                    }
                }
            }
        }

        $connectionKeys = [];
        $slotsWithLinks = [];
        $firstKeyActivateUser = null;
        $firstServerUser = null;
        $lastUpdated = null;

        foreach ($keyActivateUsers as $kau) {
            $serverUser = $kau->serverUser;
            if (!$serverUser && $kau->server_user_id) {
                $serverUser = ServerUser::with(['panel.server.location'])->find($kau->server_user_id);
                if ($serverUser) {
                    $kau->setRelation('serverUser', $serverUser);
                }
            }
            if (!$serverUser && $kau->server_user_id) {
                $serverUser = $this->serverUserRepository->findById($kau->server_user_id);
                if ($serverUser && !$serverUser->relationLoaded('panel')) {
                    $serverUser->load(['panel.server.location']);
                }
                if ($serverUser) {
                    $kau->setRelation('serverUser', $serverUser);
                }
            }
            if (!$serverUser) {
                Log::warning('Server user not found for KeyActivateUser slot', [
                    'key_activate_user_id' => $kau->id,
                    'key_activate_id' => $key_activate_id,
                    'source' => 'vpn',
                ]);
                continue;
            }
            if ($firstKeyActivateUser === null) {
                $firstKeyActivateUser = $kau;
                $firstServerUser = $serverUser;
            }
            if ($serverUser->panel && !$serverUser->panel->relationLoaded('server')) {
                $serverUser->panel->load('server.location');
            }
            $slotLinks = [];
            try {
                $links = $this->getFreshUserLinks($serverUser);
                if (!empty($links)) {
                    $slotLinks = $links;
                    $connectionKeys = array_merge($connectionKeys, $links);
                    if ($serverUser->updated_at && (!$lastUpdated || $serverUser->updated_at > $lastUpdated)) {
                        $lastUpdated = $serverUser->updated_at;
                    }
                }
                unset($links);
            } catch (\App\Exceptions\KeyReplacedException $e) {
                throw $e;
            } catch (\Throwable $e) {
                Log::warning('Failed to get fresh links for one slot, using stored', [
                    'key_activate_user_id' => $kau->id,
                    'server_user_id' => $serverUser->id,
                    'error' => $e->getMessage(),
                    'source' => 'vpn',
                ]);
                $stored = json_decode($serverUser->keys ?? '[]', true);
                if (!empty($stored) && is_array($stored)) {
                    $slotLinks = $stored;
                    $connectionKeys = array_merge($connectionKeys, $stored);
                }
                unset($stored);
            }
            if (!empty($slotLinks)) {
                $server = $serverUser->panel && $serverUser->panel->server ? $serverUser->panel->server : null;
                $location = $server && $server->relationLoaded('location') ? $server->location : null;
                $locationCode = '';
                $name = 'Сервер';
                if ($location) {
                    $locationCode = strtolower(trim($location->code ?? ''));
                    $name = $this->locationCodeToFullName($location->code ?: '');
                    if ($name === '') {
                        $name = $location->code ?: 'Сервер';
                    }
                } elseif ($server && $server->name) {
                    $name = $server->name;
                }
                $name = $this->normalizeLocationLabelName($name);
                $sectionNumber = count($slotsWithLinks) + 1;
                $locationLabel = $this->locationLabelWithEmoji($location, $name . ' #' . $sectionNumber);
                $slotsWithLinks[] = [
                    'location_label'  => $locationLabel,
                    'location_code'   => $locationCode,
                    'connection_keys' => $slotLinks,
                ];
            }
            unset($slotLinks);
        }

        if (empty($connectionKeys)) {
            throw new RuntimeException('Invalid connection keys format');
        }
        if ($firstKeyActivateUser === null) {
            $firstKeyActivateUser = $keyActivateUsers->first();
            $firstServerUser = $firstKeyActivateUser->serverUser ?? $this->serverUserRepository->findById($firstKeyActivateUser->server_user_id);
        }

        return [
            'connectionKeys' => $connectionKeys,
            'slotsWithLinks' => $slotsWithLinks,
            'firstKeyActivateUser' => $firstKeyActivateUser,
            'firstServerUser' => $firstServerUser,
            'lastUpdated' => $lastUpdated,
        ];
    }

    /**
     * Получить актуальные ссылки пользователя из панели
     *
     * @param ServerUser $serverUser Пользователь сервера
     * @return array Массив ссылок
     */
    private function getFreshUserLinks(ServerUser $serverUser): array
    {
        try {
            // Используем стратегию для работы с панелью
            $panel = $serverUser->panel;
            if (!$panel) {
                throw new \RuntimeException('Panel not found for server user');
            }

            $panelType = $panel->panel ?? \App\Models\Panel\Panel::MARZBAN;
            if ($panelType === '') {
                $panelType = \App\Models\Panel\Panel::MARZBAN;
            }
            $panelStrategyFactory = new \App\Services\Panel\PanelStrategyFactory();
            $panelStrategy = $panelStrategyFactory->create($panelType);

            // Обновляем токен через стратегию
            $panel = $panelStrategy->updateToken($panel->id);

            $marzbanApi = new MarzbanAPI($panel->api_address);

            // Получаем актуальные данные пользователя из Marzban
            $userData = $marzbanApi->getUser($panel->auth_token, $serverUser->id);

            // Если links есть, но их мало (меньше 10 для REALITY), обновляем пользователя с правильными inbounds
            if (!empty($userData['links']) && in_array($panel->config_type, ['reality', 'reality_stable', 'mixed'], true) && count($userData['links']) < 10) {
                try {
                    // Получаем конфигурацию панели, чтобы узнать доступные inbounds
                    $panelConfig = $marzbanApi->getConfig($panel->auth_token);
                    $availableInboundTags = [];
                    
                    if (!empty($panelConfig['inbounds']) && is_array($panelConfig['inbounds'])) {
                        foreach ($panelConfig['inbounds'] as $inbound) {
                            if (isset($inbound['tag'])) {
                                $availableInboundTags[] = $inbound['tag'];
                            }
                        }
                    }
                    
                    // Определяем все возможные inbounds для REALITY конфигурации
                    $allPossibleInbounds = [
                        'vmess' => ["VMESS-WS"],
                        'vless' => [
                            "VLESS-WS",
                            "VLESS TCP REALITY",
                            "VLESS GRPC REALITY",
                            "VLESS XHTTP REALITY",
                            "VLESS TCP REALITY ALT",
                            "VLESS TCP HTTP/1.1 Obfuscated",
                            "VLESS HTTP Upgrade"
                        ],
                        'trojan' => ["TROJAN-WS"],
                        'shadowsocks' => ["Shadowsocks-TCP"],
                    ];
                    
                    // Фильтруем inbounds, оставляя только те, которые существуют на панели
                    $realityInbounds = [];
                    foreach ($allPossibleInbounds as $protocol => $inboundTags) {
                        $filteredTags = [];
                        foreach ($inboundTags as $tag) {
                            if (in_array($tag, $availableInboundTags)) {
                                $filteredTags[] = $tag;
                            }
                        }
                        if (!empty($filteredTags)) {
                            $realityInbounds[$protocol] = $filteredTags;
                        }
                    }
                    
                    // Обновляем пользователя только если есть доступные inbounds
                    if (!empty($realityInbounds)) {
                        $updatedUserData = $marzbanApi->updateUser(
                            $panel->auth_token,
                            $serverUser->id,
                            $userData['expire'] ?? 0,
                            $userData['data_limit'] ?? 0,
                            $realityInbounds
                        );

                        // Получаем обновленные данные пользователя
                        $userData = $marzbanApi->getUser($panel->auth_token, $serverUser->id);
                    }
                } catch (Exception $e) {
                    Log::warning('Failed to update user inbounds for REALITY', [
                        'user_id' => $serverUser->id,
                        'panel_id' => $panel->id,
                        'error' => $e->getMessage(),
                        'source' => 'vpn'
                    ]);
                    // Продолжаем с исходными links
                }
            }

            // Обновляем ссылки в БД
            if (!empty($userData['links'])) {
                $serverUser->keys = json_encode($userData['links']);
                $serverUser->save();
                return $userData['links'];
            }

            // Если links нет, но есть subscription_url
            if (!empty($userData['subscription_url'])) {
                $links = [$userData['subscription_url']];
                $serverUser->keys = json_encode($links);
                $serverUser->save();
                return $links;
            }

            // Если не удалось получить из panel, используем сохраненные ключи
            Log::warning('Using stored keys for user', ['user_id' => $serverUser->id]);
            return json_decode($serverUser->keys, true) ?? [];

        } catch (Exception $e) {
            Log::error('Failed to get fresh user links', [
                'user_id' => $serverUser->id,
                'error' => $e->getMessage(),
                'source' => 'vpn'
            ]);

            // Проверяем, является ли это ошибкой 404 (User not found)
            // Если да, проверяем, был ли ключ перевыпущен
            // Для этого нужно получить key_activate_id из serverUser через keyActivateUser
            if (str_contains($e->getMessage(), '404') || str_contains($e->getMessage(), 'User not found')) {
                // Ищем KeyActivateUser по server_user_id
                $keyActivateUser = KeyActivateUser::where('server_user_id', $serverUser->id)->first();

                if ($keyActivateUser && $keyActivateUser->key_activate_id) {
                    // Проверяем, был ли ключ перевыпущен
                    $replacedViolation = \App\Models\VPN\ConnectionLimitViolation::where('key_activate_id', $keyActivateUser->key_activate_id)
                        ->whereNotNull('key_replaced_at')
                        ->whereNotNull('replaced_key_id')
                        ->orderBy('key_replaced_at', 'desc')
                        ->first();

                    if ($replacedViolation && $replacedViolation->replaced_key_id) {
                        // Ключ был перевыпущен - пробрасываем специальное исключение
                        throw new \App\Exceptions\KeyReplacedException(
                            'Ключ был перевыпущен',
                            $replacedViolation->replaced_key_id
                        );
                    }
                }
            }

            // В случае ошибки возвращаем сохраненные ключи, если они есть
            $storedKeys = json_decode($serverUser->keys, true) ?? [];
            if (empty($storedKeys)) {
                // Если сохраненных ключей нет, пробрасываем исключение дальше
                throw new RuntimeException('Не удалось получить ключи подключения: ' . $e->getMessage());
            }
            return $storedKeys;
        }
    }

    /**
     * Собрать список ссылок (connection keys) из уже загруженных KeyActivateUser.
     * Используется для быстрого ответа подписке без дополнительных запросов к БД.
     * Подпись (fragment) в ссылках подменяется на «Локация · Протокол» для понятных названий в клиенте.
     */
    private function collectConnectionKeysFromKeyActivateUsers(\Illuminate\Support\Collection $keyActivateUsers): array
    {
        $connectionKeys = [];
        foreach ($keyActivateUsers as $kau) {
            $serverUser = $kau->serverUser;
            if (!$serverUser || empty($serverUser->keys)) {
                continue;
            }
            if (!$serverUser->relationLoaded('panel')) {
                $serverUser->load('panel.server.location');
            }
            $locationLabel = null;
            if ($serverUser->panel && $serverUser->panel->server) {
                $server = $serverUser->panel->server;
                $server->loadMissing('location');
                if ($server->location) {
                    $code = $server->location->code ?? '';
                    $locationLabel = $this->locationCodeToFullName($code);
                    if ($locationLabel === '') {
                        $locationLabel = $code ?: 'VPN';
                    }
                    $locationLabel = $this->locationLabelWithEmoji($server->location, $locationLabel);
                } elseif (!empty($server->name)) {
                    $locationLabel = $server->name;
                }
            }
            if ($locationLabel === null) {
                $locationLabel = 'VPN';
            }
            $stored = json_decode($serverUser->keys, true);
            if (!is_array($stored)) {
                continue;
            }
            try {
                $formatted = $this->formatConnectionKeys($stored, $locationLabel);
                foreach ($formatted as $key) {
                    $connectionKeys[] = stripslashes($key['link']);
                }
            } catch (\Throwable $e) {
                // При любой ошибке форматирования отдаём сырые ссылки без подмены подписи
                foreach ($stored as $rawLink) {
                    if (is_string($rawLink) && $rawLink !== '') {
                        $connectionKeys[] = stripslashes($rawLink);
                    }
                }
            }
        }
        return $connectionKeys;
    }

    /**
     * Запрос явно принимает HTML (браузер) — иначе считаем подпиской и отдаём быстро.
     */
    private function requestAcceptsHtml(): bool
    {
        $accept = strtolower(request()->header('Accept', ''));
        return str_contains($accept, 'text/html');
    }

    /**
     * В User-Agent есть типичная для браузера подпись с версией (Chrome/, Firefox/, Safari/, Edg/).
     * Используется чтобы отдавать HTML только явным браузерам, а не приложениям с Mozilla в UA.
     */
    private function hasVersionedBrowserInUserAgent(string $userAgent): bool
    {
        $ua = strtolower($userAgent);
        return str_contains($ua, 'chrome/') || str_contains($ua, 'firefox/')
            || str_contains($ua, 'safari/') || str_contains($ua, 'edg/')
            || str_contains($ua, 'opr/') || str_contains($ua, 'msie ');
    }

    /**
     * Определяет, является ли клиент VPN приложением (без учета регистра)
     */
    private function isVpnClient(string $userAgent): bool
    {
        $userAgentLower = strtolower($userAgent);
        if ($userAgentLower === '' || $userAgentLower === 'unknown') {
            return false;
        }

        $vpnPatterns = [
            'v2rayng', 'nekobox', 'nekoray', 'singbox', 'hiddify', 'hiddifynext', 'shadowrocket',
            'surge', 'quantumult', 'loon', 'streisand', 'clash', 'v2rayu', 'v2rayn',
            'v2rayx', 'qv2ray', 'trojan', 'wireguard', 'openvpn', 'openconnect',
            'softether', 'shadowsocks', 'shadowsocksr', 'ssr', 'outline', 'zerotier',
            'tailscale', 'windscribe', 'protonvpn', 'nordvpn', 'expressvpn', 'pritunl',
            'openwrt', 'dd-wrt', 'merlin', 'pivpn', 'algo', 'strongswan', 'ikev2',
            'ipsec', 'l2tp', 'pptp', 'v2raytun', 'happ', 'v2box', 'happproxy',
            'hexasoftware', 'v2rayg', 'anxray', 'kitsunebi', 'potatso', 'rocket',
            'pharos', 'stash', 'mellow', 'leaf', 'hysteria', 'tuic', 'naive', 'brook',
            'vnet', 'http injector', 'anonym', 'proxy', 'vpn', 'sub', 'subscribe',
            'subscription', 'hiddifynext'
        ];

        foreach ($vpnPatterns as $pattern) {
            if (str_contains($userAgentLower, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Определяет, является ли клиент браузером
     */
    private function isBrowserClient(string $userAgent): bool
    {
        $userAgentLower = strtolower($userAgent);

        // Список распространенных браузеров
        $browserPatterns = [
            'mozilla', 'chrome', 'safari', 'firefox', 'edge', 'opera', 'ie', 'trident',
            'webkit', 'gecko', 'netscape', 'maxthon', 'ucbrowser', 'vivaldi', 'yabrowser',
            'samsungbrowser'
        ];

        // Дополнительные признаки браузеров
        $hasBrowserHeaders = request()->header('Accept') &&
            str_contains(strtolower(request()->header('Accept')), 'text/html');

        $hasBrowserPattern = false;
        foreach ($browserPatterns as $pattern) {
            if (str_contains($userAgentLower, $pattern)) {
                $hasBrowserPattern = true;
                break;
            }
        }

        return $hasBrowserHeaders || $hasBrowserPattern;
    }

    /**
     * Показывает страницу ошибки (для локального просмотра)
     */
    public function showError(): Response
    {
        // В продакшене этот роут не должен быть доступен
        if (!app()->environment('local')) {
            abort(404);
        }

        return response()->view('vpn.error', [
            'message' => 'Конфигурация не найдена. Ключ может быть неактивен или удален.'
        ]);
    }

    /**
     * Показывает демо-страницу для локальной разработки
     */
    private function showDemoPage(string $key_activate_id): Response
    {
        // Демо-данные для локального просмотра
        $userInfo = [
            'username' => 'demo-user',
            'status' => 'active',
            'data_limit' => 100 * 1024 * 1024 * 1024, // 100 GB
            'data_limit_tariff' => 100 * 1024 * 1024 * 1024,
            'data_used' => 25.5 * 1024 * 1024 * 1024, // 25.5 GB
            'expiration_date' => time() + (30 * 24 * 60 * 60), // 30 дней
            'days_remaining' => 30
        ];

        // Демо-ключи подключения
        $demoKeys = [
            'vless://f83ca0f9-419c-4aa2-bb7e-47a82c900bef@77.238.239.214:2095?security=none&type=ws&headerType=&path=%2Fvless&host=#🚀%20Marz%20(12d21d3a-fe23-4c04-8ade-e316eac24fdf)%20[VLESS%20-%20ws]',
            'vmess://eyJhZGQiOiAiNzcuMjM4LjIzOS4yMTQiLCAiYWlkIjogIjAiLCAiaG9zdCI6ICIiLCAiaWQiOiAiMjBjYjJiZDMtMzMwYy00Y2NmLWFkZTItNjJlMjZjNmNlNzM5IiwgIm5ldCI6ICJ3cyIsICJwYXRoIjogIi92bWVzcyIsICJwb3J0IjogMjA5NiwgInBzIjogIlx1ZDgzZFx1ZGU4MCBNYXJ6ICgxMmQyMWQzYS1mZTIzLTRjMDQtOGFkZS1lMzE2ZWFjMjRmZGYpIFtWTWVzcyAtIHdzXSIsICJzY3kiOiAiYXV0byIsICJ0bHMiOiAibm9uZSIsICJ0eXBlIjogIiIsICJ2IjogIjIifQ==',
            'trojan://OaPcTZw8NomUQXfY@77.238.239.214:2097?security=none&type=ws&headerType=&path=%2Ftrojan&host=#🚀%20Marz%20(12d21d3a-fe23-4c04-8ade-e316eac24fdf)%20[Trojan%20-%20ws]',
            'ss://Y2hhY2hhMjAtaWV0Zi1wb2x5MTMwNTpVZnhLUG1oa3liRjhMdEQ0@77.238.239.214:2098#🚀%20Marz%20(12d21d3a-fe23-4c04-8ade-e316eac24fdf)%20[Shadowsocks%20-%20tcp]'
        ];

        $formattedKeys = $this->formatConnectionKeys($demoKeys);
        $botLink = '#';
        $netcheckUrl = route('netcheck.index');
        $isDemoMode = true; // Флаг для отображения демо-баннера

        // Создаем демо-нарушение для просмотра
        // Можно изменить violation_count через параметр ?violation=1,2,3 в URL для просмотра разных состояний
        $violationCount = request()->get('violation', 2); // По умолчанию показываем 2-е нарушение
        $violationCount = in_array((int)$violationCount, [1, 2, 3]) ? (int)$violationCount : 2;

        $demoViolation = new \App\Models\VPN\ConnectionLimitViolation([
            'violation_count' => $violationCount,
            'actual_connections' => 5,
            'allowed_connections' => 3,
            'ip_addresses' => ['192.168.1.1', '192.168.1.2', '10.0.0.1', '172.16.0.1', '192.168.1.3'],
            'status' => \App\Models\VPN\ConnectionLimitViolation::STATUS_ACTIVE,
            'notifications_sent' => $violationCount,
            'created_at' => now()->subHours(2),
            'last_notification_sent_at' => now()->subHours(1)
        ]);
        // Устанавливаем ID для корректной работы методов
        $demoViolation->id = 'demo-violation-' . $key_activate_id;
        $demoViolation->exists = true;

        // Создаем коллекцию с одним нарушением
        $violations = collect([$demoViolation]);

        // Для демо-режима: если параметр ?replaced=1, показываем перевыпущенный ключ
        $showReplaced = request()->get('replaced', 0) == 1;
        $replacedViolation = null;
        $newKeyActivate = null;
        $newKeyFormattedKeys = null;
        $newKeyUserInfo = null;

        if ($showReplaced) {
            // Создаем демо-нарушение с перевыпущенным ключом
            $replacedViolation = new \App\Models\VPN\ConnectionLimitViolation([
                'violation_count' => 3,
                'actual_connections' => 5,
                'allowed_connections' => 3,
                'ip_addresses' => ['192.168.1.1', '192.168.1.2', '10.0.0.1', '172.16.0.1', '192.168.1.3'],
                'status' => \App\Models\VPN\ConnectionLimitViolation::STATUS_RESOLVED,
                'notifications_sent' => 3,
                'created_at' => now()->subDays(2),
                'key_replaced_at' => now()->subHours(1),
                'replaced_key_id' => 'demo-new-key-' . $key_activate_id
            ]);
            $replacedViolation->id = 'demo-replaced-violation-' . $key_activate_id;
            $replacedViolation->exists = true;

            // Создаем демо-новый ключ
            $newKeyActivate = new \stdClass();
            $newKeyActivate->id = 'demo-new-key-' . $key_activate_id;
            $newKeyActivate->exists = true;

            // Используем те же ключи, но помечаем как новые
            $newKeyFormattedKeys = $formattedKeys;

            // Информация о новом ключе (те же данные, но можно изменить)
            $newKeyUserInfo = $userInfo;
        }

        Log::info('Showing demo page for local development', [
            'key_activate_id' => $key_activate_id,
            'show_replaced' => $showReplaced,
            'source' => 'vpn'
        ]);

        return response()->view('vpn.config', compact(
            'userInfo',
            'formattedKeys',
            'botLink',
            'netcheckUrl',
            'isDemoMode',
            'violations',
            'replacedViolation',
            'newKeyActivate',
            'newKeyFormattedKeys',
            'newKeyUserInfo'
        ));
    }

    /**
     * Показывает страницу для браузера
     * @param array $slotsWithLinks Массив [ ['location_label' => string, 'connection_keys' => array], ... ]
     * @param bool $useStoredOnly не дергать панель (userInfo из ключа), для быстрого первого отображения
     * @param bool $partialOnly вернуть только HTML блока контента (для ответа refresh)
     * @param string|null $configRefreshUrl не используется (оставлен для совместимости)
     * @param string|null $configRefreshUrlForButton URL для кнопки «Обновить»
     * @param \DateTimeInterface|null $lastUpdated время последнего обновления конфига в БД
     */
    private function showBrowserPage(KeyActivate $keyActivate, $keyActivateUser, $serverUser, $connectionKeys, array $slotsWithLinks = [], bool $useStoredOnly = false, bool $partialOnly = false, ?string $configRefreshUrl = null, ?string $configRefreshUrlForButton = null, $lastUpdated = null): Response
    {
        try {
            if (VpnConfigPageTrace::isActive()) {
                VpnConfigPageTrace::checkpoint('browser_page_enter', [
                    'useStoredOnly' => $useStoredOnly,
                    'partialOnly' => $partialOnly,
                    'key_activate_id' => $keyActivate->id,
                ]);
            }
            // При первом открытии (useStoredOnly) не дергаем БД и не обновляем статус — страница отдаётся быстрее; статус обновится по кнопке «Обновить».
            if (!$useStoredOnly) {
                $keyActivate->refresh();
                if (!$keyActivate->relationLoaded('packSalesman')) {
                    $keyActivate->load([
                        'packSalesman' => fn ($q) => $q->select('id', 'salesman_id', 'pack_id'),
                        'packSalesman.salesman' => fn ($q) => $q->select('id', 'telegram_id', 'bot_link', 'panel_id', 'module_bot_id'),
                    ]);
                }
                $keyActivate = $this->keyActivateService->checkAndUpdateStatus($keyActivate);
            } elseif (!$keyActivate->relationLoaded('packSalesman')) {
                $keyActivate->load([
                    'packSalesman' => fn ($q) => $q->select('id', 'salesman_id', 'pack_id'),
                    'packSalesman.salesman' => fn ($q) => $q->select('id', 'telegram_id', 'bot_link', 'panel_id', 'module_bot_id'),
                ]);
            }

            // Первая подгрузка /content (useStoredOnly=true): только БД — без HTTP к Marzban, иначе ответ 5–60+ с.
            // Реальный used_traffic/data_limit — после кнопки «Обновить» (refresh) или при полной странице без useStoredOnly.
            $info = [];
            $panelType = $serverUser && $serverUser->panel ? $serverUser->panel->panel : null;
            if ($panelType !== null && $panelType !== '' && !$useStoredOnly) {
                try {
                    $panel_strategy = new PanelStrategy($panelType);
                    $info = $panel_strategy->getSubscribeInfo($serverUser->panel->id, $serverUser->id);
                    if (isset($info['key_status_updated']) && $info['key_status_updated'] === true) {
                        $keyActivate->refresh();
                    }
                } catch (Exception $e) {
                    Log::warning('Error showing browser page: could not fetch subscribe info from panel', [
                        'error' => $e->getMessage(),
                        'key_activate_id' => $keyActivate->id,
                        'source' => 'vpn'
                    ]);
                }
            }
            if (VpnConfigPageTrace::isActive()) {
                VpnConfigPageTrace::checkpoint('browser_page_after_subscribe_block', [
                    'subscribe_info_keys' => array_keys($info ?? []),
                ]);
            }

            $packSalesman = $keyActivate->packSalesman ?? null;
            $salesman = $packSalesman->salesman ?? null;
            $finishAt = $keyActivate->finish_at ?? null;
            $daysRemaining = null;
            if ($finishAt && $finishAt > 0) {
                $daysRemaining = ceil(($finishAt - time()) / \App\Constants\TimeConstants::SECONDS_IN_DAY);
            }

            $userInfo = [
                'username' => $serverUser ? $serverUser->id : '',
                'status' => $info['status'] ?? 'active',
                'data_limit' => $info['data_limit'] ?? ($keyActivate->traffic_limit ?? 0),
                'data_limit_tariff' => $keyActivate->traffic_limit ?? 0,
                'data_used' => $info['used_traffic'] ?? 0,
                'expiration_date' => $finishAt,
                'days_remaining' => $daysRemaining
            ];

            // Форматируем ключи для отображения (плоский список для обратной совместимости)
            $firstLocationLabel = (count($slotsWithLinks) === 1) ? ($slotsWithLinks[0]['location_label'] ?? null) : null;
            $formattedKeys = $this->formatConnectionKeys($connectionKeys, $firstLocationLabel);
            // Группировка по локации/серверу (массив групп — без перезаписи, все протоколы сохраняются)
            $formattedKeysGrouped = [];
            foreach ($slotsWithLinks as $slot) {
                $formattedKeysGrouped[] = [
                    'label' => $slot['location_label'],
                    'flag_code' => $slot['location_code'] ?? '',
                    'keys'  => $this->formatConnectionKeys($slot['connection_keys'], $slot['location_label'] ?? null),
                ];
            }

            // Добавляем ссылку на бота
            $botLink = $salesman->bot_link ?? '#';

            // Добавляем ссылку на страницу проверки качества сети
            $netcheckUrl = route('netcheck.index');
            $isDemoMode = false; // Это реальная страница, не демо

            // Первый фрагмент из БД (/content): без нарушений — меньше SQL; блок нарушений после «Обновить» (refresh).
            if ($useStoredOnly && $partialOnly) {
                $violations = collect();
                $replacedViolation = null;
            } elseif ($keyActivate->relationLoaded('activeViolations') && $keyActivate->relationLoaded('replacedViolation')) {
                $violations = $keyActivate->activeViolations;
                $replacedViolation = $keyActivate->replacedViolation;
            } else {
                $violations = $keyActivate->relationLoaded('activeViolations')
                    ? $keyActivate->activeViolations
                    : ConnectionLimitViolation::query()
                        ->where('key_activate_id', $keyActivate->id)
                        ->where('status', ConnectionLimitViolation::STATUS_ACTIVE)
                        ->whereNull('key_replaced_at')
                        ->orderByDesc('created_at')
                        ->get();
                $replacedViolation = $keyActivate->relationLoaded('replacedViolation')
                    ? $keyActivate->replacedViolation
                    : ConnectionLimitViolation::query()
                        ->where('key_activate_id', $keyActivate->id)
                        ->whereNotNull('key_replaced_at')
                        ->whereNotNull('replaced_key_id')
                        ->orderByDesc('key_replaced_at')
                        ->first();
            }
            if (VpnConfigPageTrace::isActive()) {
                VpnConfigPageTrace::checkpoint('browser_page_after_violations', [
                    'violations_count' => isset($violations) ? $violations->count() : 0,
                    'has_replaced' => (bool) ($replacedViolation ?? null),
                ]);
            }

            $newKeyActivate = null;
            $newKeyFormattedKeys = null;
            $newKeyUserInfo = null;

            if ($replacedViolation && $replacedViolation->replaced_key_id && !$useStoredOnly) {
                $newKeyActivate = $this->keyActivateRepository->findById($replacedViolation->replaced_key_id);

                if ($newKeyActivate) {
                    $newKeyActivate->load([
                        'packSalesman' => fn ($q) => $q->select('id', 'salesman_id', 'pack_id'),
                        'packSalesman.salesman' => fn ($q) => $q->select('id', 'telegram_id', 'bot_link', 'panel_id', 'module_bot_id'),
                    ]);
                    $newKeyActivate = $this->keyActivateService->checkAndUpdateStatus($newKeyActivate);
                    $newKeyActivateUser = $this->keyActivateUserRepository->findByKeyActivateIdWithRelations($newKeyActivate->id);

                    if ($newKeyActivateUser && $newKeyActivateUser->serverUser) {
                        $newServerUser = $newKeyActivateUser->serverUser;
                        try {
                            $newConnectionKeys = $this->getFreshUserLinks($newServerUser);

                            if ($newConnectionKeys) {
                                $newLocationLabel = 'VPN';
                                if ($newServerUser->panel && $newServerUser->panel->server) {
                                    $newServer = $newServerUser->panel->server;
                                    $newServer->loadMissing('location');
                                    if ($newServer->location) {
                                        $code = $newServer->location->code ?? '';
                                        $newLocationLabel = $this->locationCodeToFullName($code);
                                        if ($newLocationLabel === '') {
                                            $newLocationLabel = $code ?: 'VPN';
                                        }
                                        $newLocationLabel = $this->locationLabelWithEmoji($newServer->location, $newLocationLabel);
                                    } elseif (!empty($newServer->name)) {
                                        $newLocationLabel = $newServer->name;
                                    }
                                }
                                $newKeyFormattedKeys = $this->formatConnectionKeys($newConnectionKeys, $newLocationLabel);

                                // Получаем информацию о подписке для нового ключа
                                $panel_strategy = new PanelStrategy($newServerUser->panel->panel);
                                $newInfo = $panel_strategy->getSubscribeInfo($newServerUser->panel->id, $newServerUser->id);

                                // Если статус нового ключа был обновлен в getUserSubscribeInfo, перезагружаем модель
                                if (isset($newInfo['key_status_updated']) && $newInfo['key_status_updated'] === true) {
                                    $newKeyActivate->refresh();
                                }

                                $newFinishAt = $newKeyActivate->finish_at ?? null;
                                $newDaysRemaining = null;
                                if ($newFinishAt && $newFinishAt > 0) {
                                    $newDaysRemaining = ceil(($newFinishAt - time()) / \App\Constants\TimeConstants::SECONDS_IN_DAY);
                                }

                                $newKeyUserInfo = [
                                    'username' => $newServerUser->id,
                                    'status' => $newInfo['status'] ?? 'unknown',
                                    'data_limit' => $newInfo['data_limit'] ?? 0,
                                    'data_limit_tariff' => $newKeyActivate->traffic_limit ?? 0,
                                    'data_used' => $newInfo['used_traffic'] ?? 0,
                                    'expiration_date' => $newFinishAt,
                                    'days_remaining' => $newDaysRemaining
                                ];
                            }
                        } catch (Exception $e) {
                            Log::error('Ошибка при получении конфигурации нового ключа', [
                                'new_key_id' => $newKeyActivate->id,
                                'error' => $e->getMessage(),
                                'source' => 'vpn'
                            ]);
                        }
                    }
                }
            }

            $viewData = compact(
                'keyActivate',
                'userInfo',
                'formattedKeys',
                'formattedKeysGrouped',
                'botLink',
                'netcheckUrl',
                'isDemoMode',
                'violations',
                'replacedViolation',
                'newKeyActivate',
                'newKeyFormattedKeys',
                'newKeyUserInfo'
            );
            if (!$partialOnly) {
                $viewData['configRefreshUrlForButton'] = $configRefreshUrlForButton;
                $viewData['configLastUpdated'] = $lastUpdated ? $lastUpdated->format('d.m.Y H:i') : null;
            }
            if (VpnConfigPageTrace::isActive()) {
                VpnConfigPageTrace::checkpoint('browser_page_before_render', ['partialOnly' => $partialOnly]);
            }
            if ($partialOnly) {
                return response(view('vpn.partials.config-content', $viewData)->render())
                    ->header('Content-Type', 'text/html; charset=UTF-8');
            }
            return response()->view('vpn.config', $viewData);

        } catch (Exception $e) {
            if (VpnConfigPageTrace::isActive()) {
                VpnConfigPageTrace::checkpoint('browser_page_Exception', [
                    'error' => $e->getMessage(),
                    'class' => get_class($e),
                ]);
            }
            Log::error('Error showing browser page:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'source' => 'vpn'
            ]);

            // В случае ошибки при подготовке страницы показываем страницу ошибки
            return response()->view('vpn.error', [
                'message' => 'Конфигурация VPN не найдена. Пожалуйста, проверьте правильность ссылки или обратитесь в поддержку.'
            ]);
        }
    }


//    /**
//     * @param string $key_activate_id
//     * @return Response
//     * @throws GuzzleException
//     */
//    public function show(string $key_activate_id): Response
//    {
//        try {
//            // Получаем запись key_activate_user с отношениями
//            $keyActivateUser = $this->keyActivateUserRepository->findByKeyActivateIdWithRelations($key_activate_id);
//            // Получаем информацию о пользователе сервера
//            $serverUser = $this->serverUserRepository->findById($keyActivateUser->server_user_id);
//
//            if (!$serverUser) {
//                throw new RuntimeException('Server user not found');
//            }
//
//            // Декодируем ключи подключения
//            $connectionKeys = json_decode($serverUser->keys, true);
//
//            if (!$connectionKeys) {
//                throw new RuntimeException('Invalid connection keys format');
//            }
//
//            $userAgent = request()->header('User-Agent') ?? 'Unknown';
//            Log::warning('Incoming request with User-Agent:', ['User-Agent' => $userAgent]);
//
//            // Проверяем User-Agent на наличие клиентов VPN
//            $userAgent = strtolower(request()->header('User-Agent') ?? '');
//            $isVpnClient = str_contains($userAgent, 'v2rayng') || // V2RayNG (Android)
//                str_contains($userAgent, 'nekobox') || // NekoBox (Android)
//                str_contains($userAgent, 'nekoray') || // NekoRay (Windows)
//                str_contains($userAgent, 'singbox') || // Sing-Box (кроссплатформенный)
//                str_contains($userAgent, 'hiddify') || // Hiddify (кроссплатформенный)
//                str_contains($userAgent, 'shadowrocket') || // Shadowrocket (iOS)
//                str_contains($userAgent, 'surge') || // Surge (iOS/macOS)
//                str_contains($userAgent, 'quantumult') || // Quantumult (iOS)
//                str_contains($userAgent, 'quantumult x') || // Quantumult X (iOS)
//                str_contains($userAgent, 'loon') || // Loon (iOS)
//                str_contains($userAgent, 'streisand') || // Streisand (кроссплатформенный)
//                str_contains($userAgent, 'clash') || // Clash (кроссплатформенный)
//                str_contains($userAgent, 'clashx') || // ClashX (macOS)
//                str_contains($userAgent, 'clash for windows') || // Clash for Windows
//                str_contains($userAgent, 'clash.android') || // Clash for Android
//                str_contains($userAgent, 'clash.meta') || // Clash.Meta (кроссплатформенный)
//                str_contains($userAgent, 'v2rayu') || // V2RayU (macOS)
//                str_contains($userAgent, 'v2rayn') || // V2RayN (Windows)
//                str_contains($userAgent, 'v2rayx') || // V2RayX (macOS)
//                str_contains($userAgent, 'qv2ray') || // Qv2ray (кроссплатформенный)
//                str_contains($userAgent, 'trojan') || // Trojan clients (общий)
//                str_contains($userAgent, 'trojan-go') || // Trojan-Go clients
//                str_contains($userAgent, 'wireguard') || // WireGuard clients
//                str_contains($userAgent, 'openvpn') || // OpenVPN clients
//                str_contains($userAgent, 'openconnect') || // OpenConnect clients
//                str_contains($userAgent, 'softether') || // SoftEther VPN clients
//                str_contains($userAgent, 'shadowsocks') || // Shadowsocks clients
//                str_contains($userAgent, 'shadowsocksr') || // ShadowsocksR clients
//                str_contains($userAgent, 'ssr') || // SSR clients
//                str_contains($userAgent, 'outline') || // Outline clients
//                str_contains($userAgent, 'zerotier') || // ZeroTier clients
//                str_contains($userAgent, 'tailscale') || // Tailscale clients
//                str_contains($userAgent, 'windscribe') || // Windscribe clients
//                str_contains($userAgent, 'protonvpn') || // ProtonVPN clients
//                str_contains($userAgent, 'nordvpn') || // NordVPN clients
//                str_contains($userAgent, 'expressvpn') || // ExpressVPN clients
//                str_contains($userAgent, 'pritunl') || // Pritunl clients
//                str_contains($userAgent, 'openwrt') || // OpenWRT (роутеры с VPN)
//                str_contains($userAgent, 'dd-wrt') || // DD-WRT (роутеры с VPN)
//                str_contains($userAgent, 'merlin') || // Asus Merlin (роутеры с VPN)
//                str_contains($userAgent, 'pivpn') || // PiVPN (Raspberry Pi)
//                str_contains($userAgent, 'algo') || // Algo VPN
//                str_contains($userAgent, 'strongswan') || // StrongSwan clients
//                str_contains($userAgent, 'ikev2') || // IKEv2 clients
//                str_contains($userAgent, 'ipsec') || // IPSec clients
//                str_contains($userAgent, 'l2tp') || // L2TP clients
//                str_contains($userAgent, 'pptp') || // PPTP clients
//                str_contains($userAgent, 'v2raytun') || // PPTP clients
//                str_contains($userAgent, 'Happ') || // PPTP clients
//                str_contains($userAgent, 'happ') || // PPTP clients
//                str_contains($userAgent, 'V2Box') || // PPTP clients
//                str_contains($userAgent, 'happproxy') || // Happy Proxy (Android)
//                str_contains($userAgent, 'hexasoftware') || // V2Box (Android)
//                str_contains($userAgent, 'v2box') || // V2Box (Android)
//                str_contains($userAgent, 'v2rayg') || // V2RayG (клиенты)
//                str_contains($userAgent, 'anxray') || // AnXray (Android)
//                str_contains($userAgent, 'kitsunebi') || // Kitsunebi (iOS)
//                str_contains($userAgent, 'potatso') || // Potatso (iOS)
//                str_contains($userAgent, 'rocket') || // Общий для Rocket клиентов
//                str_contains($userAgent, 'pharos') || // Pharos (iOS)
//                str_contains($userAgent, 'stash') || // Stash (iOS)
//                str_contains($userAgent, 'mellow') || // Mellow (клиенты)
//                str_contains($userAgent, 'leaf') || // Leaf (клиенты)
//                str_contains($userAgent, 'hysteria') || // Hysteria (клиенты)
//                str_contains($userAgent, 'tuic') || // TUIC (клиенты)
//                str_contains($userAgent, 'naive') || // NaiveProxy (клиенты)
//                str_contains($userAgent, 'brook') || // Brook (клиенты)
//                str_contains($userAgent, 'vnet') || // VNet (клиенты)
//                str_contains($userAgent, 'http injector') || // HTTP Injector (Android)
//                str_contains($userAgent, 'anonym') || // Анонимайзеры
//                str_contains($userAgent, 'proxy') || // Прокси клиенты
//                str_contains($userAgent, 'vpn') || // Общий для VPN клиентов
//                str_contains($userAgent, 'sub') || // Для подписочных клиентов
//                str_contains($userAgent, 'subscribe'); // Для подписочных клиентов
//
//            if ($isVpnClient || request()->wantsJson()) {
//                Log::warning('ВОТ ЭТО ВЕРНУЛИ:', ['ВОТ ЭТО ВЕРНУЛИ' => response(implode("\n", $connectionKeys))
//                    ->header('Content-Type', 'text/plain')]);
//                // Для VPN клиентов возвращаем строку с конфигурациями
//                return response(implode("\n", $connectionKeys))
//                    ->header('Content-Type', 'text/plain');
//            }
//
//            $panel_strategy = new PanelStrategy($serverUser->panel->panel);
//            // Для браузера показываем HTML страницу
//            $userInfo = [
//                'username' => $serverUser->id,
//                'status' => $info['status'],
//                'data_limit' => $info['data_limit'],
//                'data_limit_tariff' => $keyActivateUser->keyActivate->traffic_limit ?? 0,
//                'data_used' => $info['used_traffic'],
//                'expiration_date' => $keyActivateUser->keyActivate->finish_at ?? null,
//                'days_remaining' => $keyActivateUser->keyActivate->finish_at ? ceil(($keyActivateUser->keyActivate->finish_at - time()) / 86400) : null
//            ];
//
//            // Форматируем ключи для отображения
//            $formattedKeys = $this->formatConnectionKeys($connectionKeys);
//
//            // Добавляем ссылку на бота
//            $botLink = $keyActivateUser->keyActivate->packSalesman->salesman->bot_link ?? '#';
//
//            return response()->view('vpn.config', compact('userInfo', 'formattedKeys', 'botLink'));
//        } catch (Exception $e) {
//            Log::error('Error showing VPN config', [
//                'key_activate_id' => $key_activate_id,
//                'error' => $e->getMessage()
//            ]);
//
//            if (request()->wantsJson()) {
//                return response()->json([
//                    'status' => 'error',
//                    'message' => 'Configuration not found'
//                ], 404);
//            }
//
//            return response()->view('vpn.error', [
//                'message' => 'Не удалось загрузить конфигурацию VPN. Пожалуйста, проверьте правильность ссылки.'
//            ]);
//        }
//    }

    /**
     * Преобразует короткий код эмодзи локации (:nl:, :ru:) в символ флага (🇳🇱, 🇷🇺).
     * Если в БД уже записан Unicode-флаг — возвращает как есть.
     *
     * @param string $emoji Значение из location.emoji (например :nl: или 🇳🇱)
     * @return string
     */
    private function locationEmojiToFlag(string $emoji): string
    {
        $emoji = trim($emoji);
        // Уже символ флага (два regional indicator) — не трогаем
        if (mb_strlen($emoji) >= 2 && preg_match('/[\x{1F1E6}-\x{1F1FF}]/u', $emoji)) {
            return $emoji;
        }
        // Формат :xx: — две буквы кода страны → Unicode флаг (regional indicators)
        if (preg_match('/^:([a-z]{2}):$/i', $emoji, $m)) {
            $code = strtoupper($m[1]);
            if (function_exists('mb_chr')) {
                $c1 = $code[0];
                $c2 = $code[1];
                if ($c1 >= 'A' && $c1 <= 'Z' && $c2 >= 'A' && $c2 <= 'Z') {
                    return mb_chr(0x1F1E6 + ord($c1) - 65) . mb_chr(0x1F1E6 + ord($c2) - 65);
                }
            }
            // Fallback: известные коды из вашей БД
            $flags = ['NL' => "\xF0\x9F\x87\xB3\xF0\x9F\x87\xB1", 'RU' => "\xF0\x9F\x87\xB7\xF0\x9F\x87\xBA"];
            if (isset($flags[$code])) {
                return $flags[$code];
            }
        }
        return $emoji;
    }

    /**
     * Полное название локации по коду (NL → Нидерланды, RU → Россия).
     *
     * @param string $code location.code из БД
     * @return string Полное название или пустая строка, если код неизвестен
     */
    private function locationCodeToFullName(string $code): string
    {
        $code = strtoupper(trim($code));
        $names = [
            'NL' => 'Нидерланды',
            'RU' => 'Россия',
            'DE' => 'Германия',
            'US' => 'США',
            'FR' => 'Франция',
            'GB' => 'Великобритания',
            'FI' => 'Финляндия',
            'SG' => 'Сингапур',
        ];
        return $names[$code] ?? '';
    }

    /**
     * Нормализация названия локации для заголовка раздела (исправление опечаток).
     *
     * @param string $name Название из location или сервера
     * @return string
     */
    private function normalizeLocationLabelName(string $name): string
    {
        $typos = [
            'Финлядния' => 'Финляндия',
        ];
        return $typos[$name] ?? $name;
    }

    /**
     * Добавить флаг (emoji) к подписи локации, если он задан в БД.
     * Поддерживает формат :xx: (код страны) → Unicode-флаг и уже готовые emoji.
     */
    private function locationLabelWithEmoji(?\App\Models\Location\Location $location, string $label): string
    {
        if (!$location || empty(trim((string) $location->emoji))) {
            return $label;
        }
        $emoji = $this->locationEmojiToUnicode(trim($location->emoji));
        return $emoji !== '' ? $emoji . ' ' . $label : $label;
    }

    /**
     * Преобразовать emoji локации в Unicode-флаг для отображения.
     * :nl: → 🇳🇱, :fi: → 🇫🇮; если уже флаг или другой emoji — вернуть как есть.
     */
    private function locationEmojiToUnicode(string $emoji): string
    {
        if (preg_match('/^:([a-z]{2}):$/i', $emoji, $m)) {
            $code = strtoupper($m[1]);
            // Regional indicator symbols: A = U+1F1E6, B = U+1F1E7, ... Z = U+1F1FF
            $a = ord('A');
            $base = 0x1F1E6;
            $c1 = mb_chr($base + (ord($code[0]) - $a));
            $c2 = mb_chr($base + (ord($code[1]) - $a));
            return $c1 . $c2;
        }
        return $emoji;
    }

    /**
     * Заменить подпись (fragment после #) в ссылке протокола.
     * Эта подпись отображается в VPN-клиенте (v2rayNG, Nekoray и т.д.) как название конфигурации.
     *
     * @param string $link Ссылка (vless://..., vmess://... и т.д.)
     * @param string $remark Новая подпись, например "Финляндия #1 · VLESS TCP"
     * @return string Ссылка с обновлённым fragment (для вставки в HTML по-прежнему с addslashes)
     */
    private function setLinkRemark(string $link, string $remark): string
    {
        $link = stripslashes((string) $link);
        if ($link === '') {
            return addslashes($link);
        }
        $remark = trim((string) $remark);
        $hashPos = strpos($link, '#');
        $base = $hashPos !== false ? substr($link, 0, $hashPos) : $link;
        $newLink = $remark !== '' ? $base . '#' . rawurlencode($remark) : $base;
        return addslashes($newLink);
    }

    /**
     * Format connection keys for display
     *
     * @param array $connectionKeys Массив сырых ссылок
     * @param string|null $locationLabel Подпись локации для отображения в клиенте (например "Финляндия #1"). Если задана, подменяет стандартную подпись Marz (uuid) на понятную.
     * @return array
     */
    private function formatConnectionKeys(array $connectionKeys, ?string $locationLabel = null): array
    {
        $protocolDescriptions = [
            'vless' => [
                'name' => 'VLESS',
                'icon' => 'V'
            ],
            'vmess' => [
                'name' => 'VMess',
                'icon' => 'VM'
            ],
            'trojan' => [
                'name' => 'Trojan',
                'icon' => 'T'
            ],
            'shadowsocks' => [
                'name' => 'Shadowsocks',
                'icon' => 'SS'
            ]
        ];

        $formattedKeys = [];
        foreach ($connectionKeys as $configString) {
            // Удаляем экранирование слешей
            $configString = stripslashes($configString);

            if (preg_match('/^(vless|vmess|trojan|ss):\/\//', $configString, $matches)) {
                $protocol = $matches[1];
                if ($protocol === 'ss') {
                    $protocol = 'shadowsocks';
                }

                $protocolInfo = $protocolDescriptions[strtolower($protocol)] ?? [
                    'name' => strtoupper($protocol),
                    'icon' => substr(strtoupper($protocol), 0, 1)
                ];

                // Извлекаем тип подключения из комментария (например [VLESS - tcp] -> "tcp")
                preg_match('/\[(.*?)\]$/', $configString, $typeMatches);
                $connectionType = $typeMatches[1] ?? '';

                $link = addslashes($configString);
                if ($locationLabel !== null && $locationLabel !== '') {
                    $remark = $locationLabel . ' · ' . $protocolInfo['name'] . ($connectionType !== '' ? ' ' . $connectionType : '');
                    $link = $this->setLinkRemark($configString, $remark);
                }

                $formattedKeys[] = [
                    'protocol' => $protocolInfo['name'],
                    'icon' => $protocolInfo['icon'],
                    'link' => $link,
                    'connection_type' => $connectionType
                ];
            }
        }

        return $formattedKeys;
    }
}

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
        // Лимит памяти для отображения конфигурации (совпадает с bootstrap, чтобы не падать при тяжёлых связях)
        if ((int) ini_get('memory_limit') < 512) {
            @ini_set('memory_limit', '512M');
        }

        try {
            // Если запрошен роут /config/error, перенаправляем на метод showError
            if ($key_activate_id === 'error') {
                return $this->showError();
            }

            // Сначала находим KeyActivate по ID (это ID из таблицы key_activate)
            $keyActivate = $this->keyActivateRepository->findById($key_activate_id);

            // Если KeyActivate не найден
            if (!$keyActivate) {
                // Демо-страница ТОЛЬКО в локальной среде с включенным debug
                // Во всех остальных случаях (включая продакшен) показываем ошибку
                $showDemo = app()->environment('local') && config('app.debug', false);

                if ($showDemo) {
                    return $this->showDemoPage($key_activate_id);
                }

                // В продакшене или при любых сомнениях показываем ошибку
                if (request()->wantsJson()) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Configuration not found'
                    ], 404);
                }

                return response()->view('vpn.error', [
                    'message' => 'Конфигурация VPN не найдена. Пожалуйста, проверьте правильность ссылки или обратитесь в поддержку.'
                ]);
            }

            // Загружаем отношения для KeyActivate (только нужные поля)
            $keyActivate->load([
                'packSalesman' => function($query) {
                    $query->select('id', 'salesman_id', 'pack_id');
                },
                'packSalesman.salesman' => function($query) {
                    $query->select('id', 'telegram_id', 'bot_link', 'panel_id', 'module_bot_id');
                }
            ]);

            // Проверяем, был ли ключ заменен из-за нарушения (даже если ключ просрочен)
            // Это нужно проверить ДО проверки keyActivateUser, чтобы показать информацию о замене
            $replacedViolation = \App\Models\VPN\ConnectionLimitViolation::where('key_activate_id', $key_activate_id)
                ->whereNotNull('key_replaced_at')
                ->whereNotNull('replaced_key_id')
                ->orderBy('key_replaced_at', 'desc')
                ->first();

            // Все слоты ключа (один — старые ключи, несколько — мульти-провайдер)
            $keyActivateUsers = $this->keyActivateUserRepository->findAllByKeyActivateId($key_activate_id);

            if ($keyActivateUsers->isEmpty()) {
                Log::warning('KeyActivateUser not found for KeyActivate', [
                    'key_activate_id' => $key_activate_id,
                    'source' => 'vpn'
                ]);

                // Если ключ был заменен, показываем информацию о замене даже без keyActivateUser
                if ($replacedViolation && $replacedViolation->replaced_key_id) {
                    $newKey = $this->keyActivateRepository->findById($replacedViolation->replaced_key_id);
                    
                    if ($newKey) {
                        Log::info('Key was replaced, showing replacement info even without keyActivateUser', [
                            'old_key_id' => $key_activate_id,
                            'new_key_id' => $replacedViolation->replaced_key_id,
                            'violation_id' => $replacedViolation->id,
                            'source' => 'vpn'
                        ]);

                        return response()->view('vpn.error', [
                            'message' => 'Ваш ключ доступа был заменен из-за нарушения лимита подключений. Пожалуйста, используйте новый ключ.',
                            'replacedKeyId' => $replacedViolation->replaced_key_id
                        ]);
                    }
                }

                if (app()->environment('local') && config('app.debug', false)) {
                    return $this->showDemoPage($key_activate_id);
                }

                return response()->view('vpn.error', [
                    'message' => 'Конфигурация VPN не найдена. Пожалуйста, проверьте правильность ссылки или обратитесь в поддержку.'
                ]);
            }

            // Недостающие провайдер-слоты добавляем в фоне (очередь), чтобы не тормозить страницу.
            // При QUEUE_CONNECTION=sync джоб выполнится в том же запросе — страница будет медленной; используйте database/redis и воркер.
            $multiProviderSlots = config('panel.multi_provider_slots', []);
            if (!empty($multiProviderSlots) && is_array($multiProviderSlots) && $keyActivate->status === KeyActivate::ACTIVE) {
                $slotCount = $keyActivateUsers->count();
                $providerCount = count($multiProviderSlots);
                if ($providerCount > 0 && $slotCount < $providerCount && config('queue.default') !== 'sync') {
                    $cacheKey = 'vpn_config_multi_provider_checked_' . $key_activate_id;
                    if (!Cache::has($cacheKey)) {
                        AddMissingSlotsForKeyJob::dispatch($key_activate_id);
                        Cache::put($cacheKey, 1, now()->addMinutes(10));
                    }
                }
            }

            // Собираем ссылки по слотам (для браузера — группировка по локации/серверу)
            $connectionKeys = [];
            $slotsWithLinks = [];
            $firstKeyActivateUser = null;
            $firstServerUser = null;
            $locationCounts = [];

            foreach ($keyActivateUsers as $kau) {
                $serverUser = $kau->serverUser;
                if (!$serverUser && $kau->server_user_id) {
                    $serverUser = ServerUser::with(['panel.server.location'])
                        ->find($kau->server_user_id);
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
                        'source' => 'vpn'
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
                    }
                } catch (\App\Exceptions\KeyReplacedException $e) {
                    throw $e;
                } catch (Exception $e) {
                    Log::warning('Failed to get fresh links for one slot, using stored', [
                        'key_activate_user_id' => $kau->id,
                        'server_user_id' => $serverUser->id,
                        'error' => $e->getMessage(),
                        'source' => 'vpn'
                    ]);
                    $stored = json_decode($serverUser->keys, true);
                    if (!empty($stored)) {
                        $slotLinks = $stored;
                        $connectionKeys = array_merge($connectionKeys, $stored);
                    }
                }
                if (!empty($slotLinks)) {
                    $server = $serverUser->panel && $serverUser->panel->server ? $serverUser->panel->server : null;
                    $location = $server && $server->relationLoaded('location') ? $server->location : null;
                    $locationCode = ''; // двухбуквенный код для картинки флага (nl, ru)
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
                    $locKey = ($location ? $location->id : 0) . '_' . ($server ? $server->id : 0) . '_' . ($serverUser->panel_id ?? 0);
                    $locationCounts[$locKey] = isset($locationCounts[$locKey]) ? $locationCounts[$locKey] + 1 : 1;
                    $suffix = $locationCounts[$locKey] > 1 ? ' #' . $locationCounts[$locKey] : '';
                    $slotsWithLinks[] = [
                        'location_label'  => $name . $suffix,
                        'location_code'   => $locationCode,
                        'connection_keys' => $slotLinks,
                    ];
                }
            }

            if (empty($connectionKeys)) {
                throw new RuntimeException('Invalid connection keys format');
            }
            if ($firstKeyActivateUser === null) {
                $firstKeyActivateUser = $keyActivateUsers->first();
                $firstServerUser = $firstKeyActivateUser->serverUser ?? $this->serverUserRepository->findById($firstKeyActivateUser->server_user_id);
            }

            $userAgent = request()->header('User-Agent') ?? 'Unknown';

            // Определяем тип клиента
            $isVpnClient = $this->isVpnClient($userAgent);
            $isBrowser = $this->isBrowserClient($userAgent);

            // Если это VPN клиент или запрос JSON - возвращаем конфигурацию
            if ($isVpnClient || request()->wantsJson()) {
                return response(implode("\n", $connectionKeys))
                    ->header('Content-Type', 'text/plain');
            }

            // Если это браузер - показываем HTML страницу (с группировкой протоколов по локации)
            if ($isBrowser) {
                return $this->showBrowserPage($keyActivate, $firstKeyActivateUser, $firstServerUser, $connectionKeys, $slotsWithLinks);
            }

            // По умолчанию для неизвестных клиентов возвращаем конфигурацию
            return response(implode("\n", $connectionKeys))
                ->header('Content-Type', 'text/plain');

        } catch (\App\Exceptions\KeyReplacedException $e) {
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
        }
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

            $panelStrategyFactory = new \App\Services\Panel\PanelStrategyFactory();
            $panelStrategy = $panelStrategyFactory->create($panel->panel);

            // Обновляем токен через стратегию
            $panel = $panelStrategy->updateToken($panel->id);

            $marzbanApi = new MarzbanAPI($panel->api_address);

            // Получаем актуальные данные пользователя из Marzban
            $userData = $marzbanApi->getUser($panel->auth_token, $serverUser->id);

            // Если links есть, но их мало (меньше 10 для REALITY), обновляем пользователя с правильными inbounds
            if (!empty($userData['links']) && $panel->config_type === 'reality' && count($userData['links']) < 10) {
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
     * Определяет, является ли клиент VPN приложением (без учета регистра)
     */
    private function isVpnClient(string $userAgent): bool
    {
        $userAgentLower = strtolower($userAgent);

        $vpnPatterns = [
            'v2rayng', 'nekobox', 'nekoray', 'singbox', 'hiddify', 'shadowrocket',
            'surge', 'quantumult', 'loon', 'streisand', 'clash', 'v2rayu', 'v2rayn',
            'v2rayx', 'qv2ray', 'trojan', 'wireguard', 'openvpn', 'openconnect',
            'softether', 'shadowsocks', 'shadowsocksr', 'ssr', 'outline', 'zerotier',
            'tailscale', 'windscribe', 'protonvpn', 'nordvpn', 'expressvpn', 'pritunl',
            'openwrt', 'dd-wrt', 'merlin', 'pivpn', 'algo', 'strongswan', 'ikev2',
            'ipsec', 'l2tp', 'pptp', 'v2raytun', 'happ', 'v2box', 'happproxy',
            'hexasoftware', 'v2rayg', 'anxray', 'kitsunebi', 'potatso', 'rocket',
            'pharos', 'stash', 'mellow', 'leaf', 'hysteria', 'tuic', 'naive', 'brook',
            'vnet', 'http injector', 'anonym', 'proxy', 'vpn', 'sub', 'subscribe'
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
     * @param array $slotsWithLinks Массив [ ['location_label' => string, 'connection_keys' => array], ... ] для группировки по локации
     */
    private function showBrowserPage(KeyActivate $keyActivate, $keyActivateUser, $serverUser, $connectionKeys, array $slotsWithLinks = []): Response
    {
        try {
            // Обновляем модель из базы данных, чтобы получить актуальные данные
            $keyActivate->refresh();

            // Загружаем отношения заново (только нужные поля)
            if (!$keyActivate->relationLoaded('packSalesman')) {
                $keyActivate->load([
                    'packSalesman' => function($query) {
                        $query->select('id', 'salesman_id', 'pack_id');
                    },
                    'packSalesman.salesman' => function($query) {
                        $query->select('id', 'telegram_id', 'bot_link', 'panel_id', 'module_bot_id');
                    }
                ]);
            }

            // ШАГ 1: Проверяем finish_at из БД (локальная дата, может быть изменена в админке)
            $keyActivate = $this->keyActivateService->checkAndUpdateStatus($keyActivate);

            // ШАГ 2: Получаем данные из Marzban API (expire из панели). При ошибке (cURL 18, таймаут) показываем страницу по сохранённым данным.
            $info = [];
            try {
                $panel_strategy = new PanelStrategy($serverUser->panel->panel);
                $info = $panel_strategy->getSubscribeInfo($serverUser->panel->id, $serverUser->id);
                if (isset($info['key_status_updated']) && $info['key_status_updated'] === true) {
                    $keyActivate->refresh();
                }
            } catch (Exception $e) {
                Log::warning('Error showing browser page: could not fetch subscribe info from panel, using stored data', [
                    'error' => $e->getMessage(),
                    'key_activate_id' => $keyActivate->id,
                    'panel_id' => $serverUser->panel_id,
                    'source' => 'vpn'
                ]);
            }

            // Получаем данные из KeyActivate (который уже загружен с отношениями)
            $packSalesman = $keyActivate->packSalesman ?? null;
            $salesman = $packSalesman->salesman ?? null;

            $finishAt = $keyActivate->finish_at ?? null;

            $daysRemaining = null;
            if ($finishAt && $finishAt > 0) {
                $daysRemaining = ceil(($finishAt - time()) / \App\Constants\TimeConstants::SECONDS_IN_DAY);
            }

            $userInfo = [
                'username' => $serverUser->id,
                'status' => $info['status'] ?? 'active',
                'data_limit' => $info['data_limit'] ?? ($keyActivate->traffic_limit ?? 0),
                'data_limit_tariff' => $keyActivate->traffic_limit ?? 0,
                'data_used' => $info['used_traffic'] ?? 0,
                'expiration_date' => $finishAt,
                'days_remaining' => $daysRemaining
            ];

            // Форматируем ключи для отображения (плоский список для обратной совместимости)
            $formattedKeys = $this->formatConnectionKeys($connectionKeys);
            // Группировка по локации/серверу (массив групп — без перезаписи, все протоколы сохраняются)
            $formattedKeysGrouped = [];
            foreach ($slotsWithLinks as $slot) {
                $formattedKeysGrouped[] = [
                    'label' => $slot['location_label'],
                    'flag_code' => $slot['location_code'] ?? '',
                    'keys'  => $this->formatConnectionKeys($slot['connection_keys']),
                ];
            }

            // Добавляем ссылку на бота
            $botLink = $salesman->bot_link ?? '#';

            // Добавляем ссылку на страницу проверки качества сети
            $netcheckUrl = route('netcheck.index');
            $isDemoMode = false; // Это реальная страница, не демо

            // Получаем активные нарушения для этого ключа
            $violations = ConnectionLimitViolation::where('key_activate_id', $keyActivate->id)
                ->where('status', ConnectionLimitViolation::STATUS_ACTIVE)
                ->whereNull('key_replaced_at')
                ->orderBy('created_at', 'desc')
                ->get();

            // Проверяем, был ли ключ перевыпущен
            $replacedViolation = ConnectionLimitViolation::where('key_activate_id', $keyActivate->id)
                ->whereNotNull('key_replaced_at')
                ->whereNotNull('replaced_key_id')
                ->orderBy('key_replaced_at', 'desc')
                ->first();

            $newKeyActivate = null;
            $newKeyFormattedKeys = null;
            $newKeyUserInfo = null;

            if ($replacedViolation && $replacedViolation->replaced_key_id) {
                // Находим новый ключ
                $newKeyActivate = $this->keyActivateRepository->findById($replacedViolation->replaced_key_id);

                if ($newKeyActivate) {
                    // Загружаем отношения для нового ключа (только нужные поля)
                    $newKeyActivate->load([
                        'packSalesman' => function($query) {
                            $query->select('id', 'salesman_id', 'pack_id');
                        },
                        'packSalesman.salesman' => function($query) {
                            $query->select('id', 'telegram_id', 'bot_link', 'panel_id', 'module_bot_id');
                        }
                    ]);

                    // Проверяем finish_at нового ключа перед запросом к Marzban
                    $newKeyActivate = $this->keyActivateService->checkAndUpdateStatus($newKeyActivate);

                    // Ищем KeyActivateUser для нового ключа
                    $newKeyActivateUser = $this->keyActivateUserRepository->findByKeyActivateIdWithRelations($newKeyActivate->id);

                    if ($newKeyActivateUser && $newKeyActivateUser->serverUser) {
                        $newServerUser = $newKeyActivateUser->serverUser;

                        // Получаем актуальные ключи для нового ключа
                        try {
                            $newConnectionKeys = $this->getFreshUserLinks($newServerUser);

                            if ($newConnectionKeys) {
                                $newKeyFormattedKeys = $this->formatConnectionKeys($newConnectionKeys);

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

            return response()->view('vpn.config', compact(
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
            ));

        } catch (Exception $e) {
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
     * Format connection keys for display
     * @param array $connectionKeys
     * @return array
     */
    private function formatConnectionKeys(array $connectionKeys): array
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

                // Извлекаем тип подключения из комментария
                preg_match('/\[(.*?)\]$/', $configString, $typeMatches);
                $connectionType = $typeMatches[1] ?? '';

                $formattedKeys[] = [
                    'protocol' => $protocolInfo['name'],
                    'icon' => $protocolInfo['icon'],
                    'link' => addslashes($configString),
                    'connection_type' => $connectionType
                ];
            }
        }

        return $formattedKeys;
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\ServerUser\ServerUser;
use App\Repositories\KeyActivateUser\KeyActivateUserRepository;
use App\Repositories\ServerUser\ServerUserRepository;
use App\Services\External\MarzbanAPI;
use App\Services\Panel\marzban\MarzbanService;
use App\Services\Panel\PanelStrategy;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class VpnConfigController extends Controller
{
    /**
     * @var KeyActivateUserRepository
     */
    private KeyActivateUserRepository $keyActivateUserRepository;
    /**
     * @var ServerUserRepository
     */
    private ServerUserRepository $serverUserRepository;

    public function __construct(
        KeyActivateUserRepository $keyActivateUserRepository,
        ServerUserRepository      $serverUserRepository
    )
    {
        $this->keyActivateUserRepository = $keyActivateUserRepository;
        $this->serverUserRepository = $serverUserRepository;
    }

    public function show(string $key_activate_id): Response
    {
        try {
            // Если запрошен роут /config/error, перенаправляем на метод showError
            if ($key_activate_id === 'error') {
                return $this->showError();
            }
            
            // Получаем запись key_activate_user с отношениями
            $keyActivateUser = $this->keyActivateUserRepository->findByKeyActivateIdWithRelations($key_activate_id);
            
            // Если ключ не найден
            if (!$keyActivateUser) {
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
                    'message' => 'Конфигурация не найдена. Ключ может быть неактивен или удален.'
                ]);
            }
            
            // Получаем информацию о пользователе сервера
            $serverUser = $this->serverUserRepository->findById($keyActivateUser->server_user_id);

            if (!$serverUser) {
                throw new RuntimeException('Server user not found');
            }

            // Декодируем ключи подключения
            $connectionKeys = json_decode($serverUser->keys, true);

            //ЖЕСТОКИЙ КОСТЫЛЬ
            // ВСЕГДА ПОЛУЧАЕМ АКТУАЛЬНЫЕ КЛЮЧИ ИЗ PANEL
            $connectionKeys = $this->getFreshUserLinks($serverUser);

            if (!$connectionKeys) {
                throw new RuntimeException('Invalid connection keys format');
            }

            $userAgent = request()->header('User-Agent') ?? 'Unknown';
            Log::warning('Incoming request with User-Agent:', ['User-Agent' => $userAgent]);

            // Определяем тип клиента
            $isVpnClient = $this->isVpnClient($userAgent);
            $isBrowser = $this->isBrowserClient($userAgent);

            Log::warning('Client detection:', [
                'is_vpn_client' => $isVpnClient,
                'is_browser' => $isBrowser,
                'wants_json' => request()->wantsJson()
            ]);

            // Если это VPN клиент или запрос JSON - возвращаем конфигурацию
            if ($isVpnClient || request()->wantsJson()) {
                Log::warning('Returning config for VPN client/JSON');
                return response(implode("\n", $connectionKeys))
                    ->header('Content-Type', 'text/plain');
            }

            // Если это браузер - показываем HTML страницу
            if ($isBrowser) {
                return $this->showBrowserPage($keyActivateUser, $serverUser, $connectionKeys);
            }

            // По умолчанию для неизвестных клиентов возвращаем конфигурацию
            Log::warning('Returning config for unknown client type');
            return response(implode("\n", $connectionKeys))
                ->header('Content-Type', 'text/plain');

        } catch (Exception $e) {
            Log::error('Error showing VPN config', [
                'key_activate_id' => $key_activate_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            if (request()->wantsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Configuration not found'
                ], 404);
            }

            return response()->view('vpn.error', [
                'message' => 'Не удалось загрузить конфигурацию VPN. Пожалуйста, проверьте правильность ссылки.'
            ]);
        }
    }


    //ЖЕСТОКИЙ КОСТЫЛЬ
    private function getFreshUserLinks(ServerUser $serverUser): array
    {
        try {
            $marzbanService = new MarzbanService();
            $panel = $marzbanService->updateMarzbanToken($serverUser->panel_id);
            $marzbanApi = new MarzbanAPI($panel->api_address);

            // Получаем актуальные данные пользователя из Marzban
            $userData = $marzbanApi->getUser($panel->auth_token, $serverUser->id);

            // Обновляем ссылки в БД
            if (!empty($userData['links'])) {
                $serverUser->keys = json_encode($userData['links']);
                $serverUser->save();

                Log::info('User links updated from panel', ['user_id' => $serverUser->id]);
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
                Log::warning('VPN pattern matched:', ['pattern' => $pattern, 'user_agent' => $userAgentLower]);
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

        Log::info('Showing demo page for local development', [
            'key_activate_id' => $key_activate_id,
            'source' => 'vpn'
        ]);

        return response()->view('vpn.config', compact('userInfo', 'formattedKeys', 'botLink', 'netcheckUrl', 'isDemoMode'));
    }

    /**
     * Показывает страницу для браузера
     */
    private function showBrowserPage($keyActivateUser, $serverUser, $connectionKeys): Response
    {
        try {
            $panel_strategy = new PanelStrategy($serverUser->panel->panel);
            $info = $panel_strategy->getSubscribeInfo($serverUser->panel->id, $serverUser->id);

            Log::info('Panel info retrieved:', ['info' => $info]);

            $userInfo = [
                'username' => $serverUser->id,
                'status' => $info['status'] ?? 'unknown',
                'data_limit' => $info['data_limit'] ?? 0,
                'data_limit_tariff' => $keyActivateUser->keyActivate->traffic_limit ?? 0,
                'data_used' => $info['used_traffic'] ?? 0,
                'expiration_date' => $keyActivateUser->keyActivate->finish_at ?? null,
                'days_remaining' => $keyActivateUser->keyActivate->finish_at ?
                    ceil(($keyActivateUser->keyActivate->finish_at - time()) / \App\Constants\TimeConstants::SECONDS_IN_DAY) : null
            ];

            // Форматируем ключи для отображения
            $formattedKeys = $this->formatConnectionKeys($connectionKeys);

            // Добавляем ссылку на бота
            $botLink = $keyActivateUser->keyActivate->packSalesman->salesman->bot_link ?? '#';
            
            // Добавляем ссылку на страницу проверки качества сети
            $netcheckUrl = route('netcheck.index');
            $isDemoMode = false; // Это реальная страница, не демо

            Log::warning('Returning browser page');
            return response()->view('vpn.config', compact('userInfo', 'formattedKeys', 'botLink', 'netcheckUrl', 'isDemoMode'));

        } catch (Exception $e) {
            Log::error('Error showing browser page:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'source' => 'vpn'
            ]);

            // В случае ошибки при подготовке страницы показываем страницу ошибки
            return response()->view('vpn.error', [
                'message' => 'Не удалось загрузить конфигурацию VPN. Пожалуйста, проверьте правильность ссылки.'
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

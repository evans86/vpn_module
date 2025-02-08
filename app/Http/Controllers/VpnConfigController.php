<?php

namespace App\Http\Controllers;

use App\Repositories\KeyActivateUser\KeyActivateUserRepository;
use App\Services\Panel\PanelStrategy;
use Exception;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class VpnConfigController extends Controller
{
    private KeyActivateUserRepository $keyActivateUserRepository;

    public function __construct(KeyActivateUserRepository $keyActivateUserRepository)
    {
        $this->keyActivateUserRepository = $keyActivateUserRepository;
    }

    /**
     * @param string $key_activate_id
     * @return Response
     */
    public function show(string $key_activate_id): Response
    {
        try {
            // Получаем запись key_activate_user с отношениями
            $keyActivateUser = $this->keyActivateUserRepository->findByKeyActivateIdWithRelations($key_activate_id);

            // Получаем информацию о пользователе сервера
            $serverUser = $keyActivateUser->serverUser;
            if (!$serverUser) {
                throw new RuntimeException('Server user not found');
            }

            Log::debug('SHOW INFO ENCODE CONNECTION KEYS', [
                'MY_KEYS' => $serverUser->keys,
            ]);

            // Декодируем ключи подключения
            $connectionKeys = json_decode($serverUser->keys, true);

            Log::debug('SHOW INFO DRCODED CONNECTION KEYS', [
                'MY_KEYS' => $connectionKeys,
            ]);

            if (!$connectionKeys) {
                throw new RuntimeException('Invalid connection keys format');
            }

            $userAgent = request()->header('User-Agent') ?? 'Unknown';
            Log::warning('Incoming request with User-Agent:', ['User-Agent' => $userAgent]);

            // Проверяем User-Agent на наличие клиентов VPN
            $userAgent = strtolower(request()->header('User-Agent') ?? '');
            $isVpnClient = str_contains($userAgent, 'v2rayng') || // V2RayNG (Android)
                str_contains($userAgent, 'nekobox') || // NekoBox (Android)
                str_contains($userAgent, 'nekoray') || // NekoRay (Windows)
                str_contains($userAgent, 'singbox') || // Sing-Box (кроссплатформенный)
                str_contains($userAgent, 'hiddify') || // Hiddify (кроссплатформенный)
                str_contains($userAgent, 'shadowrocket') || // Shadowrocket (iOS)
                str_contains($userAgent, 'surge') || // Surge (iOS/macOS)
                str_contains($userAgent, 'quantumult') || // Quantumult (iOS)
                str_contains($userAgent, 'quantumult x') || // Quantumult X (iOS)
                str_contains($userAgent, 'loon') || // Loon (iOS)
                str_contains($userAgent, 'streisand') || // Streisand (кроссплатформенный)
                str_contains($userAgent, 'clash') || // Clash (кроссплатформенный)
                str_contains($userAgent, 'clashx') || // ClashX (macOS)
                str_contains($userAgent, 'clash for windows') || // Clash for Windows
                str_contains($userAgent, 'clash.android') || // Clash for Android
                str_contains($userAgent, 'clash.meta') || // Clash.Meta (кроссплатформенный)
                str_contains($userAgent, 'v2rayu') || // V2RayU (macOS)
                str_contains($userAgent, 'v2rayn') || // V2RayN (Windows)
                str_contains($userAgent, 'v2rayx') || // V2RayX (macOS)
                str_contains($userAgent, 'qv2ray') || // Qv2ray (кроссплатформенный)
                str_contains($userAgent, 'trojan') || // Trojan clients (общий)
                str_contains($userAgent, 'trojan-go') || // Trojan-Go clients
                str_contains($userAgent, 'wireguard') || // WireGuard clients
                str_contains($userAgent, 'openvpn') || // OpenVPN clients
                str_contains($userAgent, 'openconnect') || // OpenConnect clients
                str_contains($userAgent, 'softether') || // SoftEther VPN clients
                str_contains($userAgent, 'shadowsocks') || // Shadowsocks clients
                str_contains($userAgent, 'shadowsocksr') || // ShadowsocksR clients
                str_contains($userAgent, 'ssr') || // SSR clients
                str_contains($userAgent, 'outline') || // Outline clients
                str_contains($userAgent, 'zerotier') || // ZeroTier clients
                str_contains($userAgent, 'tailscale') || // Tailscale clients
                str_contains($userAgent, 'windscribe') || // Windscribe clients
                str_contains($userAgent, 'protonvpn') || // ProtonVPN clients
                str_contains($userAgent, 'nordvpn') || // NordVPN clients
                str_contains($userAgent, 'expressvpn') || // ExpressVPN clients
                str_contains($userAgent, 'pritunl') || // Pritunl clients
                str_contains($userAgent, 'openwrt') || // OpenWRT (роутеры с VPN)
                str_contains($userAgent, 'dd-wrt') || // DD-WRT (роутеры с VPN)
                str_contains($userAgent, 'merlin') || // Asus Merlin (роутеры с VPN)
                str_contains($userAgent, 'pivpn') || // PiVPN (Raspberry Pi)
                str_contains($userAgent, 'algo') || // Algo VPN
                str_contains($userAgent, 'strongswan') || // StrongSwan clients
                str_contains($userAgent, 'ikev2') || // IKEv2 clients
                str_contains($userAgent, 'ipsec') || // IPSec clients
                str_contains($userAgent, 'l2tp') || // L2TP clients
                str_contains($userAgent, 'pptp'); // PPTP clients

            if ($isVpnClient || request()->wantsJson()) {
                // Для VPN клиентов возвращаем строку с конфигурациями
                return response(implode("\n", $connectionKeys))
                    ->header('Content-Type', 'text/plain');
            }

            $panel_strategy = new PanelStrategy($serverUser->panel->panel);
            $info = $panel_strategy->getSubscribeInfo($serverUser->panel->id, $serverUser->id);

            Log::info('SHOW INFO MARZ', [
                'error' => $info
            ]);

            // Для браузера показываем HTML страницу
            $userInfo = [
                'username' => $serverUser->id,
                'status' => $info['status'],
                'data_limit' => $info['data_limit'],
                'data_limit_tariff' => $keyActivateUser->keyActivate->traffic_limit ?? 0,
                'data_used' => $info['used_traffic'],
                'expiration_date' => $keyActivateUser->keyActivate->finish_at ?? null,
                'days_remaining' => $keyActivateUser->keyActivate->finish_at
                    ? ceil(($keyActivateUser->keyActivate->finish_at - time()) / 86400)
                    : null
            ];

            // Форматируем ключи для отображения
            $formattedKeys = $this->formatConnectionKeys($connectionKeys);

            Log::debug('SHOW INFO MARZ', [
                'MY_KEYS' => $formattedKeys,
            ]);

            // Добавляем ссылку на бота
            $botLink = $keyActivateUser->keyActivate->packSalesman->salesman->bot_link ?? '#';

            return response()->view('vpn.config', compact('userInfo', 'formattedKeys', 'botLink'));
        } catch (Exception $e) {
            Log::error('Error showing VPN config', [
                'key_activate_id' => $key_activate_id,
                'error' => $e->getMessage()
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

<?php

namespace App\Http\Controllers;

use App\Repositories\KeyActivateUser\KeyActivateUserRepository;
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

            // Декодируем ключи подключения
            $connectionKeys = json_decode($serverUser->keys, true);
            if (!$connectionKeys) {
                throw new RuntimeException('Invalid connection keys format');
            }

            // Проверяем User-Agent на наличие клиентов VPN
            $userAgent = strtolower(request()->header('User-Agent') ?? '');
            $isMarzbanClient = str_contains($userAgent, 'v2rayng') || 
                             str_contains($userAgent, 'nekobox') || 
                             str_contains($userAgent, 'nekoray') ||
                             str_contains($userAgent, 'singbox');

            if ($isMarzbanClient || request()->wantsJson()) {
                // Для VPN клиентов возвращаем формат Marzban
                return response()->json([
                    'username' => $serverUser->id,
                    'status' => $serverUser->status ?? 'active',
                    'data_limit' => ($keyActivateUser->keyActivate->traffic_limit ?? 0) * 1024 * 1024 * 1024, // Convert GB to bytes
                    'data_limit_reset_strategy' => 'no_reset',
                    'expire' => $keyActivateUser->keyActivate->finish_at ?? null,
                    'inbounds' => $this->getInboundsList($connectionKeys),
                    'links' => array_values($connectionKeys), // Массив URI для подключения
                    'subscription_url' => route('vpn.config.show', ['token' => $key_activate_id]),
                    'created_at' => $keyActivateUser->created_at->timestamp ?? time(),
                    'updated_at' => time()
                ]);
            }

            // Для браузера показываем HTML страницу
            $userInfo = [
                'username' => $serverUser->id,
                'status' => $serverUser->status ?? 'active',
                'data_limit' => $keyActivateUser->keyActivate->traffic_limit ?? 0,
                'data_used' => $serverUser->used_traffic ?? 0,
                'expiration_date' => $keyActivateUser->keyActivate->finish_at ?? null,
                'days_remaining' => $keyActivateUser->keyActivate->finish_at
                    ? ceil(($keyActivateUser->keyActivate->finish_at - time()) / 86400)
                    : null
            ];

            // Форматируем ключи для отображения
            $formattedKeys = $this->formatConnectionKeys($connectionKeys);

            return response()->view('vpn.config', compact('userInfo', 'formattedKeys'));
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
        foreach ($connectionKeys as $key) {
            $protocol = strtolower(explode('://', $key)[0]);
            if (isset($protocolDescriptions[$protocol])) {
                $formattedKeys[] = [
                    'uri' => $key,
                    'name' => $protocolDescriptions[$protocol]['name'],
                    'icon' => $protocolDescriptions[$protocol]['icon']
                ];
            }
        }

        return $formattedKeys;
    }

    /**
     * Get list of inbounds from connection keys
     * @param array $connectionKeys
     * @return array
     */
    private function getInboundsList(array $connectionKeys): array
    {
        $inbounds = [];
        foreach ($connectionKeys as $key) {
            $protocol = strtolower(explode('://', $key)[0]);
            if (!in_array($protocol, $inbounds)) {
                $inbounds[] = $protocol;
            }
        }
        return $inbounds;
    }
}

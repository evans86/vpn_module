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

            // Проверяем User-Agent на наличие Hidiffy или других клиентов
            $userAgent = request()->header('User-Agent');
            if (str_contains(strtolower($userAgent), 'hidiffy') || request()->wantsJson()) {
                // Для приложения возвращаем только список ключей
                return response()->json([
                    'status' => 'success',
                    'keys' => $connectionKeys
                ]);
            }

            // Форматируем данные для отображения
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

        // Разбираем каждую строку конфигурации
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
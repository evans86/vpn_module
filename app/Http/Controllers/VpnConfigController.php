<?php

namespace App\Http\Controllers;

use App\Models\KeyActivateUser\KeyActivateUser;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class VpnConfigController extends Controller
{
    public function show(string $key_activate_id)
    {
        try {
            // Получаем запись key_activate_user
            $keyActivateUser = KeyActivateUser::where('key_activate_id', $key_activate_id)
                ->with(['serverUser', 'keyActivate'])
                ->firstOrFail();

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
            $formattedKeys = [];
            $protocolDescriptions = [
                'vless' => [
                    'name' => 'VLESS',
//                    'description' => 'Легкий и быстрый протокол, рекомендуется для большинства пользователей',
                    'icon' => 'V'
                ],
                'vmess' => [
                    'name' => 'VMess',
//                    'description' => 'Универсальный протокол с дополнительным шифрованием',
                    'icon' => 'VM'
                ],
                'trojan' => [
                    'name' => 'Trojan',
//                    'description' => 'Протокол с высоким уровнем маскировки трафика',
                    'icon' => 'T'
                ],
                'shadowsocks' => [
                    'name' => 'Shadowsocks',
//                    'description' => 'Классический протокол для обхода блокировок',
                    'icon' => 'SS'
                ]
            ];

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
//                        'description' => 'Протокол VPN подключения',
                        'icon' => substr(strtoupper($protocol), 0, 1)
                    ];

                    // Извлекаем тип подключения из комментария
                    preg_match('/\[(.*?)\]$/', $configString, $typeMatches);
                    $connectionType = $typeMatches[1] ?? '';

                    $formattedKeys[] = [
                        'protocol' => $protocolInfo['name'],
//                        'description' => $protocolInfo['description'],
                        'icon' => $protocolInfo['icon'],
                        'link' => addslashes($configString), // Добавляем экранирование для JavaScript
                        'connection_type' => $connectionType
                    ];
                }
            }

            return view('vpn.config', compact('userInfo', 'formattedKeys'));
        } catch (Exception $e) {
            Log::error('Error showing VPN config', [
                'key_activate_id' => $key_activate_id,
                'error' => $e->getMessage()
            ]);

            return view('vpn.error', [
                'message' => 'Не удалось загрузить конфигурацию VPN. Пожалуйста, проверьте правильность ссылки.'
            ]);
        }
    }
}

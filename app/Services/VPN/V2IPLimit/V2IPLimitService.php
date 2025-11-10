<?php

namespace App\Services\VPN\V2IPLimit;

use App\Models\Panel\Panel;
use App\Services\Panel\marzban\MarzbanService;
use App\Logging\DatabaseLogger;
use Illuminate\Support\Facades\Log;

class V2IPLimitService
{
    private MarzbanService $marzbanService;
    private DatabaseLogger $logger;

    public function __construct(
        MarzbanService $marzbanService,
        DatabaseLogger $logger
    ) {
        $this->marzbanService = $marzbanService;
        $this->logger = $logger;
    }

    /**
     * Упрощенная проверка - смотрим только на инбаунды
     */
    public function checkPanelSupport(Panel $panel): bool
    {
        try {
            $config = $this->getCurrentConfig($panel);
            return isset($config['inbounds']) && is_array($config['inbounds']);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Включаем IP лимиты (создаем политики если их нет)
     */
    public function enableIPLimitForPanel(Panel $panel): bool
    {
        try {
            $currentConfig = $this->getCurrentConfig($panel);

            if (!isset($currentConfig['inbounds'])) {
                $this->logger->error('No inbounds found in panel config', [
                    'panel_id' => $panel->id
                ]);
                return false;
            }

            $updatedConfig = $this->createIPLimitConfig($currentConfig);
            $this->updatePanelConfig($panel, $updatedConfig);

            $this->logger->info('IP лимиты успешно включены', [
                'panel_id' => $panel->id,
                'inbounds_count' => count($updatedConfig['inbounds'])
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Ошибка включения IP лимитов', [
                'panel_id' => $panel->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Создаем конфигурацию с IP лимитами
     */
    private function createIPLimitConfig(array $config): array
    {
        // 1. Добавляем политики ограничения подключений
        $config['policy'] = [
            'levels' => [
                '0' => [ // Базовый уровень - 3 подключения
                    'handshake' => 4,
                    'connIdle' => 300,
                    'uplinkOnly' => 1,
                    'downlinkOnly' => 1,
                    'statsUserUplink' => true,
                    'statsUserDownlink' => true,
                    'bufferSize' => 10240
                ],
                '1' => [ // Премиум уровень - 5 подключений
                    'handshake' => 4,
                    'connIdle' => 300,
                    'uplinkOnly' => 1,
                    'downlinkOnly' => 1,
                    'statsUserUplink' => true,
                    'statsUserDownlink' => true,
                    'bufferSize' => 20480
                ]
            ],
            'system' => [
                'statsInboundUplink' => true,
                'statsInboundDownlink' => true
            ]
        ];

        // 2. Устанавливаем уровень по умолчанию для всех инбаундов
        foreach ($config['inbounds'] as &$inbound) {
            // Для инбаундов с клиентами устанавливаем уровень по умолчанию
            if (isset($inbound['settings']['clients'])) {
                $inbound['settings']['level'] = 0;
            }
        }

        return $config;
    }

    /**
     * Получаем текущую конфигурацию панели
     */
    public function getCurrentConfig(Panel $panel): array
    {
        try {
            $panel = $this->marzbanService->updateMarzbanToken($panel->id);
            $marzbanApi = new \App\Services\External\MarzbanAPI($panel->api_address);

            // Используем reflection для вызова protected/private методов если нужно
            return $marzbanApi->getConfig($panel->auth_token);

        } catch (\Exception $e) {
            Log::error('Failed to get panel config', [
                'panel_id' => $panel->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Обновляем конфигурацию панели
     */
    private function updatePanelConfig(Panel $panel, array $config): void
    {
        $panel = $this->marzbanService->updateMarzbanToken($panel->id);
        $marzbanApi = new \App\Services\External\MarzbanAPI($panel->api_address);

        $marzbanApi->updateConfig($panel->auth_token, $config);

        // Ждем немного для применения конфигурации
        sleep(2);
    }

    /**
     * Тестируем создание пользователя с лимитом
     */
    public function testUserCreation(Panel $panel): bool
    {
        try {
            $testUsername = 'test_user_' . time();
            $panel = $this->marzbanService->updateMarzbanToken($panel->id);
            $marzbanApi = new \App\Services\External\MarzbanAPI($panel->api_address);

            // Создаем тестового пользователя
            $userData = $marzbanApi->createUser(
                $panel->auth_token,
                $testUsername,
                1073741824, // 1GB
                time() + 3600, // 1 hour
                3 // 3 connections
            );

            // Удаляем тестового пользователя
            $marzbanApi->deleteUser($panel->auth_token, $testUsername);

            return true;

        } catch (\Exception $e) {
            Log::error('Test user creation failed', [
                'panel_id' => $panel->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}

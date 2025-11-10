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
            $this->logger->error('Panel support check failed', [
                'panel_id' => $panel->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Включаем IP лимиты (создаем политики если их нет)
     */
    public function enableIPLimitForPanel(Panel $panel): bool
    {
        try {
            $this->logger->info('Starting IP limit enablement', ['panel_id' => $panel->id]);

            $currentConfig = $this->getCurrentConfig($panel);

            if (!isset($currentConfig['inbounds'])) {
                $this->logger->error('No inbounds found in panel config', ['panel_id' => $panel->id]);
                return false;
            }

            $this->logger->info('Current config obtained', [
                'panel_id' => $panel->id,
                'inbounds_count' => count($currentConfig['inbounds']),
                'has_policy' => isset($currentConfig['policy'])
            ]);

            $updatedConfig = $this->createIPLimitConfig($currentConfig);
            $this->updatePanelConfig($panel, $updatedConfig);

            $this->logger->info('IP limits successfully enabled', [
                'panel_id' => $panel->id,
                'inbounds_count' => count($updatedConfig['inbounds']),
                'has_policy' => isset($updatedConfig['policy'])
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Error enabling IP limits', [
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
                    'statsUserDownlink' => true
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
            if (isset($inbound['settings'])) {
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

            $config = $marzbanApi->getConfig($panel->auth_token);

            if (empty($config)) {
                throw new \Exception('Empty configuration received');
            }

            return $config;

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

        $result = $marzbanApi->updateConfig($panel->auth_token, $config);

        // Проверяем результат
        if (isset($result['status']) && $result['status'] === 'error') {
            throw new \Exception('Config update failed: ' . ($result['message'] ?? 'Unknown error'));
        }

        // Ждем немного для применения конфигурации
        sleep(3);
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

            $this->logger->info('Testing user creation', [
                'panel_id' => $panel->id,
                'test_username' => $testUsername
            ]);

            // Создаем тестового пользователя с указанием уровня
            $userData = $marzbanApi->createUser(
                $panel->auth_token,
                $testUsername,
                1073741824, // 1GB
                time() + 3600, // 1 hour
                3 // 3 connections
            );

            if (!isset($userData['username'])) {
                throw new \Exception('User creation failed - no username in response');
            }

            $this->logger->info('Test user created successfully', [
                'panel_id' => $panel->id,
                'username' => $userData['username']
            ]);

            // Удаляем тестового пользователя
            $marzbanApi->deleteUser($panel->auth_token, $testUsername);

            $this->logger->info('Test user deleted', ['panel_id' => $panel->id]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Test user creation failed', [
                'panel_id' => $panel->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Проверяем, применены ли политики
     */
    public function verifyIPLimitsEnabled(Panel $panel): bool
    {
        try {
            $config = $this->getCurrentConfig($panel);

            $hasPolicy = isset($config['policy']['levels']);
            $hasLevelInInbounds = false;

            if (isset($config['inbounds'])) {
                foreach ($config['inbounds'] as $inbound) {
                    if (isset($inbound['settings']['level'])) {
                        $hasLevelInInbounds = true;
                        break;
                    }
                }
            }

            return $hasPolicy && $hasLevelInInbounds;

        } catch (\Exception $e) {
            $this->logger->error('IP limits verification failed', [
                'panel_id' => $panel->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}

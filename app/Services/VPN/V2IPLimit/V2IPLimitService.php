<?php

namespace App\Services\VPN\V2IPLimit;

use App\Models\Panel\Panel;
use App\Services\External\MarzbanAPI;
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
     * Проверяем поддержку политик в текущей конфигурации
     */
    public function checkPolicySupport(Panel $panel): bool
    {
        try {
            $config = $this->getCurrentConfig($panel);

            $hasPolicy = isset($config['policy']['levels']);
            $hasInbounds = isset($config['inbounds']);

            $this->logger->info('Проверка поддержки политик', [
                'panel_id' => $panel->id,
                'has_policy' => $hasPolicy,
                'has_inbounds' => $hasInbounds
            ]);

            return $hasPolicy && $hasInbounds;

        } catch (\Exception $e) {
            $this->logger->error('Ошибка проверки политик', [
                'panel_id' => $panel->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Получаем текущую конфигурацию панели
     */
    private function getCurrentConfig(Panel $panel): array
    {
        // Используем существующий метод MarzbanService для получения конфига
        $panel = $this->marzbanService->updateMarzbanToken($panel->id);
        $marzbanApi = new MarzbanAPI($panel->api_address);

        // Получаем текущий конфиг
        return $marzbanApi->getConfig($panel->auth_token);
    }

    /**
     * Включаем IP лимиты для панели
     */
    public function enableIPLimitForPanel(Panel $panel): bool
    {
        try {
            $currentConfig = $this->getCurrentConfig($panel);
            $updatedConfig = $this->injectIPLimitConfig($currentConfig);

            $this->updatePanelConfig($panel, $updatedConfig);

            $this->logger->info('IP лимиты включены для панели', [
                'panel_id' => $panel->id
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Ошибка включения IP лимитов', [
                'panel_id' => $panel->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Внедряем IP лимит в конфигурацию
     */
    private function injectIPLimitConfig(array $config): array
    {
        // Добавляем политики ограничения подключений
        $config['policy'] = [
            'levels' => [
                '0' => [ // Базовый уровень - 3 подключения
                    'handshake' => 4,
                    'connIdle' => 300,
                    'uplinkOnly' => 1,
                    'downlinkOnly' => 1,
                    'statsUserUplink' => true,
                    'statsUserDownlink' => true
                ],
                '1' => [ // Премиум уровень - 5 подключений
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

        // Устанавливаем уровень по умолчанию для всех инбаундов
        foreach ($config['inbounds'] as &$inbound) {
            if (isset($inbound['settings']['clients'])) {
                // Устанавливаем уровень для всех будущих клиентов
                $inbound['settings']['level'] = 0;
            }
        }

        return $config;
    }

    /**
     * Обновляем конфигурацию панели
     */
    private function updatePanelConfig(Panel $panel, array $config): void
    {
        $panel = $this->marzbanService->updateMarzbanToken($panel->id);
        $marzbanApi = new MarzbanAPI($panel->api_address);

        $marzbanApi->updateConfig($panel->auth_token, $config);
    }
}

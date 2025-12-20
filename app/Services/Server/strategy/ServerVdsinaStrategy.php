<?php

namespace App\Services\Server\strategy;

use App\Models\Server\Server;
use App\Services\Panel\PanelStrategy;
use App\Services\Server\vdsina\VdsinaService;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

/**
 * Стратегия для работы с серверами провайдера VDSina
 * 
 * Реализует операции создания, настройки и управления серверами через API VDSina
 */
class ServerVdsinaStrategy extends ServerMainStrategy
{
    private VdsinaService $service;

    /**
     * @param VdsinaService $service Сервис для работы с API VDSina
     */
    public function __construct(VdsinaService $service)
    {
        $this->service = $service;
    }

    /**
     * Проверка доступности сервера VDSina
     *
     * @param Server $server Сервер для проверки
     * @return bool true если сервер доступен
     */
    public function ping(Server $server): bool
    {
        return $this->service->ping($server);
    }

    /**
     * Первоначальное создание сервера у провайдера VDSina
     *
     * @param int $location_id ID локации для размещения сервера
     * @param string $provider Тип провайдера
     * @param bool $isFree Флаг бесплатного тарифа
     * @return Server Созданный сервер
     * @throws GuzzleException При ошибках API
     */
    public function configure(int $location_id, string $provider, bool $isFree): Server
    {
        return $this->service->configure($location_id, $provider, $isFree);
    }

    /**
     * Получение пароля сервера от провайдера VDSina
     *
     * @param int $server_id ID сервера
     * @return string|null Пароль сервера или null если не удалось получить
     */
    public function getServerPassword(int $server_id): ?string
    {
        return $this->service->getServerPassword($server_id);
    }

    /**
     * Проверка статуса всех созданных серверов и их окончательная настройка
     * 
     * Проверяет статус серверов со статусом SERVER_CREATED и завершает их настройку
     * 
     * ВАЖНО: Стратегия знает, какие серверы ей нужно обработать (по провайдеру)
     *
     * @return void
     * @throws Exception При ошибках настройки
     */
    public function checkStatus(): void
    {
        // Стратегия знает, какие серверы ей нужно обработать
        // Используем константу провайдера из модели Server
        $servers = \App\Models\Server\Server::query()
            ->where('provider', \App\Models\Server\Server::VDSINA)
            ->where('server_status', \App\Models\Server\Server::SERVER_CREATED)
            ->with(['location', 'panel'])
            ->get();

        \Illuminate\Support\Facades\Log::info('Checking status for VDSINA servers', [
            'count' => count($servers),
            'source' => 'server'
        ]);

        foreach ($servers as $server) {
            try {
                if (!$server->provider_id) {
                    \Illuminate\Support\Facades\Log::error('Server has no provider_id', [
                        'server_id' => $server->id,
                        'source' => 'server'
                    ]);
                    continue;
                }

                \Illuminate\Support\Facades\Log::info('Checking server status', [
                    'server_id' => $server->id,
                    'provider_id' => $server->provider_id,
                    'source' => 'server'
                ]);

                // Используем метод сервиса для проверки статуса конкретного сервера
                $this->service->checkServerStatus($server);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Error processing server', [
                    'server_id' => $server->id,
                    'provider_id' => $server->provider_id ?? 'unknown',
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'source' => 'server'
                ]);

                // Помечаем сервер как ошибочный
                $server->server_status = \App\Models\Server\Server::SERVER_ERROR;
                $server->save();
            }
        }
    }

    /**
     * Добавление панели к серверу
     *
     * @param int $server_id
     * @param string $panel
     * @return void
     * @throws \RuntimeException
     */
    public function setPanel(int $server_id, string $panel): void
    {
        $server = Server::query()->where('id', $server_id)->first();

        if (!$server) {
            throw new \RuntimeException("Server with ID {$server_id} not found");
        }

        $panelStrategy = new PanelStrategy($panel);
        $panelStrategy->create($server->id);
    }

    /**
     * Удаление сервера у провайдера VDSina
     *
     * @param Server $server Сервер для удаления
     * @return void
     * @throws Exception При ошибках удаления
     */
    public function delete(Server $server): void
    {
        try {
            $this->service->delete($server);
        } catch (Exception $e) {
            Log::error('Error in ServerVdsinaStrategy::delete', [
                'server_id' => $server->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}

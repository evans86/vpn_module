<?php

namespace App\Services\Server\strategy;

use App\Models\Server\Server;
use App\Services\Panel\PanelStrategy;
use App\Services\Server\vdsina\VdsinaService;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class ServerVdsinaStrategy extends ServerMainStrategy
{
    private VdsinaService $service;

    public function __construct()
    {
        $this->service = new VdsinaService();
    }

    public function ping(Server $server): bool
    {
        return $this->service->ping($server);
    }

    /**
     * Первоначальное создание сервера
     */
    public function configure(int $location_id, string $provider, bool $isFree): Server
    {
        return $this->service->configure($location_id, $provider, $isFree);
    }

    /**
     * @param int $server_id
     * @return string|null
     */
    public function getServerPassword(int $server_id): ?string // Исправлено на ?string
    {
        return $this->service->getServerPassword($server_id);
    }

    /**
     * Проверка статуса сервера и окончательная настройка
     */
    public function checkStatus(): void
    {
        $this->service->checkStatus();
    }

    /**
     * Добавление панели к серверу
     */
    public function setPanel(int $server_id, string $panel): void
    {
        $server = Server::query()->where('id', $server_id)->first();

        $panelStrategy = new PanelStrategy($panel);
        $panelStrategy->create($server->id);
    }

    /**
     * Удаление сервера
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

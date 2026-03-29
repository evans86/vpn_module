<?php

namespace App\Services\Server\strategy;

use App\Models\Server\Server;
use App\Services\Cloudflare\CloudflareService;
use App\Services\Panel\PanelStrategy;
use App\Services\Server\ServerInterface;
use DomainException;
use Illuminate\Support\Facades\Log;

/**
 * Стратегия для серверов, добавленных вручную (провайдер без API).
 * Создание — через ServerController::storeManual(), не через configure().
 */
class ServerManualStrategy extends ServerMainStrategy
{
    /**
     * Ручные серверы не создаются через API. Используйте маршрут store-manual.
     *
     * @throws DomainException
     */
    public function configure(int $location_id, string $provider, bool $isFree): Server
    {
        throw new DomainException(
            'Серверы провайдера «Без API» добавляются через форму «Добавить сервер вручную».'
        );
    }

    /**
     * Проверка доступности по TCP (порт SSH: поле ssh_port или 22). Для «Пинг» и перевода в «Настроен».
     */
    public function ping(Server $server): bool
    {
        $host = $server->host ?: $server->ip;
        if (empty($host)) {
            return false;
        }
        $port = $server->ssh_port !== null ? (int) $server->ssh_port : 22;
        $timeout = 5;
        $errno = 0;
        $errstr = '';
        $fp = @fsockopen($host, $port, $errno, $errstr, $timeout);
        if ($fp === false) {
            Log::debug('Manual server ping failed', [
                'server_id' => $server->id,
                'host' => $host,
                'port' => $port,
                'errno' => $errno,
                'errstr' => $errstr,
                'source' => 'server',
            ]);
            return false;
        }
        fclose($fp);
        return true;
    }

    public function getServerPassword(int $server_id): ?string
    {
        $server = Server::query()->find($server_id);
        return $server ? $server->password : null;
    }

    /**
     * Ручные серверы не участвуют в фоновой проверке статуса.
     */
    public function checkStatus(): void
    {
        // Ничего не делаем — нет API для опроса.
    }

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
     * Мягкое удаление и удаление DNS-записи в Cloudflare при наличии.
     */
    public function delete(Server $server): void
    {
        if (!empty($server->dns_record_id)) {
            try {
                $cloudflare = new CloudflareService();
                $cloudflare->deleteSubdomain($server->dns_record_id);
                Log::info('DNS record deleted for manual server', [
                    'server_id' => $server->id,
                    'dns_record_id' => $server->dns_record_id,
                    'source' => 'server',
                ]);
            } catch (\Throwable $e) {
                Log::warning('Failed to delete DNS record for manual server', [
                    'server_id' => $server->id,
                    'error' => $e->getMessage(),
                    'source' => 'server',
                ]);
            }
        }
        $server->server_status = Server::SERVER_DELETED;
        $server->save();
        Log::info('Manual server soft-deleted', [
            'server_id' => $server->id,
            'source' => 'server',
        ]);
    }
}

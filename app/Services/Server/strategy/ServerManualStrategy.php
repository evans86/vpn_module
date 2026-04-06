<?php

namespace App\Services\Server\strategy;

use App\Dto\Server\ServerFactory;
use App\Models\Server\Server;
use App\Services\Cloudflare\CloudflareService;
use App\Services\Panel\marzban\MarzbanService;
use App\Services\Panel\PanelStrategy;
use DomainException;
use Illuminate\Support\Facades\Log;
use RuntimeException;

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
     * Перезагрузка по SSH (shutdown через минуту, чтобы ответ успел вернуться).
     */
    public function reboot(Server $server): void
    {
        if (empty($server->login) || $server->password === null || $server->password === '') {
            throw new RuntimeException('Укажите логин и пароль SSH для сервера в карточке (редактирование сервера)');
        }
        $host = $server->host ?: $server->ip;
        if (empty($host)) {
            throw new RuntimeException('Не задан хост или IP сервера');
        }

        try {
            $dto = ServerFactory::fromEntity($server);
            $ssh = app(MarzbanService::class)->connectSshAdapter($dto);
            $out = $ssh->exec('/sbin/shutdown -r +1 "admin-panel" 2>&1');
            $exit = $ssh->getExitStatus();
            if ($exit !== 0 && $exit !== null) {
                throw new RuntimeException(
                    'Не удалось запланировать перезагрузку: ' . trim((string) $out)
                );
            }
            Log::info('Manual server reboot scheduled via SSH (+1 min)', [
                'server_id' => $server->id,
                'source' => 'server',
            ]);
        } catch (\Throwable $e) {
            if ($e instanceof RuntimeException) {
                throw $e;
            }
            Log::error('Manual server reboot failed', [
                'server_id' => $server->id,
                'error' => $e->getMessage(),
                'source' => 'server',
            ]);
            throw new RuntimeException('Ошибка перезагрузки по SSH: ' . $e->getMessage());
        }
    }

    /**
     * Мягкое удаление и удаление DNS-записи в Cloudflare при наличии.
     */
    public function delete(Server $server): void
    {
        if (!empty($server->dns_record_id)) {
            try {
                $cloudflare = new CloudflareService();
                $cloudflare->deleteSubdomain($server->dns_record_id, $server->cloudflare_zone_id);
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

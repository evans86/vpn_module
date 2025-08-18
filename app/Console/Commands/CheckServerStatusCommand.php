<?php

namespace App\Console\Commands;

use App\Models\Server\Server;
use App\Services\Server\ServerStrategy;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Консольная команда для проверки статуса созданных серверов и их конфигурации при готовности.
 *
 * Команда выполняет следующие действия:
 * 1. Находит все серверы со статусом "создан" и не старше 2 часов
 * 2. Для каждого сервера проверяет его текущий статус через соответствующую стратегию
 * 3. Обновляет статус сервера на основе результатов проверки
 * 4. Обрабатывает возможные ошибки, логируя их и помечая сервер как ошибочный при необходимости
 */
class CheckServerStatusCommand extends Command
{
    /**
     * Сигнатура команды для вызова из консоли.
     *
     * @var string
     */
    protected $signature = 'servers:check-status';
    /**
     * Описание команды, отображаемое при выводе списка команд.
     *
     * @var string
     */
    protected $description = 'Check status of all created servers and configure them if ready';

    /**
     * Основной метод выполнения команды.
     *
     * @return int Возвращает 0 при успешном выполнении, 1 при ошибке
     * @throws GuzzleException
     */
    public function handle(): int
    {
        try {
            // Получаем все серверы в статусе "создан" и не старше 2 часов
            $servers = Server::where('server_status', Server::SERVER_CREATED)
                ->where('created_at', '>=', now()->subHours(2))
                ->get();

            $this->info("Found {$servers->count()} servers to check");

            foreach ($servers as $server) {
                try {
                    $strategy = new ServerStrategy($server->provider);

                    $this->info("Checking server {$server->id} ({$server->provider})...");
                    $strategy->checkStatus();

                    // После checkStatus сервер должен быть либо сконфигурирован, либо остаться в статусе создан
                    $server->refresh();
                    $this->info("Server {$server->id} status: {$server->server_status}");

                } catch (\Exception $e) {
                    Log::error('Error checking server status', [
                        'server_id' => $server->id,
                        'provider' => $server->provider,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);

                    $this->error("Error checking server, retrying server {$server->id}: {$e->getMessage()}");

                    // Помечаем сервер как ошибочный
                    $server->server_status = Server::SERVER_ERROR;
                    $server->save();
                }
            }

            $this->info('Status check completed');

        } catch (\Exception $e) {
            Log::error('Server status check command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->error("Command failed: {$e->getMessage()}");
            return 1;
        }

        return 0;
    }
}

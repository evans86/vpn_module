<?php

namespace App\Console\Commands;

use App\Models\Server\Server;
use App\Services\Server\ServerStrategy;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckServerStatusCommand extends Command
{
    protected $signature = 'servers:check-status';
    protected $description = 'Check status of all created servers and configure them if ready';

    public function handle(): int
    {
        try {
            // 1. Проверяем и настраиваем новые серверы
            $this->checkAndConfigureNewServers();

            // 2. Обновляем пароли для уже сконфигурированных серверов
            $this->updatePasswordsForConfiguredServers();

            $this->info('Status check and password update completed');

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

    /**
     * Проверяет и настраивает новые серверы
     */
    private function checkAndConfigureNewServers(): void
    {
        // Получаем все серверы в статусе "создан" и не старше 2 часов
        $servers = Server::where('server_status', Server::SERVER_CREATED)
            ->where('created_at', '>=', now()->subHours(2))
            ->get();

        $this->info("Found {$servers->count()} new servers to check");

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

                $this->error("Error checking server {$server->id}: {$e->getMessage()}");

                // Помечаем сервер как ошибочный
                $server->server_status = Server::SERVER_ERROR;
                $server->save();
            }
        }
    }

    /**
     * Обновляет пароли для уже сконфигурированных серверов
     */
    private function updatePasswordsForConfiguredServers(): void
    {
        // Находим серверы VDSina которые сконфигурированы но имеют заглушку пароля
        $servers = Server::where('provider', Server::VDSINA)
            ->where('server_status', Server::SERVER_CONFIGURED)
            ->where(function($query) {
                $query->where('password', 'VDSINA_AUTO_GENERATED')
                    ->orWhere('password', 'like', 'PENDING_%')
                    ->orWhereNull('password');
            })
            ->get();

        $this->info("Found {$servers->count()} VDSina servers to update passwords");

        $updatedCount = 0;

        foreach ($servers as $server) {
            try {
                $strategy = new ServerStrategy($server->provider);

                // Пытаемся получить реальный пароль
                $realPassword = $strategy->getServerPassword($server->id);

                if ($realPassword && $realPassword !== $server->password) {
                    $server->password = $realPassword;
                    $server->save();

                    $updatedCount++;
                    $this->info("✅ Server {$server->id}: Password updated");
                } else {
                    $this->warn("⚠️ Server {$server->id}: Password not available yet");
                }

            } catch (\Exception $e) {
                Log::error('Error updating password for server', [
                    'server_id' => $server->id,
                    'error' => $e->getMessage()
                ]);
                $this->error("❌ Server {$server->id}: {$e->getMessage()}");
            }
        }

        $this->info("Password update: {$updatedCount} servers updated");
    }
}

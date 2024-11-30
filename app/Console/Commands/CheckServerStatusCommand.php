<?php

namespace App\Console\Commands;

use App\Models\Server\Server;
use App\Services\Server\ServerStrategy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckServerStatusCommand extends Command
{
    protected $signature = 'servers:check-status';
    protected $description = 'Check status of all created servers and configure them if ready';

    public function handle()
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
                    
                    $this->error("Error checking server {$server->id}: {$e->getMessage()}");
                    
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

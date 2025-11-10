<?php

namespace App\Services\VPN;

use App\Models\VPN\ConnectionLimitViolation;
use App\Models\KeyActivate\KeyActivate;
use App\Models\Panel\Panel;
use App\Logging\DatabaseLogger;
use App\Services\Panel\marzban\MarzbanService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class XrayLogMonitorService
{
    private DatabaseLogger $logger;
    private MarzbanService $marzbanService;

    public function __construct(
        DatabaseLogger $logger,
        MarzbanService $marzbanService
    ) {
        $this->logger = $logger;
        $this->marzbanService = $marzbanService;
    }

    /**
     * Основной метод мониторинга логов
     */
    public function monitorAllPanels(): void
    {
        try {
            $panels = Panel::where('panel_status', Panel::PANEL_CONFIGURED)->get();

            foreach ($panels as $panel) {
                $this->monitorPanelLogs($panel);
            }

            $this->logger->info('Xray log monitoring completed', [
                'panels_count' => $panels->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Xray log monitoring failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Мониторинг логов конкретной панели через SSH
     */
    private function monitorPanelLogs(Panel $panel): void
    {
        try {
            $ssh = $this->connectToPanel($panel);

            if (!$ssh) {
                $this->logger->warning('SSH connection failed', [
                    'panel_id' => $panel->id
                ]);
                return;
            }

            // Получаем последние логи ограничений
            $logCommand = "grep -i 'limited\\|limit exceeded\\|rejected.*user:' /var/lib/marzban/access.log | tail -20";
            $logOutput = $ssh->exec($logCommand);

            $this->processLogOutput($panel, $logOutput);

            $ssh->disconnect();

        } catch (\Exception $e) {
            Log::error('Panel log monitoring failed', [
                'panel_id' => $panel->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Подключение к панели через SSH
     */
    private function connectToPanel(Panel $panel)
    {
        try {
            $server = $panel->server;

            $ssh = new \phpseclib3\Net\SSH2($server->ip);
            $ssh->setTimeout(30);

            if ($ssh->login($server->login, $server->password)) {
                return $ssh;
            }

            return null;

        } catch (\Exception $e) {
            Log::error('SSH connection failed', [
                'panel_id' => $panel->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Обработка вывода логов
     */
    private function processLogOutput(Panel $panel, string $logOutput): void
    {
        $lines = array_filter(explode("\n", $logOutput));

        foreach ($lines as $line) {
            if (Str::contains($line, ['limited connection', 'limit exceeded', 'rejected'])) {
                $this->processLimitViolation($panel, $line);
            }
        }
    }

    /**
     * Обработка нарушения лимита
     */
    private function processLimitViolation(Panel $panel, string $logLine): void
    {
        try {
            $username = $this->extractUsernameFromLog($logLine);

            if (!$username) {
                return;
            }

            // Находим ключ активации
            $keyActivate = $this->findKeyActivateByUsername($username, $panel);

            if (!$keyActivate) {
                return;
            }

            // Получаем активные подключения пользователя
            $connectionInfo = $this->getUserConnectionInfo($panel, $username);

            if ($connectionInfo['connections'] > 3) { // Лимит 3 подключения
                $this->recordViolation($keyActivate, $connectionInfo);
            }

        } catch (\Exception $e) {
            Log::error('Error processing limit violation', [
                'log_line' => $logLine,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Извлечение username из лога
     */
    private function extractUsernameFromLog(string $logLine): ?string
    {
        // Форматы логов:
        // "rejected proxy/vless/tcp/... > common/drain: limited connection > ... user: username"
        // "... user: user-uuid ..."

        if (preg_match('/user:\s*([a-f0-9\-]+)/', $logLine, $matches)) {
            return $matches[1];
        }

        if (preg_match('/user[\s=]+([a-f0-9\-]+)/i', $logLine, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Поиск ключа активации по username (UUID)
     */
    private function findKeyActivateByUsername(string $username, Panel $panel): ?KeyActivate
    {
        return KeyActivate::whereHas('keyActivateUser.serverUser', function($query) use ($username, $panel) {
            $query->where('id', $username)
                ->where('panel_id', $panel->id);
        })->with(['keyActivateUser.serverUser'])->first();
    }

    /**
     * Получение информации о подключениях пользователя
     */
    private function getUserConnectionInfo(Panel $panel, string $username): array
    {
        try {
            $ssh = $this->connectToPanel($panel);

            if (!$ssh) {
                return ['connections' => 0, 'ips' => []];
            }

            // Получаем активные подключения пользователя
            $connectionsCommand = "grep '$username' /var/lib/marzban/access.log | grep 'accepted' | awk '{print $3}' | sort | uniq | head -10";
            $ipsOutput = $ssh->exec($connectionsCommand);

            $ssh->disconnect();

            $ips = array_filter(explode("\n", $ipsOutput));
            $connectionCount = count($ips);

            return [
                'connections' => $connectionCount,
                'ips' => $ips
            ];

        } catch (\Exception $e) {
            Log::error('Error getting user connection info', [
                'username' => $username,
                'error' => $e->getMessage()
            ]);

            return ['connections' => 0, 'ips' => []];
        }
    }

    /**
     * Запись нарушения в базу
     */
    private function recordViolation(KeyActivate $keyActivate, array $connectionInfo): void
    {
        $serverUser = $keyActivate->keyActivateUser->serverUser;
        $allowedConnections = 3;

        // Проверяем существующее активное нарушение
        $existingViolation = ConnectionLimitViolation::where([
            'key_activate_id' => $keyActivate->id,
            'status' => ConnectionLimitViolation::STATUS_ACTIVE
        ])->first();

        if ($existingViolation) {
            // Обновляем существующее нарушение
            $existingViolation->update([
                'actual_connections' => $connectionInfo['connections'],
                'ip_addresses' => $connectionInfo['ips'],
                'violation_count' => $existingViolation->violation_count + 1,
                'updated_at' => now()
            ]);
        } else {
            // Создаем новое нарушение
            ConnectionLimitViolation::create([
                'key_activate_id' => $keyActivate->id,
                'server_user_id' => $serverUser->id,
                'panel_id' => $serverUser->panel_id,
                'user_tg_id' => $keyActivate->user_tg_id,
                'allowed_connections' => $allowedConnections,
                'actual_connections' => $connectionInfo['connections'],
                'ip_addresses' => $connectionInfo['ips'],
                'violation_count' => 1,
                'status' => ConnectionLimitViolation::STATUS_ACTIVE
            ]);
        }

        $this->logger->warning('Connection limit violation recorded', [
            'key_id' => $keyActivate->id,
            'user_tg_id' => $keyActivate->user_tg_id,
            'allowed' => $allowedConnections,
            'actual' => $connectionInfo['connections'],
            'ip_count' => count($connectionInfo['ips'])
        ]);
    }
}

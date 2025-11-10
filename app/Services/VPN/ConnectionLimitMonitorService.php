<?php

namespace App\Services\VPN;

use App\Models\VPN\ConnectionLimitViolation;
use App\Models\KeyActivate\KeyActivate;
use App\Models\Panel\Panel;
use App\Logging\DatabaseLogger;
use Illuminate\Support\Facades\Log;

class ConnectionLimitMonitorService
{
    private DatabaseLogger $logger;

    public function __construct(DatabaseLogger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Запись нарушения лимита подключений
     */
    public function recordViolation(
        KeyActivate $keyActivate,
        int $actualConnections,
        array $ipAddresses = []
    ): ConnectionLimitViolation {
        try {
            $allowedConnections = 3; // Базовый лимит
            $serverUser = $keyActivate->keyActivateUser->serverUser;
            $panel = $serverUser->panel;

            // Проверяем существующее активное нарушение
            $existingViolation = ConnectionLimitViolation::where([
                'key_activate_id' => $keyActivate->id,
                'status' => ConnectionLimitViolation::STATUS_ACTIVE
            ])->first();

            if ($existingViolation) {
                // Обновляем существующее нарушение
                $existingViolation->update([
                    'actual_connections' => $actualConnections,
                    'ip_addresses' => array_unique(array_merge(
                        $existingViolation->ip_addresses ?? [],
                        $ipAddresses
                    )),
                    'violation_count' => $existingViolation->violation_count + 1
                ]);

                $this->logger->warning('Обновлено нарушение лимита подключений', [
                    'key_id' => $keyActivate->id,
                    'user_tg_id' => $keyActivate->user_tg_id,
                    'allowed' => $allowedConnections,
                    'actual' => $actualConnections,
                    'violation_count' => $existingViolation->violation_count,
                    'ip_count' => count($ipAddresses)
                ]);

                return $existingViolation;
            }

            // Создаем новое нарушение
            $violation = ConnectionLimitViolation::create([
                'key_activate_id' => $keyActivate->id,
                'server_user_id' => $serverUser->id,
                'panel_id' => $panel->id,
                'user_tg_id' => $keyActivate->user_tg_id,
                'allowed_connections' => $allowedConnections,
                'actual_connections' => $actualConnections,
                'ip_addresses' => $ipAddresses,
                'violation_count' => 1,
                'status' => ConnectionLimitViolation::STATUS_ACTIVE
            ]);

            $this->logger->warning('Зафиксировано нарушение лимита подключений', [
                'key_id' => $keyActivate->id,
                'user_tg_id' => $keyActivate->user_tg_id,
                'allowed' => $allowedConnections,
                'actual' => $actualConnections,
                'ip_count' => count($ipAddresses),
                'violation_id' => $violation->id
            ]);

            return $violation;

        } catch (\Exception $e) {
            Log::error('Ошибка записи нарушения лимита подключений', [
                'key_id' => $keyActivate->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Получить статистику нарушений
     */
    public function getViolationStats(): array
    {
        $total = ConnectionLimitViolation::count();
        $active = ConnectionLimitViolation::where('status', ConnectionLimitViolation::STATUS_ACTIVE)->count();
        $today = ConnectionLimitViolation::whereDate('created_at', today())->count();

        $topViolators = ConnectionLimitViolation::with('keyActivate')
            ->select('key_activate_id')
            ->selectRaw('COUNT(*) as violation_count')
            ->groupBy('key_activate_id')
            ->orderBy('violation_count', 'desc')
            ->limit(5)
            ->get();

        return [
            'total' => $total,
            'active' => $active,
            'today' => $today,
            'top_violators' => $topViolators
        ];
    }

    /**
     * Пометить нарушение как решенное
     */
    public function resolveViolation(ConnectionLimitViolation $violation): bool
    {
        try {
            $violation->update([
                'status' => ConnectionLimitViolation::STATUS_RESOLVED,
                'resolved_at' => now()
            ]);

            $this->logger->info('Нарушение лимита помечено как решенное', [
                'violation_id' => $violation->id,
                'key_id' => $violation->key_activate_id
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Ошибка при разрешении нарушения', [
                'violation_id' => $violation->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Пометить нарушение как проигнорированное
     */
    public function ignoreViolation(ConnectionLimitViolation $violation): bool
    {
        try {
            $violation->update([
                'status' => ConnectionLimitViolation::STATUS_IGNORED,
                'resolved_at' => now()
            ]);

            $this->logger->info('Нарушение лимита помечено как проигнорированное', [
                'violation_id' => $violation->id,
                'key_id' => $violation->key_activate_id
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Ошибка при игнорировании нарушения', [
                'violation_id' => $violation->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}

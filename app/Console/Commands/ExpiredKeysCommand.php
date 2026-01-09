<?php

namespace App\Console\Commands;

use App\Models\KeyActivate\KeyActivate;
use App\Services\Key\KeyActivateService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Команда для проверки и обновления статусов просроченных ключей
 * 
 * Проверяет:
 * 1. Оплаченные ключи (PAID) с истекшим сроком активации (deleted_at)
 * 2. Активные ключи (ACTIVE) с истекшим сроком действия (finish_at)
 */
class ExpiredKeysCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'expired:check-keys';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Проверка и обновление статусов просроченных ключей (PAID -> EXPIRED, ACTIVE -> EXPIRED)';

    private KeyActivateService $keyActivateService;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(
        KeyActivateService $keyActivateService
    )
    {
        parent::__construct();
        $this->keyActivateService = $keyActivateService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        try {
            $currentTime = now()->timestamp;
            
            // Получаем оплаченные ключи с истекшим сроком активации
            $paidKeys = KeyActivate::where('status', KeyActivate::PAID)
                ->whereNotNull('deleted_at')
                ->where('deleted_at', '<', $currentTime)
                ->get();

            // Получаем активные ключи с истекшим сроком действия
            $activeKeys = KeyActivate::where('status', KeyActivate::ACTIVE)
                ->whereNotNull('finish_at')
                ->where('finish_at', '<', $currentTime)
                ->get();

            $totalKeys = $paidKeys->count() + $activeKeys->count();
            $this->info("Found {$totalKeys} keys to check (PAID: {$paidKeys->count()}, ACTIVE: {$activeKeys->count()})");

            $updatedCount = 0;

            // Проверяем оплаченные ключи
            foreach ($paidKeys as $key) {
                try {
                    $this->info("Checking PAID key {$key->id}");

                    $key = $this->keyActivateService->checkAndUpdateStatus($key);

                    if ($key->status === KeyActivate::EXPIRED) {
                        $updatedCount++;
                        $this->info("✓ Key {$key->id} expired (activation period ended)");
                    }
                } catch (\Exception $e) {
                    Log::error('Error checking PAID key status', [
                        'source' => 'cron',
                        'key_id' => $key->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);

                    $this->error("Error checking key {$key->id}: {$e->getMessage()}");
                }
            }

            // Проверяем активные ключи
            foreach ($activeKeys as $key) {
                try {
                    $this->info("Checking ACTIVE key {$key->id}");

                    $key = $this->keyActivateService->checkAndUpdateStatus($key);

                    if ($key->status === KeyActivate::EXPIRED) {
                        $updatedCount++;
                        $this->info("✓ Key {$key->id} expired (usage period ended)");
                    }
                } catch (\Exception $e) {
                    Log::error('Error checking ACTIVE key status', [
                        'source' => 'cron',
                        'key_id' => $key->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);

                    $this->error("Error checking key {$key->id}: {$e->getMessage()}");
                }
            }

            $this->info("✓ Command completed. Updated {$updatedCount} keys to EXPIRED status.");

        } catch (\Exception $e) {
            Log::error('Keys expired check command failed', [
                'source' => 'cron',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->error("Command failed: {$e->getMessage()}");
            return 1;
        }

        return 0;
    }
}

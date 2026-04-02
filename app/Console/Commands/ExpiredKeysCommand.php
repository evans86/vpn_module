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

            $paidCount = KeyActivate::where('status', KeyActivate::PAID)
                ->whereNotNull('deleted_at')
                ->where('deleted_at', '<', $currentTime)
                ->count();

            $activeCount = KeyActivate::where('status', KeyActivate::ACTIVE)
                ->whereNotNull('finish_at')
                ->where('finish_at', '<', $currentTime)
                ->count();

            $totalKeys = $paidCount + $activeCount;
            $this->info("Found {$totalKeys} keys to check (PAID: {$paidCount}, ACTIVE: {$activeCount})");

            $updatedCount = 0;

            // Чанками — иначе при большом числе просроченных ключей ->get() съедает память (768M+)
            $processChunk = function ($keys) use (&$updatedCount) {
                foreach ($keys as $key) {
                    try {
                        // quiet: без 100k+ critical() с огромным контекстом — иначе PHP 768M не хватает
                        $key = $this->keyActivateService->checkAndUpdateStatus($key, true);

                        if ($key->status === KeyActivate::EXPIRED) {
                            $updatedCount++;
                        }
                    } catch (\Exception $e) {
                        Log::error('Error checking key status for expiry', [
                            'source' => 'cron',
                            'key_id' => $key->id,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);

                        $this->error("Error checking key {$key->id}: {$e->getMessage()}");
                    }
                    unset($key);
                }
            };

            // Нельзя chunk() с offset: после смены статуса строка выпадает из выборки, OFFSET «перескакивает» и целые
            // пачки ключей никогда не обрабатываются. chunkById курсором по id не пропускает записи.
            KeyActivate::where('status', KeyActivate::PAID)
                ->whereNotNull('deleted_at')
                ->where('deleted_at', '<', $currentTime)
                ->chunkById(200, $processChunk, 'id');

            KeyActivate::where('status', KeyActivate::ACTIVE)
                ->whereNotNull('finish_at')
                ->where('finish_at', '<', $currentTime)
                ->chunkById(200, $processChunk, 'id');

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

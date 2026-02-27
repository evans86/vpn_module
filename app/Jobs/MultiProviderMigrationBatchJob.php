<?php

namespace App\Jobs;

use App\Services\Key\MultiProviderMigrationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MultiProviderMigrationBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;

    /** @var string */
    protected $runId;
    /** @var int */
    protected $offset;
    /** @var int */
    protected $batchSize;
    /** @var bool */
    protected $dryRun;
    /** @var int|null Конечный offset среза (exclusive). Если задан — обрабатываем только [offset, maxTotal), не цепочку до конца. */
    protected $maxTotal;

    public function __construct(string $runId, int $offset, int $batchSize, bool $dryRun, ?int $maxTotal = null)
    {
        $this->runId = $runId;
        $this->offset = $offset;
        $this->batchSize = $batchSize;
        $this->dryRun = $dryRun;
        $this->maxTotal = $maxTotal;
    }

    public function handle(): void
    {
        ini_set('memory_limit', '2048M');

        $cancelKey = 'multi_provider_migration_cancel_' . $this->runId;
        if (Cache::get($cancelKey)) {
            $cacheKey = 'multi_provider_migration_' . $this->runId;
            $progress = Cache::get($cacheKey, []);
            $progress['done'] = true;
            $progress['message'] = 'Отменено пользователем';
            $progress['cancelled'] = true;
            if (!isset($progress['started_at'])) {
                $progress['started_at'] = now()->toIso8601String();
            }
            Cache::put($cacheKey, $progress, now()->addHours(3));
            return;
        }

        try {
            $cacheKey = 'multi_provider_migration_' . $this->runId;
            $progress = Cache::get($cacheKey, [
                'total' => 0,
                'processed' => 0,
                'added_total' => 0,
                'errors' => [],
                'done' => false,
                'message' => '',
                'started_at' => null,
            ]);
            if ($progress['started_at'] === null) {
                $progress['started_at'] = now()->toIso8601String();
            }

            if ($this->offset === 0 && ($progress['total'] ?? 0) === 0) {
                if ($this->maxTotal !== null) {
                    $progress['total'] = $this->maxTotal;
                } else {
                    $service = app(MultiProviderMigrationService::class);
                    $progress['total'] = $service->getTotalCount();
                }
                $progress['message'] = 'Подсчёт завершён. Обработка первой порции…';
                Cache::put($cacheKey, $progress, now()->addHours(3));
            }

            $service = app(MultiProviderMigrationService::class);
            $result = $service->runOneBatch($this->offset, $this->batchSize, $this->dryRun, $this->maxTotal);

            $progress = Cache::get($cacheKey, [
                'total' => 0,
                'processed' => 0,
                'added_total' => 0,
                'errors' => [],
                'done' => false,
                'message' => '',
                'started_at' => null,
            ]);
            if ($progress['started_at'] === null) {
                $progress['started_at'] = now()->toIso8601String();
            }

            $progress['total'] = $result['total'];
            $progress['processed'] = ($progress['processed'] ?? 0) + $result['processed'];
            $progress['added_total'] = ($progress['added_total'] ?? 0) + $result['added_total'];
            $progress['errors'] = array_merge($progress['errors'] ?? [], $result['errors']);
            $progress['done'] = $result['done'];
            $progress['message'] = $result['message'];

            Cache::put($cacheKey, $progress, now()->addHours(3));

            if (Cache::get($cancelKey)) {
                $progress['done'] = true;
                $progress['message'] = 'Отменено пользователем';
                $progress['cancelled'] = true;
                Cache::put($cacheKey, $progress, now()->addHours(3));
                return;
            }
            if (!$result['done'] && $result['success']) {
                $nextOffset = $result['next_offset'];
                $withinSlice = $this->maxTotal === null || $nextOffset < $this->maxTotal;
                if ($withinSlice) {
                    self::dispatch(
                        $this->runId,
                        $nextOffset,
                        $this->batchSize,
                        $this->dryRun,
                        $this->maxTotal
                    )->onQueue($this->queue);
                }
            }
        } catch (\Throwable $e) {
            Log::error('MultiProviderMigrationBatchJob failed', [
                'run_id' => $this->runId,
                'offset' => $this->offset,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $cacheKey = 'multi_provider_migration_' . $this->runId;
            $progress = Cache::get($cacheKey, []);
            $progress['done'] = true;
            $progress['message'] = 'Ошибка: ' . $e->getMessage();
            $progress['error'] = $e->getMessage();
            Cache::put($cacheKey, $progress, now()->addHours(3));
        }
        // Не восстанавливаем memory_limit: после обработки использование может превышать
        // исходный лимит (512M), ini_set() вызовет ошибку. Оставляем 2048M до выхода процесса.
    }
}

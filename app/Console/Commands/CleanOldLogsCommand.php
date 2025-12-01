<?php

namespace App\Console\Commands;

use App\Repositories\Log\LogRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanOldLogsCommand extends Command
{
    protected $signature = 'logs:clean {--days=30 : Количество дней для хранения логов}';

    protected $description = 'Очистка старых логов из базы данных';

    private LogRepository $logRepository;

    public function __construct(LogRepository $logRepository)
    {
        parent::__construct();
        $this->logRepository = $logRepository;
    }

    public function handle(): int
    {
        $days = (int) $this->option('days');

        $this->info("🧹 Очистка логов старше {$days} дней...");

        $deletedCount = $this->logRepository->cleanOldLogs($days);

        if ($deletedCount > 0) {
            $this->info("✅ Удалено логов: {$deletedCount}");
            
            Log::info('Cleaned old logs via command', [
                'source' => 'system',
                'deleted_count' => $deletedCount,
                'days' => $days
            ]);
        } else {
            $this->info("ℹ️ Логи для удаления не найдены");
        }

        return 0;
    }
}


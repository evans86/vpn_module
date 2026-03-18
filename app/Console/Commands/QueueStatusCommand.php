<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Показывает статус очереди: подключение, число заданий в ожидании и провалившихся.
 * Использование: php artisan queue:status
 */
class QueueStatusCommand extends Command
{
    protected $signature = 'queue:status';

    protected $description = 'Показать статус очереди (ожидающие и провалившиеся задания)';

    public function handle(): int
    {
        $connection = config('queue.default');
        $this->line('Подключение очереди: <info>' . $connection . '</info>');

        if ($connection === 'sync') {
            $this->warn('Очередь в режиме sync — задания выполняются сразу в запросе, воркер не нужен.');
            $this->line('Для рассылки и фоновых задач в .env укажите: QUEUE_CONNECTION=database');
            return 0;
        }

        $pending = 0;
        $failed = 0;

        if ($connection === 'database') {
            if (Schema::hasTable('jobs')) {
                $pending = (int) DB::table('jobs')->count();
            }
            if (Schema::hasTable('failed_jobs')) {
                $failed = (int) DB::table('failed_jobs')->count();
            }
        }

        $this->line('В ожидании: <info>' . $pending . '</info> заданий');
        $this->line('Провалилось: <info>' . $failed . '</info> заданий');

        if ($pending > 0) {
            $this->newLine();
            $this->comment('Чтобы задания обрабатывались в фоне, запустите воркер:');
            $this->line('  php artisan queue:work-safe ' . $connection);
            $this->line('Или на Windows: scripts\\start-queue-worker.bat');
        }

        if ($failed > 0) {
            $this->newLine();
            $this->comment('Просмотр провалившихся: php artisan queue:failed');
            $this->line('Повторить все: php artisan queue:retry all');
        }

        return 0;
    }
}

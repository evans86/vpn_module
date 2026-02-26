<?php

namespace App\Console\Commands;

use App\Queue\WorkerNoPcntl;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Queue\WorkerOptions;

/**
 * Запуск воркера очереди без pcntl (для хостингов с отключённым pcntl_signal).
 * Использование: php artisan queue:work-safe
 * Опции те же, что у queue:work (--once, --stop-when-empty, --queue=, --sleep= и т.д.).
 *
 * Остановка: php artisan queue:restart — воркер завершит текущий джоб и выйдет.
 * Либо Ctrl+C (процесс завершится, текущий джоб может быть прерван и повторно поставлен).
 */
class QueueWorkSafeCommand extends Command
{
    protected $signature = 'queue:work-safe
                            {connection? : The name of the queue connection to work}
                            {--name=default : The name of the worker}
                            {--queue= : The names of the queues to work}
                            {--once : Only process the next job on the queue}
                            {--stop-when-empty : Stop when the queue is empty}
                            {--delay=0 : The number of seconds to delay failed jobs (Deprecated)}
                            {--backoff=0 : The number of seconds to wait before retrying a job that encountered an uncaught exception}
                            {--max-jobs=0 : The number of jobs to process before stopping}
                            {--max-time=0 : The maximum number of seconds the worker should run}
                            {--force : Force the worker to run even in maintenance mode}
                            {--memory=128 : The memory limit in megabytes}
                            {--sleep=3 : Number of seconds to sleep when no job is available}
                            {--rest=0 : Number of seconds to rest between jobs}
                            {--timeout=60 : The number of seconds a child process can run}
                            {--tries=1 : Number of times to attempt a job before logging it failed}';

    protected $description = 'Run the queue worker without pcntl. Stop: queue:restart or Ctrl+C';

    public function handle(WorkerNoPcntl $worker, Cache $cache): int
    {
        if ($this->downForMaintenance() && $this->option('once')) {
            $worker->sleep((int) $this->option('sleep'));
            return 0;
        }

        if (!$this->option('once')) {
            $this->comment('Остановка: выполните в другом терминале «php artisan queue:restart» или нажмите Ctrl+C.');
        }

        $this->listenForEvents();

        $connection = $this->argument('connection') ?: $this->laravel['config']['queue.default'];
        $queue = $this->option('queue') ?: $this->laravel['config']->get("queue.connections.{$connection}.queue", 'default');

        $worker->setCache($cache);
        $options = $this->gatherWorkerOptions();

        return $worker->{$this->option('once') ? 'runNextJob' : 'daemon'}(
            $connection,
            $queue,
            $options
        );
    }

    protected function listenForEvents(): void
    {
        $this->laravel['events']->listen(\Illuminate\Queue\Events\JobProcessing::class, function ($event) {
            $this->writeStatus($event->job, 'Processing', 'comment');
        });
        $this->laravel['events']->listen(\Illuminate\Queue\Events\JobProcessed::class, function ($event) {
            $this->writeStatus($event->job, 'Processed', 'info');
        });
        $this->laravel['events']->listen(\Illuminate\Queue\Events\JobFailed::class, function ($event) {
            $this->writeStatus($event->job, 'Failed', 'error');
        });
    }

    protected function writeStatus($job, string $status, string $type): void
    {
        $this->output->writeln(sprintf(
            '<%s>[%s][%s] %s</%s> %s',
            $type,
            now()->format('Y-m-d H:i:s'),
            $job->getJobId(),
            str_pad($status . ':', 11),
            $type,
            $job->resolveName()
        ));
    }

    protected function gatherWorkerOptions(): WorkerOptions
    {
        return new WorkerOptions(
            $this->option('name'),
            max((int) $this->option('backoff'), (int) $this->option('delay')),
            (int) $this->option('memory'),
            (int) $this->option('timeout'),
            (int) $this->option('sleep'),
            (int) $this->option('tries'),
            $this->option('force'),
            $this->option('stop-when-empty'),
            (int) $this->option('max-jobs'),
            (int) $this->option('max-time'),
            (int) $this->option('rest')
        );
    }

    protected function downForMaintenance(): bool
    {
        return $this->option('force') ? false : $this->laravel->isDownForMaintenance();
    }
}

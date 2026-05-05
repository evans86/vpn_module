<?php

namespace App\Providers;

use App\Queue\WorkerNoPcntl;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Queue\Events\JobProcessed;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Воркер очереди без pcntl (перезаписываем после QueueServiceProvider)
        $workerFactory = function ($app) {
            return new WorkerNoPcntl(
                $app['queue'],
                $app['events'],
                $app[ExceptionHandler::class],
                function () use ($app) {
                    return $app->isDownForMaintenance();
                }
            );
        };
        $this->app->singleton(\Illuminate\Queue\Worker::class, function ($app) use ($workerFactory) {
            $worker = $workerFactory($app);
            if ($app->bound('cache')) {
                $worker->setCache($app['cache']);
            }
            return $worker;
        });
        $this->app->bind(WorkerNoPcntl::class, function ($app) use ($workerFactory) {
            return $workerFactory($app);
        });
        // Устанавливаем часовой пояс приложения для PHP
        $timezone = config('app.timezone', 'Europe/Moscow');
        date_default_timezone_set($timezone);
        
        if (config('app.env') === 'production') {
            URL::forceScheme('https');

            // За прокси: считаем запрос HTTPS (только HTTP-запросы, не консоль).
            if (!$this->app->runningInConsole() && $this->app->has('request')) {
                $req = $this->app->make('request');
                if (!$req->secure()) {
                    $req->server->set('HTTPS', 'on');
                }
            }
        }

        // HTTP: см. middleware ForceUrlRootForTrustedHost (после TrustProxies — корректный Host зеркала).
        $defaultRoot = rtrim((string) config('app.url'), '/');
        if ($this->app->runningInConsole() && $defaultRoot !== '') {
            URL::forceRootUrl($defaultRoot);
        }
        
        Paginator::useBootstrap();

        // Лог успешно обработанных заданий очереди (для страницы «Очередь заданий»).
        $this->app['events']->listen(JobProcessed::class, function (JobProcessed $event): void {
            try {
                if (!Schema::hasTable('processed_jobs')) {
                    Log::warning('Queue: processed_jobs table missing. Run: php artisan migrate');
                    return;
                }
                $job = $event->job;
                $payload = method_exists($job, 'payload') ? $job->payload() : (array) json_decode($job->getRawBody(), true);
                $jobName = $payload['displayName'] ?? class_basename($payload['job'] ?? 'Unknown');
                $uuid = method_exists($job, 'uuid') ? $job->uuid() : null;
                DB::table('processed_jobs')->insert([
                    'uuid' => $uuid,
                    'queue' => $job->getQueue(),
                    'job_name' => Str::limit($jobName, 255),
                    'processed_at' => now(),
                ]);
                // Оставляем только последние 500 записей.
                $ids = DB::table('processed_jobs')->orderByDesc('id')->limit(self::PROCESSED_JOBS_KEEP)->pluck('id');
                if ($ids->isNotEmpty()) {
                    DB::table('processed_jobs')->whereNotIn('id', $ids)->delete();
                }
            } catch (\Throwable $e) {
                Log::error('Queue: failed to log processed job', ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            }
        });
    }

    private const PROCESSED_JOBS_KEEP = 500;
}

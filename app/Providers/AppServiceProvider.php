<?php

namespace App\Providers;

use App\Queue\WorkerNoPcntl;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
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
        
        if(config('app.env') === 'production') {
            URL::forceScheme('https');
            
            // Force HTTPS for all asset URLs
            if (!request()->secure()) {
                $this->app['request']->server->set('HTTPS', true);
            }
        }

        // Генерация ссылок на тот же хост, с которого пришёл запрос (основной домен или зеркало).
        // Учитываем X-Forwarded-Host на случай, когда прокси ещё не обработан TrustProxies или хост приходит только в заголовке.
        $allowedHosts = config('app.allowed_url_hosts', []);
        if (!empty($allowedHosts)) {
            $host = request()->header('X-Forwarded-Host');
            if (is_string($host)) {
                $host = trim(explode(',', $host)[0]);
            }
            if (empty($host) || !in_array($host, $allowedHosts, true)) {
                $host = request()->getHost();
            }
            if (in_array($host, $allowedHosts, true)) {
                $scheme = request()->header('X-Forwarded-Proto', request()->getScheme());
                if (is_string($scheme)) {
                    $scheme = trim(explode(',', $scheme)[0]);
                }
                URL::forceRootUrl($scheme . '://' . $host);
            }
        }
        
        Paginator::useBootstrap();

        // Лог успешно обработанных заданий очереди (для страницы «Очередь заданий»).
        $this->app['events']->listen(JobProcessed::class, function (JobProcessed $event): void {
            if (!Schema::hasTable('processed_jobs')) {
                return;
            }
            $job = $event->job;
            $payload = json_decode($job->getRawBody(), true);
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
        });
    }

    private const PROCESSED_JOBS_KEEP = 500;
}

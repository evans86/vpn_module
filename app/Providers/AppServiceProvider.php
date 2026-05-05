<?php

namespace App\Providers;

use App\Helpers\UrlHelper;
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

        // Корень для URL::route() и redirect()->route():
        // — если Host входит в те же паттерны, что и TrustHosts (основной + APP_CONFIG_PUBLIC_URL + APP_MIRROR_URLS*
        //   включая поддомены вида *.mirror), генерируем URL на текущий origin — иначе админка с зеркала
        //   после входа собирается с APP_URL и уходит на заблокированный основной домен;
        // — в консоли, очередях и прочих случаях — канонический APP_URL.
        $defaultRoot = rtrim((string) config('app.url'), '/');
        $multiHosts = config('app.pwa_service_worker_hosts', []);
        if (!$this->app->runningInConsole() && $this->app->has('request')) {
            try {
                $request = $this->app->make('request');
                $host = $this->originalRequestHost($request);
                $hostLower = strtolower($host);
                $multiHostsLower = is_array($multiHosts) ? array_map('strtolower', $multiHosts) : [];
                $onKnownHost =
                    $hostLower !== ''
                    && (($multiHostsLower !== [] && in_array($hostLower, $multiHostsLower, true))
                        || UrlHelper::hostMatchesTrustedApplicationPatterns($hostLower));
                if ($onKnownHost) {
                    URL::forceRootUrl($this->requestOrigin($request, $hostLower));
                } elseif ($defaultRoot !== '') {
                    URL::forceRootUrl($defaultRoot);
                }
            } catch (\Throwable $e) {
                if ($defaultRoot !== '') {
                    URL::forceRootUrl($defaultRoot);
                }
            }
        } elseif ($defaultRoot !== '') {
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

    private function originalRequestHost($request): string
    {
        $forwardedHost = (string) $request->headers->get('x-forwarded-host', '');
        if ($forwardedHost !== '') {
            $first = trim(explode(',', $forwardedHost)[0]);
            if ($first !== '') {
                return $first;
            }
        }

        $refererHost = $this->allowedRefererHost($request);
        if ($refererHost !== '') {
            return $refererHost;
        }

        return (string) $request->getHost();
    }

    private function requestOrigin($request, string $host): string
    {
        $proto = (string) $request->headers->get('x-forwarded-proto', '');
        if ($proto !== '') {
            $proto = trim(explode(',', $proto)[0]);
        }
        if ($proto === '') {
            $referer = (string) $request->headers->get('referer', '');
            $refererHost = strtolower((string) parse_url($referer, PHP_URL_HOST));
            if ($refererHost !== '' && strcasecmp($refererHost, $host) === 0) {
                $proto = (string) parse_url($referer, PHP_URL_SCHEME);
            }
        }
        if ($proto === '') {
            $proto = 'https';
        }

        return strtolower($proto) . '://' . $host;
    }

    private function allowedRefererHost($request): string
    {
        $referer = (string) $request->headers->get('referer', '');
        if ($referer === '') {
            return '';
        }

        $host = strtolower((string) parse_url($referer, PHP_URL_HOST));
        if ($host === '') {
            return '';
        }

        $allowedHosts = array_map('strtolower', (array) config('app.pwa_service_worker_hosts', []));
        return in_array($host, $allowedHosts, true) ? $host : '';
    }

    private const PROCESSED_JOBS_KEEP = 500;
}

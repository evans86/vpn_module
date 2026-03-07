<?php

namespace App\Providers;

use App\Queue\WorkerNoPcntl;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\URL;

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
    }
}

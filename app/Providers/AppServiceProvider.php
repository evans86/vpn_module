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
        
        Paginator::useBootstrap();
    }
}

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
        // Воркер очереди без pcntl (для хостингов, где pcntl_signal отключён)
        $this->app->singleton(\Illuminate\Queue\Worker::class, function ($app) {
            $worker = new WorkerNoPcntl(
                $app['queue'],
                $app['events'],
                $app[ExceptionHandler::class],
                function () use ($app) {
                    return $app->isDownForMaintenance();
                }
            );
            if ($app->bound('cache')) {
                $worker->setCache($app['cache']);
            }
            return $worker;
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
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

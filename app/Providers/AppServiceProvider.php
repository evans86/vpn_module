<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Asset;

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
        if(config('app.env') === 'production') {
            \URL::forceScheme('https');
            \Asset::forceSsl();
            
            // Force HTTPS for all asset URLs
            if (!request()->secure()) {
                $this->app['request']->server->set('HTTPS', true);
            }
        }
        
        Paginator::useBootstrap();
    }
}

<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class CacheServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Override cache configuration
        $this->app['config']->set('cache.default', 'array');

        // Ensure file cache path exists
        $cachePath = storage_path('framework/cache/data');
        if (!is_dir($cachePath)) {
            mkdir($cachePath, 0777, true);
        }
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
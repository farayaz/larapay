<?php

namespace Farayaz\Larapay;

use Illuminate\Support\ServiceProvider;

class LarapayServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind('larapay', function ($app) {
            return new Larapay;
        });
    }

    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'larapay');
        // $this->publishes([
        //     __DIR__ . '/../resources/views' => resource_path('views/vendor/larapay'),
        // ]);
    }
}

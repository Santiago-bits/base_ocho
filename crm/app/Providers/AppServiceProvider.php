<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // URLs en español: /empresas/crear, /empresas/5/editar
        Route::resourceVerbs(['create' => 'crear', 'edit' => 'editar']);

        Paginator::defaultView('partials.paginacion');

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }
}

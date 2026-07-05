<?php

namespace App\Providers;

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

    public function boot(): void
    {
        \Carbon\Carbon::setLocale('es');
        date_default_timezone_set('America/Argentina/Buenos_Aires');

        // Cargar las migraciones de Sanctum de forma automática desde el directorio vendor
        $this->loadMigrationsFrom(base_path('vendor/laravel/sanctum/database/migrations'));

        // Forzar esquema HTTPS en producción
        if (config('app.env') === 'production') {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }
    }
}

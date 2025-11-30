<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * Define as rotas da aplicação.
     */
    public function boot(): void
    {
        // 🔹 Registra as rotas da aplicação (API e Web)
        $this->routes(function () {

            // Rotas de API (sem sessão / CSRF)
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            // Rotas Web (com sessão e autenticação)
            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}

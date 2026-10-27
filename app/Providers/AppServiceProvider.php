<?php

namespace App\Providers;

use App\Models\Liquidacion;
use App\Models\Tarifa;
use App\Models\Viaje;
use App\Policies\LiquidacionPolicy;
use App\Policies\TarifaPolicy;
use App\Policies\ViajePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(Viaje::class, ViajePolicy::class);
        Gate::policy(Liquidacion::class, LiquidacionPolicy::class);
        Gate::policy(Tarifa::class, TarifaPolicy::class);

        // Que un error de programación no pase inadvertido en desarrollo:
        // relaciones sin cargar, atributos que no existen, asignación masiva.
        Model::shouldBeStrict($this->app->isLocal());
    }
}

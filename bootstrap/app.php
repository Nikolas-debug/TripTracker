<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // El front es una SPA servida por este mismo Laravel, así que no hay
        // CORS que resolver. Si algún día se separa el dominio, se habilita
        // aquí con $middleware->api(prepend: [...]).
        // En Railway el HTTPS termina en su proxy: sin esto la app se cree en
        // http y arma los enlaces a JS y CSS en http, que el navegador bloquea.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'admin' => App\Http\Middleware\SoloAdministrador::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Las excepciones de dominio traen su propio render(); no hace falta
        // mapearlas aquí.
    })->create();

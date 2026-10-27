<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * El panel y el alta de consorcios son del administrador. La verificación
 * está aquí y no repartida por los controladores para que agregar una ruta
 * nueva al grupo no dependa de acordarse de comprobar el rol.
 */
class SoloAdministrador
{
    public function handle(Request $request, Closure $siguiente): Response
    {
        abort_unless(
            $request->user()?->esAdministrador(),
            403,
            'Esta sección es solo para administradores.'
        );

        return $siguiente($request);
    }
}

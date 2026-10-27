<?php

use App\Enums\RolUsuario;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;

/**
 * Para crear el primer administrador en producción, donde no se corre el
 * seeder. La clave se pide sin mostrarla, para que no quede en el historial.
 */
Artisan::command('usuarios:crear {email} {nombre} {--admin : Con rol de administrador}', function (string $email, string $nombre) {
    $clave = $this->secret('Clave (mínimo 8 caracteres)');

    $validador = Validator::make(
        ['email' => $email, 'nombre' => $nombre, 'clave' => $clave, 'clave_confirmation' => $this->secret('Repite la clave')],
        [
            'email' => ['required', 'email', 'unique:users,email'],
            'nombre' => ['required', 'string', 'max:255'],
            'clave' => ['required', 'string', 'min:8', 'confirmed'],
        ],
    );

    if ($validador->fails()) {
        foreach ($validador->errors()->all() as $error) {
            $this->error($error);
        }

        return 1;
    }

    $usuario = User::create([
        'name' => $nombre,
        'email' => $email,
        'password' => $clave,
        'rol' => $this->option('admin') ? RolUsuario::Administrador : RolUsuario::Usuario,
    ]);

    $this->info("Usuario {$usuario->email} creado como {$usuario->rol->value}.");
})->purpose('Crear un usuario del sistema (con --admin para administrador)');

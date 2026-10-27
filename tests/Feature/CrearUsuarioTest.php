<?php

use App\Enums\RolUsuario;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('crea un administrador con la clave pedida', function () {
    $this->artisan('usuarios:crear', ['email' => 'jefe@gruptous.co', 'nombre' => 'Jefe', '--admin' => true])
        ->expectsQuestion('Clave (mínimo 8 caracteres)', 'clave-segura')
        ->expectsQuestion('Repite la clave', 'clave-segura')
        ->assertSuccessful();

    $usuario = User::where('email', 'jefe@gruptous.co')->sole();

    expect($usuario->rol)->toBe(RolUsuario::Administrador)
        ->and(Hash::check('clave-segura', $usuario->password))->toBeTrue();
});

it('no crea nada si las claves no coinciden o son cortas', function () {
    $this->artisan('usuarios:crear', ['email' => 'jefe@gruptous.co', 'nombre' => 'Jefe'])
        ->expectsQuestion('Clave (mínimo 8 caracteres)', 'corta')
        ->expectsQuestion('Repite la clave', 'otra')
        ->assertFailed();

    expect(User::count())->toBe(0);
});

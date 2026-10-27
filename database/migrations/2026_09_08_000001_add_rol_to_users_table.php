<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Se reutiliza la tabla `users` que trae Laravel en lugar de crear `usuarios`:
 * auth, password resets y Sanctum funcionan sin tocar nada. Solo se le agregan
 * el rol y el interruptor de activo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('rol', 20)->default('usuario')->after('email');
            $table->boolean('activo')->default(true)->after('rol');
        });

        DB::statement("
            ALTER TABLE users
            ADD CONSTRAINT users_rol_valido
            CHECK (rol IN ('usuario', 'administrador'))
        ");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_rol_valido');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['rol', 'activo']);
        });
    }
};

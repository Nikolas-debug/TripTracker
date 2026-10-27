<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * TERCEROS: personas. Sin campo "rol".
         * Ser dueño o conductor se deduce de las relaciones:
         *   dueño     -> aparece en volquetas.propietario_id
         *   conductor -> aparece en viajes.conductor_id
         * Así una misma persona puede ser ambas cosas sin duplicarse.
         */
        Schema::create('terceros', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 80);
            $table->string('apellido', 80)->nullable();
            $table->string('cedula', 15)->unique();
            $table->string('telefono', 20)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('volquetas', function (Blueprint $table) {
            $table->id();
            $table->string('placa', 10)->unique();

            // Quien cobra por los viajes de esta volqueta.
            $table->foreignId('propietario_id')->constrained('terceros')->restrictOnDelete();

            // Solo para autocompletar el formulario de viajes.
            $table->foreignId('conductor_habitual_id')->nullable()->constrained('terceros')->nullOnDelete();

            $table->string('tipo', 40)->nullable();
            $table->smallInteger('capacidad_m3')->nullable();

            // Las 2 volquetas de la empresa: no generan pago a dueño,
            // sino pago por porcentaje al conductor.
            $table->boolean('es_propia')->default(false);

            $table->string('estado', 20)->default('activa');
            $table->timestamps();

            $table->index('propietario_id');
        });

        DB::statement("
            ALTER TABLE volquetas
            ADD CONSTRAINT volquetas_estado_valido
            CHECK (estado IN ('activa', 'inactiva'))
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('volquetas');
        Schema::dropIfExists('terceros');
    }
};

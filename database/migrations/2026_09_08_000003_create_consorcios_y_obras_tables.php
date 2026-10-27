<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consorcios', function (Blueprint $table) {
            $table->id();
            $table->string('razon_social', 150);
            $table->string('nit', 20)->unique();
            $table->string('pais', 60)->default('Colombia');
            $table->string('departamento', 60)->nullable();
            $table->string('municipio', 60)->nullable();
            $table->string('direccion_principal', 150)->nullable();
            $table->string('correo', 120)->nullable();
            $table->string('telefono', 20)->nullable();
            $table->string('estado_contrato', 20)->default('activo');
            $table->timestamps();
        });

        /**
         * OBRAS: el destino real de los viajes.
         * `nombre` es como se le dice en la práctica ("Parque San Jerónimo",
         * "Las Palomas KM 30"), y es lo que el digitador va a buscar.
         */
        Schema::create('obras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consorcio_id')->constrained('consorcios')->restrictOnDelete();
            $table->string('nombre', 120);
            $table->string('direccion', 150)->nullable();
            $table->string('municipio', 60)->nullable();
            $table->string('estado', 20)->default('activa');
            $table->timestamps();

            $table->unique(['consorcio_id', 'nombre']);
        });

        DB::statement("
            ALTER TABLE consorcios
            ADD CONSTRAINT consorcios_estado_valido
            CHECK (estado_contrato IN ('activo', 'suspendido', 'terminado'))
        ");

        DB::statement("
            ALTER TABLE obras
            ADD CONSTRAINT obras_estado_valido
            CHECK (estado IN ('activa', 'terminada'))
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('obras');
        Schema::dropIfExists('consorcios');
    }
};

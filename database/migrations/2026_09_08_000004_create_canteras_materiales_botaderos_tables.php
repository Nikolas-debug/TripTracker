<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Origen de los viajes de suministro.
        Schema::create('canteras', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120)->unique();
            $table->string('municipio', 60)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // Destino de los viajes de retiro de escombro.
        Schema::create('botaderos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120)->unique();
            $table->string('municipio', 60)->nullable();
            $table->string('direccion', 150)->nullable();

            // Si el sitio cobra por recibir. Pesos por m3, sin decimales.
            $table->bigInteger('precio_disposicion_m3')->nullable();

            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        /**
         * MATERIALES con nombre limpio: "piedra china", "arena mediana", "relleno".
         * La cantera NO va dentro del nombre (en el Excel viene pegada:
         * "PIEDRA RAJÓN CANTERA VILLA CARMEN"). Separarlas es lo que permite
         * comparar el precio de un mismo material entre canteras.
         */
        Schema::create('materiales', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120)->unique();
            $table->string('unidad', 10)->default('m3');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        /**
         * Qué vende cada cantera y a qué precio de compra.
         * (En el diagrama original se llamaba "detalle de la compra".)
         */
        Schema::create('cantera_material', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cantera_id')->constrained('canteras')->restrictOnDelete();
            $table->foreignId('material_id')->constrained('materiales')->restrictOnDelete();
            $table->bigInteger('precio_compra_m3')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['cantera_id', 'material_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cantera_material');
        Schema::dropIfExists('materiales');
        Schema::dropIfExists('botaderos');
        Schema::dropIfExists('canteras');
    }
};

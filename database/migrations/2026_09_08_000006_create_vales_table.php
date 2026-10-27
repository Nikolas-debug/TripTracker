<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * VALES: el papel firmado por el ingeniero de obra que traen los conductores
 * los jueves y viernes. Un vale puede amparar VARIOS viajes; cada viaje
 * se ampara en uno solo.
 *
 * La numeración es del talonario de la obra, por eso el único es
 * (obra_id, numero) y no el número solo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vales', function (Blueprint $table) {
            $table->id();
            $table->integer('numero');
            $table->foreignId('obra_id')->constrained('obras')->restrictOnDelete();
            $table->date('fecha_vale');
            $table->date('fecha_recepcion')->nullable();
            $table->string('firmado_por', 120)->nullable();   // ingeniero de obra
            $table->foreignId('recibido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->text('observacion')->nullable();
            $table->timestamps();

            // Esta restricción es la que impide que dos usuarios registren
            // el mismo vale al tiempo.
            $table->unique(['obra_id', 'numero']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vales');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * LIQUIDACIONES: las "CUENTAS" del Excel.
 *   tipo = cobro -> cuenta al consorcio
 *   tipo = pago  -> cuenta al dueño de volquetas o al conductor propio
 *
 * ANTICIPOS: los abonos que se descuentan del total.
 *   "ANTICIPO 13 DE AGOSTO: 8.000.000 -> SALDO PENDIENTE: 15.400.000"
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('liquidaciones', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 10);              // cobro | pago
            $table->integer('numero_cuenta');
            $table->foreignId('consorcio_id')->nullable()->constrained('consorcios')->restrictOnDelete();
            $table->foreignId('tercero_id')->nullable()->constrained('terceros')->restrictOnDelete();
            $table->date('fecha_inicio');
            $table->date('fecha_fin');

            // Totales en pesos enteros, recalculados al cerrar.
            $table->bigInteger('total_bruto')->default(0);
            $table->bigInteger('total_anticipos')->default(0);
            $table->bigInteger('saldo')->default(0);

            $table->string('estado', 20)->default('abierta');
            $table->foreignId('cerrada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cerrada_en')->nullable();
            $table->timestamps();

            $table->index(['tipo', 'estado']);
        });

        Schema::create('anticipos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('liquidacion_id')->constrained('liquidaciones')->cascadeOnDelete();
            $table->date('fecha');
            $table->bigInteger('valor');
            $table->string('observacion', 150)->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::statement("
            ALTER TABLE liquidaciones
            ADD CONSTRAINT liquidaciones_tipo_valido
            CHECK (tipo IN ('cobro', 'pago'))
        ");

        DB::statement("
            ALTER TABLE liquidaciones
            ADD CONSTRAINT liquidaciones_estado_valido
            CHECK (estado IN ('abierta', 'cerrada', 'pagada'))
        ");

        // Una cuenta de cobro es a un consorcio; una de pago, a un tercero.
        DB::statement("
            ALTER TABLE liquidaciones
            ADD CONSTRAINT liquidaciones_destinatario_coherente
            CHECK (
                (tipo = 'cobro' AND consorcio_id IS NOT NULL AND tercero_id IS NULL)
                OR
                (tipo = 'pago' AND tercero_id IS NOT NULL AND consorcio_id IS NULL)
            )
        ");

        DB::statement("
            ALTER TABLE liquidaciones
            ADD CONSTRAINT liquidaciones_periodo_coherente
            CHECK (fecha_fin >= fecha_inicio)
        ");

        DB::statement("
            ALTER TABLE anticipos
            ADD CONSTRAINT anticipos_valor_positivo
            CHECK (valor > 0)
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('anticipos');
        Schema::dropIfExists('liquidaciones');
    }
};

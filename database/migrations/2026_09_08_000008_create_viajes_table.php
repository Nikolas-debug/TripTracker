<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * VIAJES: el corazón del sistema.
 *
 * Todo viaje es una ruta:
 *   suministro -> cantera_material (origen) hacia obra (destino)
 *   retiro     -> obra (origen)             hacia botadero (destino)
 *
 * Los valores se COPIAN de tarifas al registrar y se quedan congelados aquí.
 * Los reportes leen estas columnas; nunca hacen join contra tarifas.
 * Si se calculara al vuelo, una subida de precio reescribiría los totales
 * de cuentas ya cobradas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('viajes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('volqueta_id')->constrained('volquetas')->restrictOnDelete();
            $table->foreignId('conductor_id')->nullable()->constrained('terceros')->nullOnDelete();

            $table->string('tipo_viaje', 12);   // suministro | retiro

            // Destino si es suministro; ORIGEN si es retiro.
            $table->foreignId('obra_id')->constrained('obras')->restrictOnDelete();

            $table->foreignId('cantera_material_id')->nullable()->constrained('cantera_material')->restrictOnDelete();
            $table->foreignId('botadero_id')->nullable()->constrained('botaderos')->restrictOnDelete();

            $table->date('fecha_viaje');

            // Lo acordado al digitar vs. lo que dijo el vale.
            // La diferencia entre ambos es la señal de seguimiento del administrador,
            // por eso m3_registrado no se sobrescribe nunca.
            $table->smallInteger('m3_registrado');
            $table->smallInteger('m3_confirmado')->nullable();

            // --- Snapshot de tarifas (pesos enteros) ---
            $table->bigInteger('valor_cobro_unitario');
            $table->string('unidad_cobro', 12);

            $table->bigInteger('valor_pago_unitario')->nullable();
            $table->string('unidad_pago', 12)->nullable();

            // Solo volquetas propias: base de la ruta, porcentaje y resultado.
            // Se guardan los tres para poder explicarle al conductor de dónde salió su pago.
            $table->bigInteger('valor_base_conductor')->nullable();
            $table->decimal('porcentaje_conductor', 5, 2)->nullable();
            $table->bigInteger('valor_pago_conductor')->nullable();

            // --- Confirmación ---
            $table->string('estado', 20)->default('por_confirmar');
            $table->foreignId('vale_id')->nullable()->constrained('vales')->restrictOnDelete();
            $table->timestamp('fecha_confirmacion')->nullable();

            // --- Liquidación ---
            $table->foreignId('liquidacion_cobro_id')->nullable()->constrained('liquidaciones')->restrictOnDelete();
            $table->foreignId('liquidacion_pago_id')->nullable()->constrained('liquidaciones')->restrictOnDelete();

            // --- Auditoría ---
            $table->foreignId('registrado_por')->constrained('users')->restrictOnDelete();
            $table->foreignId('confirmado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->text('observaciones')->nullable();

            $table->timestamps();

            // La pantalla de "pendientes por confirmar" vive de este índice.
            $table->index(['obra_id', 'estado', 'fecha_viaje']);
            $table->index('vale_id');
            $table->index('liquidacion_pago_id');
            $table->index('liquidacion_cobro_id');
            $table->index(['volqueta_id', 'fecha_viaje']);
        });

        DB::statement("
            ALTER TABLE viajes
            ADD CONSTRAINT viajes_tipo_valido
            CHECK (tipo_viaje IN ('suministro', 'retiro'))
        ");

        DB::statement("
            ALTER TABLE viajes
            ADD CONSTRAINT viajes_estado_valido
            CHECK (estado IN ('por_confirmar', 'confirmado', 'anulado'))
        ");

        /**
         * Coherencia de la ruta. Se valida también en la aplicación, pero la
         * app se puede saltar (una carga masiva, un script de migración);
         * la base no.
         */
        DB::statement("
            ALTER TABLE viajes
            ADD CONSTRAINT viajes_ruta_coherente
            CHECK (
                (tipo_viaje = 'suministro' AND cantera_material_id IS NOT NULL AND botadero_id IS NULL)
                OR
                (tipo_viaje = 'retiro' AND botadero_id IS NOT NULL AND cantera_material_id IS NULL)
            )
        ");

        /**
         * Un viaje confirmado SIEMPRE tiene vale, m3 confirmados y fecha.
         * Esto hace imposible que quede un confirmado sin respaldo, que es
         * justo lo que se paga.
         */
        DB::statement("
            ALTER TABLE viajes
            ADD CONSTRAINT viajes_confirmacion_coherente
            CHECK (
                estado <> 'confirmado'
                OR (vale_id IS NOT NULL AND m3_confirmado IS NOT NULL AND fecha_confirmacion IS NOT NULL)
            )
        ");

        // Solo un viaje confirmado puede entrar en una liquidación.
        DB::statement("
            ALTER TABLE viajes
            ADD CONSTRAINT viajes_liquidacion_solo_confirmados
            CHECK (
                (liquidacion_cobro_id IS NULL AND liquidacion_pago_id IS NULL)
                OR estado = 'confirmado'
            )
        ");

        DB::statement("
            ALTER TABLE viajes
            ADD CONSTRAINT viajes_m3_positivos
            CHECK (m3_registrado > 0 AND (m3_confirmado IS NULL OR m3_confirmado > 0))
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('viajes');
    }
};

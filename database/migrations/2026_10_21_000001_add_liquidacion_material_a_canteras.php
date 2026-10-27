<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La cuenta de material: lo que se le pagó a cada cantera.
 *
 * El material lo paga la empresa en la cantera al cargar, con plata que lleva
 * el conductor, y se lo cobra al consorcio en la cuenta de cobro. A la cantera
 * no se le queda debiendo nada: esta cuenta es el estado de lo pagado, para
 * cuadrarlo contra lo que diga la cantera. Por eso su saldo es siempre cero.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('liquidaciones', function (Blueprint $table) {
            $table->foreignId('cantera_id')->nullable()->after('tercero_id')->constrained('canteras')->restrictOnDelete();
        });

        Schema::table('viajes', function (Blueprint $table) {
            $table->foreignId('liquidacion_material_id')->nullable()->after('liquidacion_pago_id')->constrained('liquidaciones')->restrictOnDelete();
            $table->index('liquidacion_material_id');
        });

        DB::statement('ALTER TABLE liquidaciones DROP CONSTRAINT liquidaciones_tipo_valido');
        DB::statement("
            ALTER TABLE liquidaciones
            ADD CONSTRAINT liquidaciones_tipo_valido
            CHECK (tipo IN ('cobro', 'pago', 'material'))
        ");

        DB::statement('ALTER TABLE liquidaciones DROP CONSTRAINT liquidaciones_destinatario_coherente');
        DB::statement("
            ALTER TABLE liquidaciones
            ADD CONSTRAINT liquidaciones_destinatario_coherente
            CHECK (
                (tipo = 'cobro' AND consorcio_id IS NOT NULL AND tercero_id IS NULL AND cantera_id IS NULL)
                OR
                (tipo = 'pago' AND tercero_id IS NOT NULL AND consorcio_id IS NULL AND cantera_id IS NULL)
                OR
                (tipo = 'material' AND cantera_id IS NOT NULL AND consorcio_id IS NULL AND tercero_id IS NULL)
            )
        ");

        DB::statement('ALTER TABLE viajes DROP CONSTRAINT viajes_liquidacion_solo_confirmados');
        DB::statement("
            ALTER TABLE viajes
            ADD CONSTRAINT viajes_liquidacion_solo_confirmados
            CHECK (
                (liquidacion_cobro_id IS NULL AND liquidacion_pago_id IS NULL AND liquidacion_material_id IS NULL)
                OR estado = 'confirmado'
            )
        ");

        // Un suministro sin precio de material se cobraría sin el material.
        DB::statement("
            ALTER TABLE viajes
            ADD CONSTRAINT viajes_suministro_con_material
            CHECK (tipo_viaje <> 'suministro' OR valor_material_m3 IS NOT NULL)
        ");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE viajes DROP CONSTRAINT viajes_suministro_con_material');

        DB::statement('ALTER TABLE viajes DROP CONSTRAINT viajes_liquidacion_solo_confirmados');
        DB::statement("
            ALTER TABLE viajes
            ADD CONSTRAINT viajes_liquidacion_solo_confirmados
            CHECK (
                (liquidacion_cobro_id IS NULL AND liquidacion_pago_id IS NULL)
                OR estado = 'confirmado'
            )
        ");

        DB::statement('ALTER TABLE liquidaciones DROP CONSTRAINT liquidaciones_destinatario_coherente');
        DB::statement("
            ALTER TABLE liquidaciones
            ADD CONSTRAINT liquidaciones_destinatario_coherente
            CHECK (
                (tipo = 'cobro' AND consorcio_id IS NOT NULL AND tercero_id IS NULL)
                OR
                (tipo = 'pago' AND tercero_id IS NOT NULL AND consorcio_id IS NULL)
            )
        ");

        DB::statement('ALTER TABLE liquidaciones DROP CONSTRAINT liquidaciones_tipo_valido');
        DB::statement("
            ALTER TABLE liquidaciones
            ADD CONSTRAINT liquidaciones_tipo_valido
            CHECK (tipo IN ('cobro', 'pago'))
        ");

        Schema::table('viajes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('liquidacion_material_id');
        });

        Schema::table('liquidaciones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cantera_id');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tarifa general de pago al dueño: en muchas rutas a todos los terceros se les
 * paga lo mismo por viaje. Una tarifa de pago_propietario sin tercero vale
 * para cualquier dueño que no tenga una pactada aparte.
 *
 * El conductor sigue siendo obligatorio: su porcentaje es personal.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE tarifas DROP CONSTRAINT tarifas_tercero_requerido');
        DB::statement("
            ALTER TABLE tarifas
            ADD CONSTRAINT tarifas_tercero_requerido
            CHECK (tipo <> 'pago_conductor' OR tercero_id IS NOT NULL)
        ");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE tarifas DROP CONSTRAINT tarifas_tercero_requerido');
        DB::statement("
            ALTER TABLE tarifas
            ADD CONSTRAINT tarifas_tercero_requerido
            CHECK (tipo = 'cobro' OR tercero_id IS NOT NULL)
        ");
    }
};

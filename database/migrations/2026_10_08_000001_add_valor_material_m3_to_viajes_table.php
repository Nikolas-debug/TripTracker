<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El precio del material por m³, copiado de cantera_material al registrar
     * el viaje. Se le cobra al consorcio encima del flete y se le paga igual a
     * la cantera. Congelado aquí por la misma razón que las tarifas: si la
     * arena sube, las cuentas ya cobradas no cambian.
     *
     * Nulo en los retiros y en los viajes registrados antes de esta columna.
     */
    public function up(): void
    {
        Schema::table('viajes', function (Blueprint $table) {
            $table->bigInteger('valor_material_m3')->nullable()->after('unidad_cobro');
        });
    }

    public function down(): void
    {
        Schema::table('viajes', function (Blueprint $table) {
            $table->dropColumn('valor_material_m3');
        });
    }
};

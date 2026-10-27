<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * TARIFAS: precios pactados, con vigencia.
 *
 * La clave es la RUTA, no el material:
 *   suministro -> cantera (origen) + obra (destino)
 *   retiro     -> obra (origen)    + botadero (destino)
 *
 * `material_id` es opcional y solo se usa para excepciones puntuales.
 *
 * Un cambio de precio NO se hace con UPDATE: se cierra la vigente
 * (fecha_fin) y se crea una nueva fila.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tarifas', function (Blueprint $table) {
            $table->id();

            // cobro | pago_propietario | pago_conductor
            $table->string('tipo', 20);

            // suministro | retiro
            $table->string('tipo_viaje', 12);

            $table->foreignId('obra_id')->constrained('obras')->restrictOnDelete();
            $table->foreignId('cantera_id')->nullable()->constrained('canteras')->restrictOnDelete();
            $table->foreignId('botadero_id')->nullable()->constrained('botaderos')->restrictOnDelete();
            $table->foreignId('material_id')->nullable()->constrained('materiales')->restrictOnDelete();

            // El dueño (pago_propietario) o el conductor (pago_conductor).
            $table->foreignId('tercero_id')->nullable()->constrained('terceros')->restrictOnDelete();

            // Pesos enteros. Nunca float: las cuentas tienen que cuadrar contra el Excel.
            $table->bigInteger('valor')->nullable();

            // Solo para pago_conductor: base fija de la ruta y su porcentaje.
            $table->bigInteger('valor_base')->nullable();
            $table->decimal('porcentaje', 5, 2)->nullable();

            // m3 | viaje | porcentaje
            $table->string('unidad', 12);

            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();   // null = vigente
            $table->string('motivo_cambio', 150)->nullable();

            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // El resolvedor busca por aquí en cada registro de viaje.
            $table->index(['tipo', 'tipo_viaje', 'obra_id', 'cantera_id', 'fecha_fin']);
            $table->index(['tipo', 'tipo_viaje', 'obra_id', 'botadero_id', 'fecha_fin']);
        });

        DB::statement("
            ALTER TABLE tarifas
            ADD CONSTRAINT tarifas_tipo_valido
            CHECK (tipo IN ('cobro', 'pago_propietario', 'pago_conductor'))
        ");

        DB::statement("
            ALTER TABLE tarifas
            ADD CONSTRAINT tarifas_unidad_valida
            CHECK (unidad IN ('m3', 'viaje', 'porcentaje'))
        ");

        // La ruta tiene que ser coherente con el tipo de viaje.
        DB::statement("
            ALTER TABLE tarifas
            ADD CONSTRAINT tarifas_ruta_coherente
            CHECK (
                (tipo_viaje = 'suministro' AND cantera_id IS NOT NULL AND botadero_id IS NULL)
                OR
                (tipo_viaje = 'retiro' AND botadero_id IS NOT NULL AND cantera_id IS NULL AND material_id IS NULL)
            )
        ");

        // Pago al conductor = porcentaje sobre base. El resto lleva valor.
        DB::statement("
            ALTER TABLE tarifas
            ADD CONSTRAINT tarifas_valores_coherentes
            CHECK (
                (tipo = 'pago_conductor' AND valor_base IS NOT NULL AND porcentaje IS NOT NULL)
                OR
                (tipo <> 'pago_conductor' AND valor IS NOT NULL)
            )
        ");

        // Las tarifas de pago siempre son con alguien.
        DB::statement("
            ALTER TABLE tarifas
            ADD CONSTRAINT tarifas_tercero_requerido
            CHECK (tipo = 'cobro' OR tercero_id IS NOT NULL)
        ");

        DB::statement("
            ALTER TABLE tarifas
            ADD CONSTRAINT tarifas_vigencia_coherente
            CHECK (fecha_fin IS NULL OR fecha_fin >= fecha_inicio)
        ");

        /**
         * Solo puede haber UNA tarifa vigente por combinación.
         * Sin esto, dos tarifas abiertas para la misma ruta hacen que el
         * resolvedor devuelva una u otra según el orden del query, y nadie
         * entiende por qué dos viajes iguales quedaron con precios distintos.
         */
        DB::statement("
            CREATE UNIQUE INDEX tarifas_vigente_unica ON tarifas (
                tipo,
                tipo_viaje,
                obra_id,
                COALESCE(cantera_id, 0),
                COALESCE(botadero_id, 0),
                COALESCE(material_id, 0),
                COALESCE(tercero_id, 0)
            ) WHERE fecha_fin IS NULL
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('tarifas');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vía de acceso de la prestación (fase 3, F3-5 y B1).
 *
 * En esta fase solo `plaza` y `derivacion`; la fase 3 bis añade el resto (directa,
 * tramitación interna, expediente externo) y hasta entonces queda null. Sin
 * restricción CHECK para no tener que rehacerla en la 3 bis: el enum `ViaAcceso`
 * del modelo valida los valores.
 *
 * `tipo_plan_derivacion_id`: el tipo de plan especializado que genera una prestación
 * de derivación; obligatorio si `via_acceso = derivacion` (validado en el modelo).
 */
return new class extends Migration
{
    /**
     * Añade las dos columnas.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('prestaciones', function (Blueprint $table) {
            $table->string('via_acceso', 30)->nullable()->after('nivel_garantia');
            $table->foreignId('tipo_plan_derivacion_id')->nullable()->after('via_acceso')->constrained('tipos_plan');
        });
    }

    /**
     * Quita las dos columnas.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('prestaciones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tipo_plan_derivacion_id');
            $table->dropColumn('via_acceso');
        });
    }
};

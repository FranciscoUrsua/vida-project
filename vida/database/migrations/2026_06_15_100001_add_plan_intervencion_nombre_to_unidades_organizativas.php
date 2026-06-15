<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Añade campos de identidad y plan de intervención a unidades_organizativas.
 *
 * - nombre_corto: acrónimo o nombre breve de la UO, para mostrar en badges.
 * - plan_nombre_completo / plan_nombre_corto: permiten personalizar el nombre del
 *   Plan de Intervención por UO (p. ej. «PISO» en ASP, «PIA» en Mayores).
 *   Si quedan nulos, el componente Livewire usa los fallbacks «Plan de intervención» / «Plan».
 *
 * @see App\Models\UnidadOrganizativa
 * @see docs/instrucciones-cli/instrucciones-cli-mejoras-intervencion.md §Cambio 2 y §Cambio 4
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unidades_organizativas', function (Blueprint $table) {
            $table->string('nombre_corto', 40)->nullable()
                ->after('nombre')
                ->comment('Nombre corto o acrónimo de la UO (máx. 40 caracteres)');

            $table->string('plan_nombre_completo', 80)->nullable()
                ->after('activa')
                ->comment('Nombre completo del plan de intervención en esta UO. Fallback: «Plan de intervención»');

            $table->string('plan_nombre_corto', 10)->nullable()
                ->after('plan_nombre_completo')
                ->comment('Acrónimo del plan de intervención. Fallback: «Plan»');
        });
    }

    public function down(): void
    {
        Schema::table('unidades_organizativas', function (Blueprint $table) {
            $table->dropColumn(['nombre_corto', 'plan_nombre_completo', 'plan_nombre_corto']);
        });
    }
};

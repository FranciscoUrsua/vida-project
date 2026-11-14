<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Plan principal (fase 3, F3-1): uno de los tipos de plan de ámbito ASP es el
 * principal (el PISO). Como máximo un plan activo de ese tipo por historia; lo
 * gestiona el TSR y es el origen de las derivaciones.
 *
 * Nada decide «es el PISO» por `planes_intervencion.tipo`: se decide por este campo.
 */
return new class extends Migration
{
    /**
     * Añade `es_principal`, marca el tipo principal existente y garantiza que no haya dos.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('tipos_plan', function (Blueprint $table) {
            $table->boolean('es_principal')->default(false)->after('ambito');
        });

        // Como máximo un tipo principal en todo el catálogo.
        DB::statement('CREATE UNIQUE INDEX tipos_plan_un_principal ON tipos_plan ((es_principal)) WHERE es_principal');

        // Se marca `piso` (slug del PISO en los datos existentes); si no existe, el único
        // tipo de ámbito ASP, si solo hay uno. Una BD vacía (tests) no recibe marca: la pone el seeder.
        $principal = DB::table('tipos_plan')->where('slug', 'piso')->where('ambito', 'asp')->value('id');

        if ($principal === null) {
            $asp = DB::table('tipos_plan')->where('ambito', 'asp')->pluck('id');
            $principal = $asp->count() === 1 ? $asp->first() : null;
        }

        if ($principal !== null) {
            DB::table('tipos_plan')->where('id', $principal)->update(['es_principal' => true]);
        }
    }

    /**
     * Quita `es_principal`.
     *
     * @return void
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS tipos_plan_un_principal');

        Schema::table('tipos_plan', function (Blueprint $table) {
            $table->dropColumn('es_principal');
        });
    }
};

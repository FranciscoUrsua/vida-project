<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Corrige la asociación del Apunte: pasa de plan_id (requerido) a historia_id (requerido),
 * dejando plan_id como vínculo opcional. El apunte pertenece a la Historia Social,
 * no al Plan — un plan puede no existir todavía cuando se registran los primeros apuntes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plan_apuntes', function (Blueprint $table) {
            // Añadir historia_id nullable para poder poblar antes de poner NOT NULL
            $table->foreignId('historia_id')
                ->nullable()
                ->after('id')
                ->constrained('historias_sociales')
                ->onDelete('restrict');

            $table->index('historia_id');
        });

        // Poblar historia_id a partir del plan existente para todos los apuntes actuales
        DB::statement('
            UPDATE plan_apuntes pa
            SET historia_id = pi.historia_id
            FROM planes_intervencion pi
            WHERE pi.id = pa.plan_id
        ');

        // Ahora sí: aplicar NOT NULL
        Schema::table('plan_apuntes', function (Blueprint $table) {
            $table->foreignId('historia_id')->nullable(false)->change();
        });

        // plan_id pasa a ser nullable (vínculo opcional al plan)
        Schema::table('plan_apuntes', function (Blueprint $table) {
            $table->foreignId('plan_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('plan_apuntes', function (Blueprint $table) {
            $table->dropForeign(['historia_id']);
            $table->dropIndex(['historia_id']);
            $table->dropColumn('historia_id');
            $table->foreignId('plan_id')->nullable(false)->change();
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Añade compromiso_id a prescripciones para vinculación opcional con compromisos del plan.
 *
 * El vínculo es de referencia, no sincroniza estado: la prescripción conoce al compromiso,
 * el plan no se modifica cuando avanza la prescripción.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prescripciones', function (Blueprint $table) {
            $table->foreignId('compromiso_id')
                ->nullable()
                ->constrained('plan_actuaciones_ayuntamiento')
                ->nullOnDelete()
                ->after('plan_intervencion_id');
        });
    }

    public function down(): void
    {
        Schema::table('prescripciones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('compromiso_id');
        });
    }
};

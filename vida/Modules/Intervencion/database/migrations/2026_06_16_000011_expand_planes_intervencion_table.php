<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planes_intervencion', function (Blueprint $table) {
            $table->foreignId('tipo_plan_id')
                  ->nullable()
                  ->after('historia_id')
                  ->constrained('tipos_plan')
                  ->nullOnDelete();

            // unidad_convivencia_id ya existe (migración 2026_06_16_000002)
            // Solo se añaden los campos nuevos de contenido

            $table->text('diagnostico_social')->nullable()->after('unidad_convivencia_id');
            $table->enum('periodicidad_seguimiento', [
                'mensual', 'bimestral', 'trimestral', 'semestral', 'anual',
            ])->default('trimestral')->after('diagnostico_social');
        });
    }

    public function down(): void
    {
        Schema::table('planes_intervencion', function (Blueprint $table) {
            $table->dropForeign(['tipo_plan_id']);
            $table->dropColumn(['tipo_plan_id', 'diagnostico_social', 'periodicidad_seguimiento']);
        });
    }
};

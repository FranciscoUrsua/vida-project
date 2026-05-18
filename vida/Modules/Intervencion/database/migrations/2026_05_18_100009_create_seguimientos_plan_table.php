<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seguimientos_plan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')
                ->constrained('planes_intervencion')
                ->onDelete('restrict');
            $table->foreignId('entrevista_id')
                ->constrained('entrevistas')
                ->onDelete('restrict');
            $table->foreignId('profesional_id')
                ->constrained('users')
                ->onDelete('restrict');
            $table->date('fecha');
            $table->text('avances')->nullable();
            $table->text('objetivos_cumplidos')->nullable();
            $table->text('incidencias')->nullable();
            $table->jsonb('nuevas_prestaciones')->nullable()->comment('Array de IDs del catálogo de prestaciones');
            $table->boolean('requiere_revision_plan')->default(false);
            $table->date('fecha_siguiente_seguimiento')->nullable();
            $table->timestamps();

            $table->index('plan_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seguimientos_plan');
    }
};

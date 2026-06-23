<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_objetivo_indicadores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_objetivo_id')
                  ->constrained('plan_objetivos')
                  ->cascadeOnDelete();
            $table->foreignId('indicador_catalogo_id')
                  ->nullable()
                  ->constrained('indicadores_catalogo')
                  ->nullOnDelete();
            $table->text('descripcion');
            $table->enum('tipo_valoracion', [
                'conseguido_proceso_no',
                'favorable_mantiene_desfavorable',
                'si_no',
            ])->default('conseguido_proceso_no');
            $table->string('valoracion_actual')->nullable();
            $table->date('fecha_valoracion')->nullable();
            $table->foreignId('seguimiento_id')
                  ->nullable()
                  ->constrained('seguimientos_plan')
                  ->nullOnDelete();
            $table->timestamps();

            $table->index('plan_objetivo_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_objetivo_indicadores');
    }
};

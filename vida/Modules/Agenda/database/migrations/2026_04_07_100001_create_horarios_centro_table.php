<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('horarios_centro', function (Blueprint $table) {
            $table->id();
            $table->foreignId('centro_id')->constrained('centros')->cascadeOnDelete();
            $table->string('nombre');
            $table->json('dias_laborables');
            $table->time('hora_apertura');
            $table->time('hora_cierre');
            $table->time('hora_inicio_atencion');
            $table->time('hora_fin_atencion');
            $table->integer('buffer_inicio_minutos')->default(0);
            $table->integer('buffer_fin_minutos')->default(0);
            $table->date('vigente_desde');
            $table->date('vigente_hasta')->nullable();
            $table->string('modo_agenda')->default('estandar');
            $table->boolean('activo')->default(true);
            $table->text('notas')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('centro_id');
            $table->index(['centro_id', 'vigente_desde', 'vigente_hasta']);
            $table->index(['centro_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('horarios_centro');
    }
};

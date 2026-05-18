<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entrevistas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('historia_id')
                ->constrained('historias_sociales')
                ->onDelete('restrict');
            $table->foreignId('profesional_id')
                ->constrained('users')
                ->onDelete('restrict');
            $table->unsignedBigInteger('cita_id')->nullable()->comment('FK a módulo Agenda; nullable para entrevistas no programadas');
            $table->unsignedBigInteger('plan_intervencion_id')->nullable()->comment('FK a planes_intervencion; nullable hasta vinculación');
            $table->dateTime('fecha_hora');
            $table->string('modalidad', 30)->comment('presencial, telefonica, videollamada, domicilio');
            $table->string('tipo', 30)->comment('inicial, seguimiento, urgencia, informativa');
            $table->text('notas_generales')->nullable();
            $table->string('estado', 30)->default('realizada')->comment('programada, realizada, cancelada, no_presentado');
            $table->timestamps();

            $table->index('historia_id');
            $table->index('profesional_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entrevistas');
    }
};

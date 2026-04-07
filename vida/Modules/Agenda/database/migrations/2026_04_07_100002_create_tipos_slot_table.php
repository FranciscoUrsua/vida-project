<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_slot', function (Blueprint $table) {
            $table->id();
            $table->foreignId('horario_centro_id')->constrained('horarios_centro')->cascadeOnDelete();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->integer('duracion_minutos');
            $table->boolean('requiere_espacio')->default(false);
            $table->unsignedTinyInteger('porcentaje_urgencias')->default(0);
            $table->string('origen_permitido')->default('ambos');
            $table->boolean('genera_apunte_automatico')->default(false);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index('horario_centro_id');
            $table->index(['horario_centro_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_slot');
    }
};

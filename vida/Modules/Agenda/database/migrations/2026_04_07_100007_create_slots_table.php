<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('linea_cuadrante_id')->constrained('lineas_cuadrante')->cascadeOnDelete();
            $table->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('centro_id')->constrained('centros')->cascadeOnDelete();
            $table->foreignId('tipo_slot_id')->constrained('tipos_slot')->cascadeOnDelete();
            $table->date('fecha');
            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->string('estado')->default('disponible');
            $table->foreignId('espacio_id')->nullable()->constrained('espacios')->nullOnDelete();
            $table->timestamps();

            $table->index('linea_cuadrante_id');
            $table->index('usuario_id');
            $table->index(['usuario_id', 'fecha']);
            $table->index(['centro_id', 'fecha', 'estado']);
            $table->index(['tipo_slot_id', 'fecha', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slots');
    }
};

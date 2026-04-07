<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lineas_cuadrante', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuadrante_mes_id')->constrained('cuadrantes_mes')->cascadeOnDelete();
            $table->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('centro_id')->constrained('centros')->cascadeOnDelete();
            $table->date('fecha');
            $table->json('franjas');
            $table->boolean('anulada')->default(false);
            $table->foreignId('excepcion_id')->nullable()->constrained('excepciones_profesional')->nullOnDelete();
            $table->timestamps();

            $table->index('cuadrante_mes_id');
            $table->index('usuario_id');
            $table->index(['usuario_id', 'fecha']);
            $table->index(['centro_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lineas_cuadrante');
    }
};

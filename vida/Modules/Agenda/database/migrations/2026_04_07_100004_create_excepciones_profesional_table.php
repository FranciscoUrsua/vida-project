<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('excepciones_profesional', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('centro_id')->constrained('centros')->cascadeOnDelete();
            $table->string('tipo');
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->boolean('afecta_disponibilidad')->default(true);
            $table->json('franja_afectada')->nullable();
            $table->string('origen')->default('manual');
            $table->foreignId('creado_por_id')->constrained('users');
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->index('usuario_id');
            $table->index(['usuario_id', 'fecha_inicio', 'fecha_fin']);
            $table->index('centro_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('excepciones_profesional');
    }
};

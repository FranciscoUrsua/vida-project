<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('historias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_user_id')->constrained('social_users')->onDelete('cascade'); // Asume tabla socia_lusers existe
            $table->enum('estado', ['abierto', 'seguimiento', 'alta'])->default('abierto');
            $table->date('fecha_apertura');
            $table->foreignId('profesional_id')->constrained('profesionales')->onDelete('cascade');
            $table->foreignId('centro_id')->constrained('centros')->onDelete('set null'); // Asume tabla centros
            $table->json('metadatos')->nullable(); // e.g., {"distritos": ["Centro", "Chamberí"]}
            $table->timestamps();

            // Índices para rendimiento (queries por estado/FK comunes)
            $table->index(['estado']);
            $table->index(['social_user_id']);
            $table->index(['profesional_id']);
            $table->index(['fecha_apertura']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('historias');
    }
};

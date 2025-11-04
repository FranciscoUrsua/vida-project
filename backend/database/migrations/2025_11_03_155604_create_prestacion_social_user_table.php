<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prestacion_social_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prestacion_id')->constrained('prestaciones')->onDelete('cascade');
            $table->foreignId('social_user_id')->constrained('social_users')->onDelete('cascade');
            $table->timestamps();  // Para fecha de asignación
            $table->date('fecha_fin')->nullable();  // Opcional: fecha de baja de prestación
            $table->unique(['prestacion_id', 'social_user_id']);  // No duplicados
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prestacion_social_user');
    }
};

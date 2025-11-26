<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prestaciones', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique(); // Ej: '010101'
            $table->string('nombre'); // Ej: 'Servicio de información, valoración, orientación y asesoramiento'
            $table->text('descripcion');
            $table->enum('categoria', ['basica', 'especializada', 'complementaria']);
            $table->json('requisitos')->nullable();
            $table->integer('duracion_meses')->nullable();
            $table->decimal('costo', 8, 2)->nullable();
            $table->timestamps();
            $table->index('codigo'); // Para lookups rápidos en planes
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prestaciones');
    }
};

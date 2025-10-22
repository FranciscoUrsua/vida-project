<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('directors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('professional_id')->unique();  // 1:1 con professionals
            $table->unsignedBigInteger('centro_id');
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->text('info_relevante')->nullable();  // Ejercicio profesional
            $table->timestamps();

            $table->foreign('professional_id')->references('id')->on('professionals');
            $table->foreign('centro_id')->references('id')->on('centros');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('directors');
    }
};

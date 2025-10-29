<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('centro_profesional', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('centro_id'); // Sin constrained aún
            $table->unsignedBigInteger('profesional_id'); // Sin constrained aún
            $table->timestamps();
            $table->unique(['centro_id', 'profesional_id']);
        });

        // Agregar FKs aquí (tablas ya existen)
        Schema::table('centro_profesional', function (Blueprint $table) {
            $table->foreign('centro_id')->references('id')->on('centros')->onDelete('cascade');
            $table->foreign('profesional_id')->references('id')->on('profesionales')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('centro_profesional');
    }
};

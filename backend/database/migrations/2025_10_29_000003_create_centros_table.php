<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('centros', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 50);
            $table->string('nombre', 255);
            $table->text('direccion_postal');
            $table->string('telefono', 20);
            $table->string('email_contacto', 255)->nullable();
            $table->unsignedBigInteger('director_id')->nullable(); // Sin constrained aún
            $table->json('campos_especificos')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('centros');
    }
};

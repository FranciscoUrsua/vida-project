<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_relacion', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('etiqueta');
            $table->string('etiqueta_reciproca');
            // FK lógica (string) — slug inmutable garantiza la integridad sin constraint real
            $table->string('slug_reciproco')->nullable();
            $table->boolean('simetrica')->default(false);
            $table->string('implicacion_funcional')->nullable();
            $table->boolean('eliminable')->default(true);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index('implicacion_funcional');
            $table->index('activo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_relacion');
    }
};

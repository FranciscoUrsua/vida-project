<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estilos_informe', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unidad_organizativa_id')
                ->unique()
                ->constrained('unidades_organizativas')
                ->cascadeOnDelete();
            $table->string('logo_cabecera')->nullable();
            $table->string('nombre_unidad_cabecera')->nullable();
            $table->string('direccion_cabecera')->nullable();
            $table->string('telefono_cabecera')->nullable();
            $table->text('html_pie')->nullable();
            $table->foreignId('creado_por')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estilos_informe');
    }
};

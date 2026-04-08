<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plantillas_informe', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unidad_organizativa_id')
                ->constrained('unidades_organizativas')
                ->cascadeOnDelete();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->string('tipo_informe');
            $table->jsonb('secciones');
            $table->boolean('activa')->default(true);
            $table->foreignId('creada_por')->constrained('users');
            $table->timestamps();

            $table->index('unidad_organizativa_id');
            $table->index(['unidad_organizativa_id', 'activa']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plantillas_informe');
    }
};

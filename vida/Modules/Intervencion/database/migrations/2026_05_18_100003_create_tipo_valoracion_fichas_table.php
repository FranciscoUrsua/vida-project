<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipo_valoracion_fichas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tipo_valoracion_id')
                ->constrained('tipo_valoraciones')
                ->onDelete('restrict');
            $table->foreignId('tipo_ficha_id')
                ->constrained('tipo_fichas')
                ->onDelete('restrict');
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('obligatoria')->default(false);
            $table->timestamps();

            $table->unique(['tipo_valoracion_id', 'tipo_ficha_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipo_valoracion_fichas');
    }
};

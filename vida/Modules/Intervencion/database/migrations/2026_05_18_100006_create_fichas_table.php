<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fichas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('valoracion_id')
                ->constrained('valoraciones')
                ->onDelete('restrict');
            $table->foreignId('tipo_ficha_id')
                ->constrained('tipo_fichas')
                ->onDelete('restrict');
            $table->jsonb('datos')->nullable()->comment('Valores reales de los campos de la ficha');
            $table->text('notas')->nullable();
            $table->boolean('completada')->default(false);
            $table->timestamps();

            $table->index('valoracion_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fichas');
    }
};

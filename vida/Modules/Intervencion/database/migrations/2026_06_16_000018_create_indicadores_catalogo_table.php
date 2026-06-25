<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('indicadores_catalogo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('objetivo_catalogo_id')
                ->unique()
                ->constrained('objetivos_catalogo')
                ->cascadeOnDelete();
            $table->text('descripcion');
            $table->enum('tipo_valoracion', [
                'conseguido_proceso_no',
                'favorable_mantiene_desfavorable',
                'si_no',
            ])->default('conseguido_proceso_no');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indicadores_catalogo');
    }
};

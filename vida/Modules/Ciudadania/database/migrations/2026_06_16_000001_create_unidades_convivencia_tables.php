<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unidades_convivencia', function (Blueprint $table) {
            $table->id();
            $table->text('domicilio')->nullable();          // encriptado en aplicación
            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 10, 7)->nullable();
            $table->date('fecha_constitucion');
            $table->date('fecha_disolucion')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('unidad_convivencia_miembros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unidad_convivencia_id')
                ->constrained('unidades_convivencia')
                ->cascadeOnDelete();
            $table->foreignId('ciudadano_id')
                ->constrained('ciudadanos')
                ->cascadeOnDelete();
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->enum('fuente', ['manual', 'padron', 'importacion'])
                ->default('manual');
            $table->boolean('verificado')->default(false);
            $table->foreignId('verificado_por')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('verificado_en')->nullable();
            $table->timestamps();

            // Un ciudadano no puede estar dos veces activo en la misma UC
            // (fecha_fin null = activo). No se puede expresar como unique constraint
            // directamente por los nulls; se valida en el modelo.
            $table->index(['unidad_convivencia_id', 'ciudadano_id']);
            $table->index(['ciudadano_id', 'fecha_fin']); // consultas "membresías activas"
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unidad_convivencia_miembros');
        Schema::dropIfExists('unidades_convivencia');
    }
};

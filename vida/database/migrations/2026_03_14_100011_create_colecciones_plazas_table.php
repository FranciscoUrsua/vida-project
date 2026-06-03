<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Centro\Models\ColeccionPlazas;

/**
 * Colecciones de plazas de un centro.
 *
 * Una colección agrupa plazas del mismo tipo y modo de acceso.
 * Un centro puede tener varias colecciones: ej. "Plazas residenciales
 * de urgencia" (acceso por derivación) y "Plazas de respiro" (libre).
 *
 * tipo_plaza valores: pernocta | dia
 * modo_acceso valores: libre | prescripcion_directa | prescripcion_lista_espera
 *
 * @see ColeccionPlazas
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('colecciones_plazas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('centro_id')
                ->constrained('centros')
                ->cascadeOnDelete();

            $table->string('nombre', 200);
            $table->string('tipo_plaza', 30)->default('pernocta')
                ->comment('pernocta | dia');
            $table->string('modo_acceso', 30)->default('prescripcion_directa')
                ->comment('libre | prescripcion_directa | prescripcion_lista_espera');
            $table->unsignedInteger('capacidad')
                ->comment('Número total de plazas en esta colección');
            $table->boolean('activa')->default(true);
            $table->date('fecha_alta');
            $table->date('fecha_baja')->nullable();
            $table->text('notas')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['centro_id', 'activa']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('colecciones_plazas');
    }
};

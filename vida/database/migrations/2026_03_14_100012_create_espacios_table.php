<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Espacios físicos dentro de una colección de plazas.
 *
 * Un espacio es una unidad física (habitación, sala, módulo) que
 * contiene una o más plazas individuales.
 *
 * genero valores: mixto | mujeres | hombres (nullable = sin restricción de género)
 *
 * @see Modules\Centro\Models\Espacio
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('espacios', function (Blueprint $table) {
            $table->id();

            $table->foreignId('coleccion_plazas_id')
                ->constrained('colecciones_plazas')
                ->cascadeOnDelete();

            $table->foreignId('tipo_espacio_id')
                ->constrained('tipos_espacio')
                ->restrictOnDelete();

            $table->string('nombre', 100)->comment('Ej: "Dormitorio 3", "Habitación 12", "Módulo B"');
            $table->unsignedInteger('capacidad')->comment('Número de plazas que contiene el espacio');
            $table->string('planta', 30)->nullable()->comment('Planta del edificio donde se ubica');
            $table->boolean('accesible')->default(false)
                ->comment('Adaptado para personas con movilidad reducida');
            $table->string('genero', 20)->nullable()
                ->comment('mixto | mujeres | hombres — null = sin restricción');
            $table->boolean('activo')->default(true);
            $table->text('notas')->nullable()->comment('Características especiales del espacio');

            $table->timestamps();
            $table->softDeletes();

            $table->index('coleccion_plazas_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('espacios');
    }
};

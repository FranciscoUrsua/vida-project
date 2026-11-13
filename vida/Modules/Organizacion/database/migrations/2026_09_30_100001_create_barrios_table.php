<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: catálogo de barrios municipales.
 *
 * Segundo nivel de las unidades territoriales (distrito → barrio → sección
 * censal). El número de barrio solo es único dentro de su distrito; `codigo`
 * es el código municipal completo (distrito + barrio, p. ej. `214`).
 *
 * @see docs/modulo-asignacion.md §2
 */
return new class extends Migration
{
    /**
     * Crea la tabla `barrios`.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('barrios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distrito_id')->constrained('distritos')->restrictOnDelete();
            $table->string('codigo', 3)->unique()->comment('Código municipal completo: distrito + barrio, ej: 214');
            $table->string('codigo_en_distrito', 2)->comment('Número de barrio dentro del distrito, tal como lo devuelve la BDC, ej: 4');
            $table->string('nombre', 100);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['distrito_id', 'codigo_en_distrito']);
        });
    }

    /**
     * Elimina la tabla `barrios`.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('barrios');
    }
};

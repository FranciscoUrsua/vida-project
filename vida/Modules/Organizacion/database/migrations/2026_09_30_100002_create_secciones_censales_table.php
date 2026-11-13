<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: catálogo de secciones censales.
 *
 * Unidad territorial más fina con la que se resuelve el centro de una
 * dirección. `codigo_ine` es el código de 10 dígitos: provincia (28),
 * municipio (079), distrito y sección. El número de sección solo es único
 * dentro de su distrito. Las fechas de vigencia sirven para las revisiones
 * del INE (docs/modulo-asignacion.md §11).
 *
 * @see docs/modulo-asignacion.md §2
 */
return new class extends Migration
{
    /**
     * Crea la tabla `secciones_censales`.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('secciones_censales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distrito_id')->constrained('distritos')->restrictOnDelete();
            $table->foreignId('barrio_id')->nullable()->constrained('barrios')->restrictOnDelete();
            $table->char('codigo_ine', 10)->unique()->comment('28 + 079 + distrito + sección, ej: 2807921028');
            $table->string('codigo_en_distrito', 3)->comment('Número de sección dentro del distrito, tal como lo devuelve la BDC, ej: 28');
            $table->boolean('activa')->default(true);
            $table->date('vigente_desde')->nullable();
            $table->date('vigente_hasta')->nullable();
            $table->timestamps();

            $table->index('barrio_id');
        });
    }

    /**
     * Elimina la tabla `secciones_censales`.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('secciones_censales');
    }
};

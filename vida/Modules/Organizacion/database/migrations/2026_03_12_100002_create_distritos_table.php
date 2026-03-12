<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: tabla de distritos municipales.
 *
 * Los 21 distritos de Madrid son los valores iniciales,
 * pero el modelo es adaptable a otros municipios (principio 4.15).
 * El Centro y el Ciudadano se vinculan a Distrito por domicilio.
 *
 * @see docs/glosario.md § Distrito
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('distritos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100)->comment('Nombre del distrito, ej: Arganzuela');
            $table->string('codigo', 10)->unique()->comment('Código del distrito, ej: 02');
            $table->decimal('latitud', 10, 7)->nullable()->comment('Latitud del centroide del distrito');
            $table->decimal('longitud', 10, 7)->nullable()->comment('Longitud del centroide del distrito');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distritos');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: catálogo de titulaciones académicas.
 *
 * Catálogo configurable desde el backoffice.
 * Ejemplos: Grado en Trabajo Social, Grado en Psicología.
 *
 * @see \Modules\Usuarios\Models\Titulacion
 * @see docs/modulo-usuarios-permisos.md sección 5.2
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('titulaciones', function (Blueprint $table) {
            $table->id();

            $table->string('nombre', 200)
                ->comment('Nombre de la titulación académica');

            $table->boolean('activo')
                ->default(true)
                ->comment('Permite desactivar titulaciones obsoletas sin eliminarlas');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('titulaciones');
    }
};

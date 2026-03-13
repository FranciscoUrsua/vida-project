<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: catálogo de tipos de relación profesional.
 *
 * Catálogo configurable desde el backoffice.
 * Ejemplos: funcionario, interino, contratado laboral, externo, voluntario.
 *
 * El campo `es_externo` determina si el campo `organizacion` en Profesional
 * es relevante (para personal de empresas externas o voluntarios).
 *
 * @see \Modules\Usuarios\Models\TipoRelacionProfesional
 * @see docs/modulo-usuarios-permisos.md sección 5.2
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_relacion_profesional', function (Blueprint $table) {
            $table->id();

            $table->string('nombre', 100)
                ->comment('Nombre del tipo de relación: funcionario, interino, externo...');

            $table->boolean('es_externo')
                ->default(false)
                ->comment('Si true, el campo organizacion del profesional es relevante');

            $table->boolean('activo')
                ->default(true)
                ->comment('Permite desactivar tipos obsoletos sin eliminarlos');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_relacion_profesional');
    }
};

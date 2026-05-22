<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de perfiles de anonimización.
 *
 * Cada perfil define, campo a campo, qué técnica se aplica y con qué parámetros.
 * Los perfiles son configurables desde el backoffice y versionados: cada modificación
 * de 'campos' o 'k_valor' genera un snapshot en perfil_anonimizacion_versiones.
 *
 * Ver docs/anonimizacion.md § 5 y docs/decisiones-tecnicas.md § 7.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('perfiles_anonimizacion', function (Blueprint $table) {
            $table->id();

            // Identificador único legible — usado para referenciar el perfil desde el código
            $table->string('nombre', 100)->unique()
                ->comment('Identificador legible: supervision_interna, analitica_interna, ...');

            // Nivel técnico: 1 seudonimización, 2 generalización, 3 k-anonimato
            $table->unsignedTinyInteger('nivel');

            // Versión actual — se incrementa automáticamente al modificar campos o k_valor
            $table->unsignedInteger('version')->default(1);

            // Estado operativo
            $table->string('estado', 20)->default('activo')
                ->comment('activo | inactivo');

            // Los perfiles de sistema no pueden eliminarse
            $table->boolean('es_sistema')->default(false);

            // Configuración campo a campo en JSON
            $table->json('campos');

            // Valor de K para perfiles de Nivel 3 (null en Nivel 1 y 2)
            $table->unsignedInteger('k_valor')->nullable();

            $table->timestamps();

            $table->index('estado');
            $table->index('es_sistema');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('perfiles_anonimizacion');
    }
};

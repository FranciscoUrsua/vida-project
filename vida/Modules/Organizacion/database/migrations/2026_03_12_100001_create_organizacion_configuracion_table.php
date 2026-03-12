<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: tabla de configuración general de la organización.
 *
 * Almacena pares clave-valor configurables desde el backoffice:
 * nombre de la organización, municipio, parámetros del sistema, etc.
 * Sigue el principio 4.15 (configuración sin desarrollo) de VIDA 360.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizacion_configuracion', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 100)->unique()->comment('Clave de configuración, ej: nombre_organizacion');
            $table->text('valor')->comment('Valor de la configuración (siempre texto, se castea según tipo)');
            $table->string('descripcion', 255)->nullable()->comment('Descripción legible del parámetro');
            $table->string('tipo', 20)->default('texto')
                ->comment('Tipo de dato: texto, numero, booleano, json');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizacion_configuracion');
    }
};

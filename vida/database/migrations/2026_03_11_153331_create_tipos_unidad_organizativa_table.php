<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: tabla de tipos de Unidad Organizativa.
 *
 * Catálogo configurable de tipos de UO (ayuntamiento, dirección general,
 * departamento, centro, etc.). El campo `tipo` de `unidades_organizativas`
 * referencia el `codigo` de esta tabla, evitando enums cerrados en BD
 * (principio 4.12 de principios-vida360.md).
 */
return new class extends Migration
{
    /**
     * Crea la tabla `tipos_unidad_organizativa`.
     */
    public function up(): void
    {
        Schema::create('tipos_unidad_organizativa', function (Blueprint $table) {
            $table->id();

            // Código legible usado como referencia en unidades_organizativas.tipo
            $table->string('codigo', 50)->unique()->comment('Clave de referencia, ej: ayuntamiento, dg, departamento, centro');

            // Nombre descriptivo para mostrar en la interfaz
            $table->string('nombre', 100)->comment('Nombre descriptivo, ej: Dirección General');

            $table->timestamps();
        });
    }

    /**
     * Elimina la tabla `tipos_unidad_organizativa`.
     */
    public function down(): void
    {
        Schema::dropIfExists('tipos_unidad_organizativa');
    }
};

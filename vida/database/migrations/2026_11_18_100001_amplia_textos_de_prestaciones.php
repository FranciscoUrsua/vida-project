<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Amplía los campos de texto de `prestaciones` que la guía oficial no cabe en 255.
 *
 * La Guía de prestaciones 2026 del Ayuntamiento de Madrid (113 fichas) trae aportaciones,
 * plazos, duraciones, proveedores y financiaciones con citas normativas de hasta ~830
 * caracteres, y un nombre de 202. Se amplían para no recortar texto oficial:
 * `nombre` a 255 (ya era el máximo del formulario de Filament) y los otros cinco a `text`.
 */
return new class extends Migration
{
    /** Campos que pasan de varchar(255) a text. */
    private const TEXTOS = ['aportacion_usuario', 'plazo_concesion', 'duracion_maxima', 'proveedor', 'financiacion_detalle'];

    /**
     * Amplía las columnas.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('prestaciones', function (Blueprint $table) {
            $table->string('nombre', 255)->change();

            foreach (self::TEXTOS as $columna) {
                $table->text($columna)->nullable()->change();
            }
        });
    }

    /**
     * Vuelve a los tamaños anteriores (falla si hay valores más largos, a propósito).
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('prestaciones', function (Blueprint $table) {
            $table->string('nombre', 200)->change();

            foreach (self::TEXTOS as $columna) {
                $table->string($columna, 255)->nullable()->change();
            }
        });
    }
};

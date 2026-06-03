<?php

use App\Models\UnidadOrganizativa;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: tabla de Unidades Organizativas.
 *
 * Estructura jerárquica de la organización (N niveles), implementada
 * como Adjacency List (parent_id auto-referencial). La jerarquía se
 * recorre con el paquete staudenmeir/laravel-adjacency-list mediante
 * CTEs recursivas en PostgreSQL.
 *
 * El campo `tipo` almacena el código del tipo de UO (referencia blanda
 * a tipos_unidad_organizativa.codigo) para mantener el catálogo
 * configurable sin enums cerrados (principio 4.12).
 *
 * @see UnidadOrganizativa
 * @see docs/modulo-usuarios-permisos.md sección 1.2
 */
return new class extends Migration
{
    /**
     * Crea la tabla `unidades_organizativas`.
     */
    public function up(): void
    {
        Schema::create('unidades_organizativas', function (Blueprint $table) {
            $table->id();

            $table->string('nombre', 200)->comment('Nombre completo de la unidad organizativa');

            // Tipo de UO referenciando el catálogo configurable
            $table->string('tipo', 50)->comment('Código del tipo (ej: ayuntamiento, dg, departamento, centro). Ref: tipos_unidad_organizativa.codigo');

            // Auto-referencia para la jerarquía (null = nodo raíz)
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('unidades_organizativas')
                ->onDelete('restrict')
                ->comment('UO padre en la jerarquía. Null para el nodo raíz.');

            $table->boolean('activa')->default(true)->comment('Permite desactivar sin eliminar (historial)');

            $table->timestamps();
            $table->softDeletes();

            // Índice para navegación jerárquica eficiente
            $table->index('parent_id');

            // Índice para filtrar por tipo
            $table->index('tipo');
        });
    }

    /**
     * Elimina la tabla `unidades_organizativas`.
     */
    public function down(): void
    {
        Schema::dropIfExists('unidades_organizativas');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Añade los códigos territoriales al modelo canónico de dirección
 * (tablas con TieneDireccion: ciudadanos y centros).
 *
 * - `codigo_ndp`: identificador del portal en la BDC. Permite recalcular la
 *   asignación si cambian los límites sin volver a geocodificar, y detectar
 *   personas del mismo portal.
 * - `distrito_codigo`, `barrio_codigo`, `seccion_censal_codigo`: con ellos se
 *   resuelve el centro de la dirección (docs/modulo-asignacion.md §2.2).
 *
 * No se cifran: son necesarios para buscar y, a diferencia de `direccion_texto`,
 * no identifican el domicilio por sí solos (la vía y el número tampoco se
 * cifran hoy). No se guardan la parcela catastral ni la sección de cartería.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $tablas = ['ciudadanos', 'centros'];

    /**
     * Añade las columnas e índices.
     *
     * @return void
     */
    public function up(): void
    {
        foreach ($this->tablas as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->string('codigo_ndp', 20)->nullable();
                $table->string('distrito_codigo', 2)->nullable();
                $table->string('barrio_codigo', 3)->nullable();
                $table->char('seccion_censal_codigo', 10)->nullable();

                $table->index('seccion_censal_codigo');
                $table->index('codigo_ndp');
            });
        }
    }

    /**
     * Elimina las columnas.
     *
     * @return void
     */
    public function down(): void
    {
        foreach ($this->tablas as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->dropIndex(['seccion_censal_codigo']);
                $table->dropIndex(['codigo_ndp']);
                $table->dropColumn(['codigo_ndp', 'distrito_codigo', 'barrio_codigo', 'seccion_censal_codigo']);
            });
        }
    }
};

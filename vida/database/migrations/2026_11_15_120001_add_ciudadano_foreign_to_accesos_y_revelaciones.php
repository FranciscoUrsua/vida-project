<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Añade la clave foránea a ciudadanos en accesos_protegidos y revelaciones_identidad.
 *
 * Eran las dos últimas columnas ciudadano_id sin FK. La de revelaciones_identidad
 * se omitió a propósito «para no crear dependencia de integridad», pero los
 * ciudadanos solo tienen baja lógica y audits, que también es un registro legal,
 * ya tiene esta FK: la restricción no impide nada legítimo y evita registrar
 * accesos o revelaciones de personas inexistentes. El borrado se restringe.
 *
 * @throws RuntimeException Si alguna de las tablas tiene filas cuyo ciudadano no existe
 */
return new class extends Migration
{
    /** @var list<string> Tablas que reciben la FK */
    private array $tablas = ['accesos_protegidos', 'revelaciones_identidad'];

    /**
     * Comprueba que no hay filas huérfanas y crea las FK.
     *
     * @return void
     */
    public function up(): void
    {
        // Se comprueban las dos tablas antes de tocar ninguna, para no dejar una a medias.
        foreach ($this->tablas as $tabla) {
            $huerfanas = DB::table($tabla)
                ->leftJoin('ciudadanos', 'ciudadanos.id', '=', "{$tabla}.ciudadano_id")
                ->whereNull('ciudadanos.id')
                ->count();

            if ($huerfanas > 0) {
                throw new RuntimeException(
                    "Hay {$huerfanas} filas en {$tabla} con un ciudadano_id que no existe en ciudadanos: corregirlas antes de migrar."
                );
            }
        }

        foreach ($this->tablas as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->foreign('ciudadano_id')
                    ->references('id')
                    ->on('ciudadanos')
                    ->restrictOnDelete();
            });
        }
    }

    /**
     * Retira las FK.
     *
     * @return void
     */
    public function down(): void
    {
        foreach ($this->tablas as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->dropForeign(['ciudadano_id']);
            });
        }
    }
};

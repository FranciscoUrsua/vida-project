<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Carga el catálogo `ciudadano.sexo` en `catalogos_sistema`.
 *
 * El sexo del ciudadano es un campo clasificatorio sin lógica de negocio
 * (principio 3.1): sus valores se configuran en el catálogo, no en un enum.
 * Las claves coinciden con las que ya guardaba el alta: M, F y D.
 */
return new class extends Migration
{
    /**
     * Inserta o actualiza los tres valores del catálogo.
     *
     * @return void
     */
    public function up(): void
    {
        $ahora = now();

        foreach ([['M', 'Masculino', 1], ['F', 'Femenino', 2], ['D', 'No especificado', 3]] as [$clave, $etiqueta, $orden]) {
            DB::table('catalogos_sistema')->updateOrInsert(
                ['grupo' => 'ciudadano.sexo', 'clave' => $clave],
                ['etiqueta' => $etiqueta, 'orden' => $orden, 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora],
            );
        }
    }

    /**
     * Elimina el catálogo.
     *
     * @return void
     */
    public function down(): void
    {
        DB::table('catalogos_sistema')->where('grupo', 'ciudadano.sexo')->delete();
    }
};

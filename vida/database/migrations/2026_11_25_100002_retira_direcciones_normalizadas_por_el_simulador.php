<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Deja sin normalizar las direcciones que normalizó el simulador de geocodificación.
 *
 * El simulador (`geocoder_proveedor = 'mock'`) daba por buena cualquier dirección e
 * inventaba coordenadas, distrito, barrio y sección censal. Desde el 25 nov 2026 solo
 * se usa en los tests; estos datos falsos no deben quedar en la BD, porque la
 * asignación de centro por domicilio decide con la sección censal. Se conserva el
 * texto original (`direccion_texto`) y el código postal, que escribe el profesional.
 * En la BD compartida afectaba a 7 personas y a ningún centro.
 */
return new class extends Migration
{
    /** @var list<string> Tablas con dirección canónica (trait TieneDireccion). */
    private const TABLAS = ['ciudadanos', 'centros'];

    /**
     * Vacía los campos estructurados y territoriales de esas direcciones.
     *
     * @return void
     */
    public function up(): void
    {
        foreach (self::TABLAS as $tabla) {
            DB::table($tabla)->where('geocoder_proveedor', 'mock')->update([
                'direccion_normalizada' => false,
                'tipo_via' => null,
                'nombre_via' => null,
                'tipo_numeracion' => null,
                'numero' => null,
                'portal' => null,
                'escalera' => null,
                'piso' => null,
                'puerta' => null,
                'municipio' => null,
                'coordenadas_lat' => null,
                'coordenadas_lng' => null,
                'codigo_ndp' => null,
                'distrito_codigo' => null,
                'barrio_codigo' => null,
                'seccion_censal_codigo' => null,
                'geocoder_proveedor' => null,
                'origen_direccion' => 'profesional',
            ]);
        }
    }

    /**
     * Sin vuelta atrás: los datos retirados eran inventados.
     *
     * @return void
     */
    public function down(): void
    {
        // Nada que restaurar
    }
};

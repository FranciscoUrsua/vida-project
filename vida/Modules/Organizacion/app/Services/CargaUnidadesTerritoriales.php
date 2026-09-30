<?php

namespace Modules\Organizacion\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Carga idempotente del catálogo de barrios y secciones censales desde los
 * ficheros oficiales de `Modules/Organizacion/database/data/`.
 *
 * Es el único punto de carga del catálogo territorial: lo usan los seeders y
 * la migración de datos que lo deja cargado en cada entorno al desplegar.
 * Actualiza por código (upsert), así que volver a cargar no duplica filas ni
 * reactiva lo que se haya desactivado a mano.
 *
 * Fuentes:
 * - `barrios.csv`: fichero de barrios del Ayuntamiento de Madrid, tal cual
 *   (separador `;`, UTF-8 con BOM, fin de línea CRLF).
 * - `secciones_censales.csv`: distrito, barrio y sección extraídos de
 *   `fuentes/secciones_censales.topojson` (el TopoJSON conserva la geometría,
 *   que la v1 no usa).
 *
 * @see docs/modulo-asignacion.md §2
 */
class CargaUnidadesTerritoriales
{
    /**
     * Carga o actualiza los barrios. Requiere los distritos ya cargados.
     *
     * @return int Número de barrios procesados.
     *
     * @throws RuntimeException Si falta el fichero o un distrito referenciado.
     */
    public function cargarBarrios(): int
    {
        $distritos = $this->idsDistritos();
        $ahora = now();
        $filas = [];

        foreach ($this->leerCsv('barrios.csv') as $fila) {
            $codigoDistrito = str_pad($fila['CODDIS'], 2, '0', STR_PAD_LEFT);
            $codigo = str_pad($fila['COD_BAR'], 3, '0', STR_PAD_LEFT);

            $filas[] = [
                'distrito_id' => $distritos[$codigoDistrito]
                    ?? throw new RuntimeException("Barrio {$codigo}: no existe el distrito {$codigoDistrito}."),
                'codigo' => $codigo,
                // El número de barrio es el último dígito del código municipal. No se usa la
                // columna NUM_BAR: en el fichero oficial Sol (016) figura con NUM_BAR = 8.
                'codigo_en_distrito' => substr($codigo, 2),
                'nombre' => $fila['NOMBRE'],
                'activo' => true,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];
        }

        DB::table('barrios')->upsert($filas, ['codigo'], ['distrito_id', 'codigo_en_distrito', 'nombre', 'updated_at']);

        return count($filas);
    }

    /**
     * Carga o actualiza las secciones censales. Requiere distritos y barrios cargados.
     *
     * @return int Número de secciones procesadas.
     *
     * @throws RuntimeException Si falta el fichero, un distrito o un barrio referenciado.
     */
    public function cargarSecciones(): int
    {
        $distritos = $this->idsDistritos();
        $barrios = DB::table('barrios')->pluck('id', 'codigo')->all();
        $ahora = now();
        $filas = [];

        foreach ($this->leerCsv('secciones_censales.csv') as $fila) {
            $codigoIne = '28079'.$fila['distrito'].$fila['seccion'];

            $filas[] = [
                'distrito_id' => $distritos[$fila['distrito']]
                    ?? throw new RuntimeException("Sección {$codigoIne}: no existe el distrito {$fila['distrito']}."),
                'barrio_id' => $barrios[$fila['barrio']]
                    ?? throw new RuntimeException("Sección {$codigoIne}: no existe el barrio {$fila['barrio']}."),
                'codigo_ine' => $codigoIne,
                'codigo_en_distrito' => ltrim($fila['seccion'], '0'),
                'activa' => true,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];
        }

        foreach (array_chunk($filas, 500) as $lote) {
            DB::table('secciones_censales')->upsert($lote, ['codigo_ine'], ['distrito_id', 'barrio_id', 'codigo_en_distrito', 'updated_at']);
        }

        return count($filas);
    }

    /**
     * Mapa código de distrito (dos dígitos) → id.
     *
     * @return array<string, int>
     */
    private function idsDistritos(): array
    {
        return DB::table('distritos')->pluck('id', 'codigo')->all();
    }

    /**
     * Lee un CSV del directorio de datos del módulo como filas asociativas por cabecera.
     *
     * @param string $fichero Nombre del fichero dentro de `database/data/`.
     * @return list<array<string, string>>
     *
     * @throws RuntimeException Si el fichero no existe.
     */
    private function leerCsv(string $fichero): array
    {
        $ruta = module_path('Organizacion', 'database/data/'.$fichero);

        if (! is_file($ruta)) {
            throw new RuntimeException("No se encuentra el fichero de datos territoriales {$ruta}.");
        }

        // Se quitan el BOM y los CR del fichero oficial de barrios
        $lineas = preg_split('/\R/', preg_replace('/^\xEF\xBB\xBF/', '', trim(file_get_contents($ruta))));
        $cabecera = str_getcsv(array_shift($lineas), ';');

        return array_map(
            fn (string $linea) => array_combine($cabecera, str_getcsv($linea, ';')),
            $lineas,
        );
    }
}

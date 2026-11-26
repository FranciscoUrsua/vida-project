<?php

namespace App\Services\Catalogos;

use App\Models\CatalogoSistema;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Carga idempotente de los catálogos comunes (`comun.*`) de `catalogos_sistema` desde los
 * CSV normalizados de `database/data/catalogos/` (SD-2 de la ficha sociodemográfica).
 *
 * Es el único punto de carga: lo usan `CatalogosOficialesSeeder` y la migración de datos
 * que deja los catálogos en cada entorno al desplegar (los seeders no se ejecutan al
 * desplegar, decisiones técnicas §16). Los CSV salen de los ficheros oficiales con
 * `php artisan catalogos:normalizar`; nunca se escriben a mano.
 *
 * Reglas de la carga, por (`grupo`, `clave`):
 * - Valor nuevo → se crea activo.
 * - Valor existente → se actualizan etiqueta, orden, atributos, equivalencias y fuente;
 *   **no** se toca `activo` (lo desactivado a mano sigue desactivado).
 * - Valor que ya no está en el fichero → no se borra (las fichas guardan su clave):
 *   recibe `vigente_hasta` = ayer y deja de ofrecerse. Si vuelve a aparecer, recupera la vigencia.
 *
 * Todo o nada: se validan los ficheros antes de escribir y la escritura va en una transacción.
 *
 * Formato del CSV (separador `;`, UTF-8, con cabecera):
 * `clave;etiqueta;orden;atributos;equivalencias;fuente`, con `atributos` y `equivalencias` en JSON.
 */
class CargaCatalogosOficiales
{
    /** Cabecera obligatoria de los CSV normalizados. */
    public const CABECERA = ['clave', 'etiqueta', 'orden', 'atributos', 'equivalencias', 'fuente'];

    /** Fichero normalizado de cada catálogo oficial, por grupo. */
    public const FICHEROS = [
        'comun.pais' => 'comun.pais.csv',
        'comun.nivel_estudios' => 'comun.nivel_estudios.csv',
        'comun.situacion_laboral' => 'comun.situacion_laboral.csv',
        'comun.idioma' => 'comun.idioma.csv',
    ];

    private readonly string $directorio;

    /**
     * @param string|null $directorio Carpeta de los CSV; por defecto `database/data/catalogos` (los tests pasan otra).
     */
    public function __construct(?string $directorio = null)
    {
        $this->directorio = rtrim($directorio ?? database_path('data/catalogos'), '/');
    }

    /**
     * Carga los países (ISO 3166-1 alfa-3, M49).
     *
     * @return ResultadoCargaCatalogo
     *
     * @throws RuntimeException Si falta el fichero o tiene errores.
     */
    public function paises(): ResultadoCargaCatalogo
    {
        return $this->cargar('comun.pais', self::FICHEROS['comun.pais']);
    }

    /**
     * Carga los niveles de formación alcanzados (CNED-A 2014).
     *
     * @return ResultadoCargaCatalogo
     *
     * @throws RuntimeException Si falta el fichero o tiene errores.
     */
    public function nivelesEstudios(): ResultadoCargaCatalogo
    {
        return $this->cargar('comun.nivel_estudios', self::FICHEROS['comun.nivel_estudios']);
    }

    /**
     * Carga las situaciones de actividad autopercibidas (EPA, SIDAC y SIDI).
     *
     * @return ResultadoCargaCatalogo
     *
     * @throws RuntimeException Si falta el fichero o tiene errores.
     */
    public function situacionesLaborales(): ResultadoCargaCatalogo
    {
        return $this->cargar('comun.situacion_laboral', self::FICHEROS['comun.situacion_laboral']);
    }

    /**
     * Carga los idiomas (ISO 639-1, o 639-2 si no hay código de dos letras).
     *
     * @return ResultadoCargaCatalogo
     *
     * @throws RuntimeException Si falta el fichero o tiene errores.
     */
    public function idiomas(): ResultadoCargaCatalogo
    {
        return $this->cargar('comun.idioma', self::FICHEROS['comun.idioma']);
    }

    /**
     * Carga los cuatro catálogos en una sola transacción: si uno falla, no se carga ninguno.
     *
     * @return list<ResultadoCargaCatalogo>
     *
     * @throws RuntimeException Si falta algún fichero o alguno tiene errores.
     */
    public function todos(): array
    {
        // Se leen y validan todos antes de escribir nada
        $lotes = [];
        foreach (self::FICHEROS as $grupo => $fichero) {
            $lotes[$grupo] = $this->leer($fichero);
        }

        return DB::transaction(function () use ($lotes): array {
            $resultados = [];
            foreach ($lotes as $grupo => $filas) {
                $resultados[] = $this->aplicar($grupo, $filas);
            }

            return $resultados;
        });
    }

    /**
     * Carga un grupo desde un CSV normalizado del directorio de datos.
     *
     * @param string $grupo Grupo de `catalogos_sistema`.
     * @param string $fichero Nombre del CSV dentro del directorio de datos.
     *
     * @return ResultadoCargaCatalogo
     *
     * @throws RuntimeException Si falta el fichero o tiene errores (clave duplicada, JSON inválido…).
     */
    public function cargar(string $grupo, string $fichero): ResultadoCargaCatalogo
    {
        $filas = $this->leer($fichero);

        return DB::transaction(fn (): ResultadoCargaCatalogo => $this->aplicar($grupo, $filas));
    }

    /**
     * Aplica las filas leídas a un grupo según las reglas de la clase.
     *
     * @param string $grupo Grupo de `catalogos_sistema`.
     * @param array<string, array{etiqueta: string, orden: int, atributos: array<string, mixed>|null, equivalencias: array<string, mixed>|null, fuente: string|null}> $filas Filas por clave.
     *
     * @return ResultadoCargaCatalogo
     */
    private function aplicar(string $grupo, array $filas): ResultadoCargaCatalogo
    {
        $existentes = CatalogoSistema::where('grupo', $grupo)->get()->keyBy('clave');
        $creados = 0;
        $actualizados = 0;

        foreach ($filas as $clave => $fila) {
            $valor = $existentes->get($clave) ?? new CatalogoSistema(['grupo' => $grupo, 'clave' => $clave, 'activo' => true]);

            // jsonb reordena las claves: se comparan sin orden (`==`) para no contar como
            // actualizado lo que no ha cambiado
            foreach (['atributos', 'equivalencias'] as $columna) {
                if ($valor->exists && $valor->{$columna} == $fila[$columna]) {
                    unset($fila[$columna]);
                }
            }

            // `activo` no se toca en los existentes: lo desactivado a mano sigue desactivado (SD-2)
            $valor->fill($fila + ['vigente_hasta' => null]);

            if (! $valor->exists) {
                $valor->save();
                $creados++;
            } elseif ($valor->isDirty()) {
                $valor->save();
                $actualizados++;
            }
        }

        // Lo que ya no está en la fuente deja de estar vigente desde hoy; no se borra
        $sinVigencia = CatalogoSistema::where('grupo', $grupo)
            ->whereNotIn('clave', array_map('strval', array_keys($filas)))
            ->whereNull('vigente_hasta')
            ->update(['vigente_hasta' => today()->subDay()->toDateString(), 'updated_at' => now()]);

        return new ResultadoCargaCatalogo($grupo, $creados, $actualizados, $sinVigencia);
    }

    /**
     * Lee y valida un CSV normalizado.
     *
     * @param string $fichero Nombre del CSV dentro del directorio de datos.
     *
     * @return array<string, array{etiqueta: string, orden: int, atributos: array<string, mixed>|null, equivalencias: array<string, mixed>|null, fuente: string|null}> Filas por clave.
     *
     * @throws RuntimeException Si falta el fichero, la cabecera no es la esperada, hay una clave vacía o
     *                          duplicada, una etiqueta vacía o un JSON inválido.
     */
    private function leer(string $fichero): array
    {
        $ruta = $this->directorio.'/'.$fichero;
        if (! is_file($ruta)) {
            throw new RuntimeException("No se encuentra el fichero de catálogo {$ruta}.");
        }

        $manejador = fopen($ruta, 'r');
        $cabecera = fgetcsv($manejador, null, ';', '"', '');
        if ($cabecera !== self::CABECERA) {
            fclose($manejador);
            throw new RuntimeException("{$fichero}: la cabecera debe ser ".implode(';', self::CABECERA).'.');
        }

        $filas = [];
        $linea = 1;
        while (($datos = fgetcsv($manejador, null, ';', '"', '')) !== false) {
            $linea++;
            if ($datos === [null]) {
                continue;
            }

            $fila = array_combine(self::CABECERA, array_pad($datos, count(self::CABECERA), ''));
            $clave = trim($fila['clave']);

            if ($clave === '' || trim($fila['etiqueta']) === '') {
                fclose($manejador);
                throw new RuntimeException("{$fichero}, línea {$linea}: clave o etiqueta vacía.");
            }
            if (isset($filas[$clave])) {
                fclose($manejador);
                throw new RuntimeException("{$fichero}, línea {$linea}: la clave {$clave} aparece dos veces.");
            }

            $filas[$clave] = [
                'etiqueta' => trim($fila['etiqueta']),
                'orden' => (int) $fila['orden'],
                'atributos' => $this->json($fila['atributos'], $fichero, $linea),
                'equivalencias' => $this->json($fila['equivalencias'], $fichero, $linea),
                'fuente' => trim($fila['fuente']) ?: null,
            ];
        }
        fclose($manejador);

        return $filas;
    }

    /**
     * Decodifica una columna JSON (vacía = nulo).
     *
     * @param string $texto Contenido de la columna.
     * @param string $fichero Fichero, para el mensaje de error.
     * @param int $linea Línea, para el mensaje de error.
     *
     * @return array<string, mixed>|null
     *
     * @throws RuntimeException Si no es un objeto JSON válido.
     */
    private function json(string $texto, string $fichero, int $linea): ?array
    {
        if (trim($texto) === '') {
            return null;
        }

        $valor = json_decode($texto, true);
        if (! is_array($valor)) {
            throw new RuntimeException("{$fichero}, línea {$linea}: JSON inválido.");
        }

        return $valor;
    }
}

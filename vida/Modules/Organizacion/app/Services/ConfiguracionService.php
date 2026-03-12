<?php

namespace Modules\Organizacion\Services;

use Illuminate\Support\Facades\Cache;
use Modules\Organizacion\Models\Configuracion;

/**
 * Servicio de configuración general de la organización.
 *
 * Proporciona acceso a los parámetros de configuración almacenados
 * en la tabla organizacion_configuracion, con caché para evitar
 * consultas a BD en cada petición.
 *
 * Se registra como singleton en el ServiceProvider del módulo.
 *
 * Uso:
 *   app(ConfiguracionService::class)->get('nombre_organizacion');
 *   app(ConfiguracionService::class)->set('nombre_organizacion', 'Ayuntamiento de Madrid');
 */
class ConfiguracionService
{
    /**
     * Clave de caché para el mapa completo de configuraciones.
     */
    private const CACHE_KEY = 'organizacion_configuracion';

    /**
     * TTL de la caché en segundos (24 horas).
     */
    private const CACHE_TTL = 86400;

    /**
     * Obtiene el valor de una clave de configuración.
     *
     * Devuelve el valor casteado según el tipo declarado en la tabla.
     * Si la clave no existe, devuelve el valor por defecto.
     *
     * @param string $clave Clave de configuración, ej: 'nombre_organizacion'
     * @param mixed $defecto Valor a devolver si la clave no existe
     * @return mixed
     */
    public function get(string $clave, mixed $defecto = null): mixed
    {
        $configuraciones = $this->cargarTodas();

        if (! array_key_exists($clave, $configuraciones)) {
            return $defecto;
        }

        return $configuraciones[$clave];
    }

    /**
     * Establece el valor de una clave de configuración.
     *
     * Crea la entrada si no existe, la actualiza si ya existe.
     * Invalida la caché para que el próximo get() lea desde BD.
     *
     * @param string $clave Clave de configuración
     * @param mixed $valor Valor a almacenar (se convierte a string para la BD)
     * @return void
     */
    public function set(string $clave, mixed $valor): void
    {
        $valorTexto = is_bool($valor) ? ($valor ? 'true' : 'false')
            : (is_array($valor) ? json_encode($valor) : (string) $valor);

        Configuracion::updateOrCreate(
            ['clave' => $clave],
            ['valor' => $valorTexto]
        );

        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Invalida la caché de configuración.
     *
     * Útil cuando se modifican configuraciones desde el backoffice
     * sin pasar por este servicio.
     *
     * @return void
     */
    public function limpiarCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Carga todas las configuraciones en caché y las devuelve como mapa.
     *
     * @return array<string, mixed>
     */
    private function cargarTodas(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return Configuracion::all()
                ->mapWithKeys(fn (Configuracion $config) => [
                    $config->clave => $config->valorCasteado(),
                ])
                ->toArray();
        });
    }
}

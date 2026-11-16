<?php

namespace Modules\Documentos\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Parámetro configurable de las plantillas de informe: un merge tag con valor fijo
 * que define el administrador (p. ej. `{{ ciudad }}`, `{{ web_municipal }}`) sin tocar
 * código. Global (un valor por instalación); la variante por UO está en el BACKLOG.
 *
 * Tiene la menor prioridad al resolver: un tag contextual o una variable dinámica con
 * la misma clave gana siempre (un parámetro `nombre_ciudadano` no rompe un informe).
 * Los mapas se cachean una hora y se invalidan al guardar o borrar.
 *
 * @property int $id
 * @property string $clave Nombre del tag: minúsculas, números y guiones bajos.
 * @property string $etiqueta Texto legible del catálogo de variables.
 * @property string $valor Valor que se sustituye en el informe.
 * @property string|null $descripcion Para qué se usa (solo backoffice).
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ParametroInforme extends Model
{
    /** Formato admitido de la clave. */
    public const REGEX_CLAVE = '/^[a-z][a-z0-9_]*$/';

    /** Clave de caché del mapa clave → valor. */
    private const CACHE_MAPA = 'parametros_informe_mapa';

    /** Clave de caché del mapa clave → etiqueta. */
    private const CACHE_ETIQUETAS = 'parametros_informe_etiquetas';

    protected $table = 'parametros_informe';

    protected $fillable = ['clave', 'etiqueta', 'valor', 'descripcion'];

    /**
     * Todos los parámetros como clave → valor (caché de una hora).
     *
     * @return array<string, string>
     */
    public static function comoMapa(): array
    {
        return Cache::remember(self::CACHE_MAPA, now()->addHour(), fn (): array => static::pluck('valor', 'clave')->all());
    }

    /**
     * Todos los parámetros como clave → etiqueta, para el catálogo de merge tags.
     *
     * @return array<string, string>
     */
    public static function comoEtiquetas(): array
    {
        return Cache::remember(self::CACHE_ETIQUETAS, now()->addHour(), fn (): array => static::pluck('etiqueta', 'clave')->all());
    }

    /**
     * Invalida las cachés al guardar o borrar un parámetro.
     *
     * @return void
     */
    protected static function booted(): void
    {
        static::saved(fn () => self::olvidarCache());
        static::deleted(fn () => self::olvidarCache());
    }

    /**
     * Borra los dos mapas cacheados.
     *
     * @return void
     */
    private static function olvidarCache(): void
    {
        Cache::forget(self::CACHE_MAPA);
        Cache::forget(self::CACHE_ETIQUETAS);
    }
}

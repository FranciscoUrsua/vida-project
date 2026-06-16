<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo de valores clasificatorios configurables desde backoffice.
 *
 * Implementa el principio 3.10 de principios-vida360.md: valores puramente
 * descriptivos que un administrador funcional puede añadir, renombrar u
 * ordenar sin necesidad de un deploy.
 *
 * RESTRICCIÓN: los valores de este catálogo NUNCA deben referenciarse
 * en lógica de negocio (match/if con comportamiento diferenciado). Si el
 * código necesita distinguir entre dos valores para hacer algo distinto,
 * ese catálogo debe ser un enum, no una entrada aquí.
 *
 * Uso habitual desde Filament:
 *   CatalogoSistema::opcionesParaSelect('prestacion.objetivo_general')
 *   // → ['01' => 'Acceso, información y valoración', ...]
 *
 * @property int $id
 * @property string $grupo
 * @property string $clave
 * @property string $etiqueta
 * @property int $orden
 * @property bool $activo
 */
class CatalogoSistema extends Model
{
    protected $table = 'catalogos_sistema';

    protected $fillable = ['grupo', 'clave', 'etiqueta', 'valor', 'orden', 'activo'];

    protected $casts = ['activo' => 'boolean', 'orden' => 'integer'];

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeDeGrupo(Builder $query, string $grupo): Builder
    {
        return $query->where('grupo', $grupo)->where('activo', true)->orderBy('orden')->orderBy('etiqueta');
    }

    // -------------------------------------------------------------------------
    // Helpers estáticos
    // -------------------------------------------------------------------------

    /**
     * Devuelve las opciones activas de un grupo en formato [clave => etiqueta]
     * listo para usar en selects de Filament.
     *
     * @param string $grupo Grupo de catalogo.
     *
     * @return array<string, string>
     */
    public static function opcionesParaSelect(string $grupo): array
    {
        return static::deGrupo($grupo)->pluck('etiqueta', 'clave')->all();
    }

    /**
     * Devuelve la etiqueta de un valor del catálogo por clave única.
     * Útil para parámetros de configuración global (clave única entre todos los grupos).
     *
     * @param string $clave Clave única del catálogo
     * @param string $defecto Valor por defecto si la clave no existe o está inactiva
     *
     * @return string Etiqueta del catalogo o valor por defecto.
     */
    public static function valor(string $clave, string $defecto = ''): string
    {
        return static::where('clave', $clave)->where('activo', true)->value('etiqueta') ?? $defecto;
    }

    /**
     * Devuelve las opciones de un grupo filtradas por prefijo de clave.
     * Útil para cargar subcategorías dependientes de una categoría padre.
     *
     * Ejemplo: opcionesParaSelectConPrefijo('prestacion.categoria', '01')
     * devuelve solo las claves que empiezan por '01'.
     *
     * @param string $grupo Grupo de catalogo.
     * @param string $prefijo Prefijo de clave a filtrar.
     *
     * @return array<string, string>
     */
    public static function opcionesParaSelectConPrefijo(string $grupo, string $prefijo): array
    {
        return static::deGrupo($grupo)
            ->where('clave', 'like', $prefijo.'%')
            ->pluck('etiqueta', 'clave')
            ->all();
    }
}

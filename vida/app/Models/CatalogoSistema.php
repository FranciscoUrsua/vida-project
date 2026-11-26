<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

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
 * Catálogos versionados (SD-1, ficha sociodemográfica): algunos grupos (`comun.*`)
 * se cargan desde ficheros oficiales con `CargaCatalogosOficiales`. Su `clave` es el
 * código estándar y no cambia nunca; un valor que desaparece de la fuente no se borra,
 * deja de estar vigente (`vigente_hasta`), para que las fichas antiguas sigan
 * mostrando su etiqueta (`etiquetaDe()`).
 *
 * @property int $id
 * @property string $grupo
 * @property string $clave Código estable del valor (en los catálogos oficiales, el código estándar).
 * @property string $etiqueta
 * @property string|null $valor
 * @property int $orden
 * @property bool $activo
 * @property Carbon|null $vigente_desde Primer día vigente; nulo = desde siempre.
 * @property Carbon|null $vigente_hasta Último día vigente (inclusive); nulo = sin fin.
 * @property array<string, mixed>|null $atributos Agrupaciones del valor (región, UE, nivel agregado…).
 * @property array<string, string|null>|null $equivalencias Códigos en otros sistemas (iso2, m49, ine, hsu…).
 * @property string|null $fuente Fuente oficial y versión del fichero del que sale el valor.
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class CatalogoSistema extends Model
{
    protected $table = 'catalogos_sistema';

    protected $fillable = [
        'grupo', 'clave', 'etiqueta', 'valor', 'orden', 'activo',
        'vigente_desde', 'vigente_hasta', 'atributos', 'equivalencias', 'fuente',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'orden' => 'integer',
        'vigente_desde' => 'date',
        'vigente_hasta' => 'date',
        'atributos' => 'array',
        'equivalencias' => 'array',
    ];

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Filtra el catálogo por grupo, vigentes hoy (activos y dentro de vigencia) y orden visible.
     *
     * @param Builder<CatalogoSistema> $query Consulta base.
     * @param string $grupo Grupo a filtrar.
     *
     * @return Builder<CatalogoSistema>
     */
    public function scopeDeGrupo(Builder $query, string $grupo): Builder
    {
        return $query->where('grupo', $grupo)->vigentes()->orderBy('orden')->orderBy('etiqueta');
    }

    /**
     * Valores que se pueden elegir en una fecha: activos y dentro de su vigencia.
     *
     * Un valor sin fechas de vigencia está vigente siempre (todos los catálogos que no
     * se cargan desde una fuente oficial).
     *
     * @param Builder<CatalogoSistema> $query Consulta base.
     * @param Carbon|null $fecha Fecha de referencia; hoy si es nula.
     *
     * @return Builder<CatalogoSistema>
     */
    public function scopeVigentes(Builder $query, ?Carbon $fecha = null): Builder
    {
        $dia = ($fecha ?? today())->toDateString();

        return $query
            ->where($query->qualifyColumn('activo'), true)
            ->where(fn (Builder $q) => $q->whereNull($q->qualifyColumn('vigente_desde'))->orWhereDate($q->qualifyColumn('vigente_desde'), '<=', $dia))
            ->where(fn (Builder $q) => $q->whereNull($q->qualifyColumn('vigente_hasta'))->orWhereDate($q->qualifyColumn('vigente_hasta'), '>=', $dia));
    }

    // -------------------------------------------------------------------------
    // Helpers estáticos
    // -------------------------------------------------------------------------

    /**
     * Devuelve las opciones vigentes de un grupo en formato [clave => etiqueta]
     * listo para usar en selects de Filament.
     *
     * @param string $grupo Grupo de catálogo a consultar.
     *
     * @return array<string, string>
     */
    public static function opcionesParaSelect(string $grupo): array
    {
        return static::deGrupo($grupo)->pluck('etiqueta', 'clave')->all();
    }

    /**
     * Etiqueta actual de una clave de un grupo, aunque el valor ya no esté activo ni vigente.
     *
     * Es lo que permite mostrar fichas antiguas: la ficha guarda la clave y se pinta con la
     * etiqueta que tenga el catálogo hoy, aunque el valor ya no se pueda elegir.
     *
     * @param string $grupo Grupo del catálogo.
     * @param string $clave Clave guardada.
     *
     * @return string|null Nulo si la clave no existe en el grupo.
     */
    public static function etiquetaDe(string $grupo, string $clave): ?string
    {
        return static::where('grupo', $grupo)->where('clave', $clave)->value('etiqueta');
    }

    /**
     * Devuelve la etiqueta de un valor del catálogo por clave única.
     * Útil para parámetros de configuración global (clave única entre todos los grupos).
     *
     * @param string $clave Clave única del catálogo.
     * @param string $defecto Valor por defecto si la clave no existe o está inactiva.
     *
     * @return string
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
     * @param string $grupo Grupo de catálogo a consultar.
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

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Staudenmeir\LaravelAdjacencyList\Eloquent\HasRecursiveRelationships;

/**
 * Modelo de Unidad Organizativa (UO).
 *
 * Representa un nodo en la jerarquía organizativa del ayuntamiento:
 * puede ser el propio ayuntamiento (raíz), un Área de Gobierno,
 * una Dirección General, un Departamento, un Centro, etc.
 *
 * La jerarquía es una Adjacency List (parent_id auto-referencial).
 * Las consultas de ancestros y descendientes se ejecutan mediante
 * CTEs recursivas nativas de PostgreSQL, gestionadas por el paquete
 * staudenmeir/laravel-adjacency-list.
 *
 * El tipo de UO es una referencia blanda al catálogo configurable
 * `tipos_unidad_organizativa`, evitando enums cerrados (principio 4.12).
 *
 * @property int $id
 * @property string $nombre
 * @property string $tipo Código del tipo (ej: 'ayuntamiento', 'dg', 'departamento', 'centro')
 * @property int|null $parent_id
 * @property bool $activa
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 *
 * @see docs/modulo-usuarios-permisos.md sección 1.2
 */
class UnidadOrganizativa extends Model
{
    use HasFactory;
    use HasRecursiveRelationships;
    use SoftDeletes;

    /** @var string Tabla de base de datos */
    protected $table = 'unidades_organizativas';

    /** @var list<string> Campos asignables en masa */
    protected $fillable = [
        'nombre',
        'tipo',
        'parent_id',
        'activa',
    ];

    /** @var array<string, string> Conversiones de tipo */
    protected $casts = [
        'activa' => 'boolean',
    ];

    // -------------------------------------------------------------------------
    // Relaciones directas
    // -------------------------------------------------------------------------

    /**
     * UO padre en la jerarquía.
     * Devuelve null para el nodo raíz (Ayuntamiento).
     *
     * @return BelongsTo<UnidadOrganizativa, UnidadOrganizativa>
     */
    public function padre(): BelongsTo
    {
        return $this->belongsTo(UnidadOrganizativa::class, 'parent_id');
    }

    /**
     * UO hijas directas (un nivel por debajo).
     *
     * @return HasMany<UnidadOrganizativa>
     */
    public function hijas(): HasMany
    {
        return $this->hasMany(UnidadOrganizativa::class, 'parent_id');
    }

    /**
     * Usuarios (profesionales) actualmente adscritos a esta UO.
     * La adscripción pasa por la tabla pivot `usuario_uo`.
     *
     * @return HasMany<UsuarioUo>
     */
    public function usuarios(): HasMany
    {
        return $this->hasMany(UsuarioUo::class, 'unidad_organizativa_id');
    }

    // -------------------------------------------------------------------------
    // Relaciones recursivas (staudenmeir/laravel-adjacency-list)
    // Disponibles gracias al trait HasRecursiveRelationships:
    //   ->ancestors()      — todos los nodos por encima en la jerarquía
    //   ->descendants()    — todos los nodos por debajo (cualquier profundidad)
    //   ->ancestorsAndSelf()
    //   ->descendantsAndSelf()
    //   ->siblings()
    // -------------------------------------------------------------------------

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Filtra únicamente las UO marcadas como activas.
     *
     * @param Builder<UnidadOrganizativa> $consulta
     * @return Builder<UnidadOrganizativa>
     */
    public function scopeActivas(Builder $consulta): Builder
    {
        return $consulta->where('activa', true);
    }

    /**
     * Filtra UO raíz (sin padre).
     *
     * @param Builder<UnidadOrganizativa> $consulta
     * @return Builder<UnidadOrganizativa>
     */
    public function scopeRaiz(Builder $consulta): Builder
    {
        return $consulta->whereNull('parent_id');
    }
}

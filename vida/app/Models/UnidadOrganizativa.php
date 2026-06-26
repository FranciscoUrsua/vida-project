<?php

namespace App\Models;

use Database\Factories\UnidadOrganizativaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Staudenmeir\LaravelAdjacencyList\Eloquent\HasRecursiveRelationships;

/**
 * @use HasFactory<UnidadOrganizativaFactory>
 *
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
 * @property string|null $nombre_corto
 * @property string $tipo
 * @property int|null $parent_id
 * @property bool $activa
 * @property string|null $plan_nombre_completo
 * @property string|null $plan_nombre_corto
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
class UnidadOrganizativa extends Model
{
    /** @use HasFactory<UnidadOrganizativaFactory> */
    use HasFactory;
    use HasRecursiveRelationships;
    use SoftDeletes;

    protected $table = 'unidades_organizativas';

    protected $fillable = [
        'nombre',
        'nombre_corto',
        'tipo',
        'parent_id',
        'activa',
        'plan_nombre_completo',
        'plan_nombre_corto',
    ];

    protected $casts = [
        'activa' => 'boolean',
    ];

    /**
     * @return BelongsTo<UnidadOrganizativa, $this>
     */
    public function padre(): BelongsTo
    {
        return $this->belongsTo(UnidadOrganizativa::class, 'parent_id');
    }

    /**
     * @return HasMany<UnidadOrganizativa, $this>
     */
    public function hijas(): HasMany
    {
        return $this->hasMany(UnidadOrganizativa::class, 'parent_id');
    }

    /**
     * @return HasMany<UsuarioUo, $this>
     */
    public function usuarios(): HasMany
    {
        return $this->hasMany(UsuarioUo::class, 'unidad_organizativa_id');
    }

    public function isDescendantOf(UnidadOrganizativa $ancestor): bool
    {
        return $this->ancestors()->where('id', $ancestor->id)->exists();
    }

    public function getPlanNombreCompletoAttribute(): string
    {
        return $this->attributes['plan_nombre_completo'] ?? 'Plan de intervención';
    }

    public function getPlanNombreCortoAttribute(): string
    {
        return $this->attributes['plan_nombre_corto'] ?? 'Plan';
    }

    /**
     * @param Builder<UnidadOrganizativa> $consulta
     *
     * @return Builder<UnidadOrganizativa>
     */
    public function scopeActivas(Builder $consulta): Builder
    {
        return $consulta->where('activa', true);
    }

    /**
     * @param Builder<UnidadOrganizativa> $consulta
     *
     * @return Builder<UnidadOrganizativa>
     */
    public function scopeRaiz(Builder $consulta): Builder
    {
        return $consulta->whereNull('parent_id');
    }
}

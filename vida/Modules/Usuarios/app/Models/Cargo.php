<?php

namespace Modules\Usuarios\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Catálogo de cargos profesionales.
 *
 * Configurable desde el backoffice por adm_sistema.
 * Ejemplos: Trabajador/a Social, Psicólogo/a, Educador/a Social.
 *
 * @property int $id
 * @property string $nombre
 * @property string|null $slug  Identificador estable único, ej: 'trabajador-social'
 * @property string|null $descripcion
 * @property bool $activo
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @see docs/modulo-usuarios-permisos.md sección 5.2
 */
class Cargo extends Model
{
    /** @var string */
    protected $table = 'cargos';

    /** @var list<string> */
    protected $fillable = [
        'nombre',
        'slug',
        'descripcion',
        'activo',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'activo' => 'boolean',
    ];

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Profesionales con este cargo.
     *
     * @return HasMany<Profesional>
     */
    public function profesionales(): HasMany
    {
        return $this->hasMany(Profesional::class, 'cargo_id');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Solo cargos activos.
     *
     * @param Builder<Cargo> $consulta
     *
     * @return Builder<Cargo>
     */
    public function scopeActivos(Builder $consulta): Builder
    {
        return $consulta->where('activo', true);
    }
}

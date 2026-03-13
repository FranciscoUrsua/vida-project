<?php

namespace Modules\Usuarios\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catálogo de titulaciones académicas.
 *
 * Configurable desde el backoffice por adm_sistema.
 * Ejemplos: Grado en Trabajo Social, Grado en Psicología.
 *
 * @property int $id
 * @property string $nombre
 * @property bool $activo
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @see docs/modulo-usuarios-permisos.md sección 5.2
 */
class Titulacion extends Model
{
    /** @var string */
    protected $table = 'titulaciones';

    /** @var list<string> */
    protected $fillable = [
        'nombre',
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
     * Profesionales con esta titulación.
     *
     * @return HasMany<Profesional>
     */
    public function profesionales(): HasMany
    {
        return $this->hasMany(Profesional::class, 'titulacion_id');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Solo titulaciones activas.
     *
     * @param Builder<Titulacion> $consulta
     * @return Builder<Titulacion>
     */
    public function scopeActivas(Builder $consulta): Builder
    {
        return $consulta->where('activo', true);
    }
}

<?php

namespace Modules\Usuarios\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Catálogo de tipos de relación profesional con la organización.
 *
 * Configurable desde el backoffice por adm_sistema.
 * Ejemplos: funcionario/a de carrera, personal interino/a,
 * contratado/a laboral, empresa externa, voluntario/a.
 *
 * El campo `es_externo` determina si el campo `organizacion`
 * en Profesional es relevante para el registro.
 *
 * @property int $id
 * @property string $nombre
 * @property bool $es_externo
 * @property bool $activo
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @see docs/modulo-usuarios-permisos.md sección 5.2
 */
class TipoRelacionProfesional extends Model
{
    /** @var string */
    protected $table = 'tipos_relacion_profesional';

    /** @var list<string> */
    protected $fillable = [
        'nombre',
        'es_externo',
        'activo',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'es_externo' => 'boolean',
        'activo' => 'boolean',
    ];

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Profesionales con este tipo de relación.
     *
     * @return HasMany<Profesional>
     */
    public function profesionales(): HasMany
    {
        return $this->hasMany(Profesional::class, 'tipo_relacion_id');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Solo tipos de relación activos.
     *
     * @param Builder<TipoRelacionProfesional> $consulta
     *
     * @return Builder<TipoRelacionProfesional>
     */
    public function scopeActivos(Builder $consulta): Builder
    {
        return $consulta->where('activo', true);
    }
}

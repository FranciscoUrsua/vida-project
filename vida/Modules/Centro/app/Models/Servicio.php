<?php

namespace Modules\Centro\Models;

use App\Models\UnidadOrganizativa;
use App\Traits\Versionable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Prestaciones\Models\Prestacion;
use Modules\Usuarios\Models\Profesional;

/**
 * Nodo de tramitación de prestaciones sociales.
 *
 * A diferencia del Centro, no tiene presencia física relevante para el
 * ciudadano: tramita prestaciones en su nombre. La dirección de referencia
 * se obtiene de su UO, que es obligatoria.
 *
 * No tiene plazas, espacios, actividades ni inscripciones de ciudadanos.
 *
 * cargo_nombre: nombre del cargo del responsable definido a nivel del servicio.
 * El profesional asume el cargo al ser nombrado y lo deja al cesar.
 *
 * @property int $id
 * @property string $nombre
 * @property string $nombre_corto
 * @property int $unidad_organizativa_id
 * @property string $cargo_nombre
 * @property string|null $descripcion
 * @property bool $activo
 * @property \Illuminate\Support\Carbon $fecha_alta
 * @property \Illuminate\Support\Carbon|null $fecha_baja
 * @property string|null $notas
 */
class Servicio extends Model
{
    use SoftDeletes;
    use Versionable;

    protected $table = 'servicios';

    protected $fillable = [
        'nombre',
        'nombre_corto',
        'unidad_organizativa_id',
        'cargo_nombre',
        'descripcion',
        'activo',
        'fecha_alta',
        'fecha_baja',
        'notas',
    ];

    protected $casts = [
        'activo'     => 'boolean',
        'fecha_alta' => 'date',
        'fecha_baja' => 'date',
    ];

    // -------------------------------------------------------------------------
    // Validación en modelo
    // -------------------------------------------------------------------------

    /**
     * Garantiza que la UO es obligatoria antes de persistir.
     *
     * @return void
     */
    protected static function booted(): void
    {
        static::saving(function (self $servicio) {
            if (empty($servicio->unidad_organizativa_id)) {
                throw new \InvalidArgumentException(
                    'Un servicio requiere una unidad organizativa (unidad_organizativa_id es obligatorio).'
                );
            }
        });
    }

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Unidad organizativa a la que pertenece el servicio.
     * La dirección de referencia se obtiene de esta UO.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\UnidadOrganizativa, self>
     */
    public function unidadOrganizativa(): BelongsTo
    {
        return $this->belongsTo(UnidadOrganizativa::class, 'unidad_organizativa_id');
    }

    /**
     * Historial de responsables del servicio.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\Modules\Centro\Models\ResponsableServicio, self>
     */
    public function responsables(): HasMany
    {
        return $this->hasMany(ResponsableServicio::class, 'servicio_id');
    }

    /**
     * Profesionales actualmente asignados al servicio (sin fecha de baja).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<\Modules\Usuarios\Models\Profesional, self>
     */
    public function profesionales(): BelongsToMany
    {
        return $this->belongsToMany(
            Profesional::class,
            'profesional_servicio',
            'servicio_id',
            'profesional_id'
        )->withPivot(['fecha_alta', 'fecha_baja'])
         ->whereNull('profesional_servicio.fecha_baja');
    }

    /**
     * Prestaciones que tramita este servicio.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<\Modules\Prestaciones\Models\Prestacion, self>
     */
    public function prestaciones(): BelongsToMany
    {
        return $this->belongsToMany(
            Prestacion::class,
            'servicio_prestacion',
            'servicio_id',
            'prestacion_id'
        );
    }

    /**
     * Solicitudes de tramitación dirigidas a este servicio.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\Modules\Centro\Models\SolicitudServicio, self>
     */
    public function solicitudes(): HasMany
    {
        return $this->hasMany(SolicitudServicio::class, 'servicio_id');
    }

    // -------------------------------------------------------------------------
    // Métodos de dominio
    // -------------------------------------------------------------------------

    /**
     * Devuelve el ResponsableServicio activo (fecha_fin null), o null si no hay ninguno.
     *
     * @return \Modules\Centro\Models\ResponsableServicio|null
     */
    public function responsableActivo(): ?ResponsableServicio
    {
        return $this->responsables()->activo()->first();
    }

    /**
     * Nombra un nuevo responsable cerrando el activo actual (si existe).
     *
     * El nombre del cargo se toma de $this->cargo_nombre.
     *
     * @param  \Modules\Usuarios\Models\Profesional  $profesional
     * @param  string|null  $notas
     * @return \Modules\Centro\Models\ResponsableServicio
     */
    public function nombrarResponsable(Profesional $profesional, ?string $notas = null): ResponsableServicio
    {
        $this->responsables()->whereNull('fecha_fin')->update(['fecha_fin' => today()]);

        return $this->responsables()->create([
            'profesional_id' => $profesional->id,
            'fecha_inicio'   => today(),
            'notas'          => $notas,
        ]);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Filtra servicios activos y sin fecha de baja.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true)->whereNull('fecha_baja');
    }
}

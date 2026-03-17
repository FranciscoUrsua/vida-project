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
use Modules\Organizacion\Models\Distrito;

/**
 * Centro de servicios sociales.
 *
 * Unidad operativa donde se prestan los servicios municipales.
 * Pertenece a una UnidadOrganizativa y se ubica en un Distrito.
 *
 * tipo_gestion: municipal_directo | municipal_concertado | privado_concertado | privado_puro
 *
 * @property int $id
 * @property string $nombre
 * @property string|null $nombre_corto
 * @property string $tipo_gestion
 * @property int|null $unidad_organizativa_id
 * @property string|null $direccion
 * @property string|null $codigo_postal
 * @property int|null $distrito_id
 * @property string|null $coordenadas
 * @property string|null $telefono
 * @property string|null $email
 * @property string|null $web
 * @property bool $inscripcion_libre
 * @property array|null $horario
 * @property string|null $notas
 * @property bool $activo
 * @property \Illuminate\Support\Carbon $fecha_alta
 * @property \Illuminate\Support\Carbon|null $fecha_baja
 */
class Centro extends Model
{
    use SoftDeletes;
    use Versionable;

    protected $table = 'centros';

    protected $fillable = [
        'nombre',
        'nombre_corto',
        'tipo_gestion',
        'unidad_organizativa_id',
        'direccion',
        'codigo_postal',
        'distrito_id',
        'coordenadas',
        'telefono',
        'email',
        'web',
        'inscripcion_libre',
        'horario',
        'notas',
        'activo',
        'fecha_alta',
        'fecha_baja',
    ];

    protected $casts = [
        'inscripcion_libre' => 'boolean',
        'activo'            => 'boolean',
        'fecha_alta'        => 'date',
        'fecha_baja'        => 'date',
        'horario'           => 'array',
    ];

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    public function unidadOrganizativa(): BelongsTo
    {
        return $this->belongsTo(UnidadOrganizativa::class, 'unidad_organizativa_id');
    }

    public function distrito(): BelongsTo
    {
        return $this->belongsTo(Distrito::class, 'distrito_id');
    }

    public function coleccionesPlazas(): HasMany
    {
        return $this->hasMany(ColeccionPlazas::class, 'centro_id');
    }

    public function actividades(): HasMany
    {
        return $this->hasMany(Actividad::class, 'centro_id');
    }

    public function directores(): HasMany
    {
        return $this->hasMany(DirectorCentro::class, 'centro_id');
    }

    public function contactos(): HasMany
    {
        return $this->hasMany(ContactoCentro::class, 'centro_id');
    }

    public function inscripciones(): HasMany
    {
        return $this->hasMany(InscripcionCentro::class, 'centro_id');
    }

    public function redes(): BelongsToMany
    {
        return $this->belongsToMany(Red::class, 'red_centro', 'centro_id', 'red_id');
    }

    public function segmentosPoblacion(): BelongsToMany
    {
        return $this->belongsToMany(SegmentoPoblacion::class, 'centro_segmento_poblacion', 'centro_id', 'segmento_poblacion_id');
    }

    public function prestaciones(): BelongsToMany
    {
        return $this->belongsToMany(Prestacion::class, 'centro_prestacion', 'centro_id', 'prestacion_id');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true)->whereNull('fecha_baja');
    }
}

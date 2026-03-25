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

    /**
     * Unidad organizativa a la que pertenece el centro.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\UnidadOrganizativa, self>
     */
    public function unidadOrganizativa(): BelongsTo
    {
        return $this->belongsTo(UnidadOrganizativa::class, 'unidad_organizativa_id');
    }

    /**
     * Distrito municipal donde se ubica el centro.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\Modules\Organizacion\Models\Distrito, self>
     */
    public function distrito(): BelongsTo
    {
        return $this->belongsTo(Distrito::class, 'distrito_id');
    }

    /**
     * Colecciones de plazas del centro.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\Modules\Centro\Models\ColeccionPlazas, self>
     */
    public function coleccionesPlazas(): HasMany
    {
        return $this->hasMany(ColeccionPlazas::class, 'centro_id');
    }

    /**
     * Actividades programadas en el centro.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\Modules\Centro\Models\Actividad, self>
     */
    public function actividades(): HasMany
    {
        return $this->hasMany(Actividad::class, 'centro_id');
    }

    /**
     * Historial de directores del centro.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\Modules\Centro\Models\DirectorCentro, self>
     */
    public function directores(): HasMany
    {
        return $this->hasMany(DirectorCentro::class, 'centro_id');
    }

    /**
     * Personas de contacto adicionales del centro.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\Modules\Centro\Models\ContactoCentro, self>
     */
    public function contactos(): HasMany
    {
        return $this->hasMany(ContactoCentro::class, 'centro_id');
    }

    /**
     * Inscripciones de ciudadanos al centro.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\Modules\Centro\Models\InscripcionCentro, self>
     */
    public function inscripciones(): HasMany
    {
        return $this->hasMany(InscripcionCentro::class, 'centro_id');
    }

    /**
     * Redes a las que pertenece el centro.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<\Modules\Centro\Models\Red, self>
     */
    public function redes(): BelongsToMany
    {
        return $this->belongsToMany(Red::class, 'red_centro', 'centro_id', 'red_id');
    }

    /**
     * Segmentos de población a los que atiende el centro.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<\Modules\Centro\Models\SegmentoPoblacion, self>
     */
    public function segmentosPoblacion(): BelongsToMany
    {
        return $this->belongsToMany(SegmentoPoblacion::class, 'centro_segmento_poblacion', 'centro_id', 'segmento_poblacion_id');
    }

    /**
     * Prestaciones vinculadas al centro.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<\Modules\Centro\Models\Prestacion, self>
     */
    public function prestaciones(): BelongsToMany
    {
        return $this->belongsToMany(Prestacion::class, 'centro_prestacion', 'centro_id', 'prestacion_id');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Filtra centros activos y sin fecha de baja.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true)->whereNull('fecha_baja');
    }
}

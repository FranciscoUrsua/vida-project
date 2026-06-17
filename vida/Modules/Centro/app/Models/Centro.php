<?php

namespace Modules\Centro\Models;

use App\Models\UnidadOrganizativa;
use App\Traits\TieneDireccion;
use App\Traits\Versionable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Organizacion\Models\Distrito;
use Modules\Prestaciones\Models\Prestacion;

/**
 * Centro de servicios sociales.
 *
 * Unidad operativa donde se prestan los servicios municipales.
 * Pertenece a una UnidadOrganizativa. El ámbito territorial se modela
 * a través de AmbitoTerritorial (tabla ambitos_territoriales).
 *
 * tipo_gestion: municipal_directo | municipal_concertado | privado_concertado | privado_puro
 *
 * @property int $id
 * @property string $nombre
 * @property string|null $nombre_corto
 * @property string $tipo_gestion
 * @property int|null $unidad_organizativa_id
 * @property string|null $direccion_texto
 * @property bool $direccion_normalizada
 * @property string|null $codigo_postal
 * @property string|null $telefono
 * @property string|null $email
 * @property string|null $web
 * @property bool $inscripcion_libre
 * @property array|null $horario
 * @property string|null $notas
 * @property bool $activo
 * @property Carbon $fecha_alta
 * @property Carbon|null $fecha_baja
 */
class Centro extends Model
{
    use SoftDeletes;
    use TieneDireccion;
    use Versionable;

    protected $table = 'centros';

    protected $fillable = [
        'nombre',
        'nombre_corto',
        'tipo_gestion',
        'unidad_organizativa_id',
        // Dirección canónica — campos del trait TieneDireccion
        'direccion_texto',
        'direccion_normalizada',
        'origen_direccion',
        'tipo_via',
        'nombre_via',
        'tipo_numeracion',
        'numero',
        'portal',
        'escalera',
        'piso',
        'puerta',
        'codigo_postal',
        'municipio',
        'coordenadas_lat',
        'coordenadas_lng',
        'geocoder_proveedor',
        // Contacto y configuración
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
        'activo' => 'boolean',
        'fecha_alta' => 'date',
        'fecha_baja' => 'date',
        'horario' => 'array',
    ];

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Unidad organizativa a la que pertenece el centro.
     *
     * @return BelongsTo<UnidadOrganizativa, self>
     */
    public function unidadOrganizativa(): BelongsTo
    {
        return $this->belongsTo(UnidadOrganizativa::class, 'unidad_organizativa_id');
    }

    /**
     * Ámbitos territoriales de atención del centro.
     *
     * @return HasMany<AmbitoTerritorial, self>
     */
    public function ambitosTeritoriales(): HasMany
    {
        return $this->hasMany(AmbitoTerritorial::class, 'centro_id');
    }

    /**
     * @deprecated distrito_id fue eliminado en v1.1. Usar ambitosTeritoriales() en su lugar.
     *
     * @return BelongsTo<Distrito, self>
     */
    public function distrito(): BelongsTo
    {
        return $this->belongsTo(Distrito::class, 'distrito_id');
    }

    /**
     * Colecciones de plazas del centro.
     *
     * @return HasMany<ColeccionPlazas, self>
     */
    public function coleccionesPlazas(): HasMany
    {
        return $this->hasMany(ColeccionPlazas::class, 'centro_id');
    }

    /**
     * Actividades programadas en el centro.
     *
     * @return HasMany<Actividad, self>
     */
    public function actividades(): HasMany
    {
        return $this->hasMany(Actividad::class, 'centro_id');
    }

    /**
     * Historial de directores del centro.
     *
     * @return HasMany<DirectorCentro, self>
     */
    public function directores(): HasMany
    {
        return $this->hasMany(DirectorCentro::class, 'centro_id');
    }

    /**
     * Personas de contacto adicionales del centro.
     *
     * @return HasMany<ContactoCentro, self>
     */
    public function contactos(): HasMany
    {
        return $this->hasMany(ContactoCentro::class, 'centro_id');
    }

    /**
     * Inscripciones de ciudadanos al centro.
     *
     * @return HasMany<InscripcionCentro, self>
     */
    public function inscripciones(): HasMany
    {
        return $this->hasMany(InscripcionCentro::class, 'centro_id');
    }

    /**
     * Redes a las que pertenece el centro.
     *
     * @return BelongsToMany<Red, self>
     */
    public function redes(): BelongsToMany
    {
        return $this->belongsToMany(Red::class, 'red_centro', 'centro_id', 'red_id');
    }

    /**
     * Segmentos de población a los que atiende el centro.
     *
     * @return BelongsToMany<SegmentoPoblacion, self>
     */
    public function segmentosPoblacion(): BelongsToMany
    {
        return $this->belongsToMany(SegmentoPoblacion::class, 'centro_segmento_poblacion', 'centro_id', 'segmento_poblacion_id');
    }

    /**
     * Prestaciones vinculadas al centro.
     *
     * @return BelongsToMany<Prestacion, self>
     */
    public function prestaciones(): BelongsToMany
    {
        return $this->belongsToMany(Prestacion::class, 'centro_prestacion', 'centro_id', 'prestacion_id');
    }

    // -------------------------------------------------------------------------
    // Métodos de negocio
    // -------------------------------------------------------------------------

    /**
     * Devuelve el DirectorCentro activo (fecha_fin null), o null si no lo hay.
     *
     * @return DirectorCentro|null
     */
    public function directorActivo(): ?DirectorCentro
    {
        return $this->directores()->activo()->first();
    }

    /**
     * Nombra un nuevo director cerrando el activo actual (si existe).
     *
     * $datos debe incluir los campos de DirectorCentro (profesional_id o nombre/telefono/email)
     * y opcionalmente fecha_inicio (por defecto hoy).
     *
     * @param array<string, mixed> $datos Datos del nuevo director.
     *
     * @return DirectorCentro
     */
    public function nombrarDirector(array $datos): DirectorCentro
    {
        $this->directores()->whereNull('fecha_fin')->update(['fecha_fin' => today()]);

        return $this->directores()->create(array_merge(
            ['fecha_inicio' => today()],
            $datos,
        ));
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Filtra centros activos y sin fecha de baja.
     *
     * @param Builder<static> $query
     *
     * @return Builder<static>
     */
    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true)->whereNull('fecha_baja');
    }
}

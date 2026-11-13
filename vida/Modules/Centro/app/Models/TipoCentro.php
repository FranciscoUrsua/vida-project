<?php

namespace Modules\Centro\Models;

use App\Models\UnidadOrganizativa;
use App\Traits\Versionable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;
use Modules\Centro\Enums\ModoAsignacionReferenciaCentro;
use Modules\Centro\Traits\TieneDenominaciones;
use Modules\Intervencion\Models\TipoFicha;
use Modules\Intervencion\Models\TipoPlan;
use Modules\Prestaciones\Models\Prestacion;

/**
 * Tipo de centro: la fuente de la configuración común a todos sus centros.
 *
 * Define la UO funcional de la que dependen sus centros (RN-03), su público,
 * su catálogo y sus reglas de asignación (RN-09). El centro solo personaliza
 * lo que el tipo permite; la configuración se lee siempre por
 * ConfiguracionEfectivaCentro (RN-05).
 *
 * Restricciones de dominio: el código es inmutable (RN-02) y un tipo con centros
 * activos no se da de baja (RN-11). Los tipos nunca se borran: la baja es lógica.
 *
 * @property int $id
 * @property string $codigo Clave interna inmutable.
 * @property string $nombre Caché de la denominación vigente.
 * @property string $siglas Caché de la denominación vigente.
 * @property int $unidad_organizativa_id UO funcional.
 * @property bool $inscripcion_libre
 * @property ModoAsignacionReferenciaCentro $modo_asignacion_referencia
 * @property int $ventana_reparto_meses
 * @property int $meses_inactividad_caso
 * @property bool $permite_restriccion_en_centro
 * @property string|null $protocolo
 * @property bool $activo
 * @property Carbon $fecha_alta
 * @property Carbon|null $fecha_baja
 * @property-read UnidadOrganizativa $unidadOrganizativa
 */
class TipoCentro extends Model
{
    use TieneDenominaciones;
    use Versionable;

    protected $table = 'tipos_centro';

    protected $fillable = [
        'codigo',
        'nombre',
        'siglas',
        'unidad_organizativa_id',
        'inscripcion_libre',
        'modo_asignacion_referencia',
        'ventana_reparto_meses',
        'meses_inactividad_caso',
        'permite_restriccion_en_centro',
        'protocolo',
        'activo',
        'fecha_alta',
        'fecha_baja',
    ];

    /** @var array<string, mixed> Valores por defecto en memoria, iguales a los de la columna */
    protected $attributes = [
        'inscripcion_libre' => false,
        'modo_asignacion_referencia' => 'sorteo',
        'ventana_reparto_meses' => 12,
        'meses_inactividad_caso' => 6,
        'permite_restriccion_en_centro' => false,
        'activo' => true,
    ];

    protected $casts = [
        'inscripcion_libre' => 'boolean',
        'modo_asignacion_referencia' => ModoAsignacionReferenciaCentro::class,
        'ventana_reparto_meses' => 'integer',
        'meses_inactividad_caso' => 'integer',
        'permite_restriccion_en_centro' => 'boolean',
        'activo' => 'boolean',
        'fecha_alta' => 'date',
        'fecha_baja' => 'date',
    ];

    /**
     * Protege el código (RN-02) y la baja con centros activos (RN-11).
     *
     *
     * @return void
     *
     * @throws LogicException
     */
    protected static function booted(): void
    {
        static::updating(function (self $tipo): void {
            $tipo->impedirCambioDeCodigo();
            $tipo->impedirBajaConCentrosActivos();
        });

        static::deleting(function (): void {
            throw new LogicException('Los tipos de centro no se borran: se dan de baja (RN-11).');
        });
    }

    /**
     * Columnas que guardan la denominación vigente.
     *
     * @return array{0: string, 1: string}
     */
    public function columnasCacheDenominacion(): array
    {
        return ['nombre', 'siglas'];
    }

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * UO funcional que coordina el servicio (RN-03).
     *
     * @return BelongsTo<UnidadOrganizativa, $this>
     */
    public function unidadOrganizativa(): BelongsTo
    {
        return $this->belongsTo(UnidadOrganizativa::class, 'unidad_organizativa_id');
    }

    /**
     * Centros del tipo.
     *
     * @return HasMany<Centro, $this>
     */
    public function centros(): HasMany
    {
        return $this->hasMany(Centro::class, 'tipo_centro_id');
    }

    /**
     * Público destinatario del tipo.
     *
     * @return BelongsToMany<SegmentoPoblacion, $this>
     */
    public function segmentos(): BelongsToMany
    {
        return $this->belongsToMany(SegmentoPoblacion::class, 'tipo_centro_segmento_poblacion', 'tipo_centro_id', 'segmento_poblacion_id');
    }

    /**
     * Tipos de plan de intervención que se pueden abrir en sus centros.
     *
     * @return BelongsToMany<TipoPlan, $this>
     */
    public function tiposPlan(): BelongsToMany
    {
        return $this->belongsToMany(TipoPlan::class, 'tipo_centro_tipo_plan', 'tipo_centro_id', 'tipo_plan_id');
    }

    /**
     * Fichas de valoración disponibles en sus centros; sin filas, todas.
     *
     * @return BelongsToMany<TipoFicha, $this>
     */
    public function tiposFicha(): BelongsToMany
    {
        return $this->belongsToMany(TipoFicha::class, 'tipo_centro_tipo_ficha', 'tipo_centro_id', 'tipo_ficha_id');
    }

    /**
     * Catálogo de prestaciones del tipo (`prestacion_tipo_centro`).
     *
     * @return BelongsToMany<Prestacion, $this>
     */
    public function prestaciones(): BelongsToMany
    {
        return $this->belongsToMany(Prestacion::class, 'prestacion_tipo_centro', 'tipo_centro_id', 'prestacion_id');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Tipos activos.
     *
     * @param Builder<self> $query
     *
     * @return Builder<self>
     */
    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    // -------------------------------------------------------------------------
    // Invariantes
    // -------------------------------------------------------------------------

    /**
     * El código identifica al tipo para siempre (RN-02).
     *
     *
     * @return void
     *
     * @throws LogicException
     */
    private function impedirCambioDeCodigo(): void
    {
        if ($this->isDirty('codigo')) {
            throw new LogicException('El código de un tipo de centro no se puede cambiar (RN-02).');
        }
    }

    /**
     * Un tipo con centros activos no se da de baja (RN-11).
     *
     *
     * @return void
     *
     * @throws LogicException
     */
    private function impedirBajaConCentrosActivos(): void
    {
        if ($this->isDirty('activo') && ! $this->activo && $this->centros()->where('activo', true)->exists()) {
            throw new LogicException('No se puede dar de baja un tipo de centro con centros activos (RN-11).');
        }
    }
}

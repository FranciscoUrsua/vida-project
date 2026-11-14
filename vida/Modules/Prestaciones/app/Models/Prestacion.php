<?php

namespace Modules\Prestaciones\Models;

use App\Traits\Versionable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Intervencion\Models\TipoPlan;
use Modules\Prestaciones\Database\Factories\PrestacionFactory;
use Modules\Prestaciones\Enums\ViaAcceso;

/**
 * Prestación del catálogo oficial de servicios sociales municipales.
 *
 * Fuente de verdad sobre qué prestaciones existen, sus condiciones y cómo
 * se accede a ellas. Es un módulo de catálogo de solo lectura para el resto
 * del sistema; el mantenimiento se realiza exclusivamente desde Filament.
 *
 * Los campos clasificatorios (objetivo_general, categoria_especifica, etc.)
 * son claves de catalogos_sistema, nunca FKs (principio 3.10).
 *
 * Los únicos enums son tipo_prestacion, nivel_garantia y via_acceso porque el
 * código toma decisiones de lógica de negocio basándose en ellos (principio 3.10).
 *
 * Derivación (fase 3, F3-5): una prestación con `via_acceso = derivacion` indica en
 * `tipo_plan_derivacion_id` el tipo de plan especializado que genera; es obligatorio
 * y debe ser de ámbito especializado (ver verificarDerivacion()).
 *
 * @property int $id
 * @property string $codigo
 * @property string $nombre
 * @property string $tipo_prestacion servicio|economica
 * @property string $nivel_garantia garantizada|condicionada
 * @property ViaAcceso|null $via_acceso
 * @property int|null $tipo_plan_derivacion_id
 * @property string|null $objetivo_general
 * @property string|null $categoria_especifica
 * @property string|null $nivel_atencion
 * @property string|null $competencia
 * @property string|null $forma_gestion
 * @property string|null $financiacion
 * @property string|null $ambito_territorial
 * @property array|null $poblacion_destinataria
 * @property array|null $modalidades
 * @property string|null $finalidad
 * @property string|null $descripcion
 * @property string|null $requisitos
 * @property string|null $procedimiento
 * @property string|null $compatibilidad
 * @property string|null $condiciones_acceso
 * @property string|null $obligaciones
 * @property string|null $estandares_calidad
 * @property string|null $normativa
 * @property string|null $aportacion_usuario
 * @property string|null $plazo_concesion
 * @property string|null $duracion_maxima
 * @property string|null $proveedor
 * @property string|null $financiacion_detalle
 * @property string|null $informacion_complementaria
 * @property bool $activa
 */
class Prestacion extends Model
{
    /** @use HasFactory<PrestacionFactory> */
    use HasFactory;

    use SoftDeletes;
    use Versionable;

    /**
     * Factoría del modelo.
     *
     * @return PrestacionFactory
     */
    protected static function newFactory(): PrestacionFactory
    {
        return PrestacionFactory::new();
    }

    protected $table = 'prestaciones';

    protected $fillable = [
        'codigo',
        'nombre',
        'tipo_prestacion',
        'nivel_garantia',
        'via_acceso',
        'tipo_plan_derivacion_id',
        'objetivo_general',
        'categoria_especifica',
        'nivel_atencion',
        'competencia',
        'forma_gestion',
        'financiacion',
        'ambito_territorial',
        'poblacion_destinataria',
        'modalidades',
        'finalidad',
        'descripcion',
        'requisitos',
        'procedimiento',
        'compatibilidad',
        'condiciones_acceso',
        'obligaciones',
        'estandares_calidad',
        'normativa',
        'aportacion_usuario',
        'plazo_concesion',
        'duracion_maxima',
        'proveedor',
        'financiacion_detalle',
        'informacion_complementaria',
        'activa',
    ];

    protected $casts = [
        'poblacion_destinataria' => 'array',
        'modalidades' => 'array',
        'activa' => 'boolean',
        'via_acceso' => ViaAcceso::class,
    ];

    /**
     * Registra la validación de las prestaciones de derivación.
     *
     * @return void
     */
    protected static function booted(): void
    {
        static::saving(fn (self $prestacion) => self::verificarDerivacion($prestacion));
    }

    /**
     * Una prestación de derivación exige un tipo de plan especializado; las demás no llevan ninguno.
     *
     * @param Prestacion $prestacion
     *
     * @return void
     *
     * @throws \DomainException
     */
    private static function verificarDerivacion(self $prestacion): void
    {
        if ($prestacion->via_acceso !== ViaAcceso::Derivacion) {
            $prestacion->tipo_plan_derivacion_id = null;

            return;
        }

        $ambito = $prestacion->tipo_plan_derivacion_id !== null
            ? TipoPlan::whereKey($prestacion->tipo_plan_derivacion_id)->value('ambito')
            : null;

        if ($ambito !== 'especializado') {
            throw new \DomainException('Una prestación de derivación necesita un tipo de plan de atención especializada.');
        }
    }

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Tipo de plan especializado que genera la derivación.
     *
     * @return BelongsTo<TipoPlan, $this>
     */
    public function tipoPlanDerivacion(): BelongsTo
    {
        return $this->belongsTo(TipoPlan::class, 'tipo_plan_derivacion_id');
    }

    /**
     * Tipos de centro vinculados a esta prestación.
     *
     * @return HasMany<PrestacionTipoCentro, self>
     */
    public function tiposCentro(): HasMany
    {
        return $this->hasMany(PrestacionTipoCentro::class, 'prestacion_id');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Solo prestaciones de derivación.
     *
     * @param Builder<static> $query
     *
     * @return Builder<static>
     */
    public function scopeDerivaciones(Builder $query): Builder
    {
        return $query->where('via_acceso', ViaAcceso::Derivacion->value);
    }

    /**
     * Filtra prestaciones activas.
     *
     * @param Builder<static> $query
     *
     * @return Builder<static>
     */
    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('activa', true);
    }

    /**
     * Filtra prestaciones de tipo servicio (no económicas).
     *
     * @param Builder<static> $query
     *
     * @return Builder<static>
     */
    public function scopeDeServicio(Builder $query): Builder
    {
        return $query->where('tipo_prestacion', 'servicio');
    }

    /**
     * Filtra prestaciones de tipo económico.
     *
     * @param Builder<static> $query
     *
     * @return Builder<static>
     */
    public function scopeEconomicas(Builder $query): Builder
    {
        return $query->where('tipo_prestacion', 'economica');
    }
}

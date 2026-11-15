<?php

namespace Modules\Prestaciones\Models;

use App\Traits\Versionable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Intervencion\Models\TipoFicha;
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
 * Acceso y tramitación (fase 3 bis, RN-01 a RN-04): toda prestación activa tiene vía de
 * acceso; las de tramitación tienen unidad tramitadora (una UO o «el centro de solicitud»),
 * formulario de solicitud opcional y documentos exigidos; el código en el gestor solo tiene
 * sentido en expediente externo (ver verificarAcceso()). Cada solicitud guarda una copia de
 * esta configuración al crearse.
 *
 * @property int $id
 * @property string $codigo
 * @property string $nombre
 * @property string $tipo_prestacion servicio|economica
 * @property string $nivel_garantia garantizada|condicionada
 * @property ViaAcceso|null $via_acceso
 * @property int|null $tipo_plan_derivacion_id
 * @property string|null $unidad_tramitadora_tipo 'uo' | 'centro_solicitud' (solo vías de tramitación)
 * @property int|null $unidad_tramitadora_id UO tramitadora si el tipo es 'uo'
 * @property int|null $formulario_solicitud_id TipoFicha de uso 'solicitud'
 * @property string|null $codigo_gestor Código en el gestor de expedientes (solo expediente externo)
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
        'unidad_tramitadora_tipo',
        'unidad_tramitadora_id',
        'formulario_solicitud_id',
        'codigo_gestor',
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
        static::saving(function (self $prestacion): void {
            self::verificarDerivacion($prestacion);
            self::verificarAcceso($prestacion);
        });
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
     * Coherencia de la vía de acceso (fase 3 bis, TF-SOL-01): unidad tramitadora solo y siempre
     * en las vías de tramitación; código del gestor solo en expediente externo. Lo que no
     * corresponde a la vía se rechaza, no se borra.
     *
     * @param Prestacion $prestacion
     *
     * @return void
     *
     * @throws \DomainException
     */
    private static function verificarAcceso(self $prestacion): void
    {
        $via = $prestacion->via_acceso;

        // La vía es obligatoria en las prestaciones activas, pero eso lo exige el formulario
        // del catálogo (PrestacionResource), como dicen las instrucciones; aquí, la coherencia.
        if ($via === null) {
            if ($prestacion->unidad_tramitadora_tipo !== null || ($prestacion->codigo_gestor ?? '') !== '') {
                throw new \DomainException('Sin vía de acceso no hay unidad tramitadora ni código del gestor.');
            }

            return;
        }

        if ($via->esTramitacion()) {
            if (! in_array($prestacion->unidad_tramitadora_tipo, ['uo', 'centro_solicitud'], true)) {
                throw new \DomainException('Una prestación con tramitación necesita unidad tramitadora.');
            }

            if ($prestacion->unidad_tramitadora_tipo === 'uo' && $prestacion->unidad_tramitadora_id === null) {
                throw new \DomainException('Indica la UO que tramita la prestación.');
            }
        } elseif ($prestacion->unidad_tramitadora_tipo !== null || $prestacion->unidad_tramitadora_id !== null) {
            throw new \DomainException('Solo las prestaciones con tramitación tienen unidad tramitadora.');
        }

        if ($prestacion->unidad_tramitadora_tipo === 'centro_solicitud') {
            $prestacion->unidad_tramitadora_id = null;
        }

        if ($prestacion->codigo_gestor !== null && $prestacion->codigo_gestor !== '' && $via !== ViaAcceso::ExpedienteExterno) {
            throw new \DomainException('El código del gestor solo se indica en las prestaciones de expediente externo.');
        }
    }

    /**
     * Documentos que exige la prestación, en su orden.
     *
     * @return HasMany<PrestacionDocumentoExigido, $this>
     */
    public function documentosExigidos(): HasMany
    {
        return $this->hasMany(PrestacionDocumentoExigido::class, 'prestacion_id')->orderBy('orden');
    }

    /**
     * Formulario de solicitud (TipoFicha de uso «solicitud»).
     *
     * @return BelongsTo<TipoFicha, $this>
     */
    public function formularioSolicitud(): BelongsTo
    {
        return $this->belongsTo(TipoFicha::class, 'formulario_solicitud_id');
    }

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

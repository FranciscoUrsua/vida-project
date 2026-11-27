<?php

namespace Modules\Intervencion\Models;

use App\Enums\AccionAuditEnum;
use App\Models\HistoriaSocial;
use App\Models\Scopes\AmbitoUoScope;
use App\Models\User;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Centro\Models\Centro;
use Modules\Ciudadania\Models\UnidadConvivencia;
use Modules\Intervencion\Database\Factories\PlanDeIntervencionFactory;
use Modules\Intervencion\Enums\EstadoPlan;
use Modules\Intervencion\Enums\MotivoCierre;
use Modules\Intervencion\Enums\TipoPlan;

/**
 * Plan de Intervención Social (PISO).
 *
 * Acuerdo formal entre el profesional y el ciudadano con objetivos,
 * prestaciones comprometidas y compromisos del ciudadano. Requiere firma
 * de ambas partes para activarse (ver estaFirmado()).
 *
 * El versionado es no destructivo: crearNuevaVersion() genera un nuevo
 * registro con version+1; el original pasa a estado en_revision.
 *
 * Un plan especializado requiere plan_asp_id salvo que su tipo de plan
 * admita entrada directa (ver verificarOrigenPlanEspecializado()).
 *
 * Tipo y plan principal (fase 3, F3-1): `tipo` se deriva del ámbito del tipo de
 * plan (ver derivarTipo()); un plan nuevo de una persona exige tipo de plan. Si
 * el plan es el principal (el PISO) lo decide su tipo de plan (esPrincipal()),
 * nunca `tipo`. Una historia no tiene dos planes vigentes del mismo tipo de plan
 * (índice único parcial `planes_intervencion_un_vigente_por_tipo`).
 *
 * Centro (F3-2): `centro_id` es el centro donde se abre el plan; lo exige el
 * servicio de creación (los planes antiguos pueden no tenerlo).
 *
 * @property int $id
 * @property int $historia_id
 * @property int|null $tipo_plan_id
 * @property int|null $centro_id
 * @property int|null $unidad_convivencia_id
 * @property TipoPlan $tipo
 * @property int|null $servicio_especializado_id
 * @property int $profesional_responsable_id
 * @property int|null $plan_asp_id
 * @property EstadoPlan $estado
 * @property string|null $diagnostico_social
 * @property string $periodicidad_seguimiento
 * @property Carbon $fecha_inicio
 * @property Carbon|null $fecha_firma
 * @property Carbon|null $fecha_cierre
 * @property MotivoCierre|null $motivo_cierre
 * @property string|null $objetivos
 * @property int $version
 */
class PlanDeIntervencion extends Model
{
    use Auditable;
    use HasFactory;
    use SoftDeletes;

    /**
     * Factoría del modelo.
     *
     * @return PlanDeIntervencionFactory
     */
    protected static function newFactory(): PlanDeIntervencionFactory
    {
        return PlanDeIntervencionFactory::new();
    }

    /**
     * Columna de FK a historias_sociales usada por AmbitoUoScope.
     * El ámbito de UO se resuelve vía Historia Social del plan.
     */
    public string $ambitoHistoriaColumn = 'historia_id';

    protected $table = 'planes_intervencion';

    protected $fillable = [
        'historia_id',
        'tipo_plan_id',
        'centro_id',
        'unidad_convivencia_id',
        'tipo',
        'servicio_especializado_id',
        'profesional_responsable_id',
        'plan_asp_id',
        'estado',
        'diagnostico_social',
        'periodicidad_seguimiento',
        'fecha_inicio',
        'fecha_firma',
        'fecha_cierre',
        'motivo_cierre',
        'objetivos',
        'version',
    ];

    protected $casts = [
        'tipo' => TipoPlan::class,
        'estado' => EstadoPlan::class,
        'motivo_cierre' => MotivoCierre::class,
        'fecha_inicio' => 'date',
        'fecha_firma' => 'date',
        'fecha_cierre' => 'date',
        'version' => 'integer',
    ];

    protected static function booted(): void
    {
        // Registrar el Global Scope de ámbito de UO para filtrado automático.
        static::addGlobalScope(new AmbitoUoScope);

        // Impide activar un plan sin firma cuando se ACTUALIZA el estado a activo.
        // No aplica al crear un plan nuevo directamente con estado=activo
        // (esa protección corresponde al flujo de UI o a la lógica de firma explícita).
        static::saving(function (PlanDeIntervencion $plan) {
            if ($plan->exists && $plan->isDirty('estado') && $plan->estado === EstadoPlan::Activo) {
                if (! $plan->estaFirmado()) {
                    throw new \DomainException(
                        'Un plan no puede activarse sin firma de ambas partes (ciudadano y profesional).'
                    );
                }
            }

            self::derivarTipo($plan);
            self::verificarOrigenPlanEspecializado($plan);
        });
    }

    /**
     * Fija `tipo` desde el ámbito del tipo de plan y rechaza un `tipo` incoherente (F3-1).
     *
     * Un plan nuevo de una persona exige tipo de plan; los planes de unidad de
     * convivencia quedan fuera (sin cambios en la fase 3). En actualizaciones solo se
     * comprueba si cambia `tipo` o `tipo_plan_id`, para no bloquear planes antiguos sin tipo.
     *
     * @param PlanDeIntervencion $plan Plan que se va a guardar.
     *
     * @return void
     *
     * @throws \DomainException
     */
    private static function derivarTipo(PlanDeIntervencion $plan): void
    {
        if ($plan->exists && ! $plan->isDirty(['tipo', 'tipo_plan_id'])) {
            return;
        }

        if ($plan->tipo_plan_id === null) {
            if (! $plan->exists && $plan->unidad_convivencia_id === null) {
                throw new \DomainException('Un plan nuevo necesita un tipo de plan.');
            }

            return;
        }

        $ambito = \Modules\Intervencion\Models\TipoPlan::whereKey($plan->tipo_plan_id)->value('ambito');
        $esperado = $ambito === 'asp' ? TipoPlan::GeneralAsp : TipoPlan::Especializado;

        // Sin tipo explícito se rellena; uno explícito distinto del ámbito es un error del llamante.
        // En una actualización, «explícito» es que el llamante cambie `tipo`.
        $explicito = $plan->exists ? $plan->isDirty('tipo') : ($plan->getAttributes()['tipo'] ?? null) !== null;

        if ($explicito) {
            if ($plan->tipo !== $esperado) {
                throw new \DomainException('El tipo del plan no coincide con el ámbito de su tipo de plan.');
            }

            return;
        }

        $plan->tipo = $esperado;
    }

    /**
     * Impide guardar un plan especializado sin plan ASP salvo que su tipo admita entrada directa.
     *
     * Regla de dominio: el plan especializado nace de una derivación desde un plan ASP
     * (plan_asp_id obligatorio). Excepción: los tipos de plan con admite_entrada_directa
     * (p. ej. el PIA del CIAM, puerta alternativa de entrada) pueden no tener plan ASP.
     * Si más adelante la persona recibe un PISO, el plan de entrada directa no se vincula
     * retroactivamente: ambos conviven como planes independientes de la misma Historia Social.
     *
     * En actualizaciones solo se comprueba si cambia alguno de los campos implicados, para
     * no bloquear la edición de planes antiguos por cambios ajenos a esta regla.
     *
     * @param PlanDeIntervencion $plan Plan que se va a guardar
     *
     * @throws \DomainException Si el plan especializado no tiene plan ASP y su tipo no admite entrada directa
     */
    private static function verificarOrigenPlanEspecializado(PlanDeIntervencion $plan): void
    {
        if ($plan->tipo !== TipoPlan::Especializado || $plan->plan_asp_id !== null) {
            return;
        }

        if ($plan->exists && ! $plan->isDirty(['tipo', 'plan_asp_id', 'tipo_plan_id'])) {
            return;
        }

        $admiteEntradaDirecta = $plan->tipo_plan_id !== null
            && (bool) \Modules\Intervencion\Models\TipoPlan::whereKey($plan->tipo_plan_id)
                ->value('admite_entrada_directa');

        if (! $admiteEntradaDirecta) {
            throw new \DomainException(
                'Un plan especializado debe nacer de una derivación desde un plan ASP, '.
                'salvo que su tipo de plan admita entrada directa.'
            );
        }
    }

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Devuelve el ciudadano asociado a la historia social del plan.
     */
    public function getCiudadanoId(): ?int
    {
        return $this->historia()->withoutGlobalScopes()->value('ciudadano_id');
    }

    /**
     * @return BelongsTo<HistoriaSocial, $this>
     */
    public function historia(): BelongsTo
    {
        return $this->belongsTo(HistoriaSocial::class, 'historia_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function profesionalResponsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'profesional_responsable_id');
    }

    /**
     * Plan general ASP al que está vinculado este plan especializado.
     *
     * @return BelongsTo<PlanDeIntervencion, $this>
     */
    public function planAsp(): BelongsTo
    {
        return $this->belongsTo(PlanDeIntervencion::class, 'plan_asp_id');
    }

    /**
     * Planes especializados vinculados a este plan general.
     *
     * @return HasMany<PlanDeIntervencion, $this>
     */
    public function planesEspecializados(): HasMany
    {
        return $this->hasMany(PlanDeIntervencion::class, 'plan_asp_id');
    }

    /**
     * @return HasMany<FirmaPlan, $this>
     */
    public function firmas(): HasMany
    {
        return $this->hasMany(FirmaPlan::class, 'plan_id');
    }

    /**
     * @return HasMany<RevisionPlan, $this>
     */
    public function revisiones(): HasMany
    {
        return $this->hasMany(RevisionPlan::class, 'plan_id');
    }

    /**
     * @return HasMany<SeguimientoPlan, $this>
     */
    public function seguimientos(): HasMany
    {
        return $this->hasMany(SeguimientoPlan::class, 'plan_id');
    }

    /**
     * @return HasMany<Apunte, $this>
     */
    public function apuntes(): HasMany
    {
        return $this->hasMany(Apunte::class, 'plan_id');
    }

    // -------------------------------------------------------------------------
    // Métodos de dominio
    // -------------------------------------------------------------------------

    /**
     * Comprueba si la versión actual del plan tiene ambas firmas registradas.
     *
     * Consulta firmas_plan filtrando por el plan_id actual Y la version actual
     * para garantizar que la firma de una versión anterior no valide la nueva.
     */
    public function estaFirmado(): bool
    {
        return FirmaPlan::where('plan_id', $this->id)
            ->where('version', $this->version)
            ->where('profesional_firmado', true)
            ->where('ciudadano_firmado', true)
            ->exists();
    }

    /**
     * Crea una nueva versión del plan a partir del estado actual.
     *
     * El plan original pasa a estado en_revision. Se crea un nuevo registro
     * con version+1 y estado borrador. Se registra la revisión en revisiones_plan.
     *
     * @param string $motivo Motivo de la revisión
     * @param int $profesionalId ID del profesional que inicia la revisión
     * @param int|null $seguimientoId Seguimiento de origen si aplica
     *
     * @return static El nuevo plan (nueva versión)
     *
     * @throws \DomainException Si el plan no está en estado activo
     */
    public function crearNuevaVersion(string $motivo, int $profesionalId, ?int $seguimientoId = null): static
    {
        return DB::transaction(function () use ($motivo, $profesionalId, $seguimientoId) {
            $versionAnterior = $this->version;

            // Archivar el plan actual como en_revision (sin pasar por el guard de firma)
            $this->timestamps = false;
            DB::table('planes_intervencion')
                ->where('id', $this->id)
                ->update(['estado' => EstadoPlan::EnRevision->value]);
            $this->timestamps = true;

            // Crear nuevo registro como siguiente versión
            $nuevoPlan = $this->replicate(['deleted_at']);
            $nuevoPlan->version = $versionAnterior + 1;
            $nuevoPlan->estado = EstadoPlan::Borrador;
            $nuevoPlan->fecha_firma = null;
            $nuevoPlan->fecha_cierre = null;
            $nuevoPlan->motivo_cierre = null;
            $nuevoPlan->saveQuietly();

            // Registrar la revisión
            RevisionPlan::create([
                'plan_id' => $this->id,
                'version_anterior' => $versionAnterior,
                'version_nueva' => $nuevoPlan->version,
                'profesional_id' => $profesionalId,
                'fecha' => now()->toDateString(),
                'motivo_revision' => $motivo,
                'seguimiento_id' => $seguimientoId,
            ]);

            return $nuevoPlan;
        });
    }

    // -------------------------------------------------------------------------
    // Relaciones — contenido del plan
    // -------------------------------------------------------------------------

    /**
     * Tipo de plan del catálogo al que pertenece.
     *
     * Nota: se usa FQCN para evitar colisión con el enum TipoPlan importado.
     *
     * @return BelongsTo<\Modules\Intervencion\Models\TipoPlan, $this>
     */
    public function tipoPlan(): BelongsTo
    {
        return $this->belongsTo(\Modules\Intervencion\Models\TipoPlan::class, 'tipo_plan_id');
    }

    /**
     * Centro donde se abrió el plan (F3-2).
     *
     * @return BelongsTo<Centro, $this>
     */
    public function centro(): BelongsTo
    {
        return $this->belongsTo(Centro::class, 'centro_id');
    }

    /**
     * Indica si es el plan principal (el PISO): lo decide su tipo de plan, nunca `tipo` (F3-1).
     *
     * @return bool
     */
    public function esPrincipal(): bool
    {
        return $this->tipo_plan_id !== null && (bool) $this->tipoPlan?->es_principal;
    }

    /**
     * Solo planes del tipo de plan principal (el PISO).
     *
     * @param Builder<self> $query
     *
     * @return Builder<self>
     */
    public function scopePrincipal(Builder $query): Builder
    {
        return $query->whereIn(
            $query->qualifyColumn('tipo_plan_id'),
            \Modules\Intervencion\Models\TipoPlan::query()->select('id')->where('es_principal', true),
        );
    }

    /**
     * Plan en curso al que se vinculan los apuntes que un profesional registra en la Historia
     * Social: el principal en curso; si no hay, el último en curso del que es responsable
     * (p. ej. el PIA en el CIAM). Con varios planes por persona (fase 3, F3-1).
     *
     * @param int $historiaId Historia Social.
     * @param int $usuarioId Profesional que registra el apunte.
     *
     * @return self|null
     */
    public static function enCursoParaApuntes(int $historiaId, int $usuarioId): ?self
    {
        $enCurso = fn () => self::withoutGlobalScopes()
            ->where('historia_id', $historiaId)
            ->whereIn('estado', ['borrador', 'activo', 'en_revision'])
            ->latest();

        return $enCurso()->principal()->first()
            ?? $enCurso()->where('profesional_responsable_id', $usuarioId)->first();
    }

    /**
     * Solo planes vigentes: borrador o activo (los que cuentan para «un plan por tipo»).
     *
     * @param Builder<self> $query
     *
     * @return Builder<self>
     */
    public function scopeVigentes(Builder $query): Builder
    {
        return $query->whereIn($query->qualifyColumn('estado'), [EstadoPlan::Borrador->value, EstadoPlan::Activo->value]);
    }

    /**
     * Unidad de convivencia vinculada al plan, si es un plan familiar.
     *
     * @return BelongsTo<UnidadConvivencia, $this>
     */
    public function unidadConvivencia(): BelongsTo
    {
        return $this->belongsTo(
            UnidadConvivencia::class,
            'unidad_convivencia_id'
        );
    }

    /**
     * Objetivos del plan ordenados.
     *
     * @return HasMany<PlanObjetivo, $this>
     */
    public function objetivos(): HasMany
    {
        return $this->hasMany(PlanObjetivo::class, 'plan_id')->orderBy('orden');
    }

    /**
     * Solo los objetivos generales del plan (nivel = 'general').
     *
     * @return HasMany<PlanObjetivo, $this>
     */
    public function objetivosGenerales(): HasMany
    {
        return $this->objetivos()->where('nivel', 'general');
    }

    /**
     * Solo los objetivos específicos del plan (nivel = 'especifico').
     * Son independientes de los generales; se vinculan a un área temática (tipo_ficha_id).
     *
     * @return HasMany<PlanObjetivo, $this>
     */
    public function objetivosEspecificos(): HasMany
    {
        return $this->objetivos()->where('nivel', 'especifico');
    }

    /**
     * Actuaciones del Ayuntamiento en el plan.
     *
     * @return HasMany<PlanActuacionAyuntamiento, $this>
     */
    public function actuacionesAyuntamiento(): HasMany
    {
        return $this->hasMany(PlanActuacionAyuntamiento::class, 'plan_id')->orderBy('orden');
    }

    /**
     * Compromisos del ciudadano en el plan.
     *
     * @return HasMany<PlanActuacionCiudadano, $this>
     */
    public function actuacionesCiudadano(): HasMany
    {
        return $this->hasMany(PlanActuacionCiudadano::class, 'plan_id')->orderBy('orden');
    }

    /**
     * Todos los profesionales participantes.
     *
     * @return HasMany<PlanParticipante, $this>
     */
    public function participantes(): HasMany
    {
        return $this->hasMany(PlanParticipante::class, 'plan_id');
    }

    /**
     * Solo los participantes activos (sin fecha_fin).
     *
     * @return HasMany<PlanParticipante, $this>
     */
    public function participantesActivos(): HasMany
    {
        return $this->participantes()->whereNull('fecha_fin');
    }

    /**
     * Fichas de valoración incluidas en el diagnóstico social del plan.
     *
     * @return HasMany<PlanFichaDiagnostico, $this>
     */
    public function fichasDiagnostico(): HasMany
    {
        return $this->hasMany(PlanFichaDiagnostico::class, 'plan_id')->orderBy('orden');
    }

    /**
     * Historial de cambios del plan, más reciente primero.
     *
     * @return HasMany<PlanCambio, $this>
     */
    public function cambios(): HasMany
    {
        return $this->hasMany(PlanCambio::class, 'plan_id')->orderByDesc('created_at');
    }

    // -------------------------------------------------------------------------
    // Métodos de dominio — historial de cambios
    // -------------------------------------------------------------------------

    /**
     * Registra un cambio en el historial con snapshot del estado previo.
     * Debe llamarse ANTES de aplicar los cambios.
     *
     * @param string $origen 'discrecional' | 'seguimiento'
     */
    public function registrarCambio(
        int $profesionalId,
        string $motivo,
        string $origen = 'discrecional',
        ?int $seguimientoId = null
    ): PlanCambio {
        $snapshot = [
            'diagnostico_social' => $this->diagnostico_social,
            'periodicidad_seguimiento' => $this->periodicidad_seguimiento,
            'objetivos' => $this->objetivos()->get()->toArray(),
            'actuaciones_ayuntamiento' => $this->actuacionesAyuntamiento()->with('prestacion')->get()->toArray(),
            'actuaciones_ciudadano' => $this->actuacionesCiudadano()->get()->toArray(),
            'participantes' => $this->participantesActivos()->with('profesional')->get()->toArray(),
        ];

        return $this->cambios()->create([
            'version' => $this->version,
            'profesional_id' => $profesionalId,
            'origen' => $origen,
            'seguimiento_id' => $seguimientoId,
            'motivo' => $motivo,
            'snapshot' => $snapshot,
            'created_at' => now(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Solo planes activos.
     *
     * @param Builder<PlanDeIntervencion> $query
     *
     * @return Builder<PlanDeIntervencion>
     */
    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('estado', EstadoPlan::Activo);
    }

    /**
     * Resumen de auditoría del plan (U3): «Creó el plan PIA (borrador)», «Activó el plan PIA»,
     * «Pasó el plan PIA a “Cerrado”» o «Modificó el plan PIA».
     *
     * @param AccionAuditEnum $accion
     * @param array<string, mixed> $antes
     * @param array<string, mixed> $despues
     *
     * @return string|null
     */
    public function resumenAuditoria(AccionAuditEnum $accion, array $antes, array $despues): ?string
    {
        $tipo = $this->tipoPlan()->value('nombre') ?? 'de intervención';
        $estado = $this->estado instanceof EstadoPlan ? $this->estado : EstadoPlan::tryFrom((string) $this->estado);

        return match (true) {
            $accion === AccionAuditEnum::Crear => "Creó el plan {$tipo}".($estado ? ' ('.mb_strtolower($estado->label()).')' : ''),
            $accion === AccionAuditEnum::Editar && array_key_exists('estado', $despues) && $estado === EstadoPlan::Activo => "Activó el plan {$tipo}",
            $accion === AccionAuditEnum::Editar && array_key_exists('estado', $despues) && $estado !== null => "Pasó el plan {$tipo} a «{$estado->label()}»",
            $accion === AccionAuditEnum::Editar => "Modificó el plan {$tipo}",
            $accion === AccionAuditEnum::Eliminar => "Eliminó el plan {$tipo}",
            default => null,
        };
    }
}

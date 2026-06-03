<?php

namespace Modules\Intervencion\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Intervencion\Database\Factories\SeguimientoPlanFactory;

/**
 * Registro de una sesión de seguimiento del Plan de Intervención.
 *
 * Se genera a partir de una entrevista de tipo seguimiento.
 * Si requiere_revision_plan = true, el flujo posterior debe invocar
 * PlanDeIntervencion::crearNuevaVersion() para iniciar el proceso de revisión.
 *
 * @property int $id
 * @property int $plan_id
 * @property int $entrevista_id
 * @property int $profesional_id
 * @property Carbon $fecha
 * @property string|null $avances
 * @property string|null $objetivos_cumplidos
 * @property string|null $incidencias
 * @property array|null $nuevas_prestaciones
 * @property bool $requiere_revision_plan
 * @property Carbon|null $fecha_siguiente_seguimiento
 */
class SeguimientoPlan extends Model
{
    use HasFactory;

    protected static function newFactory(): SeguimientoPlanFactory
    {
        return SeguimientoPlanFactory::new();
    }

    protected $table = 'seguimientos_plan';

    protected $fillable = [
        'plan_id',
        'entrevista_id',
        'profesional_id',
        'fecha',
        'avances',
        'objetivos_cumplidos',
        'incidencias',
        'nuevas_prestaciones',
        'requiere_revision_plan',
        'fecha_siguiente_seguimiento',
    ];

    protected $casts = [
        'fecha' => 'date',
        'nuevas_prestaciones' => 'array',
        'requiere_revision_plan' => 'boolean',
        'fecha_siguiente_seguimiento' => 'date',
    ];

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * @return BelongsTo<PlanDeIntervencion, SeguimientoPlan>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlanDeIntervencion::class, 'plan_id');
    }

    /**
     * @return BelongsTo<Entrevista, SeguimientoPlan>
     */
    public function entrevista(): BelongsTo
    {
        return $this->belongsTo(Entrevista::class, 'entrevista_id');
    }

    /**
     * @return BelongsTo<User, SeguimientoPlan>
     */
    public function profesional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'profesional_id');
    }

    // -------------------------------------------------------------------------
    // Métodos de dominio
    // -------------------------------------------------------------------------

    /**
     * Solicita al módulo de Agenda la creación de una cita para la siguiente sesión.
     *
     * Stub — la lógica real se implementará con la integración del módulo Agenda.
     * Ver docs/modulo-intervencion.md §6.2.
     */
    public function solicitarCitaSiguiente(): void
    {
        // Stub: en producción invocar al módulo Agenda mediante su interfaz interna.
        // El módulo de Intervención nunca escribe en tablas de Agenda directamente.
    }
}

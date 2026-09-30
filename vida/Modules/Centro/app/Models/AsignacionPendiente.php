<?php

namespace Modules\Centro\Models;

use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use App\Models\Scopes\AmbitoUoScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Centro\Enums\EstadoAsignacionPendiente;
use Modules\Centro\Enums\MotivoAsignacionPendiente;
use Modules\Centro\Enums\TipoAsignacionPendiente;

/**
 * Entrada de la bandeja de asignaciones del supervisor.
 *
 * Algo que el sistema no ha podido decidir solo (RN-09, RN-10): una persona
 * sin centro, una historia sin referencia o una propuesta de cambio de centro
 * por cambio de domicilio. No se borra: se resuelve o se descarta, con rastro.
 * Solo la escriben los servicios de asignación.
 *
 * @property int $id
 * @property int $ciudadano_id
 * @property int|null $historia_id
 * @property TipoAsignacionPendiente $tipo
 * @property string|null $tipo_centro
 * @property MotivoAsignacionPendiente|null $motivo
 * @property int|null $centro_id Bandeja en la que aparece; null = todos los centros del tipo.
 * @property int|null $centro_propuesto_id
 * @property list<int>|null $centros_candidatos
 * @property EstadoAsignacionPendiente $estado
 * @property int|null $resuelta_por_id
 * @property Carbon|null $resuelta_en
 * @property string|null $motivo_resolucion Cifrado.
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Ciudadano $ciudadano
 * @property-read HistoriaSocial|null $historia
 * @property-read Centro|null $centro
 * @property-read Centro|null $centroPropuesto
 *
 * @see docs/modulo-asignacion.md §7
 */
class AsignacionPendiente extends Model
{
    /** @var string */
    protected $table = 'asignaciones_pendientes';

    /** @var list<string> */
    protected $fillable = [
        'ciudadano_id',
        'historia_id',
        'tipo',
        'tipo_centro',
        'motivo',
        'centro_id',
        'centro_propuesto_id',
        'centros_candidatos',
        'estado',
        'resuelta_por_id',
        'resuelta_en',
        'motivo_resolucion',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'tipo' => TipoAsignacionPendiente::class,
        'motivo' => MotivoAsignacionPendiente::class,
        'estado' => EstadoAsignacionPendiente::class,
        'centros_candidatos' => 'array',
        'resuelta_en' => 'datetime',
        'motivo_resolucion' => 'encrypted',
    ];

    /**
     * Persona afectada.
     *
     * Sin AmbitoUoScope: quien está en la bandeja casi nunca tiene aún historia
     * en la UO del supervisor, y con el scope la relación saldría vacía. El
     * alcance de la bandeja lo pone scopeVisiblesPara(), por centro.
     *
     * @return BelongsTo<Ciudadano, $this>
     */
    public function ciudadano(): BelongsTo
    {
        return $this->belongsTo(Ciudadano::class, 'ciudadano_id')->withoutGlobalScope(AmbitoUoScope::class);
    }

    /**
     * Historia sin referencia (tipo sin_referencia).
     *
     * @return BelongsTo<HistoriaSocial, $this>
     */
    public function historia(): BelongsTo
    {
        return $this->belongsTo(HistoriaSocial::class, 'historia_id')->withoutGlobalScopes();
    }

    /**
     * Centro en cuya bandeja aparece.
     *
     * @return BelongsTo<Centro, $this>
     */
    public function centro(): BelongsTo
    {
        return $this->belongsTo(Centro::class, 'centro_id');
    }

    /**
     * Centro que corresponde por el nuevo domicilio (tipo cambio_domicilio).
     *
     * @return BelongsTo<Centro, $this>
     */
    public function centroPropuesto(): BelongsTo
    {
        return $this->belongsTo(Centro::class, 'centro_propuesto_id');
    }

    /**
     * Filtra las entradas abiertas.
     *
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeAbiertas(Builder $query): Builder
    {
        return $query->where('estado', EstadoAsignacionPendiente::Pendiente);
    }

    /**
     * Entradas que ve el supervisor de un centro: las de su bandeja, las
     * ambiguas en las que es candidato y las que no tienen bandeja y son de su
     * tipo de centro.
     *
     * @param Builder<self> $query
     * @param Centro $centro Centro del supervisor.
     * @return Builder<self>
     */
    public function scopeVisiblesPara(Builder $query, Centro $centro): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->where('centro_id', $centro->id)
            ->orWhereJsonContains('centros_candidatos', $centro->id)
            ->orWhere(fn (Builder $sin) => $sin
                ->whereNull('centro_id')
                ->where('tipo_centro', $centro->tipo_centro)));
    }
}

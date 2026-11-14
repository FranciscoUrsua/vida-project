<?php

namespace Modules\Intervencion\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Una valoración de un indicador de objetivo, en un seguimiento o al editar el plan (fase 3, F3-7).
 *
 * Inmutable (principio 4.2): corregir es valorar de nuevo. El modelo rechaza actualizar
 * y borrar, y un trigger de PostgreSQL hace lo mismo fuera de Eloquent. La escribe
 * PlanObjetivoIndicador::registrarValoracion().
 *
 * @property int $id
 * @property int $plan_objetivo_indicador_id
 * @property int|null $seguimiento_id
 * @property string $valoracion
 * @property string|null $comentario
 * @property int|null $profesional_id
 * @property Carbon $fecha
 * @property-read PlanObjetivoIndicador $indicador
 * @property-read User|null $profesional
 */
class PlanIndicadorValoracion extends Model
{
    /** @var string */
    protected $table = 'plan_indicador_valoraciones';

    /** @var list<string> */
    protected $fillable = ['plan_objetivo_indicador_id', 'seguimiento_id', 'valoracion', 'comentario', 'profesional_id', 'fecha'];

    /** @var array<string, string> */
    protected $casts = ['fecha' => 'date'];

    /**
     * Impide modificar o borrar el historial desde Eloquent.
     *
     * @return void
     */
    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('El historial de valoraciones es inmutable: para corregir, valora de nuevo.'));
        static::deleting(fn () => throw new LogicException('El historial de valoraciones es inmutable: no se borra.'));
    }

    /**
     * Indicador valorado.
     *
     * @return BelongsTo<PlanObjetivoIndicador, $this>
     */
    public function indicador(): BelongsTo
    {
        return $this->belongsTo(PlanObjetivoIndicador::class, 'plan_objetivo_indicador_id');
    }

    /**
     * Profesional que valoró.
     *
     * @return BelongsTo<User, $this>
     */
    public function profesional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'profesional_id');
    }
}

<?php

namespace Modules\Agenda\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Agenda\Enums\MotivoReasignacion;

/**
 * Historial de reasignación de una cita.
 *
 * Cuando una cita pasa de un profesional a otro (por no-show, baja sobrevenida
 * u otras causas), se crea un registro de reasignación. La reasignación siempre
 * la realiza un supervisor; el sistema asiste mostrando los slots disponibles.
 *
 * @property int $id
 * @property int $cita_id
 * @property int $slot_original_id
 * @property int $slot_nuevo_id
 * @property int $profesional_original_id
 * @property int $profesional_nuevo_id
 * @property MotivoReasignacion $motivo
 * @property string|null $notas
 * @property int $realizada_por_id
 */
class ReasignacionCita extends Model
{
    protected $table = 'reasignaciones_cita';

    protected $guarded = [];

    protected $casts = [
        'motivo' => MotivoReasignacion::class,
    ];

    public function cita(): BelongsTo
    {
        return $this->belongsTo(Cita::class);
    }

    public function slotOriginal(): BelongsTo
    {
        return $this->belongsTo(Slot::class, 'slot_original_id');
    }

    public function slotNuevo(): BelongsTo
    {
        return $this->belongsTo(Slot::class, 'slot_nuevo_id');
    }

    public function profesionalOriginal(): BelongsTo
    {
        return $this->belongsTo(User::class, 'profesional_original_id');
    }

    public function profesionalNuevo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'profesional_nuevo_id');
    }

    public function realizadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'realizada_por_id');
    }
}

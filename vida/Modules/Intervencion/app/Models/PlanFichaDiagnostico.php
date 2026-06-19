<?php

namespace Modules\Intervencion\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pivote entre un Plan de Intervención y las fichas de valoración
 * incluidas como evidencia en su diagnóstico social.
 *
 * @property int $id
 * @property int $plan_id
 * @property int $ficha_id
 * @property int $orden
 */
class PlanFichaDiagnostico extends Model
{
    protected $table = 'plan_fichas_diagnostico';

    protected $fillable = ['plan_id', 'ficha_id', 'orden'];

    /**
     * Ficha de valoración incluida en el diagnóstico.
     *
     * @return BelongsTo<Ficha, self>
     */
    public function ficha(): BelongsTo
    {
        return $this->belongsTo(Ficha::class, 'ficha_id');
    }

    /**
     * Plan al que pertenece esta asociación de ficha.
     *
     * @return BelongsTo<PlanDeIntervencion, self>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlanDeIntervencion::class, 'plan_id');
    }
}

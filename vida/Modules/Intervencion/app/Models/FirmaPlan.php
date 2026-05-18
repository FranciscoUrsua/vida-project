<?php

namespace Modules\Intervencion\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Firma de una versión concreta de un Plan de Intervención.
 *
 * Registra las firmas del ciudadano y del profesional responsable.
 * Cada revisión que requiere nueva firma genera un nuevo registro.
 * Un plan no puede activarse sin firma de ambas partes.
 *
 * @property int $id
 * @property int $plan_id
 * @property int $version
 * @property string|null $firma_ciudadano
 * @property string|null $firma_profesional
 * @property string|null $metodo_firma
 * @property \Illuminate\Support\Carbon|null $fecha_firma
 */
class FirmaPlan extends Model
{
    protected $table = 'firmas_plan';

    protected $fillable = [
        'plan_id',
        'version',
        'firma_ciudadano',
        'firma_profesional',
        'metodo_firma',
        'fecha_firma',
    ];

    protected $casts = [
        'version'     => 'integer',
        'fecha_firma' => 'date',
    ];

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * @return BelongsTo<PlanDeIntervencion, FirmaPlan>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlanDeIntervencion::class, 'plan_id');
    }
}

<?php

namespace Modules\Intervencion\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Firma de una versión concreta de un Plan de Intervención.
 *
 * Registra mediante booleanos si el profesional y el ciudadano han firmado
 * en papel. El plan no puede activarse sin ambos booleanos en true.
 * Cada revisión que requiere nueva firma genera un nuevo registro.
 *
 * @property int $id
 * @property int $plan_id
 * @property int $version
 * @property bool $profesional_firmado
 * @property Carbon|null $profesional_firmado_en
 * @property bool $ciudadano_firmado
 * @property Carbon|null $ciudadano_firmado_en
 * @property string|null $metodo_firma
 * @property Carbon|null $fecha_firma
 * @property int|null $documento_firmado_id
 * @property string|null $observaciones_seguimiento
 */
class FirmaPlan extends Model
{
    protected $table = 'firmas_plan';

    protected $fillable = [
        'plan_id',
        'version',
        'profesional_firmado',
        'profesional_firmado_en',
        'ciudadano_firmado',
        'ciudadano_firmado_en',
        'metodo_firma',
        'fecha_firma',
        'documento_firmado_id',
        'observaciones_seguimiento',
    ];

    protected $casts = [
        'version'               => 'integer',
        'profesional_firmado'   => 'boolean',
        'ciudadano_firmado'     => 'boolean',
        'profesional_firmado_en' => 'datetime',
        'ciudadano_firmado_en'  => 'datetime',
        'fecha_firma'           => 'date',
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

    // -------------------------------------------------------------------------
    // Métodos de dominio
    // -------------------------------------------------------------------------

    /**
     * La firma está completa cuando ambas partes han firmado en papel.
     *
     * @return bool
     */
    public function estaCompleta(): bool
    {
        return $this->profesional_firmado && $this->ciudadano_firmado;
    }
}

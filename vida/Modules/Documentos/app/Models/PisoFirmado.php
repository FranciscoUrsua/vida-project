<?php

namespace Modules\Documentos\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Documentos\Enums\MetodoConformidadCiudadano;

/**
 * Custodia del Plan de Intervención (PISO) con doble firma.
 *
 * En v1.0 la doble firma (profesional + ciudadano) se resuelve fuera del sistema
 * digital: impresión, firma manuscrita de ambas partes, custodia del escaneado.
 *
 * La FK a plan_de_intervencion_id no tiene constrained(): la tabla
 * planes_de_intervencion aún no existe. Se añadirá cuando el módulo
 * Intervención implemente esa tabla.
 *
 * @property int $id
 * @property int $plan_de_intervencion_id
 * @property int $documento_id
 * @property int $subido_por
 * @property MetodoConformidadCiudadano $metodo_conformidad_ciudadano
 * @property string|null $observaciones
 */
class PisoFirmado extends Model
{
    protected $table = 'piso_firmados';

    protected $guarded = [];

    protected $casts = [
        'metodo_conformidad_ciudadano' => MetodoConformidadCiudadano::class,
    ];

    /**
     * Plan de intervención asociado al PISO.
     *
     * @return BelongsTo<User, $this>
     */
    public function planDeIntervencion(): BelongsTo
    {
        // La relación apunta al modelo stub — se actualizará cuando el módulo
        // Intervención lo implemente con su modelo real.
        return $this->belongsTo(User::class, 'plan_de_intervencion_id');
    }

    /**
     * Documento custodiado del PISO.
     *
     * @return BelongsTo<Documento, $this>
     */
    public function documento(): BelongsTo
    {
        return $this->belongsTo(Documento::class, 'documento_id');
    }

    /**
     * Usuario que subió el documento.
     *
     * @return BelongsTo<User, $this>
     */
    public function subidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subido_por');
    }
}

<?php

namespace Modules\Documentos\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Documentos\Enums\MetodoConformidadCiudadano;
use Modules\Intervencion\Models\PlanDeIntervencion;

/**
 * Custodia del Plan de Intervención (PISO) con doble firma manuscrita escaneada.
 *
 * En v1.0 la doble firma (profesional + ciudadano) se resuelve fuera del sistema
 * digital: impresión, firma manuscrita de ambas partes y custodia del escaneado.
 *
 * Un plan tiene como máximo un PISO firmado activo (`sustituido_en` nulo, índice
 * único parcial). Subir uno nuevo no borra el anterior: el registro queda con
 * `sustituido_en` y su fichero, como versión anterior del mismo documento (F5-4).
 * Solo escribe en esta tabla `PisoFirmadoService`.
 *
 * @property int $id
 * @property int $plan_de_intervencion_id
 * @property int $documento_id
 * @property int $subido_por
 * @property MetodoConformidadCiudadano $metodo_conformidad_ciudadano
 * @property string|null $observaciones
 * @property Carbon|null $sustituido_en
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read PlanDeIntervencion $planDeIntervencion
 * @property-read Documento $documento
 * @property-read User $subidoPor
 */
class PisoFirmado extends Model
{
    protected $table = 'piso_firmados';

    protected $guarded = [];

    protected $casts = [
        'metodo_conformidad_ciudadano' => MetodoConformidadCiudadano::class,
        'sustituido_en' => 'datetime',
    ];

    /**
     * Plan de intervención al que pertenece el PISO firmado.
     *
     * @return BelongsTo<PlanDeIntervencion, $this>
     */
    public function planDeIntervencion(): BelongsTo
    {
        return $this->belongsTo(PlanDeIntervencion::class, 'plan_de_intervencion_id');
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
     * Usuario que subió el escaneo.
     *
     * @return BelongsTo<User, $this>
     */
    public function subidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subido_por');
    }

    /**
     * Filtra los PISO firmados activos (no sustituidos).
     *
     * @param Builder<self> $query Consulta base.
     *
     * @return Builder<self>
     */
    public function scopeActivos(Builder $query): Builder
    {
        return $query->whereNull('sustituido_en');
    }
}

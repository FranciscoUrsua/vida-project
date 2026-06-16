<?php

namespace Modules\Ciudadania\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Membresía de un ciudadano en una unidad de convivencia.
 *
 * Registra la pertenencia de un ciudadano a una UC con sus fechas de vigencia,
 * la fuente del dato (manual / padrón / importación) y la verificación de
 * residencia. Sin verificación, el ciudadano no puede ser perceptor de
 * prestaciones municipales.
 *
 * @property int $id
 * @property int $unidad_convivencia_id
 * @property int $ciudadano_id
 * @property \Illuminate\Support\Carbon $fecha_inicio
 * @property \Illuminate\Support\Carbon|null $fecha_fin
 * @property string $fuente manual|padron|importacion
 * @property bool $verificado
 * @property int|null $verificado_por FK a users
 * @property \Illuminate\Support\Carbon|null $verificado_en
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class UnidadConvivenciaMiembro extends Model
{
    protected $table = 'unidad_convivencia_miembros';

    protected $fillable = [
        'unidad_convivencia_id',
        'ciudadano_id',
        'fecha_inicio',
        'fecha_fin',
        'fuente',
        'verificado',
        'verificado_por',
        'verificado_en',
    ];

    protected $casts = [
        'fecha_inicio'  => 'date',
        'fecha_fin'     => 'date',
        'verificado'    => 'boolean',
        'verificado_en' => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * @return BelongsTo<UnidadConvivencia, self>
     */
    public function unidadConvivencia(): BelongsTo
    {
        return $this->belongsTo(UnidadConvivencia::class, 'unidad_convivencia_id');
    }

    /**
     * @return BelongsTo<\App\Models\Ciudadano, self>
     */
    public function ciudadano(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Ciudadano::class, 'ciudadano_id');
    }

    /**
     * Profesional que realizó la verificación manual.
     *
     * @return BelongsTo<User, self>
     */
    public function verificadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verificado_por');
    }

    // -------------------------------------------------------------------------
    // Métodos de negocio
    // -------------------------------------------------------------------------

    /**
     * Marca la membresía como verificada por el profesional dado.
     * Operación idempotente: no lanza excepción si ya estaba verificada.
     */
    public function verificar(User $profesional): void
    {
        $this->update([
            'verificado'     => true,
            'verificado_por' => $profesional->id,
            'verificado_en'  => now(),
        ]);
    }

    /**
     * Indica si esta membresía está actualmente activa (sin fecha de fin).
     */
    public function estaActiva(): bool
    {
        return $this->fecha_fin === null;
    }

    /**
     * Indica si este miembro puede ser perceptor de prestaciones municipales.
     * Requiere membresía activa Y verificación de residencia.
     */
    public function puedeSerPerceptorPrestaciones(): bool
    {
        return $this->estaActiva() && $this->verificado;
    }
}

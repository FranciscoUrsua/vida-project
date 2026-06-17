<?php

namespace Modules\Ciudadania\Models;

use App\Models\Ciudadano;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

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
 * @property Carbon $fecha_inicio
 * @property Carbon|null $fecha_fin
 * @property string $fuente manual|padron|importacion
 * @property bool $verificado
 * @property int|null $verificado_por FK a users
 * @property Carbon|null $verificado_en
 * @property Carbon $created_at
 * @property Carbon $updated_at
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
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'verificado' => 'boolean',
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
     * @return BelongsTo<Ciudadano, self>
     */
    public function ciudadano(): BelongsTo
    {
        return $this->belongsTo(Ciudadano::class, 'ciudadano_id');
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
     *
     * @param User $profesional Profesional que verifica la residencia.
     *
     * @return void
     */
    public function verificar(User $profesional): void
    {
        $this->update([
            'verificado' => true,
            'verificado_por' => $profesional->id,
            'verificado_en' => now(),
        ]);
    }

    /**
     * Indica si esta membresía está actualmente activa (sin fecha de fin).
     *
     * @return bool
     */
    public function estaActiva(): bool
    {
        return $this->fecha_fin === null;
    }

    /**
     * Indica si este miembro puede ser perceptor de prestaciones municipales.
     * Requiere membresía activa Y verificación de residencia.
     *
     * @return bool
     */
    public function puedeSerPerceptorPrestaciones(): bool
    {
        return $this->estaActiva() && $this->verificado;
    }
}

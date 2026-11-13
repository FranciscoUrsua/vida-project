<?php

namespace Modules\Intervencion\Models;

use App\Models\HistoriaSocial;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Centro\Models\Centro;
use Modules\Intervencion\Enums\OrigenAsignacionReferencia;

/**
 * Asignación de un profesional de referencia a una Historia Social.
 *
 * Registra qué profesional es el responsable de un caso durante cada período.
 * El registro vigente tiene fecha_fin null. Los registros cerrados mantienen
 * el historial de cambios de profesional responsable a lo largo del tiempo.
 * Historial aditivo: nunca se cambia el profesional de una fila; se cierra y
 * se crea otra. Solo escriben aquí AperturaHistoriaService y los servicios de
 * asignación (docs/modulo-asignacion.md).
 *
 * RN-23 (docs/modulo-tipo-centro.md): el caso es de una historia en un centro, no
 * del profesional. Ningún cambio de adscripción del profesional toca estas filas.
 *
 * @property int $id
 * @property int $historia_id
 * @property int $profesional_id
 * @property int $centro_id Centro del caso (RN-23); obligatorio desde el 13 nov 2026.
 * @property OrigenAsignacionReferencia $origen
 * @property bool $cuenta_en_reparto Solo sorteo y elección cuentan como entrada.
 * @property array{profesionales: list<array{usuario_id: int, peso: float, esperado?: float, recibido?: int, candidato?: bool}>, elegido: int|null}|null $sorteo
 * @property string|null $motivo Cifrado. Obligatorio en manual.
 * @property int|null $asignado_por_id
 * @property int|null $reparto_id
 * @property Carbon $fecha_inicio
 * @property Carbon|null $fecha_fin
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
class AsignacionProfesional extends Model
{
    use SoftDeletes;

    /** @var string Tabla de base de datos */
    protected $table = 'asignaciones_profesional';

    /** @var list<string> Campos asignables en masa */
    protected $fillable = [
        'historia_id',
        'profesional_id',
        'fecha_inicio',
        'fecha_fin',
        'centro_id',
        'origen',
        'cuenta_en_reparto',
        'sorteo',
        'motivo',
        'asignado_por_id',
        'reparto_id',
    ];

    /** @var array<string, string> Conversiones de tipo */
    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'origen' => OrigenAsignacionReferencia::class,
        'cuenta_en_reparto' => 'boolean',
        'sorteo' => 'array',
        'motivo' => 'encrypted',
    ];

    /** @var array<string, mixed> Valores por defecto en memoria, iguales a los de la columna */
    protected $attributes = [
        'origen' => 'quien_abre',
        'cuenta_en_reparto' => false,
    ];

    /**
     * Sin centro indicado, el caso es del centro de la UO de su historia (RN-23;
     * centro y UO 1:1, addenda D1). Si la UO no tiene centro, la BD rechaza la fila.
     *
     * @return void
     */
    protected static function booted(): void
    {
        static::creating(function (AsignacionProfesional $asignacion): void {
            if ($asignacion->centro_id === null) {
                $asignacion->centro_id = self::centroDeLaHistoria($asignacion->historia_id);
            }
        });
    }

    /**
     * Centro de la UO de una historia; el activo si la UO tuviera también uno de baja.
     *
     * @param int $historiaId
     *
     * @return int|null
     */
    private static function centroDeLaHistoria(int $historiaId): ?int
    {
        $uoId = HistoriaSocial::withoutGlobalScopes()->whereKey($historiaId)->value('unidad_organizativa_id');

        return $uoId === null ? null : Centro::withoutGlobalScopes()
            ->where('unidad_organizativa_id', $uoId)
            ->orderByRaw('deleted_at IS NULL DESC')
            ->orderByDesc('id')
            ->value('id');
    }

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Historia Social a la que pertenece esta asignación.
     *
     * @return BelongsTo<HistoriaSocial, $this>
     */
    public function historia(): BelongsTo
    {
        return $this->belongsTo(HistoriaSocial::class, 'historia_id');
    }

    /**
     * Profesional responsable durante el período de esta asignación. Incluye los
     * dados de baja: el historial de referencias no cambia aunque el usuario se borre.
     *
     * @return BelongsTo<User, $this>
     */
    public function profesional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'profesional_id')->withTrashed();
    }

    /**
     * Reparto por salida que originó la asignación (origen reparto).
     *
     * @return BelongsTo<RepartoCasos, $this>
     */
    public function reparto(): BelongsTo
    {
        return $this->belongsTo(RepartoCasos::class, 'reparto_id');
    }

    /**
     * Centro en el que se hizo la asignación.
     *
     * @return BelongsTo<Centro, $this>
     */
    public function centro(): BelongsTo
    {
        return $this->belongsTo(Centro::class, 'centro_id');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Filtra asignaciones actualmente vigentes (fecha_fin null).
     *
     * @param Builder<self> $query
     *
     * @return Builder<self>
     */
    public function scopeVigente(Builder $query): Builder
    {
        return $query->whereNull('fecha_fin');
    }
}

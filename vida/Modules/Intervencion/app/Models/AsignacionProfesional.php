<?php

namespace Modules\Intervencion\Models;

use App\Models\HistoriaSocial;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Asignación de un profesional de referencia a una Historia Social.
 *
 * Registra qué profesional es el responsable de un caso durante cada período.
 * El registro vigente tiene fecha_fin null. Los registros cerrados mantienen
 * el historial de cambios de profesional responsable a lo largo del tiempo.
 *
 * @property int $id
 * @property int $historia_id
 * @property int $profesional_id
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
    ];

    /** @var array<string, string> Conversiones de tipo */
    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
    ];

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Historia Social a la que pertenece esta asignación.
     *
     * @return BelongsTo<HistoriaSocial, AsignacionProfesional>
     */
    public function historia(): BelongsTo
    {
        return $this->belongsTo(HistoriaSocial::class, 'historia_id');
    }

    /**
     * Profesional responsable durante el período de esta asignación.
     *
     * @return BelongsTo<User, AsignacionProfesional>
     */
    public function profesional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'profesional_id');
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

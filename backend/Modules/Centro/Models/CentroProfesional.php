<?php

namespace Modules\Centro\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\Versionable; // Para trazabilidad de cambios en asignaciones
use Modules\Centro\Models\Centro;
use App\Models\Profesional; // Como existe en app/Models

class CentroProfesional extends Model
{
    use HasFactory, SoftDeletes, Versionable;

    protected $table = 'centro_profesional';

    protected $fillable = [
        'profesional_id',
        'centro_id',
        'fecha_alta',
        'fecha_baja',
    ];

    protected $casts = [
        'fecha_alta' => 'date',
        'fecha_baja' => 'date',
    ];

    /**
     * Relación con el profesional.
     */
    public function profesional(): BelongsTo
    {
        return $this->belongsTo(Profesional::class, 'profesional_id');
    }

    /**
     * Relación con el centro.
     */
    public function centro(): BelongsTo
    {
        return $this->belongsTo(Centro::class, 'centro_id');
    }

    /**
     * Scope para asignaciones activas (sin fecha_baja y no soft-deleted).
     */
    public function scopeActivas($query)
    {
        return $query->whereNull('fecha_baja')
                     ->whereNull('deleted_at');
    }

    /**
     * Scope para asignaciones en una fecha específica (histórico).
     */
    public function scopeEnFecha($query, $fecha)
    {
        return $query->where('fecha_alta', '<=', $fecha)
                     ->where(function ($q) use ($fecha) {
                         $q->whereNull('fecha_baja')
                           ->orWhere('fecha_baja', '>', $fecha);
                     })
                     ->whereNull('deleted_at');
    }

    /**
     * Accesor para verificar si es la asignación actual.
     */
    public function getEsActualAttribute(): bool
    {
        return is_null($this->fecha_baja) && is_null($this->deleted_at);
    }

    /**
     * Método para obtener profesionales actuales de un centro.
     */
    public static function profesionalesActualesDelCentro($centroId)
    {
        return static::activas()
                     ->where('centro_id', $centroId)
                     ->with('profesional')
                     ->get();
    }

    /**
     * Método para obtener historial de centros de un profesional en fecha.
     */
    public static function centrosDeProfesionalEnFecha($profesionalId, $fecha)
    {
        return static::enFecha($fecha)
                     ->where('profesional_id', $profesionalId)
                     ->with('centro')
                     ->get();
    }
}

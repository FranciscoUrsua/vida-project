<?php

namespace Modules\Centro\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Common\Traits\Versionable; // Para trazabilidad de cambios en asignaciones
use Modules\Centro\Models\Centro;
use Modules\Centro\Models\Profesional;

class Director extends Model
{
    use HasFactory, SoftDeletes, Versionable;

    protected $table = 'directores';

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
     * Relación con el profesional (empleado asignado como director).
     */
    public function profesional(): BelongsTo
    {
        return $this->belongsTo(Profesional::class, 'profesional_id');
    }

    /**
     * Relación con el centro (asignación histórica).
     */
    public function centro(): BelongsTo
    {
        return $this->belongsTo(Centro::class, 'centro_id');
    }

    /**
     * Scope para directores activos (sin fecha_baja y no soft-deleted).
     */
    public function scopeActivos($query)
    {
        return $query->whereNull('fecha_baja')
                     ->whereNull('deleted_at');
    }

    /**
     * Scope para directores en una fecha específica (e.g., histórico).
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
     * Accesor para verificar si es el director actual.
     */
    public function getEsActualAttribute(): bool
    {
        return is_null($this->fecha_baja) && is_null($this->deleted_at);
    }

    /**
     * Método para obtener el director actual de un centro (estático para conveniencia).
     */
    public static function directorActualDelCentro($centroId)
    {
        return static::activos()
                     ->where('centro_id', $centroId)
                     ->first();
    }
}

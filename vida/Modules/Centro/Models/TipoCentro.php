<?php

namespace Modules\Centro\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Centro\Models\Centro;

class TipoCentro extends Model
{
    use HasFactory;

    protected $table = 'tipos_centro';

    protected $fillable = [
        'nombre',
        'descripcion',
        'tiene_plazas',
        'numero_plazas',
        'criterio_asignacion_plazas',
        'prestaciones_default', // JSON array de IDs
        'publico_objetivo', // JSON array de targets
        'schema_campos_dinamicos', // JSON para forms dinámicos
    ];

    protected $casts = [
        'tiene_plazas' => 'boolean',
        'numero_plazas' => 'integer',
        'prestaciones_default' => 'array',
        'publico_objetivo' => 'array',
        'schema_campos_dinamicos' => 'array',
    ];

    /**
     * Relación: Un tipo tiene muchos centros.
     */
    public function centros(): HasMany
    {
        return $this->hasMany(Centro::class, 'tipo_centro_id');
    }

    /**
     * Relación many-to-many con prestaciones (asignadas por defecto a este tipo).
     * Asume tabla pivot 'tipo_centro_prestacion'; ajusta si es diferente.
     */
    public function prestaciones(): BelongsToMany
    {
        return $this->belongsToMany(
            \App\Models\Prestacion::class, // Asumiendo modelo global de Prestaciones
            'tipo_centro_prestacion',
            'tipo_centro_id',
            'prestacion_id'
        )->withTimestamps();
    }

    /**
     * Scope para tipos con plazas.
     */
    public function scopeConPlazas($query)
    {
        return $query->where('tiene_plazas', true);
    }

    /**
     * Scope para filtrar por público objetivo (e.g., 'mayores').
     */
    public function scopeParaPublico($query, $publico)
    {
        return $query->whereJsonContains('publico_objetivo', $publico);
    }

    /**
     * Accesor para obtener prestaciones efectivas (del JSON o relación pivot).
     * Prioriza pivot si existe; fallback a JSON para compatibilidad.
     */
    public function getPrestacionesEfectivasAttribute()
    {
        if ($this->relationLoaded('prestaciones')) {
            return $this->prestaciones;
        }
        $ids = $this->prestaciones_default ?? [];
        return \App\Models\Prestacion::whereIn('id', $ids)->get();
    }
}

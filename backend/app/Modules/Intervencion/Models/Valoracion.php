<?php

namespace App\Modules\Intervencion\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;
use App\Modules\Intervencion\Models\Historia;
use App\Models\Profesional; // Asume en app/Models
use App\Modules\Intervencion\Models\Ficha; // Preparado para hasMany
//use App\Modules\Common\Traits\HasDocuments; // Para docs (e.g., PDF de valoración)
//use App\Modules\Common\Traits\Encryptable; // Para encriptar resumen si sensible

class Valoracion extends Model implements Auditable
{
    use HasFactory, HasDocuments, Encryptable \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'historia_id',
        'profesional_id',
        'tipo',
        'fecha_realizacion',
        'resumen',
        'resumen_ia',
    ];

    protected $casts = [
        'fecha_realizacion' => 'date',
        'resumen_ia' => 'array',
    ];

    /**
     * Relaciones clave (compuesta por Fichas, ligada a Historia/Profesional).
     */
    public function historia(): BelongsTo
    {
        return $this->belongsTo(Historia::class);
    }

    public function profesional(): BelongsTo
    {
        return $this->belongsTo(Profesional::class);
    }

    public function fichas(): HasMany
    {
        return $this->hasMany(Ficha::class);
    }

    /**
     * Scope para valoraciones iniciales (e.g., para dashboards de aperturas recientes).
     */
    public function scopeIniciales($query)
    {
        return $query->where('tipo', 'inicial');
    }

    public function scopePorHistoria($query, int $historiaId)
    {
        return $query->where('historia_id', $historiaId)->latest('fecha_realizacion');
    }

    /**
     * Accesor para label legible (alineado con Guía pág. 22: prediagnóstico inicial).
     */
    public function getTipoLabelAttribute(): string
    {
        return match ($this->tipo) {
            'inicial' => 'Inicial (Diagnóstico Base)',
            'sucesiva' => 'Sucesiva (Revisión Evolutiva)',
            default => 'Desconocida',
        };
    }
}

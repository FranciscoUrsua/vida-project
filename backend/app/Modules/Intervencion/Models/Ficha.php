<?php

namespace App\Modules\Intervencion\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Modules\Intervencion\Models\Valoracion;
use App\Modules\Intervencion\Models\TipoFicha;
use App\Modules\Common\Traits\HasDocuments; // Para docs futuros en fichas
use App\Modules\Common\Traits\Encryptable; // Encriptar datos JSON sensibles

class Ficha extends Model
{
    use HasFactory, SoftDeletes, HasDocuments, Encryptable;

    protected $fillable = [
        'valoracion_id',
        'tipo_ficha_id',
        'datos',
        'notas',
    ];

    protected $casts = [
        'datos' => 'array',
    ];

    /**
     * Relaciones: pertenece a Valoracion y TipoFicha (hereda Profesional via Valoracion).
     */
    public function valoracion(): BelongsTo
    {
        return $this->belongsTo(Valoracion::class);
    }

    public function tipoFicha(): BelongsTo
    {
        return $this->belongsTo(TipoFicha::class);
    }

    /**
     * Scope para fichas completas (e.g., no borradores).
     */
    public function scopeCompletas($query)
    {
        return $query->whereNotNull('datos');
    }

    public function scopePorTipo($query, string $nombreTipo)
    {
        return $query->whereHas('tipoFicha', function ($q) use ($nombreTipo) {
            $q->where('nombre', $nombreTipo);
        });
    }
}

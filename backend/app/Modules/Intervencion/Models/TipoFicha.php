<?php

namespace App\Modules\Intervencion\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Modules\Intervencion\Models\Ficha;

class TipoFicha extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'descripcion',
        'schema',
    ];

    protected $casts = [
        'schema' => 'array',
    ];

    /**
     * Relaciones: tiene muchas Fichas.
     */
    public function fichas(): HasMany
    {
        return $this->hasMany(Ficha::class);
    }

    /**
     * Scope para tipos por nombre (e.g., para seeds: 'familia').
     */
    public function scopePorNombre($query, string $nombre)
    {
        return $query->where('nombre', $nombre)->firstOrFail();
    }
}

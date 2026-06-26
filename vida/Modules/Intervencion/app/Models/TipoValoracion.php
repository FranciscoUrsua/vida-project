<?php

namespace Modules\Intervencion\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Intervencion\Database\Factories\TipoValoracionFactory;

/**
 * Tipo de valoración configurable desde el backoffice.
 *
 * Define qué fichas componen una valoración, en qué orden y cuáles son obligatorias.
 * Un tipo de valoración agrupa fichas para un contexto específico (ASP, especializada_mayores, etc.).
 *
 * @property int $id
 * @property string $nombre
 * @property string $contexto
 * @property string|null $descripcion
 * @property bool $activo
 */
class TipoValoracion extends Model
{
    use HasFactory;

    protected static function newFactory(): TipoValoracionFactory
    {
        return TipoValoracionFactory::new();
    }

    protected $table = 'tipo_valoraciones';

    protected $fillable = [
        'nombre',
        'contexto',
        'descripcion',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Fichas asociadas a este tipo de valoración, con metadata de orden y obligatoriedad.
     *
     * @return HasMany<TipoValoracionFicha, $this>
     */
    public function tipoValoracionFichas(): HasMany
    {
        return $this->hasMany(TipoValoracionFicha::class, 'tipo_valoracion_id');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Solo tipos de valoración activos.
     *
     * @param Builder<TipoValoracion> $query
     *
     * @return Builder<TipoValoracion>
     */
    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }
}

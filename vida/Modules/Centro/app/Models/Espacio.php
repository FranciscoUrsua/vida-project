<?php

namespace Modules\Centro\Models;

use App\Traits\Versionable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Espacio físico (habitación, sala, módulo) dentro de una colección de plazas.
 *
 * @property int $id
 * @property int $coleccion_plazas_id
 * @property int $tipo_espacio_id
 * @property string $nombre
 * @property int $capacidad
 * @property string|null $planta
 * @property bool $accesible
 * @property string|null $genero  mixto | mujeres | hombres | null
 * @property bool $activo
 * @property string|null $notas
 */
class Espacio extends Model
{
    use SoftDeletes;
    use Versionable;

    protected $table = 'espacios';

    protected $fillable = [
        'coleccion_plazas_id',
        'tipo_espacio_id',
        'nombre',
        'capacidad',
        'planta',
        'accesible',
        'genero',
        'activo',
        'notas',
    ];

    protected $casts = [
        'capacidad' => 'integer',
        'accesible' => 'boolean',
        'activo'    => 'boolean',
    ];

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    public function coleccionPlazas(): BelongsTo
    {
        return $this->belongsTo(ColeccionPlazas::class, 'coleccion_plazas_id');
    }

    public function tipoEspacio(): BelongsTo
    {
        return $this->belongsTo(TipoEspacio::class, 'tipo_espacio_id');
    }

    public function plazas(): HasMany
    {
        return $this->hasMany(Plaza::class, 'espacio_id');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeAccesibles(Builder $query): Builder
    {
        return $query->where('accesible', true);
    }

    public function scopePorGenero(Builder $query, string $genero): Builder
    {
        return $query->where(function (Builder $q) use ($genero) {
            $q->where('genero', $genero)->orWhere('genero', 'mixto')->orWhereNull('genero');
        });
    }
}

<?php

namespace Modules\Organizacion\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Barrio municipal: unidad territorial intermedia entre distrito y sección censal.
 *
 * Catálogo cargado de los datos oficiales; no se edita a mano salvo para
 * activar o desactivar. Los ámbitos territoriales de los centros pueden
 * referenciarlo para repartir un distrito entre varios centros.
 *
 * @property int $id
 * @property int $distrito_id
 * @property string $codigo Código municipal completo (distrito + barrio), ej: 214.
 * @property string $codigo_en_distrito Número dentro del distrito, ej: 4.
 * @property string $nombre
 * @property bool $activo
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Distrito $distrito
 * @property-read \Illuminate\Database\Eloquent\Collection<int, SeccionCensal> $secciones
 *
 * @see docs/modulo-asignacion.md §2
 */
class Barrio extends Model
{
    /** @var string */
    protected $table = 'barrios';

    /** @var list<string> */
    protected $fillable = [
        'distrito_id',
        'codigo',
        'codigo_en_distrito',
        'nombre',
        'activo',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'activo' => 'boolean',
    ];

    /**
     * Distrito al que pertenece el barrio.
     *
     * @return BelongsTo<Distrito, $this>
     */
    public function distrito(): BelongsTo
    {
        return $this->belongsTo(Distrito::class, 'distrito_id');
    }

    /**
     * Secciones censales del barrio.
     *
     * @return HasMany<SeccionCensal, $this>
     */
    public function secciones(): HasMany
    {
        return $this->hasMany(SeccionCensal::class, 'barrio_id');
    }

    /**
     * Filtra únicamente los barrios activos.
     *
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }
}

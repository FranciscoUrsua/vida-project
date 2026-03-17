<?php

namespace Modules\Centro\Models;

use App\Traits\Versionable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Colección de plazas de un centro.
 *
 * Agrupa plazas del mismo tipo y modo de acceso.
 * Una colección puede tener varios espacios, cada uno con varias plazas.
 *
 * tipo_plaza:  pernocta | dia
 * modo_acceso: libre | prescripcion_directa | prescripcion_lista_espera
 *
 * @property int $id
 * @property int $centro_id
 * @property string $nombre
 * @property string $tipo_plaza
 * @property string $modo_acceso
 * @property int $capacidad
 * @property bool $activa
 * @property string|null $notas
 */
class ColeccionPlazas extends Model
{
    use SoftDeletes;
    use Versionable;

    protected $table = 'colecciones_plazas';

    protected $fillable = [
        'centro_id',
        'nombre',
        'tipo_plaza',
        'modo_acceso',
        'capacidad',
        'activa',
        'fecha_alta',
        'fecha_baja',
        'notas',
    ];

    protected $casts = [
        'capacidad'  => 'integer',
        'activa'     => 'boolean',
        'fecha_alta' => 'date',
        'fecha_baja' => 'date',
    ];

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    public function centro(): BelongsTo
    {
        return $this->belongsTo(Centro::class, 'centro_id');
    }

    public function espacios(): HasMany
    {
        return $this->hasMany(Espacio::class, 'coleccion_plazas_id');
    }

    public function plazas(): HasManyThrough
    {
        return $this->hasManyThrough(Plaza::class, Espacio::class, 'coleccion_plazas_id', 'espacio_id');
    }

    public function prescripciones(): HasMany
    {
        return $this->hasMany(Prescripcion::class, 'destino_id')
            ->where('tipo_destino', 'coleccion_plazas');
    }

    public function listaEspera(): HasOne
    {
        return $this->hasOne(ListaEspera::class, 'coleccion_plazas_id');
    }

    // -------------------------------------------------------------------------
    // Accesores
    // -------------------------------------------------------------------------

    /**
     * Número de plazas con estado 'libre' en esta colección.
     */
    public function getPlazasDisponiblesAttribute(): int
    {
        return $this->plazas()->where('estado', 'libre')->count();
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('activa', true);
    }
}

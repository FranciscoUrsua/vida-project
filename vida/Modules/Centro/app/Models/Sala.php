<?php

namespace Modules\Centro\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Centro\Database\Factories\SalaFactory;

/**
 * Espacio funcional de un centro (aula, sala de reuniones, despacho...).
 *
 * Entidad distinta de Espacio, que pertenece a la jerarquía de alojamiento
 * (ColeccionPlazas → Espacio → Plaza). Las salas no tienen plazas asignables;
 * se referencian desde SesionActividad como dato informativo de ubicación.
 * La disponibilidad no se gestiona aquí; corresponde al módulo de Agenda.
 *
 * @property int $id
 * @property int $centro_id
 * @property string $nombre
 * @property string|null $descripcion
 * @property int|null $capacidad  Personas, no plazas de alojamiento
 * @property bool $accesible
 * @property bool $activa
 * @property string|null $notas
 */
class Sala extends Model
{
    /** @use HasFactory<SalaFactory> */
    use HasFactory;

    protected $fillable = [
        'centro_id',
        'nombre',
        'descripcion',
        'capacidad',
        'accesible',
        'activa',
        'notas',
    ];

    protected $casts = [
        'accesible' => 'boolean',
        'activa'    => 'boolean',
        'capacidad' => 'integer',
    ];

    /**
     * Centro al que pertenece esta sala.
     *
     * @return BelongsTo<Centro, $this>
     */
    public function centro(): BelongsTo
    {
        return $this->belongsTo(Centro::class);
    }

    /**
     * Sesiones de actividad que se celebran en esta sala.
     *
     * @return HasMany<SesionActividad, $this>
     */
    public function sesiones(): HasMany
    {
        return $this->hasMany(SesionActividad::class);
    }

    /**
     * Filtra solo las salas activas.
     *
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('activa', true);
    }
}

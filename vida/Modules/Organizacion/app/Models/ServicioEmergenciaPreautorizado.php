<?php

namespace Modules\Organizacion\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Modelo de Servicio de Emergencia Preautorizado.
 *
 * Los servicios listados aquí tienen acceso preautorizado en modo
 * consulta a Historias de ciudadanos especialmente protegidos,
 * sin necesidad de aprobación previa (principio 3.6, excepción urgencia).
 * El acceso queda registrado en auditoría.
 * Por defecto: SAMUR Social.
 *
 * @property int $id
 * @property string $nombre
 * @property string|null $slug  Identificador estable único, ej: 'samur-social'
 * @property string|null $descripcion
 * @property bool $activo
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @see docs/modulo-usuarios-permisos.md sección 3
 */
class ServicioEmergenciaPreautorizado extends Model
{
    /** @var string */
    protected $table = 'servicios_emergencia_preautorizados';

    /** @var list<string> */
    protected $fillable = [
        'nombre',
        'slug',
        'descripcion',
        'activo',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'activo' => 'boolean',
    ];

    /**
     * Filtra únicamente los servicios activos.
     *
     * @param Builder<ServicioEmergenciaPreautorizado> $consulta
     *
     * @return Builder<ServicioEmergenciaPreautorizado>
     */
    public function scopeActivos(Builder $consulta): Builder
    {
        return $consulta->where('activo', true);
    }
}

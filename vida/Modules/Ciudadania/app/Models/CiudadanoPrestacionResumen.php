<?php

namespace Modules\Ciudadania\Models;

use App\Models\Ciudadano;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Registro de prestación o actividad del ciudadano sin historia social.
 *
 * Tabla de agregación alimentada por observers de cada módulo origen.
 * La FichaCiudadanoPage nunca consulta las tablas de módulos origen directamente.
 *
 * @property int $id
 * @property int $ciudadano_id
 * @property string $modulo_origen centros | teleasistencia | prestaciones...
 * @property int $origen_id id en la tabla del módulo origen
 * @property string $tipo clave de catálogo
 * @property string $descripcion nombre legible, desnormalizado
 * @property string $estado activo | en_tramite | finalizado | denegado | baja
 * @property Carbon $fecha_inicio
 * @property Carbon|null $fecha_fin
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class CiudadanoPrestacionResumen extends Model
{
    /** @var string */
    protected $table = 'ciudadano_prestaciones_resumen';

    /** @var list<string> */
    protected $fillable = [
        'ciudadano_id',
        'modulo_origen',
        'origen_id',
        'tipo',
        'descripcion',
        'estado',
        'fecha_inicio',
        'fecha_fin',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin'    => 'date',
    ];

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Filtra prestaciones activas o en trámite.
     *
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeActivas(Builder $query): Builder
    {
        return $query->whereIn('estado', ['activo', 'en_tramite']);
    }

    /**
     * Ordena por estado (activos primero) y fecha descendente, limitando a $limit registros.
     *
     * @param Builder<self> $query
     * @param int $limit
     * @return Builder<self>
     */
    public function scopeRecientes(Builder $query, int $limit = 4): Builder
    {
        return $query
            ->orderByRaw("CASE estado WHEN 'activo' THEN 0 WHEN 'en_tramite' THEN 1 WHEN 'finalizado' THEN 2 ELSE 3 END")
            ->orderByDesc('fecha_inicio')
            ->limit($limit);
    }

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * @return BelongsTo<Ciudadano, self>
     */
    public function ciudadano(): BelongsTo
    {
        return $this->belongsTo(Ciudadano::class);
    }
}

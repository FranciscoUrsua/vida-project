<?php

namespace Modules\Intervencion\Models;

use App\Models\Ciudadano;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Intervencion\Database\Factories\SiaContactoFactory;
use Modules\Intervencion\Enums\ClasificacionSia;
use Modules\Intervencion\Enums\UrgenciaSia;

/**
 * Registro de contacto del Servicio de Información y Asesoramiento (SIA).
 *
 * El SIA es opcional: si el municipio no lo tiene implementado, las funciones
 * de alta, clasificación y determinación de urgencia recaen en el TSR.
 * Ver docs/modulo-intervencion.md §2.
 *
 * @property int $id
 * @property int $ciudadano_id
 * @property int $auxiliar_id
 * @property Carbon $fecha_hora
 * @property string $canal
 * @property string $descripcion_demanda
 * @property ClasificacionSia $clasificacion
 * @property string|null $informacion_prestada
 * @property UrgenciaSia|null $urgencia
 * @property int|null $cita_id
 * @property array|null $prestaciones_identificadas
 */
class SiaContacto extends Model
{
    use HasFactory;

    protected static function newFactory(): SiaContactoFactory
    {
        return SiaContactoFactory::new();
    }

    protected $table = 'sia_contactos';

    protected $fillable = [
        'ciudadano_id',
        'auxiliar_id',
        'fecha_hora',
        'canal',
        'descripcion_demanda',
        'clasificacion',
        'informacion_prestada',
        'urgencia',
        'cita_id',
        'prestaciones_identificadas',
    ];

    protected $casts = [
        'fecha_hora' => 'datetime',
        'clasificacion' => ClasificacionSia::class,
        'urgencia' => UrgenciaSia::class,
        'prestaciones_identificadas' => 'array',
    ];

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * @return BelongsTo<Ciudadano, SiaContacto>
     */
    public function ciudadano(): BelongsTo
    {
        return $this->belongsTo(Ciudadano::class, 'ciudadano_id');
    }

    /**
     * @return BelongsTo<User, SiaContacto>
     */
    public function auxiliar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auxiliar_id');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Solo contactos de competencia municipal.
     *
     * @param Builder<SiaContacto> $query
     *
     * @return Builder<SiaContacto>
     */
    public function scopeCompetenciaMunicipal(Builder $query): Builder
    {
        return $query->where('clasificacion', ClasificacionSia::CompetenciaMunicipal->value);
    }
}

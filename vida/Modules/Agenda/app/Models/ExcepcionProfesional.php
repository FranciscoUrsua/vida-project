<?php

namespace Modules\Agenda\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Agenda\Database\Factories\ExcepcionProfesionalFactory;
use Modules\Agenda\Enums\OrigenExcepcion;
use Modules\Agenda\Enums\TipoExcepcion;
use Modules\Centro\Models\Centro;

/**
 * Excepción en el horario de un profesional.
 *
 * Registra ausencias, reducciones o modificaciones puntuales del horario.
 * VIDA no gestiona la solicitud ni la autorización (es competencia de RRHH);
 * el supervisor introduce el resultado en el sistema.
 *
 * @property int $id
 * @property int $usuario_id
 * @property int $centro_id
 * @property TipoExcepcion $tipo
 * @property Carbon $fecha_inicio
 * @property Carbon $fecha_fin
 * @property bool $afecta_disponibilidad
 * @property array|null $franja_afectada
 * @property OrigenExcepcion $origen
 * @property int $creado_por_id
 * @property string|null $notas
 */
class ExcepcionProfesional extends Model
{
    use HasFactory;

    protected static function newFactory(): ExcepcionProfesionalFactory
    {
        return ExcepcionProfesionalFactory::new();
    }

    protected $table = 'excepciones_profesional';

    protected $guarded = [];

    protected $casts = [
        'tipo' => TipoExcepcion::class,
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'afecta_disponibilidad' => 'boolean',
        'franja_afectada' => 'array',
        'origen' => OrigenExcepcion::class,
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function centro(): BelongsTo
    {
        return $this->belongsTo(Centro::class);
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por_id');
    }

    public function scopeVigentes(Builder $query): Builder
    {
        return $query->where('fecha_fin', '>=', now()->toDateString());
    }

    public function scopeQueAfectanDisponibilidad(Builder $query): Builder
    {
        return $query->where('afecta_disponibilidad', true);
    }

    public function scopeDelProfesional(Builder $query, int $usuarioId): Builder
    {
        return $query->where('usuario_id', $usuarioId);
    }

    public function scopeEnPeriodo(Builder $query, $desde, $hasta): Builder
    {
        // Solapamiento de períodos: existen si fecha_inicio <= $hasta Y fecha_fin >= $desde
        return $query->where('fecha_inicio', '<=', $hasta)
            ->where('fecha_fin', '>=', $desde);
    }
}

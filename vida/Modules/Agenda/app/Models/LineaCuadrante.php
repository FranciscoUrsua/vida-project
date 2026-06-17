<?php

namespace Modules\Agenda\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Agenda\Database\Factories\LineaCuadranteFactory;
use Modules\Centro\Models\Centro;

/**
 * Línea del cuadrante: asignación de un profesional en un día concreto.
 *
 * Define la franja horaria real de trabajo de un profesional en un día,
 * incorporando las particularidades de su perfil. Las excepciones pueden
 * anular la línea correspondiente.
 *
 * @property int $id
 * @property int $cuadrante_mes_id
 * @property int $usuario_id
 * @property int $centro_id
 * @property Carbon $fecha
 * @property array $franjas
 * @property bool $anulada
 * @property int|null $excepcion_id
 */
class LineaCuadrante extends Model
{
    use HasFactory;

    protected static function newFactory(): LineaCuadranteFactory
    {
        return LineaCuadranteFactory::new();
    }

    protected $table = 'lineas_cuadrante';

    protected $guarded = [];

    protected $casts = [
        'fecha' => 'date',
        'franjas' => 'array',
        'anulada' => 'boolean',
    ];

    /**
     * Cuadrante mensual al que pertenece la línea.
     *
     * @return BelongsTo<CuadranteMes, self>
     */
    public function cuadranteMes(): BelongsTo
    {
        return $this->belongsTo(CuadranteMes::class, 'cuadrante_mes_id');
    }

    /**
     * Profesional asignado a la línea.
     *
     * @return BelongsTo<User, self>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /**
     * Centro al que pertenece la línea.
     *
     * @return BelongsTo<Centro, self>
     */
    public function centro(): BelongsTo
    {
        return $this->belongsTo(Centro::class);
    }

    /**
     * Slots generados a partir de la línea.
     *
     * @return HasMany<Slot>
     */
    public function slots(): HasMany
    {
        return $this->hasMany(Slot::class, 'linea_cuadrante_id');
    }

    /**
     * Excepción profesional que anula la línea, si existe.
     *
     * @return BelongsTo<ExcepcionProfesional, self>
     */
    public function excepcion(): BelongsTo
    {
        return $this->belongsTo(ExcepcionProfesional::class, 'excepcion_id');
    }

    /**
     * Filtra líneas no anuladas.
     *
     * @param Builder<LineaCuadrante> $query
     *
     * @return Builder<LineaCuadrante>
     */
    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('anulada', false);
    }

    /**
     * Filtra líneas de una fecha concreta.
     *
     * @param Builder<LineaCuadrante> $query
     * @param mixed $fecha
     *
     * @return Builder<LineaCuadrante>
     */
    public function scopeDelDia(Builder $query, $fecha): Builder
    {
        return $query->where('fecha', $fecha);
    }

    /**
     * Filtra líneas de un profesional.
     *
     * @param Builder<LineaCuadrante> $query
     * @param int $usuarioId
     *
     * @return Builder<LineaCuadrante>
     */
    public function scopeDelProfesional(Builder $query, int $usuarioId): Builder
    {
        return $query->where('usuario_id', $usuarioId);
    }
}

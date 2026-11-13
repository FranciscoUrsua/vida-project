<?php

namespace Modules\Centro\Models;

use App\Models\Ciudadano;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Centro\Enums\ModoAsignacionCentro;

/**
 * Centro asignado a una persona durante un periodo.
 *
 * Una persona tiene como máximo una asignación vigente por tipo de centro
 * (RN-02). El historial es aditivo: una asignación nueva cierra la anterior
 * con `fecha_fin`; nunca se cambia el centro de una fila (principio 4.3).
 * Solo AsignacionCentroService escribe en esta tabla.
 *
 * @property int $id
 * @property int $ciudadano_id
 * @property int $tipo_centro_id Tipo de centro: como mucho una asignación vigente por tipo (RN-02).
 * @property int $centro_id
 * @property ModoAsignacionCentro $modo
 * @property string|null $seccion_censal_codigo Sección con la que se resolvió (modo geográfico).
 * @property string|null $motivo Cifrado. Obligatorio en modo manual.
 * @property int|null $asignado_por_id Null si fue automática.
 * @property Carbon $fecha_inicio
 * @property Carbon|null $fecha_fin
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Ciudadano $ciudadano
 * @property-read Centro $centro
 * @property-read User|null $asignadoPor
 *
 * @see docs/modulo-asignacion.md §3
 */
class AsignacionCentro extends Model
{
    use SoftDeletes;

    /** @var string */
    protected $table = 'asignaciones_centro';

    /** @var list<string> */
    protected $fillable = [
        'ciudadano_id',
        'tipo_centro_id',
        'centro_id',
        'modo',
        'seccion_censal_codigo',
        'motivo',
        'asignado_por_id',
        'fecha_inicio',
        'fecha_fin',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'modo' => ModoAsignacionCentro::class,
        'motivo' => 'encrypted',
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
    ];

    /**
     * Tipo de centro de la asignación.
     *
     * @return BelongsTo<TipoCentro, $this>
     */
    public function tipoCentro(): BelongsTo
    {
        return $this->belongsTo(TipoCentro::class, 'tipo_centro_id');
    }

    /**
     * Asignaciones de un tipo de centro, por su código.
     *
     * @param Builder<self> $query
     * @param string $codigoTipo Código del tipo (p. ej. `css_general`).
     *
     * @return Builder<self>
     */
    public function scopeDelTipo(Builder $query, string $codigoTipo): Builder
    {
        return $query->whereIn('tipo_centro_id', TipoCentro::where('codigo', $codigoTipo)->select('id'));
    }

    /**
     * Persona asignada.
     *
     * @return BelongsTo<Ciudadano, $this>
     */
    public function ciudadano(): BelongsTo
    {
        return $this->belongsTo(Ciudadano::class, 'ciudadano_id');
    }

    /**
     * Centro asignado.
     *
     * @return BelongsTo<Centro, $this>
     */
    public function centro(): BelongsTo
    {
        return $this->belongsTo(Centro::class, 'centro_id');
    }

    /**
     * Usuario que hizo la asignación (null si fue automática).
     *
     * @return BelongsTo<User, $this>
     */
    public function asignadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asignado_por_id');
    }

    /**
     * Filtra las asignaciones vigentes (sin fecha de fin).
     *
     * @param Builder<self> $query
     *
     * @return Builder<self>
     */
    public function scopeVigentes(Builder $query): Builder
    {
        return $query->whereNull('fecha_fin');
    }
}

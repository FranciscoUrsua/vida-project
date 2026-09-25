<?php

namespace Modules\Documentos\Models;

use App\Models\User;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Documentos\Enums\EstadoPropuestaEliminacion;

/**
 * Propuesta de destrucción de versiones cuyo plazo de conservación ha vencido.
 *
 * No destruye nada por sí misma: solo la aprobación de un adm_sistema lo hace, y
 * vuelve a comprobar las retenciones en ese momento. Una vez resuelta no se borra.
 *
 * @property int $id
 * @property EstadoPropuestaEliminacion $estado
 * @property list<int> $versiones
 * @property list<int>|null $excluidas
 * @property int|null $resuelta_por
 * @property Carbon|null $resuelta_en
 * @property int|null $acta_eliminacion_id
 * @property string|null $observaciones
 * @property Carbon $created_at
 * @property-read ActaEliminacion|null $acta
 */
class PropuestaEliminacion extends Model
{
    use Auditable;

    /** @var string */
    protected $table = 'propuestas_eliminacion';

    /** @var list<string> */
    protected $fillable = [
        'estado',
        'versiones',
        'excluidas',
        'resuelta_por',
        'resuelta_en',
        'acta_eliminacion_id',
        'observaciones',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'estado' => EstadoPropuestaEliminacion::class,
        'versiones' => 'array',
        'excluidas' => 'array',
        'resuelta_en' => 'datetime',
    ];

    /**
     * Impide borrar propuestas: son parte del rastro de la destrucción.
     *
     * @throws \LogicException al intentar borrar
     *
     * @return void
     */
    protected static function booted(): void
    {
        static::deleting(function (): void {
            throw new \LogicException('Una propuesta de eliminación no se puede borrar.');
        });
    }

    /**
     * Acta levantada al aprobar la propuesta.
     *
     * @return BelongsTo<ActaEliminacion, $this>
     */
    public function acta(): BelongsTo
    {
        return $this->belongsTo(ActaEliminacion::class, 'acta_eliminacion_id');
    }

    /**
     * Usuario que aprobó o rechazó la propuesta.
     *
     * @return BelongsTo<User, $this>
     */
    public function resueltaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resuelta_por');
    }

    /**
     * Propuestas pendientes de resolver.
     *
     * @param Builder<self> $query
     *
     * @return Builder<self>
     */
    public function scopePendientes(Builder $query): Builder
    {
        return $query->where('estado', EstadoPropuestaEliminacion::Pendiente->value);
    }

    /**
     * Versiones propuestas, con su documento y tipo.
     *
     * @return Collection<int, DocumentoVersion>
     */
    public function versionesPropuestas(): Collection
    {
        return DocumentoVersion::query()->with('documento.tipo')->whereIn('id', $this->versiones)->orderBy('id')->get();
    }

    /**
     * Indica si la propuesta aún se puede aprobar o rechazar.
     *
     * @return bool
     */
    public function estaPendiente(): bool
    {
        return $this->estado === EstadoPropuestaEliminacion::Pendiente;
    }
}

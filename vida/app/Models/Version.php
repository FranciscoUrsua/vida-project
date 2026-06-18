<?php

namespace App\Models;

use App\Traits\Versionable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Snapshot histórico de un registro versionable.
 *
 * Cada fila representa el estado completo de una entidad
 * en el momento ANTERIOR a un cambio. El registro principal
 * siempre tiene el estado actual; esta tabla guarda el historial.
 *
 * Índice compuesto sobre (versionable_type, versionable_id, created_at)
 * para consultas históricas eficientes.
 *
 * @property int $id
 * @property string $versionable_type
 * @property int $versionable_id
 * @property array $datos
 * @property int|null $usuario_id
 * @property string|null $motivo
 * @property Carbon $created_at
 *
 * @see Versionable
 * @see docs/modulo-usuarios-permisos.md sección 5.4
 */
class Version extends Model
{
    /** @var string */
    protected $table = 'versiones';

    /** No se actualiza — el timestamp ES la fecha de la versión. */
    const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [
        'versionable_type',
        'versionable_id',
        'datos',
        'usuario_id',
        'motivo',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'datos' => 'array',
    ];

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Entidad a la que pertenece esta versión.
     *
     * @return MorphTo<Model, Version>
     */
    public function versionable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Usuario que realizó el cambio que generó esta versión.
     *
     * @return BelongsTo<User, Version>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}

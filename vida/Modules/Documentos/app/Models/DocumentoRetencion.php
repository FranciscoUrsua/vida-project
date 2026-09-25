<?php

namespace Modules\Documentos\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Modules\Documentos\Enums\MotivoRetencion;

/**
 * Retención que impide purgar o destruir un documento (o una versión concreta).
 *
 * Un documento «anclado» es un documento con al menos una retención activa. Es
 * genérica: la futura remisión a otra administración será un motivo más.
 *
 * @property int $id
 * @property int $documento_id
 * @property int|null $documento_version_id
 * @property MotivoRetencion $motivo
 * @property string|null $retenedor_type
 * @property int|null $retenedor_id
 * @property Carbon $desde
 * @property Carbon|null $hasta
 * @property string|null $observaciones
 * @property int $creado_por
 */
class DocumentoRetencion extends Model
{
    use Auditable;

    /** @var string */
    protected $table = 'documento_retenciones';

    /** @var list<string> */
    protected $fillable = [
        'documento_id',
        'documento_version_id',
        'motivo',
        'retenedor_type',
        'retenedor_id',
        'desde',
        'hasta',
        'observaciones',
        'creado_por',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'motivo' => MotivoRetencion::class,
        'desde' => 'datetime',
        'hasta' => 'datetime',
    ];

    /**
     * Documento retenido.
     *
     * @return BelongsTo<Documento, $this>
     */
    public function documento(): BelongsTo
    {
        return $this->belongsTo(Documento::class);
    }

    /**
     * Versión retenida, si la retención no abarca todo el documento.
     *
     * @return BelongsTo<DocumentoVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(DocumentoVersion::class, 'documento_version_id');
    }

    /**
     * Entidad que causa la retención (p. ej. la intervención cerrada).
     *
     * @return MorphTo<Model, $this>
     */
    public function retenedor(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Retenciones activas a fecha actual.
     *
     * @param Builder<self> $query
     *
     * @return Builder<self>
     */
    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('desde', '<=', now())
            ->where(fn (Builder $q) => $q->whereNull('hasta')->orWhere('hasta', '>', now()));
    }
}

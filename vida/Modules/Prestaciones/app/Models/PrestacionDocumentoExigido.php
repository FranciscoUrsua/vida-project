<?php

namespace Modules\Prestaciones\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Documentos\Models\TipoDocumental;

/**
 * Documento que exige una prestación para tramitarla (RN-04). Configuración del catálogo.
 *
 * @property int $id
 * @property int $prestacion_id
 * @property int $tipo_documental_id
 * @property bool $obligatorio
 * @property int $orden
 */
class PrestacionDocumentoExigido extends Model
{
    /** @var string */
    protected $table = 'prestacion_documentos_exigidos';

    /** @var list<string> */
    protected $fillable = ['prestacion_id', 'tipo_documental_id', 'obligatorio', 'orden'];

    /** @var array<string, string> */
    protected $casts = ['obligatorio' => 'boolean'];

    /**
     * Prestación que lo exige.
     *
     * @return BelongsTo<Prestacion, $this>
     */
    public function prestacion(): BelongsTo
    {
        return $this->belongsTo(Prestacion::class, 'prestacion_id');
    }

    /**
     * Tipo documental exigido.
     *
     * @return BelongsTo<TipoDocumental, $this>
     */
    public function tipoDocumental(): BelongsTo
    {
        return $this->belongsTo(TipoDocumental::class, 'tipo_documental_id');
    }
}

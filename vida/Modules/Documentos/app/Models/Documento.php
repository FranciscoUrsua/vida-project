<?php

namespace Modules\Documentos\Models;

use App\Models\CatalogoSistema;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Documentos\Enums\OrigenDocumento;

/**
 * Fichero custodiado en el sistema.
 *
 * Puede ser un documento subido externamente (PDF aportado por el ciudadano
 * o un profesional) o el PDF resultante de un informe generado y firmado.
 *
 * Los ficheros nunca se sirven desde rutas públicas. El acceso siempre pasa
 * por un controlador que verifica permisos y genera URLs firmadas temporales.
 *
 * @property int $id
 * @property string $documentable_type
 * @property int $documentable_id
 * @property int $tipo_documento_id
 * @property OrigenDocumento $origen
 * @property string $nombre_original
 * @property string $ruta_almacenamiento
 * @property string $disco
 * @property string $mime_type
 * @property int $tamano_bytes
 * @property string $hash_sha256
 * @property int $subido_por
 * @property string|null $descripcion
 */
class Documento extends Model
{
    protected $table = 'documentos';

    protected $guarded = [];

    protected $casts = [
        'origen' => OrigenDocumento::class,
        'tamano_bytes' => 'integer',
    ];

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(CatalogoSistema::class, 'tipo_documento_id');
    }

    public function subidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subido_por');
    }

    public function informe(): HasOne
    {
        return $this->hasOne(Informe::class, 'documento_id');
    }

    public function scopeExternos(Builder $query): Builder
    {
        return $query->where('origen', OrigenDocumento::Externo->value);
    }

    public function scopeGenerados(Builder $query): Builder
    {
        return $query->where('origen', OrigenDocumento::Generado->value);
    }
}

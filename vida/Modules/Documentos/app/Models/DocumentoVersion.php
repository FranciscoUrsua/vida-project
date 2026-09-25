<?php

namespace Modules\Documentos\Models;

use App\Models\User;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Documentos\Enums\CanalCaptura;
use Modules\Documentos\Enums\EstadoVersion;

/**
 * Versión de un documento: un PDF normalizado, cifrado con su propia clave de datos.
 *
 * clave_almacenamiento es un UUID sin significado; la ruta en disco no revela nada.
 * La clave de datos se guarda cifrada con la clave maestra (envelope encryption).
 * Destruir la versión es borrar esa clave (crypto-shredding) y el objeto del disco.
 * El nombre original se guarda cifrado en BBDD porque puede contener datos personales.
 *
 * @property int $id
 * @property int $documento_id
 * @property int $numero
 * @property string $clave_almacenamiento
 * @property string $disco
 * @property string $hash_sha256
 * @property int $tamanyo_bytes
 * @property int $paginas
 * @property string $nombre_original
 * @property string $mime_original
 * @property bool $convertido
 * @property CanalCaptura $canal
 * @property int $subido_por
 * @property Carbon $fecha_captura
 * @property int|null $plantilla_informe_id
 * @property int|null $informe_id
 * @property EstadoVersion $estado
 * @property string|null $clave_cifrada
 * @property string|null $id_clave_maestra
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Documento $documento
 */
class DocumentoVersion extends Model
{
    use Auditable;

    /** @var string */
    protected $table = 'documento_versiones';

    /** @var list<string> */
    protected $fillable = [
        'documento_id',
        'numero',
        'clave_almacenamiento',
        'disco',
        'hash_sha256',
        'tamanyo_bytes',
        'paginas',
        'nombre_original',
        'mime_original',
        'convertido',
        'canal',
        'subido_por',
        'fecha_captura',
        'plantilla_informe_id',
        'informe_id',
        'estado',
        'clave_cifrada',
        'id_clave_maestra',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'numero' => 'integer',
        'tamanyo_bytes' => 'integer',
        'paginas' => 'integer',
        'nombre_original' => 'encrypted',
        'convertido' => 'boolean',
        'canal' => CanalCaptura::class,
        'fecha_captura' => 'datetime',
        'estado' => EstadoVersion::class,
    ];

    /** @var list<string> */
    protected $hidden = ['clave_cifrada'];

    /**
     * Documento al que pertenece la versión.
     *
     * @return BelongsTo<Documento, $this>
     */
    public function documento(): BelongsTo
    {
        return $this->belongsTo(Documento::class);
    }

    /**
     * Usuario que subió la versión.
     *
     * @return BelongsTo<User, $this>
     */
    public function subidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subido_por');
    }

    /**
     * Informe firmado del que procede la versión (solo canal generado).
     *
     * @return BelongsTo<Informe, $this>
     */
    public function informe(): BelongsTo
    {
        return $this->belongsTo(Informe::class);
    }

    /**
     * Plantilla con la que se elaboró el informe (solo canal generado).
     *
     * @return BelongsTo<PlantillaInforme, $this>
     */
    public function plantillaInforme(): BelongsTo
    {
        return $this->belongsTo(PlantillaInforme::class);
    }

    /**
     * Retenciones sobre esta versión concreta.
     *
     * @return HasMany<DocumentoRetencion, $this>
     */
    public function retenciones(): HasMany
    {
        return $this->hasMany(DocumentoRetencion::class, 'documento_version_id');
    }

    /**
     * Indica si la versión está retenida, por una retención suya o del documento entero.
     *
     * @return bool
     */
    public function estaRetenida(): bool
    {
        return DocumentoRetencion::query()
            ->activas()
            ->where('documento_id', $this->documento_id)
            ->where(fn ($q) => $q->whereNull('documento_version_id')->orWhere('documento_version_id', $this->id))
            ->exists();
    }

    /**
     * Indica si la versión aún tiene clave y objeto (no está purgada ni destruida).
     *
     * @return bool
     */
    public function tieneContenido(): bool
    {
        return in_array($this->estado, [EstadoVersion::Vigente, EstadoVersion::Sustituida], true);
    }

    /**
     * Campos de la auditoría automática: sin la clave cifrada ni el nombre original.
     *
     * @return list<string>
     */
    public function camposAuditables(): array
    {
        return array_values(array_diff($this->getFillable(), ['clave_cifrada', 'nombre_original']));
    }
}

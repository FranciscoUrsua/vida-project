<?php

namespace Modules\Documentos\Models;

use App\Models\Ciudadano;
use App\Models\User;
use App\Traits\Auditable;
use App\Traits\Versionable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Modules\Documentos\Enums\EstadoDocumento;
use Modules\Documentos\Enums\EstadoVersion;

/**
 * Documento lógico custodiado (custodia v2).
 *
 * Tiene una o más versiones, cada una con su PDF cifrado, y se vincula n:M a
 * personas (y, según el tipo, a intervenciones o valoraciones). Toda la
 * información sobre qué es, a quién pertenece y dónde está vive aquí: el fichero
 * del almacenamiento es un blob opaco.
 *
 * «Caducado» no es un estado almacenado: es fecha_validez anterior a hoy.
 *
 * @property int $id
 * @property string $uuid
 * @property int $tipo_documental_id
 * @property string|null $titulo
 * @property Carbon|null $fecha_emision
 * @property Carbon|null $fecha_validez
 * @property string|null $organo_emisor
 * @property bool $visible_ciudadano
 * @property EstadoDocumento $estado
 * @property array<string, mixed> $metadatos
 * @property int $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read TipoDocumental $tipo
 * @property-read DocumentoVersion|null $versionVigente
 */
class Documento extends Model
{
    use Auditable;
    use Versionable;

    /** @var string */
    protected $table = 'documentos';

    /** @var list<string> */
    protected $fillable = [
        'uuid',
        'tipo_documental_id',
        'titulo',
        'fecha_emision',
        'fecha_validez',
        'organo_emisor',
        'visible_ciudadano',
        'estado',
        'metadatos',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'fecha_emision' => 'date',
        'fecha_validez' => 'date',
        'visible_ciudadano' => 'boolean',
        'estado' => EstadoDocumento::class,
        'metadatos' => 'array',
    ];

    /**
     * Asigna el identificador estable (futuro identificador ENI) al crear.
     *
     * @return void
     */
    protected static function booted(): void
    {
        static::creating(function (self $documento): void {
            $documento->uuid ??= (string) Str::uuid();
            $documento->metadatos ??= [];
        });
    }

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Tipo documental.
     *
     * @return BelongsTo<TipoDocumental, $this>
     */
    public function tipo(): BelongsTo
    {
        return $this->belongsTo(TipoDocumental::class, 'tipo_documental_id');
    }

    /**
     * Todas las versiones, de la más reciente a la más antigua.
     *
     * @return HasMany<DocumentoVersion, $this>
     */
    public function versiones(): HasMany
    {
        return $this->hasMany(DocumentoVersion::class)->orderByDesc('numero');
    }

    /**
     * Versión vigente (como mucho una, garantizado por índice parcial único).
     *
     * @return HasOne<DocumentoVersion, $this>
     */
    public function versionVigente(): HasOne
    {
        return $this->hasOne(DocumentoVersion::class)->where('estado', EstadoVersion::Vigente->value);
    }

    /**
     * Todos los vínculos, activos o dados de baja.
     *
     * @return HasMany<DocumentoVinculo, $this>
     */
    public function vinculos(): HasMany
    {
        return $this->hasMany(DocumentoVinculo::class);
    }

    /**
     * Vínculos activos.
     *
     * @return HasMany<DocumentoVinculo, $this>
     */
    public function vinculosActivos(): HasMany
    {
        return $this->vinculos()->where('activo', true);
    }

    /**
     * Retenciones del documento o de alguna de sus versiones.
     *
     * @return HasMany<DocumentoRetencion, $this>
     */
    public function retenciones(): HasMany
    {
        return $this->hasMany(DocumentoRetencion::class);
    }

    /**
     * Usuario que dio de alta el documento.
     *
     * @return BelongsTo<User, $this>
     */
    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Informe firmado que generó este documento, si lo hay.
     *
     * @return HasOne<Informe, $this>
     */
    public function informe(): HasOne
    {
        return $this->hasOne(Informe::class, 'documento_id');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Documentos con vínculo activo a la entidad dada.
     *
     * @param Builder<self> $query
     * @param Model $entidad Persona, intervención o valoración.
     *
     * @return Builder<self>
     */
    public function scopeVinculadosA(Builder $query, Model $entidad): Builder
    {
        return $query->whereHas('vinculosActivos', fn (Builder $q) => $q
            ->where('vinculable_type', $entidad->getMorphClass())
            ->where('vinculable_id', $entidad->getKey()));
    }

    /**
     * Documentos caducados: con fecha de validez anterior a hoy.
     *
     * @param Builder<self> $query
     *
     * @return Builder<self>
     */
    public function scopeCaducados(Builder $query): Builder
    {
        return $query->whereNotNull('fecha_validez')->whereDate('fecha_validez', '<', today());
    }

    // -------------------------------------------------------------------------
    // Consultas
    // -------------------------------------------------------------------------

    /**
     * Indica si el documento ha superado su fecha de validez.
     *
     * @return bool
     */
    public function estaCaducado(): bool
    {
        return $this->fecha_validez !== null && $this->fecha_validez->lt(today());
    }

    /**
     * Indica si hay alguna retención activa sobre el documento entero (no sobre una versión concreta).
     *
     * @return bool
     */
    public function estaRetenido(): bool
    {
        return $this->retenciones()->activas()->whereNull('documento_version_id')->exists();
    }

    /**
     * Primer ciudadano con vínculo activo, para la auditoría automática de escrituras.
     *
     * Un documento compartido afecta a varias personas; la auditoría de accesos
     * registra cada una por separado (paso 6).
     *
     * @return ?int
     */
    public function getCiudadanoId(): ?int
    {
        $id = $this->vinculosActivos()
            ->where('vinculable_type', (new Ciudadano)->getMorphClass())
            ->orderBy('id')
            ->value('vinculable_id');

        return $id !== null ? (int) $id : null;
    }
}

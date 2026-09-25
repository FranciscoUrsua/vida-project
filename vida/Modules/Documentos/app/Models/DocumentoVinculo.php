<?php

namespace Modules\Documentos\Models;

use App\Models\Ciudadano;
use App\Models\User;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Modules\Ciudadania\Models\UnidadConvivencia;

/**
 * Vínculo entre un documento y una persona (o, según el tipo, una intervención o valoración).
 *
 * Siempre apunta al id interno de la entidad, nunca a DNI/NIE/pasaporte. No se
 * vincula a UnidadConvivencia: la unidad cambia con el tiempo, así que un
 * documento de la unidad se vincula a cada miembro. Dar de baja un vínculo es
 * lógico (activo = false); nunca borra el documento ni su fichero.
 *
 * @property int $id
 * @property int $documento_id
 * @property string $vinculable_type
 * @property int $vinculable_id
 * @property bool $activo
 * @property Carbon $fecha_alta
 * @property Carbon|null $fecha_baja
 * @property int $creado_por
 * @property int|null $baja_por
 * @property-read Documento $documento
 */
class DocumentoVinculo extends Model
{
    use Auditable;

    /** @var string */
    protected $table = 'documento_vinculos';

    /** @var list<string> */
    protected $fillable = [
        'documento_id',
        'vinculable_type',
        'vinculable_id',
        'activo',
        'fecha_alta',
        'fecha_baja',
        'creado_por',
        'baja_por',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'activo' => 'boolean',
        'fecha_alta' => 'datetime',
        'fecha_baja' => 'datetime',
    ];

    /**
     * Valida la entidad vinculada antes de crear el vínculo.
     *
     *
     * @throws \DomainException si la entidad es una unidad de convivencia o el tipo no la admite
     *
     * @return void
     */
    protected static function booted(): void
    {
        static::creating(function (self $vinculo): void {
            $vinculo->validarEntidad();
            $vinculo->activo ??= true;
            $vinculo->fecha_alta ??= now();
        });
    }

    /**
     * Rechaza vínculos a unidades de convivencia y a entidades que el tipo documental no admite.
     *
     * @throws \DomainException
     *
     * @return void
     */
    private function validarEntidad(): void
    {
        $clase = Model::getActualClassNameForMorph($this->vinculable_type);

        if (is_a($clase, UnidadConvivencia::class, true)) {
            throw new \DomainException('Un documento no se vincula a la unidad de convivencia: vincúlalo a cada miembro.');
        }

        $tipo = Documento::query()->with('tipo')->findOrFail($this->documento_id)->tipo;

        if (! $tipo->permiteVincularA($clase)) {
            throw new \DomainException("Los documentos de tipo «{$tipo->codigo}» no se pueden vincular a esa entidad.");
        }
    }

    /**
     * Documento vinculado.
     *
     * @return BelongsTo<Documento, $this>
     */
    public function documento(): BelongsTo
    {
        return $this->belongsTo(Documento::class);
    }

    /**
     * Entidad vinculada.
     *
     * @return MorphTo<Model, $this>
     */
    public function vinculable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Usuario que dio de baja el vínculo.
     *
     * @return BelongsTo<User, $this>
     */
    public function bajaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'baja_por');
    }

    /**
     * Vínculos activos.
     *
     * @param Builder<self> $query
     *
     * @return Builder<self>
     */
    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    /**
     * Ciudadano del vínculo, para la auditoría automática.
     *
     * @return ?int
     */
    public function getCiudadanoId(): ?int
    {
        return $this->vinculable_type === (new Ciudadano)->getMorphClass() ? $this->vinculable_id : null;
    }
}

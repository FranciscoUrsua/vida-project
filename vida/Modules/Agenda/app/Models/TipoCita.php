<?php

namespace Modules\Agenda\Models;

use App\Models\User;
use App\Traits\Auditable;
use App\Traits\Versionable;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Agenda\Enums\HerramientaCita;
use Modules\Agenda\Enums\ModalidadCita;

/**
 * Tipo de cita: qué se va a hacer en ella (docs/modulo-citas.md §2.1).
 *
 * Catálogo global gestionado en Filament (solo adm_sistema). Separa el nombre
 * interno, que solo ven los roles con acceso a la Historia Social, de la
 * etiqueta pública: en un CIAM, quien da la cita no debe poder deducir de la
 * etiqueta que se trata de violencia de género. La resolución por usuario está
 * solo en nombreParaUsuario(); las vistas no deciden.
 *
 * El código no puede cambiar cuando hay citas del tipo.
 *
 * @property int $id
 * @property string $codigo
 * @property string $nombre Nombre interno.
 * @property string $etiqueta_publica
 * @property HerramientaCita $herramienta
 * @property ModalidadCita $modalidad_defecto
 * @property bool $requiere_historia_social
 * @property bool $activo
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, TipoSlot> $tiposSlot
 */
class TipoCita extends Model
{
    use Auditable;
    use SoftDeletes;
    use Versionable;

    /** Código del tipo genérico (modo de agenda básico, citas externas sin mapeo). */
    public const CODIGO_GENERICO = 'cita';

    /** @var string */
    protected $table = 'tipos_cita';

    /** @var list<string> */
    protected $fillable = [
        'codigo',
        'nombre',
        'etiqueta_publica',
        'herramienta',
        'modalidad_defecto',
        'requiere_historia_social',
        'activo',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'herramienta' => HerramientaCita::class,
        'modalidad_defecto' => ModalidadCita::class,
        'requiere_historia_social' => 'boolean',
        'activo' => 'boolean',
    ];

    /**
     * Impide cambiar el código de un tipo con citas: las existentes se clasificaron con él.
     *
     * @return void
     *
     * @throws DomainException
     */
    protected static function booted(): void
    {
        static::updating(fn (self $tipo) => $tipo->impedirCambioDeCodigo());
    }

    /**
     * Tipo genérico «cita».
     *
     * @return self
     */
    public static function generico(): self
    {
        return static::where('codigo', self::CODIGO_GENERICO)->firstOrFail();
    }

    /**
     * Tipos de slot en los que puede darse una cita de este tipo.
     *
     * @return BelongsToMany<TipoSlot, $this>
     */
    public function tiposSlot(): BelongsToMany
    {
        return $this->belongsToMany(TipoSlot::class, 'tipo_cita_tipo_slot')->withTimestamps();
    }

    /**
     * Ids de los tipos de slot activos en los que puede darse. El tipo genérico
     * admite cualquiera: es el de los centros en modo básico y el de las citas
     * externas mientras no haya mapeo de servicios.
     *
     * @return list<int>
     */
    public function idsTiposSlotCompatibles(): array
    {
        $consulta = $this->codigo === self::CODIGO_GENERICO ? TipoSlot::query() : $this->tiposSlot();

        return $consulta->where('tipos_slot.activo', true)->pluck('tipos_slot.id')->all();
    }

    /**
     * Si una cita de este tipo puede darse en un slot del tipo dado.
     *
     * @param int $tipoSlotId
     * @return bool
     */
    public function admiteTipoSlot(int $tipoSlotId): bool
    {
        return $this->codigo === self::CODIGO_GENERICO || $this->tiposSlot()->whereKey($tipoSlotId)->exists();
    }

    /**
     * Citas de este tipo.
     *
     * @return HasMany<Cita, $this>
     */
    public function citas(): HasMany
    {
        return $this->hasMany(Cita::class, 'tipo_cita_id');
    }

    /**
     * Filtra los tipos activos.
     *
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    /**
     * Nombre que puede ver el usuario: el interno si tiene acceso a la Historia
     * Social (permiso `historia.leer`); si no, la etiqueta pública.
     *
     * @param User|null $usuario
     * @return string
     */
    public function nombreParaUsuario(?User $usuario): string
    {
        return $usuario?->can('historia.leer') ? $this->nombre : $this->etiqueta_publica;
    }

    /**
     * @return void
     *
     * @throws DomainException Si cambia el código y hay citas del tipo.
     */
    private function impedirCambioDeCodigo(): void
    {
        if ($this->isDirty('codigo') && $this->citas()->withTrashed()->exists()) {
            throw new DomainException("El tipo de cita «{$this->getOriginal('codigo')}» tiene citas: su código no puede cambiar.");
        }
    }
}

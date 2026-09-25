<?php

namespace Modules\Documentos\Models;

use App\Models\Ciudadano;
use App\Traits\Auditable;
use App\Traits\Versionable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Documentos\Enums\FamiliaDocumental;
use Modules\Documentos\Enums\OrigenEni;
use Modules\Documentos\Enums\PoliticaVersiones;
use Modules\Intervencion\Models\PlanDeIntervencion;
use Modules\Intervencion\Models\Valoracion;

/**
 * Tipo documental: fija el comportamiento por defecto de los documentos de ese tipo.
 *
 * Se gestiona en Filament (solo adm_sistema). Una vez que hay documentos del tipo,
 * código, familia y origen ENI no pueden cambiar (los documentos existentes se
 * clasificaron con ellos) y el tipo no se puede borrar, solo desactivar.
 *
 * @property int $id
 * @property string $codigo
 * @property string $nombre
 * @property FamiliaDocumental $familia
 * @property OrigenEni $origen_eni
 * @property bool $caduca
 * @property int|null $validez_dias
 * @property PoliticaVersiones $politica_versiones
 * @property int|null $conservacion_anyos
 * @property bool $visible_ciudadano_defecto
 * @property bool $requiere_firma
 * @property int $max_bytes
 * @property int $max_paginas
 * @property list<string> $metadatos_requeridos
 * @property list<string> $vinculables
 * @property bool $activo
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class TipoDocumental extends Model
{
    use Auditable;
    use Versionable;

    /**
     * Entidades a las que puede vincularse un documento, por clave de `vinculables`.
     *
     * «intervencion» es el plan de intervención y «valoracion» la valoración del módulo Intervención.
     * No incluye UnidadConvivencia: un documento de la unidad se vincula a cada miembro.
     *
     * @var array<string, class-string<Model>>
     */
    public const ENTIDADES_VINCULABLES = [
        'ciudadano' => Ciudadano::class,
        'intervencion' => PlanDeIntervencion::class,
        'valoracion' => Valoracion::class,
    ];

    /** Campos que no pueden cambiar si ya hay documentos del tipo. */
    private const CAMPOS_INMUTABLES_CON_DOCUMENTOS = ['codigo', 'familia', 'origen_eni'];

    /** @var string */
    protected $table = 'tipos_documentales';

    /** @var list<string> */
    protected $fillable = [
        'codigo',
        'nombre',
        'familia',
        'origen_eni',
        'caduca',
        'validez_dias',
        'politica_versiones',
        'conservacion_anyos',
        'visible_ciudadano_defecto',
        'requiere_firma',
        'max_bytes',
        'max_paginas',
        'metadatos_requeridos',
        'vinculables',
        'activo',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'familia' => FamiliaDocumental::class,
        'origen_eni' => OrigenEni::class,
        'politica_versiones' => PoliticaVersiones::class,
        'caduca' => 'boolean',
        'validez_dias' => 'integer',
        'conservacion_anyos' => 'integer',
        'visible_ciudadano_defecto' => 'boolean',
        'requiere_firma' => 'boolean',
        'max_bytes' => 'integer',
        'max_paginas' => 'integer',
        'metadatos_requeridos' => 'array',
        'vinculables' => 'array',
        'activo' => 'boolean',
    ];

    /**
     * Aplica los valores por defecto de configuración y protege código, familia y origen.
     *
     *
     * @throws \DomainException al cambiar un campo inmutable o borrar un tipo con documentos
     *
     * @return void
     */
    protected static function booted(): void
    {
        static::creating(function (self $tipo): void {
            $tipo->max_bytes ??= (int) config('documentos.max_bytes_defecto');
            $tipo->max_paginas ??= (int) config('documentos.max_paginas_defecto');
            $tipo->metadatos_requeridos ??= [];
            $tipo->vinculables ??= ['ciudadano'];
            $tipo->caduca ??= false;
            $tipo->visible_ciudadano_defecto ??= false;
            $tipo->requiere_firma ??= false;
            $tipo->activo ??= true;
            $tipo->politica_versiones ??= PoliticaVersiones::Conservar;
        });

        static::updating(function (self $tipo): void {
            $tipo->impedirCambiosInmutables();
        });

        static::deleting(function (self $tipo): void {
            if ($tipo->documentos()->exists()) {
                throw new \DomainException("El tipo documental «{$tipo->codigo}» tiene documentos: no se puede borrar, solo desactivar.");
            }
        });
    }

    /**
     * Rechaza el cambio de código, familia u origen ENI cuando el tipo ya tiene documentos.
     *
     * @throws \DomainException
     *
     * @return void
     */
    private function impedirCambiosInmutables(): void
    {
        $cambiados = array_filter(self::CAMPOS_INMUTABLES_CON_DOCUMENTOS, fn (string $campo): bool => $this->isDirty($campo));

        if ($cambiados !== [] && $this->documentos()->exists()) {
            throw new \DomainException(
                'No se puede cambiar '.implode(', ', $cambiados)." del tipo documental «{$this->getOriginal('codigo')}»: ya tiene documentos."
            );
        }
    }

    // -------------------------------------------------------------------------
    // Relaciones y scopes
    // -------------------------------------------------------------------------

    /**
     * Documentos de este tipo.
     *
     * @return HasMany<Documento, $this>
     */
    public function documentos(): HasMany
    {
        return $this->hasMany(Documento::class, 'tipo_documental_id');
    }

    /**
     * Tipos activos: los que se ofrecen en el alta de documentos.
     *
     * @param Builder<self> $query
     *
     * @return Builder<self>
     */
    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    // -------------------------------------------------------------------------
    // Reglas
    // -------------------------------------------------------------------------

    /**
     * Indica si un documento de este tipo puede vincularse a una entidad de la clase dada.
     *
     * @param class-string<Model> $clase Clase Eloquent de la entidad.
     *
     * @return bool
     */
    public function permiteVincularA(string $clase): bool
    {
        foreach ($this->vinculables ?? [] as $clave) {
            if ((self::ENTIDADES_VINCULABLES[$clave] ?? null) === $clase) {
                return true;
            }
        }

        return false;
    }

    /**
     * Fecha de validez por defecto de un documento de este tipo capturado en la fecha dada.
     *
     * @param Carbon $desde Fecha de alta de la versión.
     *
     * @return Carbon|null null si el tipo no caduca o no tiene validez definida.
     */
    public function fechaValidezDesde(Carbon $desde): ?Carbon
    {
        if (! $this->caduca || $this->validez_dias === null) {
            return null;
        }

        return $desde->copy()->startOfDay()->addDays($this->validez_dias);
    }

    /**
     * Indica si los documentos de este tipo son informes profesionales.
     *
     * @return bool
     */
    public function esInforme(): bool
    {
        return $this->familia === FamiliaDocumental::InformeProfesional;
    }
}

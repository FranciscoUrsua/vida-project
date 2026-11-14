<?php

namespace Modules\Intervencion\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Intervencion\Database\Factories\TipoPlanFactory;

/**
 * Tipo de plan configurable desde el backoffice.
 *
 * Define la estructura y catálogos disponibles para un Plan de Intervención.
 * Los tipos del seeder tienen eliminable=false y no pueden borrarse desde UI.
 *
 * admite_entrada_directa: si es true, los planes especializados de este tipo
 * pueden existir sin plan ASP previo (plan_asp_id = null). Es el caso del PIA
 * del CIAM, que es una puerta alternativa de entrada al sistema. Si es false,
 * el plan especializado debe nacer de una derivación desde un plan ASP.
 *
 * es_principal (fase 3, F3-1): uno de los tipos de ámbito ASP es el plan principal
 * (el PISO): como máximo un plan vigente por historia, lo gestiona el TSR y es el
 * origen de las derivaciones. Solo puede haber un tipo principal y debe ser de
 * ámbito ASP (validado aquí y por un índice único parcial). Nada decide «es el PISO»
 * por `planes_intervencion.tipo`: se decide por este campo.
 *
 * @property int $id
 * @property string $slug Identificador estable no editable
 * @property string $nombre
 * @property string $ambito 'asp' | 'especializado'
 * @property bool $es_principal Tipo del plan principal (el PISO)
 * @property bool $admite_entrada_directa Admite planes especializados sin plan ASP previo
 * @property string|null $descripcion
 * @property bool $activo
 * @property bool $eliminable
 */
class TipoPlan extends Model
{
    /** @use HasFactory<TipoPlanFactory> */
    use HasFactory;

    /**
     * Factoría del modelo.
     *
     * @return TipoPlanFactory
     */
    protected static function newFactory(): TipoPlanFactory
    {
        return TipoPlanFactory::new();
    }

    protected $table = 'tipos_plan';

    protected $fillable = [
        'slug', 'nombre', 'ambito', 'es_principal', 'admite_entrada_directa', 'descripcion', 'activo', 'eliminable',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'eliminable' => 'boolean',
        'admite_entrada_directa' => 'boolean',
        'es_principal' => 'boolean',
    ];

    // -------------------------------------------------------------------------
    // Hooks
    // -------------------------------------------------------------------------

    /**
     * Registra las reglas del tipo: no se borran los del sistema y el principal es único y de ASP.
     *
     * @return void
     */
    protected static function booted(): void
    {
        static::saving(fn (self $tipo) => self::verificarPrincipal($tipo));

        static::deleting(function (self $tipo): void {
            if (! $tipo->eliminable) {
                throw new \LogicException(
                    "El tipo de plan '{$tipo->slug}' es del sistema y no puede eliminarse."
                );
            }
        });
    }

    /**
     * Impide un tipo principal que no sea de ASP o un segundo tipo principal (F3-1).
     *
     * @param TipoPlan $tipo Tipo que se va a guardar.
     *
     * @return void
     *
     * @throws \DomainException
     */
    private static function verificarPrincipal(self $tipo): void
    {
        if (! $tipo->es_principal) {
            return;
        }

        if ($tipo->ambito !== 'asp') {
            throw new \DomainException('Solo un tipo de plan de ámbito ASP puede ser el plan principal.');
        }

        $otro = static::query()->where('es_principal', true)->whereKeyNot($tipo->getKey())->value('nombre');

        if ($otro !== null) {
            throw new \DomainException("El plan principal ya es «{$otro}»: solo puede haber un tipo principal.");
        }
    }

    /**
     * Tipo de plan principal (el PISO), si está configurado.
     *
     * @return TipoPlan|null
     */
    public static function principal(): ?self
    {
        return static::query()->where('es_principal', true)->first();
    }

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Planes de intervención que usan este tipo.
     *
     * @return HasMany<PlanDeIntervencion, $this>
     */
    public function planes(): HasMany
    {
        return $this->hasMany(PlanDeIntervencion::class, 'tipo_plan_id');
    }

    /**
     * Objetivos del catálogo disponibles para este tipo de plan.
     *
     * @return HasMany<ObjetivoCatalogo, $this>
     */
    public function objetivosCatalogo(): HasMany
    {
        return $this->hasMany(ObjetivoCatalogo::class, 'tipo_plan_id');
    }

    /**
     * Solo los objetivos generales activos, ordenados.
     *
     * @return HasMany<ObjetivoCatalogo, $this>
     */
    public function objetivosGenerales(): HasMany
    {
        return $this->objetivosCatalogo()
            ->where('nivel', 'general')
            ->where('activo', true)
            ->orderBy('orden');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Solo tipos activos.
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
     * Solo tipos de ámbito ASP.
     *
     * @param Builder<self> $query
     *
     * @return Builder<self>
     */
    public function scopeAsp(Builder $query): Builder
    {
        return $query->where('ambito', 'asp');
    }

    /**
     * Solo tipos de ámbito especializado.
     *
     * @param Builder<self> $query
     *
     * @return Builder<self>
     */
    public function scopeEspecializados(Builder $query): Builder
    {
        return $query->where('ambito', 'especializado');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Devuelve las opciones activas indexadas por id para selectores de formulario.
     *
     * @return array<int, string>
     */
    public static function opcionesParaSelect(): array
    {
        return static::activos()->orderBy('nombre')->pluck('nombre', 'id')->toArray();
    }
}

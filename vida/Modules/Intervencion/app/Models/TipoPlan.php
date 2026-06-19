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
 * @property int $id
 * @property string $slug Identificador estable no editable
 * @property string $nombre
 * @property string $ambito 'asp' | 'especializado'
 * @property string|null $descripcion
 * @property bool $activo
 * @property bool $eliminable
 */
class TipoPlan extends Model
{
    use HasFactory;

    protected static function newFactory(): TipoPlanFactory
    {
        return TipoPlanFactory::new();
    }

    protected $table = 'tipos_plan';

    protected $fillable = [
        'slug', 'nombre', 'ambito', 'descripcion', 'activo', 'eliminable',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'eliminable' => 'boolean',
    ];

    // -------------------------------------------------------------------------
    // Hooks
    // -------------------------------------------------------------------------

    protected static function booted(): void
    {
        static::deleting(function (self $tipo): void {
            if (! $tipo->eliminable) {
                throw new \LogicException(
                    "El tipo de plan '{$tipo->slug}' es del sistema y no puede eliminarse."
                );
            }
        });
    }

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Planes de intervención que usan este tipo.
     *
     * @return HasMany<PlanDeIntervencion>
     */
    public function planes(): HasMany
    {
        return $this->hasMany(PlanDeIntervencion::class, 'tipo_plan_id');
    }

    /**
     * Objetivos del catálogo disponibles para este tipo de plan.
     *
     * @return HasMany<ObjetivoCatalogo>
     */
    public function objetivosCatalogo(): HasMany
    {
        return $this->hasMany(ObjetivoCatalogo::class, 'tipo_plan_id');
    }

    /**
     * Solo los objetivos generales activos, ordenados.
     *
     * @return HasMany<ObjetivoCatalogo>
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

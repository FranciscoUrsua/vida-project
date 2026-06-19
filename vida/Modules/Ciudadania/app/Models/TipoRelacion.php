<?php

namespace Modules\Ciudadania\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Modules\Ciudadania\Database\Factories\TipoRelacionFactory;
use Modules\Ciudadania\Enums\ImplicacionFuncional;

/**
 * Tipo de relación entre ciudadanos.
 *
 * Catálogo editable por el backoffice. Cada tipo define su slug (inmutable,
 * contrato interno del código), su etiqueta visible para el TSR, y su
 * implicación funcional (si la tiene). El código nunca evalúa slugs ni
 * etiquetas — siempre evalúa `implicacion_funcional`.
 *
 * Los tipos con `eliminable = false` son del seeder y no pueden borrarse.
 *
 * @property int $id
 * @property string $slug Identificador interno inmutable
 * @property string $etiqueta Texto visible para el TSR
 * @property string $etiqueta_reciproca Etiqueta del tipo inverso
 * @property string|null $slug_reciproco Slug del tipo recíproco (null si simétrico)
 * @property bool $simetrica True si ambas partes tienen el mismo rol
 * @property string|null $implicacion_funcional Valor semántico que evalúa el código
 * @property bool $eliminable False para tipos del seeder
 * @property bool $activo
 *
 * @throws \LogicException al intentar borrar un tipo con eliminable = false
 */
class TipoRelacion extends Model
{
    use HasFactory;

    protected static function newFactory(): TipoRelacionFactory
    {
        return TipoRelacionFactory::new();
    }

    protected $table = 'tipos_relacion';

    protected $fillable = [
        'slug',
        'etiqueta',
        'etiqueta_reciproca',
        'slug_reciproco',
        'simetrica',
        'implicacion_funcional',
        'eliminable',
        'activo',
    ];

    protected $casts = [
        'simetrica' => 'boolean',
        'eliminable' => 'boolean',
        'activo' => 'boolean',
    ];

    // -------------------------------------------------------------------------
    // Hooks
    // -------------------------------------------------------------------------

    protected static function booted(): void
    {
        static::deleting(function (self $tipo) {
            if (! $tipo->eliminable) {
                throw new \LogicException(
                    "El tipo de relación '{$tipo->slug}' es del sistema y no puede eliminarse."
                );
            }
        });
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Filtra tipos activos.
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
     * @param Builder<self> $query
     * @param ImplicacionFuncional $implicacion Implicación funcional requerida.
     *
     * @return Builder<self>
     */
    public function scopeConImplicacion(Builder $query, ImplicacionFuncional $implicacion): Builder
    {
        return $query->where('implicacion_funcional', $implicacion->value);
    }

    // -------------------------------------------------------------------------
    // Métodos de instancia
    // -------------------------------------------------------------------------

    /**
     * Devuelve el tipo recíproco. Para tipos simétricos devuelve $this.
     * Para asimétricos consulta el catálogo por `slug_reciproco`.
     */
    public function tipoRecíproco(): ?self
    {
        if ($this->simetrica || ! $this->slug_reciproco) {
            return $this;
        }

        return static::where('slug', $this->slug_reciproco)->first();
    }

    // -------------------------------------------------------------------------
    // Métodos estáticos de negocio
    // -------------------------------------------------------------------------

    /**
     * Tipos activos que tienen la implicación funcional indicada.
     * El código debe usar este método, nunca comparar slugs directamente.
     *
     * @param ImplicacionFuncional $implicacion Implicación funcional requerida.
     *
     * @return Collection<int, self>
     */
    public static function conImplicacionFuncional(ImplicacionFuncional $implicacion): Collection
    {
        return static::where('implicacion_funcional', $implicacion->value)
            ->where('activo', true)
            ->get();
    }

    /**
     * ¿Existe al menos un tipo activo con esta implicación funcional?
     *
     * @param ImplicacionFuncional $implicacion Implicación funcional requerida.
     */
    public static function existeImplicacion(ImplicacionFuncional $implicacion): bool
    {
        return static::where('implicacion_funcional', $implicacion->value)
            ->where('activo', true)
            ->exists();
    }

    /**
     * Array slug → etiqueta de tipos activos, ordenado por etiqueta.
     * Útil para selects en formularios.
     *
     * @return array<string, string>
     */
    public static function opcionesParaSelect(): array
    {
        return static::activos()
            ->orderBy('etiqueta')
            ->pluck('etiqueta', 'slug')
            ->toArray();
    }
}

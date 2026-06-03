<?php

namespace Modules\Escalas\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Modules\Escalas\Database\Factories\TipoEscalaFactory;

/**
 * Instrumento estandarizado de valoración.
 *
 * Define la estructura completa de una escala (secciones, ítems, opciones con valor numérico)
 * y su tabla de rangos de interpretación. Gestionada exclusivamente desde el backoffice de Filament.
 * Los profesionales aplican instancias de este instrumento mediante PaseEscala.
 *
 * @property int $id
 * @property string $nombre
 * @property string $codigo Identificador estable. Inmutable una vez que existen pases asociados.
 * @property string|null $descripcion
 * @property string|null $instrucciones_aplicacion
 * @property bool $confirmar_instrucciones
 * @property string|null $fuente Referencia bibliográfica del instrumento original.
 * @property array $contextos
 * @property array $schema Estructura de secciones e ítems (ver docs/modulo-escala.md §2.2)
 * @property array $rangos_interpretacion Tabla de rangos de score (ver docs/modulo-escala.md §2.3)
 * @property bool $activa
 */
class TipoEscala extends Model
{
    /** @use HasFactory<TipoEscalaFactory> */
    use HasFactory;

    protected $table = 'tipo_escalas';

    protected $fillable = [
        'nombre',
        'codigo',
        'descripcion',
        'instrucciones_aplicacion',
        'confirmar_instrucciones',
        'fuente',
        'contextos',
        'schema',
        'rangos_interpretacion',
        'activa',
    ];

    protected $casts = [
        'schema' => 'array',
        'rangos_interpretacion' => 'array',
        'contextos' => 'array',
        'activa' => 'boolean',
        'confirmar_instrucciones' => 'boolean',
    ];

    // -------------------------------------------------------------------------
    // Ciclo de vida
    // -------------------------------------------------------------------------

    /**
     * Registra validaciones de integridad y restricciones de inmutabilidad.
     */
    protected static function booted(): void
    {
        static::saving(function (self $model) {
            self::validarSchema($model->schema);
            self::validarRangos($model->rangos_interpretacion);
        });

        static::updating(function (self $model) {
            if (! $model->pases()->exists()) {
                return;
            }

            // El código es inmutable una vez que existen pases
            if ($model->isDirty('codigo')) {
                throw new \LogicException(
                    'El código de una escala es inmutable cuando ya existen pases asociados.'
                );
            }

            // Los ítems existentes en el schema son inmutables con pases asociados
            $schemaOriginal = $model->getOriginal('schema');
            if (is_string($schemaOriginal)) {
                $schemaOriginal = json_decode($schemaOriginal, true);
            }

            if ($model->isDirty('schema') && is_array($schemaOriginal)) {
                self::validarInmutabilidadItems($schemaOriginal, $model->schema);
            }
        });
    }

    // -------------------------------------------------------------------------
    // Validaciones internas
    // -------------------------------------------------------------------------

    /**
     * Valida que el schema tenga al menos una sección con ítems y opciones suficientes.
     *
     * @throws \InvalidArgumentException
     */
    private static function validarSchema(mixed $schema): void
    {
        if (! is_array($schema) || ! isset($schema['secciones']) || ! is_array($schema['secciones'])) {
            throw new \InvalidArgumentException(
                'El schema de la escala debe ser un array con la clave "secciones".'
            );
        }

        if (empty($schema['secciones'])) {
            throw new \InvalidArgumentException('El schema debe contener al menos una sección.');
        }

        foreach ($schema['secciones'] as $seccion) {
            if (empty($seccion['items']) || ! is_array($seccion['items'])) {
                throw new \InvalidArgumentException(
                    'Cada sección del schema debe contener al menos un ítem.'
                );
            }

            foreach ($seccion['items'] as $item) {
                if (empty($item['opciones']) || ! is_array($item['opciones']) || count($item['opciones']) < 2) {
                    throw new \InvalidArgumentException(
                        'Cada ítem debe tener al menos dos opciones.'
                    );
                }
            }
        }
    }

    /**
     * Valida que los rangos de interpretación no tengan huecos ni solapamientos.
     *
     * @throws \InvalidArgumentException
     */
    private static function validarRangos(mixed $rangosInterpretacion): void
    {
        if (! is_array($rangosInterpretacion) || ! isset($rangosInterpretacion['rangos']) || ! is_array($rangosInterpretacion['rangos'])) {
            throw new \InvalidArgumentException(
                'Los rangos de interpretación deben ser un array con la clave "rangos".'
            );
        }

        $rangos = $rangosInterpretacion['rangos'];

        if (empty($rangos)) {
            throw new \InvalidArgumentException('Debe definirse al menos un rango de interpretación.');
        }

        // Ordenar por 'desde' para facilitar la validación
        usort($rangos, fn ($a, $b) => $a['desde'] <=> $b['desde']);

        for ($i = 0; $i < count($rangos) - 1; $i++) {
            $actual = $rangos[$i];
            $siguiente = $rangos[$i + 1];

            if ($actual['hasta'] >= $siguiente['desde']) {
                throw new \InvalidArgumentException(
                    "Los rangos se solapan: el rango [{$actual['desde']}-{$actual['hasta']}] se solapa con [{$siguiente['desde']}-{$siguiente['hasta']}]."
                );
            }

            if ($siguiente['desde'] > $actual['hasta'] + 1) {
                throw new \InvalidArgumentException(
                    "Existe un hueco entre los rangos: entre {$actual['hasta']} y {$siguiente['desde']}."
                );
            }
        }
    }

    /**
     * Verifica que los ítems ya existentes en el schema no hayan sido modificados.
     * Se permite añadir nuevas secciones o ítems al final.
     *
     * @throws \LogicException
     */
    private static function validarInmutabilidadItems(array $schemaOriginal, array $schemaNuevo): void
    {
        $itemsOriginales = [];
        foreach ($schemaOriginal['secciones'] ?? [] as $seccion) {
            foreach ($seccion['items'] ?? [] as $item) {
                $itemsOriginales[$item['id']] = $item['opciones'] ?? [];
            }
        }

        $itemsNuevos = [];
        foreach ($schemaNuevo['secciones'] ?? [] as $seccion) {
            foreach ($seccion['items'] ?? [] as $item) {
                $itemsNuevos[$item['id']] = $item['opciones'] ?? [];
            }
        }

        foreach ($itemsOriginales as $id => $opcionesOriginales) {
            if (! isset($itemsNuevos[$id])) {
                throw new \LogicException(
                    "El ítem '{$id}' no puede eliminarse del schema porque ya existen pases con respuestas para él."
                );
            }

            if ($itemsNuevos[$id] !== $opcionesOriginales) {
                throw new \LogicException(
                    "Las opciones del ítem '{$id}' son inmutables porque ya existen pases con respuestas para él."
                );
            }
        }
    }

    // -------------------------------------------------------------------------
    // Métodos estáticos
    // -------------------------------------------------------------------------

    /**
     * Devuelve el id del TipoEscala con el código dado, desde caché.
     *
     * @throws ModelNotFoundException
     */
    public static function codigoId(string $codigo): int
    {
        return Cache::remember(
            "tipo_escala_id_{$codigo}",
            now()->addDay(),
            fn () => static::where('codigo', $codigo)->firstOrFail()->id
        );
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Escalas disponibles para aplicar a un ciudadano.
     *
     * @param Builder<self> $query
     *
     * @return Builder<self>
     */
    public function scopeAplicables(Builder $query): Builder
    {
        return $query->where('activa', true);
    }

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Pases realizados de este instrumento.
     *
     * @return HasMany<PaseEscala, self>
     */
    public function pases(): HasMany
    {
        return $this->hasMany(PaseEscala::class, 'tipo_escala_id');
    }

    // -------------------------------------------------------------------------
    // Factory
    // -------------------------------------------------------------------------

    protected static function newFactory(): TipoEscalaFactory
    {
        return TipoEscalaFactory::new();
    }
}

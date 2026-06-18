<?php

namespace Modules\Intervencion\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;
use Modules\Intervencion\Database\Factories\TipoFichaFactory;

/**
 * Tipo de ficha configurable desde el backoffice.
 *
 * Define la estructura de campos de una ficha de valoración: qué campos existen,
 * su tipo, orden y reglas de visibilidad condicional. El campo schema es un array
 * JSON con la clave raíz "campos" que contiene la lista de definiciones de campo.
 *
 * La validación del schema se aplica en el evento saving para garantizar la
 * integridad estructural independientemente del canal de entrada.
 * Cuando existen fichas cumplimentadas, cambiar el tipo de un campo existente
 * está prohibido; eliminar campos está permitido (las fichas conservan schema_snapshot).
 *
 * @property int $id
 * @property string $nombre
 * @property string|null $descripcion
 * @property array $schema Definición de campos — estructura: {'campos': [{id, tipo, etiqueta, obligatorio, orden, ...}]}
 * @property bool $activo
 */
class TipoFicha extends Model
{
    use HasFactory;

    /** Tipos de campo válidos en el schema JSON. */
    public const TIPOS_CAMPO = ['texto', 'numero', 'select', 'booleano', 'fecha', 'escala'];

    protected static function newFactory(): TipoFichaFactory
    {
        return TipoFichaFactory::new();
    }

    protected $table = 'tipo_fichas';

    protected $fillable = [
        'nombre',
        'descripcion',
        'schema',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    // -------------------------------------------------------------------------
    // Hooks del modelo
    // -------------------------------------------------------------------------

    protected static function booted(): void
    {
        static::saving(function (TipoFicha $ficha): void {
            $ficha->validarSchema();
        });
    }

    // -------------------------------------------------------------------------
    // Mutadores / Accessors
    // -------------------------------------------------------------------------

    /**
     * Valida el schema antes de almacenarlo.
     *
     * Si se recibe un string, se comprueba que sea JSON válido antes de que el
     * cast lo procese. Si se recibe un array, se serializa directamente.
     * La validación se hace en el mutador (no en el evento saving) porque el
     * cast 'array' transformaría el string antes de que el evento pudiese inspeccionarlo.
     *
     * @param mixed $value
     *
     * @return void
     *
     * @throws \InvalidArgumentException Si el string recibido no es JSON válido
     */
    public function setSchemaAttribute(mixed $value): void
    {
        if (is_string($value)) {
            json_decode($value);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \InvalidArgumentException(
                    'El schema de TipoFicha debe ser un JSON válido: '.json_last_error_msg()
                );
            }
            $this->attributes['schema'] = $value;
        } else {
            $this->attributes['schema'] = json_encode($value);
        }
    }

    /**
     * Devuelve el schema siempre como array PHP.
     *
     * @param mixed $value
     *
     * @return array
     */
    public function getSchemaAttribute(mixed $value): array
    {
        if (is_string($value)) {
            return json_decode($value, true) ?? [];
        }

        return (array) ($value ?? []);
    }

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Fichas cumplimentadas que usan este tipo.
     *
     * @return HasMany<Ficha>
     */
    public function fichas(): HasMany
    {
        return $this->hasMany(Ficha::class, 'tipo_ficha_id');
    }

    /**
     * Asociaciones de este tipo de ficha con tipos de valoración.
     *
     * @return HasMany<TipoValoracionFicha>
     */
    public function tipoValoracionFichas(): HasMany
    {
        return $this->hasMany(TipoValoracionFicha::class, 'tipo_ficha_id');
    }

    // -------------------------------------------------------------------------
    // Métodos de estado
    // -------------------------------------------------------------------------

    /**
     * Indica si esta ficha ya tiene instancias reales de datos (fichas cumplimentadas).
     * Cuando es true, los ids y tipos de campos existentes son inmutables.
     *
     * @return bool
     */
    public function tieneFichasAsociadas(): bool
    {
        return $this->fichas()->exists();
    }

    // -------------------------------------------------------------------------
    // Validación de schema
    // -------------------------------------------------------------------------

    /**
     * Valida la estructura del schema JSON antes de persistir.
     * Lanza ValidationException si el schema no cumple el contrato.
     *
     * @return void
     *
     * @throws ValidationException
     */
    public function validarSchema(): void
    {
        $schema = $this->schema;

        if (! is_array($schema) || ! isset($schema['campos']) || ! is_array($schema['campos'])) {
            throw ValidationException::withMessages([
                'schema' => 'El schema debe ser un objeto con la clave "campos" (array).',
            ]);
        }

        $idsVistos = [];

        foreach ($schema['campos'] as $i => $campo) {
            $prefijo = "schema.campos.{$i}";

            foreach (['id', 'tipo', 'etiqueta', 'obligatorio', 'orden'] as $requerido) {
                if (! isset($campo[$requerido])) {
                    throw ValidationException::withMessages([
                        $prefijo => "El campo [{$i}] no tiene el atributo obligatorio '{$requerido}'.",
                    ]);
                }
            }

            if (! in_array($campo['tipo'], self::TIPOS_CAMPO, true)) {
                throw ValidationException::withMessages([
                    "{$prefijo}.tipo" => "Tipo '{$campo['tipo']}' no válido en campo [{$i}].",
                ]);
            }

            if ($campo['tipo'] === 'select') {
                if (empty($campo['opciones']) || ! is_array($campo['opciones']) || count($campo['opciones']) < 2) {
                    throw ValidationException::withMessages([
                        "{$prefijo}.opciones" => "El campo select [{$i}] debe tener al menos 2 opciones.",
                    ]);
                }
            }

            if ($campo['tipo'] === 'escala') {
                if (empty($campo['tipo_escala_id'])) {
                    throw ValidationException::withMessages([
                        "{$prefijo}.tipo_escala_id" => "El campo escala [{$i}] requiere 'tipo_escala_id'.",
                    ]);
                }
            }

            if (in_array($campo['id'], $idsVistos, true)) {
                throw ValidationException::withMessages([
                    "{$prefijo}.id" => "El id '{$campo['id']}' está duplicado en el schema.",
                ]);
            }

            $idsVistos[] = $campo['id'];
        }

        // Inmutabilidad: si ya hay fichas asociadas, no se pueden eliminar ni cambiar tipo
        // de campos existentes. Solo se pueden añadir campos nuevos.
        if ($this->exists && $this->tieneFichasAsociadas()) {
            $schemaOriginal = TipoFicha::find($this->id)?->schema ?? ['campos' => []];
            $idsOriginales = collect($schemaOriginal['campos'])->pluck('tipo', 'id')->all();

            foreach ($idsOriginales as $id => $tipo) {
                $campoActual = collect($schema['campos'])->firstWhere('id', $id);

                // Eliminar un campo está permitido: las fichas existentes conservan
                // schema_snapshot y siguen siendo coherentes (campo marcado como «retirado»).
                if ($campoActual === null) {
                    continue;
                }

                if ($campoActual['tipo'] !== $tipo) {
                    throw ValidationException::withMessages([
                        'schema' => "No se puede cambiar el tipo del campo '{$id}': ya existen fichas "
                            . "cumplimentadas. Los datos existentes ({$tipo}) serían ininterpretables "
                            . "como {$campoActual['tipo']}.",
                    ]);
                }
            }
        }
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Fichas activas disponibles para componer valoraciones.
     *
     * @param Builder<TipoFicha> $query
     *
     * @return Builder<TipoFicha>
     */
    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }
}

<?php

namespace Modules\Intervencion\Models;

use App\Models\CatalogoSistema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;
use Modules\Intervencion\Database\Factories\TipoFichaFactory;

/**
 * @use HasFactory<TipoFichaFactory>
 *
 * Tipo de ficha configurable desde el backoffice.
 *
 * Define la estructura de campos de una ficha: qué campos existen, su tipo y su orden
 * (no hay visibilidad condicional: ver BACKLOG). El campo schema es un array JSON con la
 * clave raíz "campos" que contiene la lista de definiciones de campo.
 *
 * Usos: `valoracion` (fichas de valoración de la Historia Social), `solicitud` (formularios
 * de solicitud de prestación, fase 3 bis) y `persona` (datos de la persona, como la ficha
 * sociodemográfica: pertenecen al ciudadano y todos sus campos son voluntarios, SD-9).
 *
 * Un campo `catalogo` guarda la clave de un grupo de `catalogos_sistema` (SD-4): el grupo
 * no se puede cambiar si hay fichas, porque las claves guardadas dejarían de significar lo mismo.
 * `codigo` es el identificador estable del tipo (SD-6) e igual de inmutable con fichas.
 *
 * La validación del schema se aplica en el evento saving para garantizar la
 * integridad estructural independientemente del canal de entrada.
 * Cuando existen fichas cumplimentadas, cambiar el tipo de un campo existente
 * está prohibido; eliminar campos está permitido (las fichas conservan schema_snapshot).
 *
 * @property int $id
 * @property string|null $codigo Identificador estable (analítica y código); inmutable si hay fichas (SD-6).
 * @property string $nombre
 * @property string|null $descripcion
 * @property array<string, mixed> $schema Definición de campos — estructura: {'campos': [{id, tipo, etiqueta, obligatorio, orden, ...}]}
 * @property bool $activo
 * @property string $uso 'valoracion' | 'solicitud' (formularios de prestación, fase 3 bis) | 'persona' (datos de la persona, SD-7)
 */
class TipoFicha extends Model
{
    use HasFactory;

    /** Tipos de campo válidos en el schema JSON. */
    public const TIPOS_CAMPO = ['texto', 'numero', 'select', 'booleano', 'fecha', 'escala', 'catalogo'];

    /** Usos válidos del tipo de ficha. */
    public const USOS = ['valoracion', 'solicitud', 'persona'];

    /** Formato del código estable: minúsculas, cifras y guion bajo, empezando por letra. */
    private const FORMATO_CODIGO = '/^[a-z][a-z0-9_]*$/';

    /**
     * Factoría del modelo (vive en el módulo, no en database/factories).
     *
     * @return TipoFichaFactory
     */
    protected static function newFactory(): TipoFichaFactory
    {
        return TipoFichaFactory::new();
    }

    protected $table = 'tipo_fichas';

    protected $fillable = [
        'codigo',
        'nombre',
        'descripcion',
        'schema',
        'activo',
        'uso',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    /**
     * Registra la validación del schema en cada guardado.
     *
     * @return void
     *
     * @throws ValidationException Si el schema no es válido (desde validarSchema())
     */
    protected static function booted(): void
    {
        // Ningún tipo de ficha se guarda con un schema inválido, venga de donde venga
        static::saving(function (TipoFicha $ficha): void {
            $ficha->validarCodigo();
            $ficha->validarSchema();
        });
    }

    /**
     * Valida el schema antes de almacenarlo.
     *
     * Si se recibe un string, se comprueba que sea JSON válido antes de que el
     * cast lo procese. Si se recibe un array, se serializa directamente.
     * La validación se hace en el mutador (no en el evento saving) porque el
     * cast 'array' transformaría el string antes de que el evento pudiese inspeccionarlo.
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
     * @return array<string, mixed>
     */
    public function getSchemaAttribute(mixed $value): array
    {
        if (is_string($value)) {
            /** @var array<string, mixed> */
            return json_decode($value, true) ?? [];
        }

        /** @var array<string, mixed> */
        return (array) ($value ?? []);
    }

    /**
     * Fichas cumplimentadas que usan este tipo.
     *
     * @return HasMany<Ficha, $this>
     */
    public function fichas(): HasMany
    {
        return $this->hasMany(Ficha::class, 'tipo_ficha_id');
    }

    /**
     * Asociaciones de este tipo de ficha con tipos de valoración.
     *
     * @return HasMany<TipoValoracionFicha, $this>
     */
    public function tipoValoracionFichas(): HasMany
    {
        return $this->hasMany(TipoValoracionFicha::class, 'tipo_ficha_id');
    }

    /**
     * Indica si esta ficha ya tiene instancias reales de datos (fichas cumplimentadas).
     * Cuando es true, los ids y tipos de campos existentes son inmutables.
     */
    public function tieneFichasAsociadas(): bool
    {
        return $this->fichas()->exists();
    }

    /**
     * Valida el código estable (SD-6): formato, unicidad e inmutabilidad si hay fichas.
     *
     * @return void
     *
     * @throws ValidationException
     */
    public function validarCodigo(): void
    {
        if ($this->exists && $this->isDirty('codigo') && $this->tieneFichasAsociadas()) {
            throw ValidationException::withMessages([
                'codigo' => 'No se puede cambiar el código: ya hay fichas de este tipo y el código las identifica en la analítica.',
            ]);
        }

        if ($this->codigo === null || $this->codigo === '') {
            $this->codigo = null;

            return;
        }

        if (! preg_match(self::FORMATO_CODIGO, $this->codigo)) {
            throw ValidationException::withMessages([
                'codigo' => 'El código solo admite minúsculas, cifras y guion bajo, y empieza por una letra.',
            ]);
        }

        if (static::where('codigo', $this->codigo)->whereKeyNot($this->getKey())->exists()) {
            throw ValidationException::withMessages(['codigo' => "Ya hay otro tipo de ficha con el código {$this->codigo}."]);
        }
    }

    /**
     * Valida la estructura del schema JSON antes de persistir.
     * Lanza ValidationException si el schema no cumple el contrato.
     *
     * @throws ValidationException
     */
    public function validarSchema(): void
    {
        $schema = $this->schema;
        $campos = $schema['campos'] ?? null;

        if (! is_array($campos)) {
            throw ValidationException::withMessages([
                'schema' => 'El schema debe ser un objeto con la clave "campos" (array).',
            ]);
        }

        $idsVistos = [];

        foreach ($campos as $i => $campo) {
            $prefijo = "schema.campos.{$i}";

            if (! is_array($campo)) {
                throw ValidationException::withMessages([
                    $prefijo => "El campo [{$i}] debe ser un objeto JSON válido.",
                ]);
            }

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

            if ($campo['tipo'] === 'catalogo') {
                $this->validarCampoCatalogo($campo, $prefijo, $i);
            }

            // SD-9: en las fichas de la persona todo es voluntario
            if ($this->uso === 'persona' && ! empty($campo['obligatorio'])) {
                throw ValidationException::withMessages([
                    "{$prefijo}.obligatorio" => "El campo [{$i}] no puede ser obligatorio: en las fichas de la persona todos los datos son voluntarios.",
                ]);
            }

            if ($campo['tipo'] === 'escala' && empty($campo['tipo_escala_id'])) {
                throw ValidationException::withMessages([
                    "{$prefijo}.tipo_escala_id" => "El campo escala [{$i}] requiere 'tipo_escala_id'.",
                ]);
            }

            if (in_array($campo['id'], $idsVistos, true)) {
                throw ValidationException::withMessages([
                    "{$prefijo}.id" => "El id '{$campo['id']}' está duplicado en el schema.",
                ]);
            }

            $idsVistos[] = $campo['id'];
        }

        if ($this->exists && $this->tieneFichasAsociadas()) {
            $schemaOriginal = TipoFicha::findOrFail($this->id)->schema;
            $camposOriginales = $schemaOriginal['campos'] ?? [];
            $tiposOriginales = [];
            $gruposOriginales = [];

            if (is_array($camposOriginales)) {
                foreach ($camposOriginales as $campoOriginal) {
                    if (! is_array($campoOriginal)) {
                        continue;
                    }

                    $id = $campoOriginal['id'] ?? null;
                    $tipo = $campoOriginal['tipo'] ?? null;

                    if (is_string($id) && is_string($tipo)) {
                        $tiposOriginales[$id] = $tipo;
                        $gruposOriginales[$id] = $campoOriginal['catalogo'] ?? null;
                    }
                }
            }

            foreach ($tiposOriginales as $id => $tipo) {
                $campoActual = null;

                foreach ($campos as $campo) {
                    if ($campo['id'] === $id) {
                        $campoActual = $campo;
                        break;
                    }
                }

                if ($campoActual === null) {
                    continue;
                }

                if ($campoActual['tipo'] !== $tipo) {
                    throw ValidationException::withMessages([
                        'schema' => "No se puede cambiar el tipo del campo '{$id}': ya existen fichas "
                            ."cumplimentadas. Los datos existentes ({$tipo}) serían ininterpretables "
                            ."como {$campoActual['tipo']}.",
                    ]);
                }

                // SD-4: las fichas guardan claves del grupo; con otro grupo dejarían de significar lo mismo
                if ($tipo === 'catalogo' && ($campoActual['catalogo'] ?? null) !== $gruposOriginales[$id]) {
                    throw ValidationException::withMessages([
                        'schema' => "No se puede cambiar el catálogo del campo '{$id}': ya existen fichas "
                            .'cumplimentadas que guardan claves del catálogo actual.',
                    ]);
                }
            }
        }
    }

    /**
     * Valida un campo de tipo catálogo (SD-4): indica un grupo de `catalogos_sistema` con al
     * menos dos valores vigentes.
     *
     * @param array<string, mixed> $campo Definición del campo.
     * @param string $prefijo Ruta del campo para el mensaje.
     * @param int|string $i Posición del campo.
     *
     * @return void
     *
     * @throws ValidationException
     */
    private function validarCampoCatalogo(array $campo, string $prefijo, int|string $i): void
    {
        $grupo = $campo['catalogo'] ?? null;

        if (! is_string($grupo) || $grupo === '') {
            throw ValidationException::withMessages([
                "{$prefijo}.catalogo" => "El campo catálogo [{$i}] debe indicar el catálogo del que salen sus valores.",
            ]);
        }

        if (CatalogoSistema::where('grupo', $grupo)->vigentes()->count() < 2) {
            throw ValidationException::withMessages([
                "{$prefijo}.catalogo" => "El catálogo '{$grupo}' del campo [{$i}] no existe o tiene menos de dos valores vigentes.",
            ]);
        }
    }

    /**
     * Fichas activas disponibles para componer valoraciones.
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
     * Solo fichas de valoración (no los formularios de solicitud de prestación, fase 3 bis).
     *
     * @param Builder<self> $query
     *
     * @return Builder<self>
     */
    public function scopeDeValoracion(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('uso'), 'valoracion');
    }

    /**
     * Solo fichas de la persona (SD-7): pertenecen al ciudadano, no a una Historia Social.
     *
     * @param Builder<self> $query
     *
     * @return Builder<self>
     */
    public function scopeDePersona(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('uso'), 'persona');
    }

    /**
     * Si es un tipo de ficha de la persona.
     *
     * @return bool
     */
    public function esDePersona(): bool
    {
        return $this->uso === 'persona';
    }

    /**
     * Solo formularios de solicitud de prestación (fase 3 bis, RN-04).
     *
     * @param Builder<self> $query
     *
     * @return Builder<self>
     */
    public function scopeDeSolicitud(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('uso'), 'solicitud');
    }
}

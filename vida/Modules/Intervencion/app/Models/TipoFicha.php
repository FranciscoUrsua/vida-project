<?php

namespace Modules\Intervencion\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Intervencion\Database\Factories\TipoFichaFactory;

/**
 * Tipo de ficha configurable desde el backoffice.
 *
 * Define la estructura de campos de una ficha de valoración: qué campos existen,
 * su tipo, orden y reglas de visibilidad condicional. El campo schema es un array
 * JSON que define esta estructura.
 *
 * La validación del schema se aplica a nivel de modelo antes de guardar
 * para garantizar la integridad estructural independientemente del canal de entrada.
 *
 * @property int $id
 * @property string $nombre
 * @property string|null $descripcion
 * @property array $schema Definición de campos (array JSON validado)
 * @property bool $activo
 */
class TipoFicha extends Model
{
    use HasFactory;

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
     * Asociaciones de este tipo de ficha con tipos de valoración.
     *
     * @return HasMany<TipoValoracionFicha>
     */
    public function tipoValoracionFichas(): HasMany
    {
        return $this->hasMany(TipoValoracionFicha::class, 'tipo_ficha_id');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Solo tipos de ficha activos.
     */
    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }
}

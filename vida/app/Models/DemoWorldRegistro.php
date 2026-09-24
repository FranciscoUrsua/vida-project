<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Traza de una entidad creada por un mundo demo aditivo.
 *
 * Cada fila vincula una entidad de dominio (polimórfica) con la etiqueta del
 * mundo que la creó (p. ej. TEST_CIAM) y con su clave lógica en el YAML
 * (p. ej. "usuaria_037.plan"). Sirve para localizar lo creado por un mundo y
 * como base de la idempotencia de `demo:load`.
 *
 * Las entidades referenciadas (existentes y no creadas por el mundo) nunca
 * se registran aquí.
 *
 * @property int $id
 * @property string $etiqueta
 * @property string $clave
 * @property string $registrable_type
 * @property int $registrable_id
 * @property Carbon|null $created_at
 * @property-read Model|null $registrable
 */
class DemoWorldRegistro extends Model
{
    /** Solo se registra la creación: el registro no se actualiza. */
    public const UPDATED_AT = null;

    /** @var string */
    protected $table = 'demo_world_registros';

    /** @var list<string> */
    protected $fillable = [
        'etiqueta',
        'clave',
        'registrable_type',
        'registrable_id',
    ];

    /**
     * Entidad de dominio registrada.
     *
     * @return MorphTo<Model, $this>
     */
    public function registrable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Consulta todos los registros de una etiqueta de mundo.
     *
     * @param string $etiqueta Etiqueta del mundo (p. ej. TEST_CIAM)
     *
     * @return Builder<self>
     */
    public static function de(string $etiqueta): Builder
    {
        return self::query()->where('etiqueta', $etiqueta);
    }

    /**
     * Filtra los registros de un tipo de entidad concreto.
     *
     * @param Builder<self> $query
     * @param class-string<Model> $modelClass Clase del modelo registrado
     *
     * @return Builder<self>
     */
    public function scopeDeTipo(Builder $query, string $modelClass): Builder
    {
        return $query->where('registrable_type', (new $modelClass)->getMorphClass());
    }
}

<?php

namespace Modules\Organizacion\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de configuración general de la organización.
 *
 * Almacena pares clave-valor configurables desde el backoffice.
 * El tipo de dato del valor determina cómo se castea al leerlo.
 *
 * @property int $id
 * @property string $clave
 * @property string $valor
 * @property string|null $descripcion
 * @property string $tipo texto|numero|booleano|json
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @see \Modules\Organizacion\Services\ConfiguracionService
 */
class Configuracion extends Model
{
    /** @var string */
    protected $table = 'organizacion_configuracion';

    /** @var list<string> */
    protected $fillable = [
        'clave',
        'valor',
        'descripcion',
        'tipo',
    ];

    /**
     * Devuelve el valor casteado según el tipo declarado.
     *
     * @return mixed
     */
    public function valorCasteado(): mixed
    {
        return match ($this->tipo) {
            'numero'   => is_int($this->valor + 0) ? (int) $this->valor : (float) $this->valor,
            'booleano' => in_array(strtolower($this->valor), ['true', '1', 'yes', 'si'], true),
            'json'     => json_decode($this->valor, true),
            default    => $this->valor,
        };
    }

    /**
     * Filtra por tipo de configuración.
     *
     * @param Builder<Configuracion> $consulta
     * @param string $tipo
     * @return Builder<Configuracion>
     */
    public function scopeTipo(Builder $consulta, string $tipo): Builder
    {
        return $consulta->where('tipo', $tipo);
    }
}

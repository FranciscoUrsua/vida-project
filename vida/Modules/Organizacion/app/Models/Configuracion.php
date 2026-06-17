<?php

namespace Modules\Organizacion\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Modules\Organizacion\Services\ConfiguracionService;

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
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @see ConfiguracionService
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
            'numero' => is_int($this->valor + 0) ? (int) $this->valor : (float) $this->valor,
            'booleano' => in_array(strtolower($this->valor), ['true', '1', 'yes', 'si'], true),
            'json' => json_decode($this->valor, true),
            default => $this->valor,
        };
    }

    /**
     * Filtra por tipo de configuración.
     *
     * @param Builder<Configuracion> $consulta
     * @param string $tipo
     *
     * @return Builder<Configuracion>
     */
    public function scopeTipo(Builder $consulta, string $tipo): Builder
    {
        return $consulta->where('tipo', $tipo);
    }

    // -------------------------------------------------------------------------
    // Helpers de branding (Cambio 1 — logotipo configurable)
    // -------------------------------------------------------------------------

    /**
     * URL pública del logotipo de la aplicación.
     * Lee la clave «logo_path» del almacén de configuración.
     *
     * @return string|null URL completa o null si no hay logotipo configurado.
     */
    public static function logoUrl(): ?string
    {
        $path = app(ConfiguracionService::class)->get('logo_path');

        return $path ? Storage::url((string) $path) : null;
    }

    /**
     * Nombre personalizado de la aplicación.
     * Lee la clave «nombre_aplicacion» del almacén de configuración.
     *
     * @return string|null Nombre configurado o null si no se ha establecido.
     */
    public static function nombreAplicacion(): ?string
    {
        $nombre = app(ConfiguracionService::class)->get('nombre_aplicacion');

        return ($nombre && $nombre !== '') ? (string) $nombre : null;
    }
}

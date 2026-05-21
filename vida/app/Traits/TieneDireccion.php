<?php

namespace App\Traits;

use App\Enums\OrigenDireccion;
use App\Enums\TipoNumeracion;
use Illuminate\Database\Eloquent\Builder;

/**
 * Trait que añade el modelo canónico de dirección a una entidad Eloquent.
 *
 * Aplicable a cualquier modelo que tenga dirección postal: Ciudadano, Centro
 * y cualquier entidad futura. Los campos se almacenan en la propia tabla de
 * la entidad — no hay tabla centralizada de direcciones.
 *
 * Las migraciones correspondientes añaden las columnas a cada tabla.
 * Los modelos que usen este trait deben incluir los campos canónicos en su
 * propia lista $fillable.
 *
 * Ver docs/geocodificacion.md § 3.2 y docs/decisiones-tecnicas.md § 9.
 *
 * @property string|null         $direccion_texto
 * @property bool                $direccion_normalizada
 * @property OrigenDireccion|null $origen_direccion
 * @property string|null         $tipo_via
 * @property string|null         $nombre_via
 * @property TipoNumeracion|null $tipo_numeracion
 * @property string|null         $numero
 * @property string|null         $portal
 * @property string|null         $escalera
 * @property string|null         $piso
 * @property string|null         $puerta
 * @property string|null         $codigo_postal
 * @property string|null         $municipio
 * @property float|null          $coordenadas_lat
 * @property float|null          $coordenadas_lng
 * @property string|null         $geocoder_proveedor
 */
trait TieneDireccion
{
    /**
     * Inyecta los casts de los campos de dirección al instanciar el modelo.
     *
     * @return void
     */
    public function initializeTieneDireccion(): void
    {
        $this->mergeCasts([
            'direccion_normalizada' => 'boolean',
            'tipo_numeracion'       => TipoNumeracion::class,
            'origen_direccion'      => OrigenDireccion::class,
            'coordenadas_lat'       => 'float',
            'coordenadas_lng'       => 'float',
        ]);
    }

    // -------------------------------------------------------------------------
    // Métodos de acceso
    // -------------------------------------------------------------------------

    /**
     * Devuelve la dirección estructurada como cadena legible.
     *
     * Construye la representación a partir de los campos normalizados cuando
     * están disponibles. Usa el texto libre como fallback.
     *
     * @return string
     */
    public function direccionFormateada(): string
    {
        if (!$this->direccion_normalizada || empty($this->nombre_via)) {
            return $this->direccion_texto ?? '';
        }

        $partes = [];

        if ($this->tipo_via) {
            $partes[] = $this->tipo_via;
        }

        $partes[] = $this->nombre_via;

        if ($this->tipo_numeracion === TipoNumeracion::SinNumero) {
            $partes[] = 's/n';
        } elseif ($this->numero) {
            $partes[] = $this->numero;
        }

        $complemento = implode(' ', array_filter([
            $this->portal   ? 'Portal ' . $this->portal   : null,
            $this->escalera ? 'Esc. '   . $this->escalera : null,
            $this->piso     ? $this->piso . 'º'            : null,
            $this->puerta,
        ]));

        if ($complemento) {
            $partes[] = $complemento;
        }

        $linea = implode(' ', $partes);

        if ($this->codigo_postal || $this->municipio) {
            $linea .= ', ' . implode(' ', array_filter([$this->codigo_postal, $this->municipio]));
        }

        return $linea;
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Filtra entidades cuya dirección aún no ha sido normalizada por el geocoder.
     *
     * @param  Builder<static> $query
     * @return Builder<static>
     */
    public function scopeSinNormalizar(Builder $query): Builder
    {
        return $query->where('direccion_normalizada', false);
    }
}

<?php

namespace App\Contracts;

use App\Enums\OrigenDireccion;
use App\Enums\TipoNumeracion;

/**
 * Contrato estático para modelos que exponen la API del trait TieneDireccion.
 *
 * @property string|null $direccion_texto
 * @property bool $direccion_normalizada
 * @property OrigenDireccion|null $origen_direccion
 * @property string|null $tipo_via
 * @property string|null $nombre_via
 * @property TipoNumeracion|null $tipo_numeracion
 * @property string|null $numero
 * @property string|null $portal
 * @property string|null $escalera
 * @property string|null $piso
 * @property string|null $puerta
 * @property string|null $codigo_postal
 * @property string|null $municipio
 * @property float|null $coordenadas_lat
 * @property float|null $coordenadas_lng
 * @property string|null $geocoder_proveedor
 */
interface DireccionableModel
{
    public function direccionFormateada(): string;
}

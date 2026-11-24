<?php

namespace App\Models\Callejero;

use App\Enums\TipoNumeracion;
use App\Enums\TipoNumeroCallejero;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Número de policía del callejero oficial de Madrid (portal, parcela, fachada…).
 *
 * Copia local del fichero de numeraciones del conjunto 200075 de datos.madrid.es. La
 * consulta el geocodificador `callejero` para normalizar domicilios sin enviar ninguna
 * dirección de una persona a terceros. Se reemplaza entera en cada importación
 * (`callejero:importar`): no tiene baja lógica ni versionado porque no es un dato de
 * VIDA sino un reflejo del callejero municipal.
 *
 * @property int $id
 * @property string $cod_ndp Código del número de policía de la Base de Datos Ciudad.
 * @property string $cod_via
 * @property string $clase_via CALLE, AVENIDA, PLAZA, LUGAR…
 * @property string|null $particula_via
 * @property string $nombre_via Mayúsculas sin tildes, como viene.
 * @property string $nombre_via_mostrar Con tildes.
 * @property string $nombre_via_busqueda Minúsculas sin tildes.
 * @property TipoNumeracion $clase_numero Número o kilómetro.
 * @property string $numero
 * @property string|null $calificador
 * @property TipoNumeroCallejero $tipo_numero
 * @property string|null $codigo_postal
 * @property string $distrito_codigo
 * @property string $barrio_codigo Distrito + número de barrio (`016`).
 * @property string|null $seccion_censal_codigo Código INE de diez dígitos.
 * @property float|null $lat
 * @property float|null $lng
 * @property Carbon $importado_en
 *
 * @see docs/instrucciones-cli/2026-11-callejero-madrid.md
 */
class CallejeroPortal extends Model
{
    protected $table = 'callejero_portales';

    public $timestamps = false;

    protected $guarded = ['id'];

    /**
     * Conversión de tipos de las columnas.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'clase_numero' => TipoNumeracion::class,
            'tipo_numero' => TipoNumeroCallejero::class,
            'lat' => 'float',
            'lng' => 'float',
            'importado_en' => 'datetime',
        ];
    }
}

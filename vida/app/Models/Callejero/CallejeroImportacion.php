<?php

namespace App\Models\Callejero;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Constancia de una carga del callejero oficial: qué versión, de dónde y cuántos números.
 *
 * Permite saber con qué fichero se normalizaron las direcciones y detectar que la
 * importación programada ha dejado de ejecutarse.
 *
 * @property int $id
 * @property string $version AAAAMM del fichero, o «local».
 * @property string $origen URL descargada o ruta del fichero.
 * @property int $numeros Números importados.
 * @property int $descartados Filas que no se pudieron interpretar.
 * @property Carbon $importado_en
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CallejeroImportacion extends Model
{
    protected $table = 'callejero_importaciones';

    protected $guarded = ['id'];

    /**
     * Conversión de tipos de las columnas.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'numeros' => 'integer',
            'descartados' => 'integer',
            'importado_en' => 'datetime',
        ];
    }
}

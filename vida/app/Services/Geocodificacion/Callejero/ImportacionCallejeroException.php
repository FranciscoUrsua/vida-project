<?php

namespace App\Services\Geocodificacion\Callejero;

use RuntimeException;

/**
 * Fallo de la importación del callejero que deja intacta la copia anterior.
 *
 * Se lanza antes de tocar la tabla (fichero ilegible, cabeceras cambiadas, versión no
 * encontrada en el portal) o dentro de la transacción, que entonces se deshace.
 */
class ImportacionCallejeroException extends RuntimeException {}

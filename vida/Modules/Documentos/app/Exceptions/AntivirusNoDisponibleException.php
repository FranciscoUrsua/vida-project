<?php

namespace Modules\Documentos\Exceptions;

use RuntimeException;

/**
 * El escáner antivirus no ha podido analizar el fichero (no responde, error o límite propio).
 *
 * La tubería de entrada la convierte en un rechazo: un fichero sin analizar nunca entra.
 */
class AntivirusNoDisponibleException extends RuntimeException {}

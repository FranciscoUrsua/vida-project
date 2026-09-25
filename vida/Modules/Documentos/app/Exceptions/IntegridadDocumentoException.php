<?php

namespace Modules\Documentos\Exceptions;

use RuntimeException;

/**
 * El contenido de una versión no se puede descifrar o no coincide con su hash: ha sido alterado.
 */
class IntegridadDocumentoException extends RuntimeException {}

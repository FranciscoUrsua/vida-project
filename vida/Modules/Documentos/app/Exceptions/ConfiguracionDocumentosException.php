<?php

namespace Modules\Documentos\Exceptions;

use RuntimeException;

/**
 * La custodia de documentos no está configurada (clave maestra o disco de documentos).
 *
 * Se lanza en el primer uso, nunca se sustituye por un modo en claro ni por otro disco.
 */
class ConfiguracionDocumentosException extends RuntimeException {}

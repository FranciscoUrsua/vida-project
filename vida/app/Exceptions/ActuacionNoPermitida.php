<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * 403 al abrir una pantalla de actuación que el usuario no puede hacer (R1, RN-15): a
 * diferencia del resto de 403, su mensaje está escrito para el usuario y en español, así que
 * la pantalla de error lo muestra en lugar del texto genérico.
 */
class ActuacionNoPermitida extends AccessDeniedHttpException {}

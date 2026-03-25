<?php

namespace Modules\Centro\Models;

/**
 * Alias de App\Models\Ciudadano para uso interno del módulo Centro.
 *
 * La implementación completa (cifrado AES-256 de PII, relaciones, etc.)
 * vive en App\Models\Ciudadano hasta que el módulo Ciudadania esté
 * implementado. Este alias garantiza que los casts 'encrypted' se apliquen
 * siempre, independientemente del namespace con que se acceda.
 *
 * Cuando exista Modules\Ciudadania\Models\Ciudadano, cambiar la clase
 * padre aquí y en App\Models\Ciudadano.
 *
 * @see \App\Models\Ciudadano
 * @see docs/modulo-ciudadania.md
 */
class Ciudadano extends \App\Models\Ciudadano
{
    //
}

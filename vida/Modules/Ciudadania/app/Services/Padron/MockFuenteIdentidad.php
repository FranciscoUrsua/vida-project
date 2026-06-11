<?php

namespace Modules\Ciudadania\Services\Padron;

use Modules\Ciudadania\Contracts\FuenteIdentidadInterface;

/**
 * Adaptador mock del padrón municipal para entornos de desarrollo y pruebas.
 *
 * Activo por defecto (principio 3.6): ningún entorno de desarrollo necesita
 * conexión real al padrón para ejercitar el flujo completo de alta.
 *
 * En tests, sustituir con $this->app->instance(FuenteIdentidadInterface::class, mock).
 */
class MockFuenteIdentidad implements FuenteIdentidadInterface
{
    /**
     * Devuelve null simulando persona no empadronada.
     *
     * Esto obliga al flujo de alta a presentar las excepciones de padrón,
     * lo que permite probar todos los caminos sin datos reales.
     *
     * @return null
     */
    public function consultarDatos(string $valorDocumento): ?array
    {
        return null;
    }
}

<?php

namespace App\Services\Catalogos;

/**
 * Recuento de una carga de catálogo oficial: cuántos valores se crearon, cuántos
 * cambiaron y cuántos dejaron de estar vigentes por haber desaparecido de la fuente.
 */
final readonly class ResultadoCargaCatalogo
{
    /**
     * @param string $grupo Grupo de `catalogos_sistema` cargado.
     * @param int $creados Valores nuevos.
     * @param int $actualizados Valores existentes con algún cambio (etiqueta, orden, atributos…).
     * @param int $sinVigencia Valores que ya no están en la fuente y reciben `vigente_hasta` en esta carga.
     */
    public function __construct(
        public string $grupo,
        public int $creados,
        public int $actualizados,
        public int $sinVigencia,
    ) {}

    /**
     * Resumen legible para la consola.
     *
     * @return string
     */
    public function resumen(): string
    {
        return "{$this->grupo}: {$this->creados} creados, {$this->actualizados} actualizados, {$this->sinVigencia} sin vigencia";
    }
}

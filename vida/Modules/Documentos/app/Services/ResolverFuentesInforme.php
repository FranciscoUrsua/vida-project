<?php

namespace Modules\Documentos\Services;

/**
 * Resuelve las fuentes de datos automáticas de las secciones de tipo 'automatico'.
 *
 * Dado un ciudadano_id y una referencia de fuente declarada en la plantilla
 * (ej: 'ciudadano.datos_basicos', 'historia_social.prestaciones_activas'),
 * devuelve los datos estructurados listos para renderizar en el PDF.
 *
 * Centraliza la lógica de extracción de datos para que las plantillas sean
 * declarativas.
 */
class ResolverFuentesInforme
{
    /**
     * Resuelve los datos de una fuente para un ciudadano.
     *
     * @param  string $fuente      Referencia de fuente declarada en la sección (ej: 'ciudadano.datos_basicos')
     * @param  int    $ciudadanoId ID del ciudadano
     * @return array               Datos estructurados para renderizar
     *
     * @todo Implementar cuando el módulo Intervención esté disponible.
     *       Fuentes previstas: ciudadano.datos_basicos, historia_social.prestaciones_activas
     */
    public function resolver(string $fuente, int $ciudadanoId): array
    {
        // TODO: implementar cuando el módulo Intervención esté disponible.
        // Fuentes previstas: ciudadano.datos_basicos, historia_social.prestaciones_activas
        return [];
    }
}

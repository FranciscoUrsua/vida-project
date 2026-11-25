<?php

namespace Modules\Documentos\Support;

use Modules\Documentos\Models\PlantillaInforme;

/**
 * Qué datos automáticos puede usar un informe hecho desde un encargo profesional (EA-9 de
 * docs/instrucciones-cli/2026-11-encargo-actuaciones.md).
 *
 * Restricción de dominio: el profesional de encargo solo accede a lo suyo dentro de sus
 * encargos (EA-7). Las fuentes `historia_social.*` y `escalas.*` y los merge tags de
 * expediente, valoración y plan leen datos de toda la Historia Social, incluidos los de
 * otros profesionales: un informe del encargo que los usara le mostraría lo que no puede
 * ver. Por eso las fuentes van por lista blanca (una fuente nueva queda prohibida hasta
 * que alguien decida lo contrario) y los merge tags que leen la Historia, por lista negra
 * (los del ciudadano, el profesional, el centro, la fecha, las variables de sistema y los
 * parámetros configurables no leen la Historia).
 *
 * Lo usan el backoffice, al asociar una plantilla a un área, y la resolución de las
 * secciones de un informe en contexto de encargo (bloque E).
 */
final class InformeEncargo
{
    /** Fuentes automáticas permitidas en un informe del encargo. */
    public const FUENTES_PERMITIDAS = [
        'ciudadano.datos_basicos',
        'ciudadano.datos_contacto',
        'profesional.datos',
        'encargo.datos',
    ];

    /** Merge tags que leen la Historia Social, prohibidos en un informe del encargo. */
    public const MERGE_TAGS_PROHIBIDOS = [
        'numero_expediente',
        'fecha_apertura',
        'motivo_demanda',
        'fecha_valoracion',
        'score_barthel',
        'interpretacion_barthel',
        'score_pfeiffer',
        'interpretacion_pfeiffer',
        'score_lawton',
        'interpretacion_lawton',
        'lista_prestaciones',
        'fecha_inicio_plan',
        'objetivos_plan',
    ];

    /**
     * Indica si una fuente automática se puede resolver en un informe del encargo.
     *
     * @param string $fuente Referencia de fuente (p. ej. `ciudadano.datos_basicos`).
     *
     * @return bool
     */
    public static function fuentePermitida(string $fuente): bool
    {
        return in_array($fuente, self::FUENTES_PERMITIDAS, true);
    }

    /**
     * Motivos por los que una plantilla no sirve para un informe del encargo (vacío si sirve).
     *
     * @param PlantillaInforme $plantilla Plantilla que se quiere ofrecer en un área.
     *
     * @return list<string>
     */
    public static function infracciones(PlantillaInforme $plantilla): array
    {
        $infracciones = [];

        foreach ((array) $plantilla->secciones as $seccion) {
            $titulo = (string) ($seccion['titulo'] ?? $seccion['id'] ?? 'sin título');
            $fuente = $seccion['fuente'] ?? null;

            if (($seccion['tipo'] ?? null) === 'automatico' && ! self::fuentePermitida((string) $fuente)) {
                $infracciones[] = "la sección «{$titulo}» usa la fuente «{$fuente}»";
            }

            foreach (self::mergeTagsProhibidosEn((string) ($seccion['contenido_plantilla'] ?? '')) as $clave) {
                $infracciones[] = "la sección «{$titulo}» usa la variable «{$clave}»";
            }
        }

        return $infracciones;
    }

    /**
     * Merge tags prohibidos que aparecen en un texto, con o sin espacios dentro de las llaves.
     *
     * @param string $html Contenido de una sección de texto libre.
     *
     * @return list<string>
     */
    public static function mergeTagsProhibidosEn(string $html): array
    {
        return array_values(array_filter(
            self::MERGE_TAGS_PROHIBIDOS,
            fn (string $clave): bool => preg_match('/\{\{\s*'.preg_quote($clave, '/').'\s*\}\}/', $html) === 1,
        ));
    }
}

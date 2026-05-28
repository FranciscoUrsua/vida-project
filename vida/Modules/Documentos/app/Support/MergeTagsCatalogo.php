<?php

namespace Modules\Documentos\Support;

/**
 * Catálogo centralizado de merge tags disponibles en plantillas de informe.
 * Las claves son los identificadores que se insertan en el contenido ({{ clave }}).
 * Los valores son las etiquetas legibles que se muestran en el editor de Filament.
 *
 * Al añadir un nuevo tag aquí también hay que implementar su resolución
 * en ResolverFuentesInforme::resolverMergeTag().
 */
class MergeTagsCatalogo
{
    /**
     * Devuelve el array de merge tags en el formato que espera RichEditor::mergeTags().
     * Formato: ['clave' => 'Etiqueta legible'].
     *
     * @return array<string, string>
     */
    public static function todos(): array
    {
        return [
            // Ciudadano
            'nombre_ciudadano'        => 'Ciudadano — Nombre completo',
            'fecha_nacimiento'        => 'Ciudadano — Fecha de nacimiento',
            'edad'                    => 'Ciudadano — Edad',
            'nie_nif'                 => 'Ciudadano — Documento identificativo',
            'direccion'               => 'Ciudadano — Dirección de empadronamiento',
            'telefono'                => 'Ciudadano — Teléfono de contacto',

            // Expediente
            'numero_expediente'       => 'Expediente — Número',
            'fecha_apertura'          => 'Expediente — Fecha de apertura',
            'motivo_demanda'          => 'Expediente — Motivo de la demanda',

            // Valoración
            'fecha_valoracion'        => 'Valoración — Fecha de la última valoración',
            'score_barthel'           => 'Valoración — Puntuación escala Barthel',
            'interpretacion_barthel'  => 'Valoración — Interpretación escala Barthel',
            'score_pfeiffer'          => 'Valoración — Puntuación escala Pfeiffer',
            'interpretacion_pfeiffer' => 'Valoración — Interpretación escala Pfeiffer',
            'score_lawton'            => 'Valoración — Puntuación escala Lawton-Brody',
            'interpretacion_lawton'   => 'Valoración — Interpretación escala Lawton-Brody',

            // Plan de intervención
            'lista_prestaciones'      => 'Plan — Prestaciones del plan activo',
            'fecha_inicio_plan'       => 'Plan — Fecha de inicio del plan activo',
            'objetivos_plan'          => 'Plan — Objetivos del plan activo',

            // Profesional y centro
            'nombre_profesional'      => 'Profesional — Nombre del TSR autor',
            'cargo_profesional'       => 'Profesional — Cargo',
            'numero_colegiado'        => 'Profesional — Número de colegiación',
            'nombre_centro'           => 'Centro — Nombre del centro',
            'direccion_centro'        => 'Centro — Dirección del centro',
            'telefono_centro'         => 'Centro — Teléfono del centro',

            // Informe
            'fecha_informe'           => 'Informe — Fecha de generación',
        ];
    }

    /**
     * Devuelve solo las claves, para validación.
     *
     * @return array<string>
     */
    public static function claves(): array
    {
        return array_keys(self::todos());
    }
}

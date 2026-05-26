<?php

namespace Modules\Escalas\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Escalas\Models\TipoEscala;

/**
 * Carga los instrumentos de uso libre incluidos en el módulo Escalas.
 * Idempotente: usa updateOrCreate con el campo 'codigo' como clave.
 *
 * Instrumentos incluidos: Barthel, Pfeiffer SPMSQ, Lawton-Brody AIVD.
 * Instrumentos excluidos por licencia: Zarit ZBI, GHQ-28, Yesavage GDS, Beck BDI-II.
 * Ver docs/modulo-escala.md §7 para el análisis completo de licencias.
 */
class EscalaSeeder extends Seeder
{
    public function run(): void
    {
        TipoEscala::updateOrCreate(
            ['codigo' => 'barthel'],
            $this->barthel()
        );

        TipoEscala::updateOrCreate(
            ['codigo' => 'pfeiffer_spmsq'],
            $this->pfeifferSpmsq()
        );

        TipoEscala::updateOrCreate(
            ['codigo' => 'lawton_brody'],
            $this->lawtonBrody()
        );
    }

    // -------------------------------------------------------------------------
    // Instrumentos
    // -------------------------------------------------------------------------

    private function barthel(): array
    {
        return [
            'nombre'                   => 'Índice de Barthel',
            'descripcion'              => 'Mide la capacidad funcional para las actividades básicas de la vida diaria. Score de 0 a 100: a mayor puntuación, mayor independencia.',
            'instrucciones_aplicacion' => 'Evalúe la situación de los últimos 7-10 días. Puntúe lo que el ciudadano HACE realmente, no lo que podría hacer. Si existe duda entre dos puntuaciones, elija la inferior.',
            'confirmar_instrucciones'  => false,
            'fuente'                   => 'Mahoney FI, Barthel D. "Functional evaluation: the Barthel Index." Maryland State Med Journal 1965;14:56-61. Uso no comercial permitido con esta cita.',
            'contextos'                => ['dependencia'],
            'activa'                   => true,
            'schema'                   => [
                'secciones' => [
                    [
                        'id'            => 'sec_avd',
                        'titulo'        => 'Actividades básicas de la vida diaria',
                        'instrucciones' => 'Evalúe la realización durante los últimos 7-10 días.',
                        'orden'         => 1,
                        'items'         => [
                            [
                                'id'            => 'barthel_comer',
                                'texto'         => 'Comer',
                                'instrucciones' => 'Capaz de utilizar cualquier instrumento. Come en un tiempo razonable. No incluye cortar carne ni untar mantequilla.',
                                'orden'         => 1,
                                'opciones'      => [
                                    ['valor' => 0,  'etiqueta' => 'Dependiente'],
                                    ['valor' => 5,  'etiqueta' => 'Necesita ayuda'],
                                    ['valor' => 10, 'etiqueta' => 'Independiente'],
                                ],
                            ],
                            [
                                'id'            => 'barthel_lavarse',
                                'texto'         => 'Lavarse / ducharse',
                                'instrucciones' => 'Independiente: entra y sale del baño solo, se lava sin supervisión.',
                                'orden'         => 2,
                                'opciones'      => [
                                    ['valor' => 0, 'etiqueta' => 'Dependiente'],
                                    ['valor' => 5, 'etiqueta' => 'Independiente'],
                                ],
                            ],
                            [
                                'id'            => 'barthel_vestirse',
                                'texto'         => 'Vestirse',
                                'instrucciones' => 'Incluye ponerse, quitarse y abrochar la ropa. Se incluye ponerse una prótesis si procede.',
                                'orden'         => 3,
                                'opciones'      => [
                                    ['valor' => 0,  'etiqueta' => 'Dependiente'],
                                    ['valor' => 5,  'etiqueta' => 'Necesita ayuda'],
                                    ['valor' => 10, 'etiqueta' => 'Independiente'],
                                ],
                            ],
                            [
                                'id'            => 'barthel_arreglarse',
                                'texto'         => 'Arreglarse',
                                'instrucciones' => 'Lavarse la cara, peinarse, afeitarse (hombres), maquillarse (mujeres). Independiente: no necesita ninguna ayuda.',
                                'orden'         => 4,
                                'opciones'      => [
                                    ['valor' => 0, 'etiqueta' => 'Dependiente'],
                                    ['valor' => 5, 'etiqueta' => 'Independiente'],
                                ],
                            ],
                            [
                                'id'            => 'barthel_deposicion',
                                'texto'         => 'Deposición',
                                'instrucciones' => 'Evalúe la semana previa. Accidente ocasional: menos de una vez por semana.',
                                'orden'         => 5,
                                'opciones'      => [
                                    ['valor' => 0,  'etiqueta' => 'Incontinente'],
                                    ['valor' => 5,  'etiqueta' => 'Accidente ocasional'],
                                    ['valor' => 10, 'etiqueta' => 'Continente'],
                                ],
                            ],
                            [
                                'id'            => 'barthel_miccion',
                                'texto'         => 'Micción',
                                'instrucciones' => 'Evalúe la semana previa. Accidente ocasional: menos de una vez al día.',
                                'orden'         => 6,
                                'opciones'      => [
                                    ['valor' => 0,  'etiqueta' => 'Incontinente'],
                                    ['valor' => 5,  'etiqueta' => 'Accidente ocasional'],
                                    ['valor' => 10, 'etiqueta' => 'Continente'],
                                ],
                            ],
                            [
                                'id'            => 'barthel_retrete',
                                'texto'         => 'Usar el retrete',
                                'instrucciones' => 'Incluye sentarse y levantarse, usar papel higiénico y tirar de la cadena. Independiente: no necesita ningún tipo de ayuda.',
                                'orden'         => 7,
                                'opciones'      => [
                                    ['valor' => 0,  'etiqueta' => 'Dependiente'],
                                    ['valor' => 5,  'etiqueta' => 'Necesita ayuda'],
                                    ['valor' => 10, 'etiqueta' => 'Independiente'],
                                ],
                            ],
                            [
                                'id'            => 'barthel_traslado',
                                'texto'         => 'Trasladarse sillón / cama',
                                'instrucciones' => 'Incluye todas las fases: sentarse en la cama, pasar a la silla, volver a la cama.',
                                'orden'         => 8,
                                'opciones'      => [
                                    ['valor' => 0,  'etiqueta' => 'Dependiente'],
                                    ['valor' => 5,  'etiqueta' => 'Ayuda importante'],
                                    ['valor' => 10, 'etiqueta' => 'Mínima ayuda'],
                                    ['valor' => 15, 'etiqueta' => 'Independiente'],
                                ],
                            ],
                            [
                                'id'            => 'barthel_deambulacion',
                                'texto'         => 'Deambulación',
                                'instrucciones' => 'Independiente: puede usar ayudas técnicas (bastón, muletas) sin supervisión.',
                                'orden'         => 9,
                                'opciones'      => [
                                    ['valor' => 0,  'etiqueta' => 'Dependiente'],
                                    ['valor' => 5,  'etiqueta' => 'Independiente en silla de ruedas'],
                                    ['valor' => 10, 'etiqueta' => 'Camina con ayuda'],
                                    ['valor' => 15, 'etiqueta' => 'Independiente'],
                                ],
                            ],
                            [
                                'id'            => 'barthel_escaleras',
                                'texto'         => 'Subir y bajar escaleras',
                                'instrucciones' => 'Puede usar barandilla o bastón. Necesita ayuda: supervisión o ayuda física.',
                                'orden'         => 10,
                                'opciones'      => [
                                    ['valor' => 0,  'etiqueta' => 'Dependiente'],
                                    ['valor' => 5,  'etiqueta' => 'Necesita ayuda'],
                                    ['valor' => 10, 'etiqueta' => 'Independiente'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'rangos_interpretacion'    => [
                'rangos' => [
                    ['desde' => 0,   'hasta' => 20,  'etiqueta' => 'Dependencia total',    'codigo' => 'total'],
                    ['desde' => 21,  'hasta' => 60,  'etiqueta' => 'Dependencia severa',   'codigo' => 'severa'],
                    ['desde' => 61,  'hasta' => 90,  'etiqueta' => 'Dependencia moderada', 'codigo' => 'moderada'],
                    ['desde' => 91,  'hasta' => 99,  'etiqueta' => 'Dependencia escasa',   'codigo' => 'escasa'],
                    ['desde' => 100, 'hasta' => 100, 'etiqueta' => 'Independencia',        'codigo' => 'independiente'],
                ],
                'nota_interpretacion' => 'Una variación de ±5 puntos no es clínicamente significativa. Valorar siempre la evolución temporal respecto a pases anteriores.',
            ],
        ];
    }

    private function pfeifferSpmsq(): array
    {
        return [
            'nombre'                   => 'Cuestionario Portátil del Estado Mental de Pfeiffer (SPMSQ)',
            'descripcion'              => 'Detección del deterioro cognitivo en personas mayores mediante 10 preguntas. El score es el número de respuestas incorrectas (0-10): a mayor puntuación, mayor deterioro.',
            'instrucciones_aplicacion' => "Administre las preguntas oralmente. Anote 1 por cada respuesta incorrecta y 0 por cada correcta.\n\nCorrección por nivel educativo:\n- Sin educación primaria completa: se permite 1 error adicional.\n- Con estudios superiores: se exige 1 error menos.\n\nRegistre en el campo de notas del pase el nivel educativo del ciudadano si ha aplicado la corrección.",
            'confirmar_instrucciones'  => false,
            'fuente'                   => 'Pfeiffer E. "A short portable mental status questionnaire for the assessment of organic brain deficit in elderly patients." J Am Geriatr Soc. 1975;23(10):433-41. Adaptación española: Martínez de la Iglesia J et al. Rev Esp Geriatr Gerontol. 2001;36(2):92-8.',
            'contextos'                => ['dependencia', 'asp'],
            'activa'                   => true,
            'schema'                   => [
                'secciones' => [
                    [
                        'id'            => 'sec_spmsq',
                        'titulo'        => 'Preguntas de orientación y memoria',
                        'instrucciones' => 'Puntúe 0 si la respuesta es correcta, 1 si es incorrecta.',
                        'orden'         => 1,
                        'items'         => [
                            [
                                'id'            => 'spmsq_01',
                                'texto'         => '¿Cuál es la fecha de hoy? (día, mes, año)',
                                'instrucciones' => 'Se acepta un margen de ±1 día.',
                                'orden'         => 1,
                                'opciones'      => [
                                    ['valor' => 0, 'etiqueta' => 'Correcta'],
                                    ['valor' => 1, 'etiqueta' => 'Incorrecta'],
                                ],
                            ],
                            [
                                'id'            => 'spmsq_02',
                                'texto'         => '¿Qué día de la semana es hoy?',
                                'instrucciones' => null,
                                'orden'         => 2,
                                'opciones'      => [
                                    ['valor' => 0, 'etiqueta' => 'Correcta'],
                                    ['valor' => 1, 'etiqueta' => 'Incorrecta'],
                                ],
                            ],
                            [
                                'id'            => 'spmsq_03',
                                'texto'         => '¿Cuál es el nombre de este lugar?',
                                'instrucciones' => 'Se refiere al nombre del centro o lugar donde se realiza la entrevista.',
                                'orden'         => 3,
                                'opciones'      => [
                                    ['valor' => 0, 'etiqueta' => 'Correcta'],
                                    ['valor' => 1, 'etiqueta' => 'Incorrecta'],
                                ],
                            ],
                            [
                                'id'            => 'spmsq_04',
                                'texto'         => '¿Cuál es su número de teléfono? (o su dirección si no tiene teléfono)',
                                'instrucciones' => 'Verificar con algún familiar o registro si es posible.',
                                'orden'         => 4,
                                'opciones'      => [
                                    ['valor' => 0, 'etiqueta' => 'Correcta'],
                                    ['valor' => 1, 'etiqueta' => 'Incorrecta'],
                                ],
                            ],
                            [
                                'id'            => 'spmsq_05',
                                'texto'         => '¿Cuántos años tiene usted?',
                                'instrucciones' => null,
                                'orden'         => 5,
                                'opciones'      => [
                                    ['valor' => 0, 'etiqueta' => 'Correcta'],
                                    ['valor' => 1, 'etiqueta' => 'Incorrecta'],
                                ],
                            ],
                            [
                                'id'            => 'spmsq_06',
                                'texto'         => '¿Cuándo nació usted?',
                                'instrucciones' => null,
                                'orden'         => 6,
                                'opciones'      => [
                                    ['valor' => 0, 'etiqueta' => 'Correcta'],
                                    ['valor' => 1, 'etiqueta' => 'Incorrecta'],
                                ],
                            ],
                            [
                                'id'            => 'spmsq_07',
                                'texto'         => '¿Quién es el presidente del gobierno actualmente?',
                                'instrucciones' => null,
                                'orden'         => 7,
                                'opciones'      => [
                                    ['valor' => 0, 'etiqueta' => 'Correcta'],
                                    ['valor' => 1, 'etiqueta' => 'Incorrecta'],
                                ],
                            ],
                            [
                                'id'            => 'spmsq_08',
                                'texto'         => '¿Quién fue el presidente del gobierno anterior?',
                                'instrucciones' => null,
                                'orden'         => 8,
                                'opciones'      => [
                                    ['valor' => 0, 'etiqueta' => 'Correcta'],
                                    ['valor' => 1, 'etiqueta' => 'Incorrecta'],
                                ],
                            ],
                            [
                                'id'            => 'spmsq_09',
                                'texto'         => '¿Cuál es el primer apellido de su madre?',
                                'instrucciones' => null,
                                'orden'         => 9,
                                'opciones'      => [
                                    ['valor' => 0, 'etiqueta' => 'Correcta'],
                                    ['valor' => 1, 'etiqueta' => 'Incorrecta'],
                                ],
                            ],
                            [
                                'id'            => 'spmsq_10',
                                'texto'         => 'Reste de 3 en 3 empezando desde 20',
                                'instrucciones' => 'Correcto si realiza la resta de forma consecutiva sin errores: 20, 17, 14, 11, 8.',
                                'orden'         => 10,
                                'opciones'      => [
                                    ['valor' => 0, 'etiqueta' => 'Correcta'],
                                    ['valor' => 1, 'etiqueta' => 'Incorrecta'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'rangos_interpretacion'    => [
                'rangos' => [
                    ['desde' => 0,  'hasta' => 2,  'etiqueta' => 'Normal (sin deterioro)', 'codigo' => 'intacto'],
                    ['desde' => 3,  'hasta' => 4,  'etiqueta' => 'Deterioro leve',         'codigo' => 'leve'],
                    ['desde' => 5,  'hasta' => 7,  'etiqueta' => 'Deterioro moderado',     'codigo' => 'moderado'],
                    ['desde' => 8,  'hasta' => 10, 'etiqueta' => 'Deterioro grave',        'codigo' => 'grave'],
                ],
                'nota_interpretacion' => 'Recuerde aplicar la corrección por nivel educativo antes de interpretar el resultado. Ver instrucciones del instrumento.',
            ],
        ];
    }

    private function lawtonBrody(): array
    {
        return [
            'nombre'                   => 'Escala de Lawton y Brody — Actividades Instrumentales (AIVD)',
            'descripcion'              => 'Evalúa la capacidad para realizar actividades instrumentales de la vida diaria. Score 0-8: a mayor puntuación, mayor independencia.',
            'instrucciones_aplicacion' => "Los ítems en los que la persona no ha participado nunca (p.ej. preparar la comida en personas que nunca lo han hecho) deben excluirse del cómputo. En ese caso el score máximo posible se reduce en 1 por ítem excluido.\n\nEl profesional debe registrar en el campo de notas del pase qué ítems han sido excluidos y el motivo.",
            'confirmar_instrucciones'  => false,
            'fuente'                   => 'Lawton MP, Brody EM. "Assessment of older people: self-maintaining and instrumental activities of daily living." Gerontologist. 1969;9(3):179-86. Dominio público.',
            'contextos'                => ['dependencia'],
            'activa'                   => true,
            'schema'                   => [
                'secciones' => [
                    [
                        'id'            => 'sec_aivd',
                        'titulo'        => 'Actividades instrumentales de la vida diaria',
                        'instrucciones' => 'Puntúe 1 si el ciudadano es capaz de realizar la actividad de forma independiente, 0 si es incapaz o requiere ayuda.',
                        'orden'         => 1,
                        'items'         => [
                            [
                                'id'            => 'lawton_telefono',
                                'texto'         => 'Usar el teléfono',
                                'instrucciones' => 'Capaz: marca los números y mantiene una conversación sin ayuda.',
                                'orden'         => 1,
                                'opciones'      => [
                                    ['valor' => 0, 'etiqueta' => 'Incapaz'],
                                    ['valor' => 1, 'etiqueta' => 'Capaz'],
                                ],
                            ],
                            [
                                'id'            => 'lawton_compras',
                                'texto'         => 'Hacer compras',
                                'instrucciones' => 'Capaz: realiza las compras necesarias de forma independiente.',
                                'orden'         => 2,
                                'opciones'      => [
                                    ['valor' => 0, 'etiqueta' => 'Incapaz'],
                                    ['valor' => 1, 'etiqueta' => 'Capaz'],
                                ],
                            ],
                            [
                                'id'            => 'lawton_comida',
                                'texto'         => 'Preparar la comida',
                                'instrucciones' => 'Capaz: planifica, prepara y sirve comidas adecuadas de forma independiente.',
                                'orden'         => 3,
                                'opciones'      => [
                                    ['valor' => 0, 'etiqueta' => 'Incapaz'],
                                    ['valor' => 1, 'etiqueta' => 'Capaz'],
                                ],
                            ],
                            [
                                'id'            => 'lawton_hogar',
                                'texto'         => 'Cuidado del hogar',
                                'instrucciones' => 'Capaz: mantiene la casa sola o con ayuda ocasional para tareas pesadas.',
                                'orden'         => 4,
                                'opciones'      => [
                                    ['valor' => 0, 'etiqueta' => 'Incapaz'],
                                    ['valor' => 1, 'etiqueta' => 'Capaz'],
                                ],
                            ],
                            [
                                'id'            => 'lawton_ropa',
                                'texto'         => 'Lavado de ropa',
                                'instrucciones' => 'Capaz: lava personalmente toda su ropa.',
                                'orden'         => 5,
                                'opciones'      => [
                                    ['valor' => 0, 'etiqueta' => 'Incapaz'],
                                    ['valor' => 1, 'etiqueta' => 'Capaz'],
                                ],
                            ],
                            [
                                'id'            => 'lawton_transporte',
                                'texto'         => 'Uso de medios de transporte',
                                'instrucciones' => 'Capaz: viaja en transporte público o conduce su propio coche de forma independiente.',
                                'orden'         => 6,
                                'opciones'      => [
                                    ['valor' => 0, 'etiqueta' => 'Incapaz'],
                                    ['valor' => 1, 'etiqueta' => 'Capaz'],
                                ],
                            ],
                            [
                                'id'            => 'lawton_medicacion',
                                'texto'         => 'Control de medicación',
                                'instrucciones' => 'Capaz: toma la medicación en dosis y horarios correctos de forma autónoma.',
                                'orden'         => 7,
                                'opciones'      => [
                                    ['valor' => 0, 'etiqueta' => 'Incapaz'],
                                    ['valor' => 1, 'etiqueta' => 'Capaz'],
                                ],
                            ],
                            [
                                'id'            => 'lawton_dinero',
                                'texto'         => 'Manejo de dinero',
                                'instrucciones' => 'Capaz: maneja los asuntos financieros de forma independiente.',
                                'orden'         => 8,
                                'opciones'      => [
                                    ['valor' => 0, 'etiqueta' => 'Incapaz'],
                                    ['valor' => 1, 'etiqueta' => 'Capaz'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'rangos_interpretacion'    => [
                'rangos' => [
                    ['desde' => 0, 'hasta' => 1, 'etiqueta' => 'Dependencia total',    'codigo' => 'total'],
                    ['desde' => 2, 'hasta' => 3, 'etiqueta' => 'Dependencia severa',   'codigo' => 'severa'],
                    ['desde' => 4, 'hasta' => 5, 'etiqueta' => 'Dependencia moderada', 'codigo' => 'moderada'],
                    ['desde' => 6, 'hasta' => 7, 'etiqueta' => 'Dependencia leve',     'codigo' => 'leve'],
                    ['desde' => 8, 'hasta' => 8, 'etiqueta' => 'Independencia',        'codigo' => 'independiente'],
                ],
                'nota_interpretacion' => 'El score máximo puede ser inferior a 8 si se han excluido ítems. Interpretar siempre en relación al máximo aplicable para este ciudadano.',
            ],
        ];
    }
}

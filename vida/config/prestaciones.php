<?php

/*
|--------------------------------------------------------------------------
| Prestaciones: gestor de expedientes (fase 3 bis)
|--------------------------------------------------------------------------
|
| `gestor_expedientes`: adaptador activo (`manual` por defecto, `simulado` en desarrollo y
| tests). `estados`: mapeo de los códigos de estado de cada adaptador a los normalizados del
| catálogo `expediente.estado`. `consulta_minutos`: frecuencia de `expedientes:consultar`.
| Ver docs/modulo-solicitudes-expedientes.md §4.
*/

return [
    'gestor_expedientes' => env('PRESTACIONES_GESTOR_EXPEDIENTES', 'manual'),

    'consulta_minutos' => (int) env('PRESTACIONES_CONSULTA_MINUTOS', 60),

    'estados' => [
        'simulado' => [
            'TRAMITE_01' => 'en_tramite',
            'TRAMITE_02' => 'en_tramite',
            'REQUERIMIENTO' => 'incidencia',
            'RESUELTO' => 'resuelta',
            'DESISTIDO' => 'desistida',
            'ARCHIVADO' => 'archivada',
        ],
    ],
];

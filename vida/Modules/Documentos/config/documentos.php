<?php

return [
    'disco' => env('DOCUMENTOS_DISCO', 'local'),
    'cache_ttl_segundos' => env('DOCUMENTOS_CACHE_TTL', 3600),
    'tipografia' => [
        'familia' => env('DOCUMENTOS_TIPOGRAFIA_FAMILIA', 'DejaVu Sans'),
        'tamano_base_pt' => env('DOCUMENTOS_TIPOGRAFIA_TAMANO', 10),
    ],
    'estilo_defecto' => [
        'logo_cabecera' => null,
        'nombre_unidad_cabecera' => env('APP_NAME', 'Servicios Sociales'),
        'direccion_cabecera' => null,
        'telefono_cabecera' => null,
        'html_pie' => null,
    ],
];

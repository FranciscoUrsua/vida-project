<?php

return [
    // Disco de Laravel donde se guardan los objetos cifrados (ver config/filesystems.php).
    'disco' => env('DOCUMENTOS_DISCO', 'documentos'),

    // Clave maestra que cifra las claves de datos de cada versión. Distinta de APP_KEY.
    // Formato: base64:<32 bytes en base64>. Sin ella el servicio de documentos no funciona:
    // no existe modo de almacenamiento en claro.
    'clave_maestra' => env('DOCUMENTOS_CLAVE_MAESTRA'),

    // Límites por defecto de un tipo documental nuevo.
    'max_bytes_defecto' => (int) env('DOCUMENTOS_MAX_BYTES', 20 * 1024 * 1024),
    'max_paginas_defecto' => (int) env('DOCUMENTOS_MAX_PAGINAS', 50),

    // Tubería de entrada. El temporal nunca es el disco de documentos.
    'ingesta' => [
        'directorio_temporal' => storage_path('app/tmp/ingesta'),
        'timeout_conversion_segundos' => (int) env('DOCUMENTOS_TIMEOUT_CONVERSION', 120),
        'timeout_saneado_segundos' => (int) env('DOCUMENTOS_TIMEOUT_SANEADO', 120),
    ],

    // Binarios externos.
    'binarios' => [
        'pdfinfo' => env('DOCUMENTOS_BIN_PDFINFO', 'pdfinfo'),
        'gs' => env('DOCUMENTOS_BIN_GS', 'gs'),
        'qpdf' => env('DOCUMENTOS_BIN_QPDF', 'qpdf'),
        'soffice' => env('DOCUMENTOS_BIN_SOFFICE', 'soffice'),
        'clamd_socket' => env('DOCUMENTOS_CLAMD_SOCKET', '/var/run/clamav/clamd.ctl'),
    ],

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

<?php

/**
 * Configuración de la geocodificación local.
 *
 * El proveedor activo no está aquí: se elige en backoffice (`geocoder.proveedor` en
 * la configuración del sistema). Aquí solo van los datos de la fuente del callejero,
 * que no son secretos.
 *
 * @see docs/geocodificacion.md
 * @see docs/instrucciones-cli/2026-11-callejero-madrid.md
 */
return [

    'callejero' => [
        // Fichero de numeraciones del conjunto 200075 («Callejero. Información adicional
        // asociada»), CC BY 4.0, mensual. Su nombre cambia cada mes, así que la URL se
        // resuelve con la API CKAN del portal.
        'api_ckan' => 'https://datos.madrid.es/api/3/action/package_show',
        'conjunto' => '200075-0-callejero',
        'recurso' => '200075-1-callejero-csv',

        // Segundos para descargar el fichero (unos 95 MB)
        'timeout_descarga' => 600,

        // Proporción máxima de filas ilegibles; por encima se aborta (cambio de formato)
        'max_descartados' => 0.01,
    ],

];

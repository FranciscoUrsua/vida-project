<?php

/*
|--------------------------------------------------------------------------
| Centro Module Configuration
|--------------------------------------------------------------------------
|
| Aquí puedes configurar opciones específicas del módulo Centro.
| Estas se cargan via mergeConfigFrom en el ServiceProvider.
| Ejemplos: paths, defaults para validaciones, o integraciones.
|
*/

return [
    /*
    |--------------------------------------------------------------------------
    | Nombre del Módulo
    |--------------------------------------------------------------------------
    |
    | Nombre descriptivo para logs o UI.
    |
    */
    'name' => 'Centro',

    /*
    |--------------------------------------------------------------------------
    | Paths Personalizados
    |--------------------------------------------------------------------------
    |
    | Si necesitas overrides para views, lang, etc., en estructura custom.
    |
    */
    'paths' => [
        'migrations' => __DIR__ . '/../Database/Migrations',
        'seeders' => __DIR__ . '/../Database/Seeders',
        'routes' => __DIR__ . '/../Routes',
        'views' => __DIR__ . '/../Resources/views',
        'lang' => __DIR__ . '/../Resources/lang',
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuraciones por Entidad
    |--------------------------------------------------------------------------
    |
    | Defaults para Centro/TipoCentro, e.g., enums o validaciones dinámicas.
    |
    */
    'entities' => [
        'centro' => [
            'estados' => ['activo', 'inactivo'], // Enum para estado
            'defaults' => [
                'city' => 'Madrid',
                'country' => 'España',
            ],
        ],
        'tipo_centro' => [
            'prestaciones_max' => 20, // Límite en JSON para prestaciones_default
            'publico_objetivo_opciones' => ['familias', 'mayores', 'mujeres', 'menores', 'inmigrantes'], // Para forms
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Integraciones (Opcional)
    |--------------------------------------------------------------------------
    |
    | Si usas APIs externas, e.g., para geovalidación (HasValidatableAddress).
    |
    */
    'integrations' => [
        'google_places_api_key' => env('GOOGLE_PLACES_API_KEY'), // Para trait, si aplica
    ],
];

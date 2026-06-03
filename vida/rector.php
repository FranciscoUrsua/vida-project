<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Laravel\Set\LaravelSetList;
use Rector\Set\ValueObject\SetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/app',
        __DIR__.'/Modules',
    ])
    ->withSkip([
        // No tocar migraciones ni seeders: son historial inmutable
        __DIR__.'/Modules/*/database/migrations',
        __DIR__.'/Modules/*/database/seeders',
        // No tocar ficheros generados por Filament
        __DIR__.'/app/Filament',
    ])
    // PHP 8.3: aplicar mejoras de sintaxis modernas
    ->withPhpSets(php83: true)
    // Conjunto de buenas prácticas generales
    ->withSets([
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
        SetList::EARLY_RETURN,
        SetList::TYPE_DECLARATION,
        // Laravel: modernización de helpers y patrones
        LaravelSetList::LARAVEL_120,
    ])
    // NO activar NAMING: cambia nombres de variables y puede romper
    // lógica de dominio en un proyecto con términos en español
    ->withoutParallel(); // desactivar paralelismo en primera ejecución

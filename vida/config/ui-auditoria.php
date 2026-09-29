<?php

/*
|--------------------------------------------------------------------------
| Ámbito de `php artisan ui:auditar`
|--------------------------------------------------------------------------
|
| Qué vistas, hojas de estilo y ficheros revisa el auditor de estilos.
| Bootstrap es el único sistema de estilos de las superficies operativa y
| pública; Filament y las plantillas PDF quedan fuera.
| Ver docs/instrucciones-cli/2026-09-bootstrap-unico.md.
|
| Todas las rutas son relativas a base_path(). Los patrones usan la sintaxis
| de fnmatch() sobre la ruta relativa (`*` cruza directorios).
|
*/

return [

    // Cada bundle: su entrada de Vite y las vistas que se pintan con él.
    // Una vista se asigna al primer bundle cuyo patrón encaje.
    'bundles' => [
        'publico' => [
            'entrada' => 'resources/scss/app-public.scss',
            'vistas' => [
                'resources/views/inicio.blade.php',
                'resources/views/welcome.blade.php',
                'resources/views/auth/*.blade.php',
                'resources/views/errors/*.blade.php',
                'resources/views/layouts/public.blade.php',
            ],
        ],
        'operativo' => [
            'entrada' => 'resources/scss/app-operativo.scss',
            'vistas' => [
                'resources/views/*.blade.php',
                'Modules/*/resources/views/*.blade.php',
            ],
        ],
    ],

    // Vistas fuera del ámbito: Filament (Tailwind) y PDF (sin Bootstrap).
    'excluir' => [
        'resources/views/filament/*',
        'resources/views/livewire/admin/*',
        'resources/views/livewire/centros/*',
        'Modules/*/resources/views/filament/*',
        'Modules/*/resources/views/pdf/*',
        'Modules/Documentos/resources/views/informe.blade.php',
    ],

    // Directorios donde se buscan vistas.
    'directorios_vistas' => [
        'resources/views',
        'Modules',
    ],

    // Parciales SCSS propios. Los ficheros de tokens pueden contener colores
    // literales; el resto, no.
    'scss' => 'resources/scss',
    'scss_tokens' => [
        '_bootstrap-overrides.scss',
        '_vida-sass-tokens.scss',
    ],

    // CSS de Bootstrap sin modificar: sus clases no son propias y no van al catálogo.
    'bootstrap_css' => 'node_modules/bootstrap/dist/css/bootstrap.css',

    // Manifest de Vite con el CSS compilado de cada entrada.
    'manifest' => 'public/build/manifest.json',

    // Clases que tienen que existir en el CSS compilado de cada bundle (regla R7).
    // La familia del color de tema `protected` (colectivos especialmente
    // protegidos) sale de mapas Sass: si se reordenan los imports de
    // `_bootstrap-vida.scss`, desaparece sin que Sass dé error.
    'clases_exigidas' => [
        'operativo' => [
            'bg-protected',
            'bg-protected-subtle',
            'text-protected',
            'text-protected-emphasis',
            'text-bg-protected',
            'border-protected',
            'border-protected-subtle',
            'btn-protected',
            'btn-outline-protected',
            'alert-protected',
            'link-protected',
        ],
    ],

    // Dónde se busca el uso de las clases del catálogo (regla R5).
    'directorios_uso' => [
        'resources/views',
        'resources/js',
        'app',
        'Modules',
    ],

    // Ficheros que no cuentan como uso (el propio catálogo, tests…).
    'excluir_uso' => [
        'Modules/*/tests/*',
        'app/Console/Commands/UiAuditarCommand.php',
        'app/Support/Ui/*',
    ],

];

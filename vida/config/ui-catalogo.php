<?php

/*
|--------------------------------------------------------------------------
| Catálogo cerrado de clases propias
|--------------------------------------------------------------------------
|
| Toda clase CSS que no sea de Bootstrap y se use en las superficies operativa
| o pública debe figurar aquí. Lo que no está en el catálogo no existe: lo
| comprueba `php artisan ui:auditar` (reglas R1, R4 y R5).
|
| tipo:
|   componente — pieza reutilizable `op-*` que Bootstrap no modela
|   pantalla   — estructura propia de una pantalla, sin equivalente Bootstrap
|   gancho     — sin estilos; selector para JS, Livewire o tests
| estado:
|   pendiente  — heredada, sin revisar (fase 4 del plan)
|   aprobada   — revisada: la descripción dice por qué Bootstrap no basta
|
| Ver docs/instrucciones-cli/2026-09-bootstrap-unico.md.
|
*/

return [

    // Revisado el 2026-09-28 (fase 4 del plan): no queda ninguna clase pendiente.
    'clases' => [
        // resources/scss/_op-components.scss
        'op-page' => ['tipo' => 'componente', 'estado' => 'aprobada', 'descripcion' => 'Contenedor raíz de cada pantalla operativa: alto mínimo de la ventana menos el topbar.'],
        'op-page--fill' => ['tipo' => 'componente', 'estado' => 'aprobada', 'descripcion' => 'Pantalla que ocupa el alto de la ventana menos el topbar, con scroll por zonas. Bootstrap no tiene ese alto.'],
        'op-empty' => ['tipo' => 'componente', 'estado' => 'aprobada', 'descripcion' => 'Estado vacío: icono y mensaje centrados. Bootstrap no modela este patrón.'],
        'op-empty__icon' => ['tipo' => 'componente', 'estado' => 'aprobada', 'descripcion' => 'Icono del estado vacío (tamaño y color atenuado).'],
        'op-empty__text' => ['tipo' => 'componente', 'estado' => 'aprobada', 'descripcion' => 'Texto del estado vacío.'],
        'op-avatar' => ['tipo' => 'componente', 'estado' => 'aprobada', 'descripcion' => 'Círculo con las iniciales de una persona (<x-avatar>). Bootstrap no tiene avatar.'],
        'op-avatar--sm' => ['tipo' => 'componente', 'estado' => 'aprobada', 'descripcion' => 'Avatar pequeño (topbar, listas).'],
        'op-lista-scroll' => ['tipo' => 'componente', 'estado' => 'aprobada', 'descripcion' => 'Lista corta con scroll propio y alto máximo (selector de profesionales). Bootstrap no tiene utilidad de alto máximo.'],
        'op-toggle-icon' => ['tipo' => 'componente', 'estado' => 'aprobada', 'descripcion' => 'Chevron que gira cuando su ancestro tiene aria-expanded="true" (collapse y dropdown de Bootstrap).'],

        // resources/scss/_op-layout.scss
        'op-layout' => ['tipo' => 'componente', 'estado' => 'aprobada', 'descripcion' => 'Shell operativo: sidebar fija y contenido. Estructura propia de la aplicación.'],
        'op-sidebar' => ['tipo' => 'componente', 'estado' => 'aprobada', 'descripcion' => 'Barra lateral fija bajo el topbar, con scroll propio.'],
        'op-nav' => ['tipo' => 'componente', 'estado' => 'aprobada', 'descripcion' => 'Lista de navegación de la barra lateral.'],
        'op-nav-item' => ['tipo' => 'componente', 'estado' => 'aprobada', 'descripcion' => 'Elemento de la navegación lateral.'],
        'op-nav-item--activo' => ['tipo' => 'componente', 'estado' => 'aprobada', 'descripcion' => 'Elemento de la navegación de la ruta actual. Lo comprueban los tests de navegación.'],
        'op-nav-icon' => ['tipo' => 'componente', 'estado' => 'aprobada', 'descripcion' => 'Icono de un elemento de la navegación lateral.'],
        'op-nav-badge' => ['tipo' => 'componente', 'estado' => 'aprobada', 'descripcion' => 'Contador de un elemento de la navegación lateral.'],
        'op-nav-badge--alerta' => ['tipo' => 'componente', 'estado' => 'aprobada', 'descripcion' => 'Contador de la navegación en tono de alerta.'],
        'op-main' => ['tipo' => 'componente', 'estado' => 'aprobada', 'descripcion' => 'Zona de contenido del shell, desplazada por la sidebar y el topbar fijos.'],
        'op-topbar' => ['tipo' => 'componente', 'estado' => 'aprobada', 'descripcion' => 'Barra superior fija del shell operativo.'],
        'topbar__logo' => ['tipo' => 'componente', 'estado' => 'aprobada', 'descripcion' => 'Zona del logo del topbar, alineada con el ancho de la sidebar.'],
        'topbar__logo-img' => ['tipo' => 'componente', 'estado' => 'aprobada', 'descripcion' => 'Imagen del logo (alto máximo).'],
        'topbar__logo-text' => ['tipo' => 'componente', 'estado' => 'aprobada', 'descripcion' => 'Nombre de la aplicación junto al logo.'],
        'topbar__user-nombre' => ['tipo' => 'componente', 'estado' => 'aprobada', 'descripcion' => 'Ancho máximo del nombre del usuario en el topbar (el recorte lo pone text-truncate). Bootstrap no tiene utilidad de ancho máximo en píxeles.'],

        // resources/scss/_op-mensajes.scss
        'mensajes-bandeja' => ['tipo' => 'pantalla', 'estado' => 'aprobada', 'descripcion' => 'Alto mínimo de la bandeja de alertas y mensajes.'],
        'mensajes-bandeja__list' => ['tipo' => 'pantalla', 'estado' => 'aprobada', 'descripcion' => 'Lista de la bandeja con scroll propio y alto máximo.'],
        'mensajes-hilo__messages' => ['tipo' => 'pantalla', 'estado' => 'aprobada', 'descripcion' => 'Mensajes de un hilo con scroll propio y alto máximo.'],
        'mensajes-hilo__bubble' => ['tipo' => 'pantalla', 'estado' => 'aprobada', 'descripcion' => 'Burbuja de mensaje con ancho máximo relativo (Bootstrap solo tiene mw-100).'],
        'mensajes-toasts' => ['tipo' => 'pantalla', 'estado' => 'aprobada', 'descripcion' => 'Contenedor de toasts de alertas: centrado bajo el topbar fijo y con ancho relativo.'],
        'mensajes-toasts-minimizadas' => ['tipo' => 'pantalla', 'estado' => 'aprobada', 'descripcion' => 'Barra fija al pie con las alertas minimizadas.'],
        'mensajes-toasts-minimizadas__barra' => ['tipo' => 'pantalla', 'estado' => 'aprobada', 'descripcion' => 'Fondo opaco y sombra de la barra de alertas minimizadas.'],
        'mensajes-toasts-minimizadas__chip' => ['tipo' => 'pantalla', 'estado' => 'aprobada', 'descripcion' => 'Chip de una alerta minimizada (ancho máximo y tono de alerta).'],

        // resources/scss/_op-plan.scss
        'plan-index' => ['tipo' => 'pantalla', 'estado' => 'aprobada', 'descripcion' => 'Índice lateral del plan, fijo por debajo de la barra superior del plan (sticky-top lo taparía).'],
        'plan-editor-area' => ['tipo' => 'pantalla', 'estado' => 'aprobada', 'descripcion' => 'Alto mínimo del editor contenteditable de la síntesis, montado sobre .form-control.'],

        // resources/scss/_op-utilities.scss
        'icon-12' => ['tipo' => 'componente', 'estado' => 'aprobada', 'descripcion' => 'Tamaño de icono Heroicons de 12px. Bootstrap no dimensiona SVG.'],
        'icon-14' => ['tipo' => 'componente', 'estado' => 'aprobada', 'descripcion' => 'Tamaño de icono Heroicons de 14px. Bootstrap no dimensiona SVG.'],
        'icon-16' => ['tipo' => 'componente', 'estado' => 'aprobada', 'descripcion' => 'Tamaño de icono Heroicons de 16px. Bootstrap no dimensiona SVG.'],
        'icon-18' => ['tipo' => 'componente', 'estado' => 'aprobada', 'descripcion' => 'Tamaño de icono Heroicons de 18px. Bootstrap no dimensiona SVG.'],
        'icon-20' => ['tipo' => 'componente', 'estado' => 'aprobada', 'descripcion' => 'Tamaño de icono Heroicons de 20px. Bootstrap no dimensiona SVG.'],
        'icon-40' => ['tipo' => 'componente', 'estado' => 'aprobada', 'descripcion' => 'Tamaño de icono Heroicons de 40px. Bootstrap no dimensiona SVG.'],
    ],

];

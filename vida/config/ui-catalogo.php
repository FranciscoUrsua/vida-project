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

    // Inventario inicial (2026-09-28): todas las clases propias existentes, sin
    // revisar. En la fase 4 cada una se borra, se aprueba o se documenta.
    'clases' => [
        // resources/scss/_op-ciudadano.scss
        'icon-13' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'icon-15' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'icon-18' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],

        // resources/scss/_op-components.scss
        'op-collapse-label-collapsed' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'op-collapse-label-expanded' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'op-empty' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'op-empty__icon' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'op-empty__text' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'op-page' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'op-avatar' => ['tipo' => 'componente', 'estado' => 'aprobada', 'descripcion' => 'Círculo con las iniciales de una persona (<x-avatar>). Bootstrap no tiene avatar.'],
        'op-avatar--sm' => ['tipo' => 'componente', 'estado' => 'aprobada', 'descripcion' => 'Avatar pequeño (topbar, listas).'],
        'op-page--fill' => ['tipo' => 'componente', 'estado' => 'aprobada', 'descripcion' => 'Pantalla que ocupa el alto de la ventana menos el topbar, con scroll por zonas. Bootstrap no tiene ese alto.'],
        'op-toggle-icon' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],

        // resources/scss/_op-layout.scss
        'activo' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'alerta' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'op-layout' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'op-main' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'op-nav' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'op-nav-badge' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'op-nav-icon' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'op-nav-item' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'op-sidebar' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'op-topbar' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'topbar__logo' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'topbar__logo-img' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'topbar__logo-text' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'topbar__section' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'topbar__title' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'topbar__title-sep' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'topbar__user' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'topbar__user-detail-name' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'topbar__user-detail-role' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'topbar__user-divider' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'topbar__user-info' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'topbar__user-menu' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'topbar__user-nombre' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],

        // resources/scss/_op-mensajes.scss
        'mensajes-bandeja' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'mensajes-bandeja__list' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'mensajes-hilo__bubble' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'mensajes-hilo__messages' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'mensajes-hilo__modal' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'mensajes-toasts' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'mensajes-toasts-minimizadas' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'mensajes-toasts-minimizadas__barra' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'mensajes-toasts-minimizadas__chip' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],

        // resources/scss/_op-plan.scss
        'plan-editor-area' => ['tipo' => 'pantalla', 'estado' => 'aprobada', 'descripcion' => 'Alto mínimo del editor contenteditable de la síntesis, montado sobre .form-control.'],
        'plan-index' => ['tipo' => 'pantalla', 'estado' => 'aprobada', 'descripcion' => 'Índice lateral del plan, fijo por debajo de la barra superior del plan (sticky-top lo taparía).'],

        // resources/scss/_op-support-pages.scss
        'agenda-page' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],

        // resources/scss/_op-utilities.scss
        'icon-12' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'icon-14' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'icon-16' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'icon-20' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'icon-40' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],

        // resources/scss/_public-pages.scss
        'auth-card' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'auth-card--blocked' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'auth-card__alert' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'auth-card__body' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'auth-card__divider' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'auth-card__env' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'auth-card__input' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'auth-card__label' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'auth-card__link' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'auth-card__meta' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'auth-card__mobile-brand' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'auth-card__mobile-copy' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'auth-card__status-icon' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'auth-card__submit' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'auth-card__subtitle' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'auth-card__subtitle--center' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'auth-card__title' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'auth-page' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'auth-page--centered' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'auth-page__aside' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'auth-page__aside-inner' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'auth-page__aside-note' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'auth-page__brand' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'auth-page__brand-copy' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'auth-page__brand-title' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'auth-page__center-wrap' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'auth-page__chip' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'auth-page__chip-list' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'auth-page__main' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'auth-page__shell' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'blocked-card__details' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'blocked-card__label' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'blocked-card__row' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'blocked-card__value' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'onboarding-card' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'onboarding-card__label' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'onboarding-card__row' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'onboarding-card__summary' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'onboarding-card__value' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'public-shell' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'public-shell__body' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'public-shell__brand' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'public-shell__status' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'public-shell__topbar' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'public-shell__user' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'public-shell__user-name' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'welcome-page' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'welcome-page__actions' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'welcome-page__brand' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'welcome-page__brand-copy' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'welcome-page__brand-mark' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'welcome-page__brand-title' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'welcome-page__cta' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'welcome-page__eyebrow' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'welcome-page__header' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'welcome-page__hero-panel' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'welcome-page__lead' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'welcome-page__main' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'welcome-page__module-list' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'welcome-page__nav' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'welcome-page__pill' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'welcome-page__pill-list' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'welcome-page__section-title' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'welcome-page__stat' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'welcome-page__stats' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'welcome-page__summary' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'welcome-page__summary-note' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
        'welcome-page__title' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],
    ],

];

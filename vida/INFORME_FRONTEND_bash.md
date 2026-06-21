# VIDA 360 — Informe de Deuda Técnica de Front-end

**Generado:** 20/06/2026 15:08
**Analizado:** ./resources/views

---

> Este informe detecta cuatro categorías de problemas en las vistas Blade/Livewire:
> 1. CSS ad-hoc (bloques `<style>` y atributos `style=""` estructurales)
> 2. Colores hardcodeados (hex, rgb, rgba) fuera de los tokens VIDA
> 3. JS innecesario para navegación (donde un `<a href>` bastaría)
> 4. Clases Bootstrap residuales (incompatibles con el design system VIDA)

---


## 1. Bloques `<style>` en vistas Blade

⚠️ **5 ocurrencia(s) encontrada(s):**

- ~~`resources/views/errors/sin-rol.blade.php:85` — `<style>`~~
- ~~`resources/views/inicio.blade.php:85` — `<style>`~~
- ~~`resources/views/welcome.blade.php:85` — `<style>`~~
- ~~`resources/views/auth/login.blade.php:85` — `<style>`~~
- ~~`resources/views/auth/onboarding.blade.php:85` — `<style>`~~

## 2. Atributos `style=""` estructurales

⚠️ **14 ocurrencia(s) — estilos inline estructurales (no dinámicos):**

- ~~`resources/views/errors/sin-rol.blade.php:113` — `<i data-lucide="lock" style="width:40px;height:40px;" aria-hidden="true"></i>`~~
- ~~`resources/views/livewire/centros/selector-prestaciones-centro.blade.php:113` — `style="max-height: 360px;">`~~
- ~~`resources/views/livewire/centros/selector-prestaciones-centro.blade.php:113` — `style="max-height: 360px;">`~~
- ~~`resources/views/welcome.blade.php:113` — `<g style="mix-blend-mode: hard-light" class="transition-all delay-300 translate-y-0 opacity-100 duration-750 starting:op`~~
- ~~`resources/views/welcome.blade.php:113` — `<g style="mix-blend-mode: plus-darker" class="transition-all delay-300 translate-y-0 opacity-100 duration-750 starting:o`~~
- ~~`resources/views/welcome.blade.php:113` — `<g style="mix-blend-mode: hard-light" class="transition-all delay-300 translate-y-0 opacity-100 duration-750 starting:op`~~
- ~~`resources/views/welcome.blade.php:113` — `<g style="mix-blend-mode: hard-light" class="transition-all delay-300 translate-y-0 opacity-100 duration-750 starting:op`~~
- ~~`resources/views/welcome.blade.php:113` — `<g class="transition-all delay-300 translate-y-0 opacity-100 duration-750 starting:opacity-0 starting:translate-y-4" sty`~~
- ~~`resources/views/welcome.blade.php:113` — `<g class="transition-all delay-300 translate-y-0 opacity-100 duration-750 starting:opacity-0 starting:translate-y-4" sty`~~
- ~~`resources/views/welcome.blade.php:113` — `<g class="transition-all delay-300 translate-y-0 opacity-100 duration-750 starting:opacity-0 starting:translate-y-4" sty`~~
- ~~`resources/views/layouts/operativo.blade.php:113` — `style="width:16px;height:16px;"`~~
- ~~`resources/views/layouts/operativo.blade.php:113` — `:style="abierto ? 'transform:rotate(180deg)' : ''"`~~
- ~~`resources/views/layouts/operativo.blade.php:113` — `style="display:none;">`~~
- ~~`resources/views/layouts/operativo.blade.php:113` — `<i data-lucide="log-out" style="width:16px;height:16px;" aria-hidden="true"></i>`~~

## 3. Colores hardcodeados

⚠️ **80 ocurrencia(s) de colores fuera de tokens VIDA:**


### 3a. Hex / rgb en vistas Blade

- ~~`resources/views/welcome.blade.php:161` — `<body class="bg-[#FDFDFC] dark:bg-[#0a0a0a] text-[#1b1b18] flex p-6 lg:p-8 items-center lg:justify-center min-h-screen f`~~
- ~~`resources/views/welcome.blade.php:161` — `class="inline-block px-5 py-1.5 dark:text-[#EDEDEC] border-[#19140035] hover:border-[#1915014a] border text-[#1b1b18] da`~~
- ~~`resources/views/welcome.blade.php:161` — `class="inline-block px-5 py-1.5 dark:text-[#EDEDEC] text-[#1b1b18] border border-transparent hover:border-[#19140035] da`~~
- ~~`resources/views/welcome.blade.php:161` — `class="inline-block px-5 py-1.5 dark:text-[#EDEDEC] border-[#19140035] hover:border-[#1915014a] border text-[#1b1b18] da`~~
- ~~`resources/views/welcome.blade.php:161` — `<div class="text-[13px] leading-[20px] flex-1 p-6 pb-12 lg:p-20 bg-white dark:bg-[#161615] dark:text-[#EDEDEC] shadow-[i`~~
- ~~`resources/views/welcome.blade.php:161` — `<p class="mb-2 text-[#706f6c] dark:text-[#A1A09A]">Laravel has an incredibly rich ecosystem. <br>We suggest starting wit`~~
- `resources/views/welcome.blade.php:161` — `<li class="flex items-center gap-4 py-2 relative before:border-l before:border-[#e3e3e0] dark:before:border-[#3E3E3A] be`
- `resources/views/welcome.blade.php:161` — `<span class="relative py-1 bg-white dark:bg-[#161615]">`
- `resources/views/welcome.blade.php:161` — `<span class="flex items-center justify-center rounded-full bg-[#FDFDFC] dark:bg-[#161615] shadow-[0px_0px_1px_0px_rgba(0`
- `resources/views/welcome.blade.php:161` — `<span class="rounded-full bg-[#dbdbd7] dark:bg-[#3E3E3A] w-1.5 h-1.5"></span>`
- `resources/views/welcome.blade.php:161` — `<a href="https://laravel.com/docs" target="_blank" class="inline-flex items-center space-x-1 font-medium underline under`
- `resources/views/welcome.blade.php:161` — `<li class="flex items-center gap-4 py-2 relative before:border-l before:border-[#e3e3e0] dark:before:border-[#3E3E3A] be`
- `resources/views/welcome.blade.php:161` — `<span class="relative py-1 bg-white dark:bg-[#161615]">`
- `resources/views/welcome.blade.php:161` — `<span class="flex items-center justify-center rounded-full bg-[#FDFDFC] dark:bg-[#161615] shadow-[0px_0px_1px_0px_rgba(0`
- `resources/views/welcome.blade.php:161` — `<span class="rounded-full bg-[#dbdbd7] dark:bg-[#3E3E3A] w-1.5 h-1.5"></span>`
- `resources/views/welcome.blade.php:161` — `<a href="https://laracasts.com" target="_blank" class="inline-flex items-center space-x-1 font-medium underline underlin`
- `resources/views/welcome.blade.php:161` — `<a href="https://cloud.laravel.com" target="_blank" class="inline-block dark:bg-[#eeeeec] dark:border-[#eeeeec] dark:tex`
- `resources/views/welcome.blade.php:161` — `<div class="bg-[#fff2f2] dark:bg-[#1D0002] relative lg:-ml-px -mb-px lg:mb-0 rounded-t-lg lg:rounded-t-none lg:rounded-r`
- `resources/views/welcome.blade.php:161` — `<svg class="w-full text-[#F53003] dark:text-[#F61500] transition-all translate-y-0 opacity-100 max-w-none duration-750 s`
- `resources/views/errors/sin-rol.blade.php:161` — `.sinrol-card { background: #fff; border-radius: 12px; padding: 2.5rem 2rem; max-width: 440px; width: 100%; box-shadow: 0`
- `resources/views/welcome.blade.php:161` — `<div class="text-[13px] leading-[20px] flex-1 p-6 pb-12 lg:p-20 bg-white dark:bg-[#161615] dark:text-[#EDEDEC] shadow-[i`
- `resources/views/welcome.blade.php:161` — `<span class="flex items-center justify-center rounded-full bg-[#FDFDFC] dark:bg-[#161615] shadow-[0px_0px_1px_0px_rgba(0`
- `resources/views/welcome.blade.php:161` — `<span class="flex items-center justify-center rounded-full bg-[#FDFDFC] dark:bg-[#161615] shadow-[0px_0px_1px_0px_rgba(0`
- `resources/views/welcome.blade.php:161` — `<div class="absolute inset-0 rounded-t-lg lg:rounded-t-none lg:rounded-r-lg shadow-[inset_0px_0px_0px_1px_rgba(26,26,0,0`
- `resources/views/auth/login.blade.php:161` — `.pill { display: inline-block; background: rgba(255,255,255,0.15); border-radius: 20px; padding: 0.25rem 0.85rem; font-s`
- `resources/views/auth/login.blade.php:161` — `.access-note { font-size: 0.8rem; opacity: 0.7; border-top: 1px solid rgba(255,255,255,0.2); padding-top: 1rem; margin-t`

### 3b. Hex en archivos CSS (fuera de tokens)

- `resources/css/filament/admin/theme.css:173` — `--color-primary-50:  #EBF2F9;`
- `resources/css/filament/admin/theme.css:173` — `--color-primary-100: #C7DDEF;`
- `resources/css/filament/admin/theme.css:173` — `--color-primary-200: #9EC4E3;`
- `resources/css/filament/admin/theme.css:173` — `--color-primary-300: #6FAAD5;`
- `resources/css/filament/admin/theme.css:173` — `--color-primary-400: #4A8EC4;`
- `resources/css/filament/admin/theme.css:173` — `--color-primary-500: #2A5B8A;`
- `resources/css/filament/admin/theme.css:173` — `--color-primary-600: #214A72;`
- `resources/css/filament/admin/theme.css:173` — `--color-primary-700: #1A3A5A;`
- `resources/css/filament/admin/theme.css:173` — `--color-primary-800: #122842;`
- `resources/css/filament/admin/theme.css:173` — `--color-primary-900: #0A1828;`
- `resources/css/filament/admin/theme.css:173` — `--color-primary-950: #060D17;`
- `resources/css/filament/admin/theme.css:173` — `--color-primary-50:  #EBF2F9;`
- `resources/css/filament/admin/theme.css:173` — `--color-primary-100: #C7DDEF;`
- `resources/css/filament/admin/theme.css:173` — `--color-primary-200: #9EC4E3;`
- `resources/css/filament/admin/theme.css:173` — `--color-primary-300: #6FAAD5;`
- `resources/css/filament/admin/theme.css:173` — `--color-primary-400: #4A8EC4;`
- `resources/css/filament/admin/theme.css:173` — `--color-primary-600: #214A72;`
- `resources/css/filament/admin/theme.css:173` — `--color-primary-700: #1A3A5A;`
- `resources/css/filament/admin/theme.css:173` — `--color-primary-800: #122842;`
- `resources/css/filament/admin/theme.css:173` — `--color-primary-900: #0A1828;`
- `resources/css/filament/admin/theme.css:173` — `--color-primary-950: #060D17;`
- `resources/css/filament/admin/theme.css:173` — `--color-gray-200: #E6E1D8;`
- `resources/css/filament/admin/theme.css:173` — `--color-gray-300: #D7CFBE;`
- `resources/css/filament/admin/theme.css:173` — `--color-gray-400: #B8B0A0;`
- `resources/css/filament/admin/theme.css:173` — `--color-gray-500: #8C8070;`
- `resources/css/filament/admin/theme.css:173` — `--color-gray-600: #6B5F52;`
- `resources/css/filament/admin/theme.css:173` — `--color-gray-700: #4A3F35;`
- `resources/css/filament/admin/theme.css:173` — `--color-gray-800: #2E231A;`
- `resources/css/filament/admin/theme.css:173` — `--color-gray-950: #120D08;`
- `resources/css/filament/admin/theme.css:173` — `background-color: #FFFFFF;`
- `resources/css/filament/admin/theme.css:173` — `border-right: 1px solid #E6E1D8;`
- `resources/css/filament/admin/theme.css:173` — `color: #8C8070;`
- `resources/css/filament/admin/theme.css:173` — `color: #6B5F52;`
- `resources/css/filament/admin/theme.css:173` — `background-color: #FAF7F1;`
- `resources/css/filament/admin/theme.css:173` — `color: #1D160E;`
- `resources/css/filament/admin/theme.css:173` — `background-color: #FAF7F1;`
- `resources/css/filament/admin/theme.css:173` — `color: #1D160E;`
- `resources/css/filament/admin/theme.css:173` — `background-color: #FFFFFF;`
- `resources/css/filament/admin/theme.css:173` — `border-bottom: 1px solid #E6E1D8;`
- `resources/css/filament/admin/theme.css:173` — `background-color: #FAF7F1;`
- `resources/css/filament/admin/theme.css:173` — `background-color: #F2EADA;`
- `resources/css/filament/admin/theme.css:173` — `color: #6B5F52;`
- `resources/css/filament/admin/theme.css:173` — `background-color: #FAF7F1;`
- `resources/css/filament/admin/theme.css:173` — `background-color: #FFFFFF;`
- `resources/css/filament/admin/theme.css:173` — `border: 1px solid #E6E1D8;`
- `resources/css/filament/admin/theme.css:173` — `background-color: #2A5B8A;`
- `resources/css/filament/admin/theme.css:173` — `background-color: #214A72;`
- `resources/css/filament/admin/theme.css:173` — `background-color: #1A3A5A;`
- `resources/css/filament/admin/theme.css:173` — `outline: 2px solid #2A5B8A;`
- `resources/css/filament/admin/theme.css:173` — `border: 1px solid #E6E1D8;`
- `resources/css/filament/admin/theme.css:173` — `background-color: #FFFFFF;`
- `resources/css/filament/admin/theme.css:173` — `border-color: #2A5B8A;`
- `resources/css/filament/admin/theme.css:173` — `box-shadow: 0 0 0 1px #2A5B8A inset;`
- `resources/css/filament/admin/theme.css:173` — `color: #6B3D6B;`

## 4. JavaScript innecesario para navegación


### 4a. `window.location` en Blade / JS

✅ Sin ocurrencias.

### 4b. `@click` con rutas o redirects (posible `<a href>`)

✅ Sin ocurrencias detectadas.

### 4c. `wire:click` con métodos de tipo redirect/navigate

✅ Sin ocurrencias detectadas.

### 4d. `x-on:click` con navegación (Alpine.js)

✅ Sin ocurrencias detectadas.

## 5. Clases Bootstrap residuales

⚠️ **110 ocurrencia(s) de clases Bootstrap (incompatibles con el design system VIDA):**


**`resources/views/auth/login.blade.php`**

- Línea 301 — `19:<div class="container-fluid p-0">`
- Línea 301 — `20:    <div class="row g-0">`
- Línea 301 — `23:        <div class="col-lg-5 login-left d-none d-lg-flex">`
- Línea 301 — `25:                <div class="mb-4">`
- Línea 301 — `26:                    <h1 class="fw-bold fs-3 mb-1">VIDA 360</h1>`
- Línea 301 — `27:                    <p class="mb-0 opacity-75">Plataforma integrada de servicios sociales</p>`
- Línea 301 — `29:                <div class="mb-4">`
- Línea 301 — `45:        <div class="col-12 col-lg-7 login-right">`
- Línea 301 — `49:                <div class="text-end mb-3">`
- Línea 301 — `50:                    <span class="badge bg-secondary env-badge">{{ config('app.env_label') }}</span>`
- Línea 301 — `53:                <h2 class="fw-semibold mb-1 fs-5">{{ saludo() }}</h2>`
- Línea 301 — `54:                <p class="text-muted small mb-4">Introduce tus credenciales para acceder</p>`
- Línea 301 — `57:                    <div class="alert alert-danger py-2 small" role="alert">`
- Línea 301 — `65:                    <div class="mb-3">`
- Línea 301 — `71:                            class="form-control @error('email') is-invalid @enderror"`
- Línea 301 — `83:                    <div class="mb-4">`
- Línea 301 — `89:                            class="form-control @error('password') is-invalid @enderror"`
- Línea 301 — `99:                    <div class="d-grid mb-3">`
- Línea 301 — `100:                        <button type="submit" class="btn btn-primary">Entrar</button>`
- Línea 301 — `106:                <div class="text-center small">`
- Línea 301 — `110:                <div class="text-center small mt-3 text-muted">`

**`resources/views/auth/onboarding.blade.php`**

- Línea 301 — `14:<div class="onboarding-card card shadow-sm p-4 p-md-5">`
- Línea 301 — `15:    <div class="text-center mb-4">`
- Línea 301 — `16:        <span class="fs-1">👋</span>`
- Línea 301 — `18:    <h2 class="fw-bold mb-1">`
- Línea 301 — `21:    <p class="text-muted mb-4">Tu cuenta está lista. Estos son tus datos de acceso configurados:</p>`
- Línea 301 — `23:    <ul class="list-unstyled mb-4">`
- Línea 301 — `24:        <li class="mb-2">`
- Línea 301 — `29:        <li class="mb-2">`
- Línea 301 — `38:        <div class="d-grid">`
- Línea 301 — `39:            <button type="submit" class="btn btn-primary btn-lg">Empezar</button>`

**`resources/views/errors/sin-rol.blade.php`**

- Línea 301 — `67:    <form method="POST" action="{{ route('logout') }}" class="text-center">`
- Línea 301 — `69:        <button type="submit" class="btn btn-outline-secondary btn-sm px-4">`

**`resources/views/filament/pages/demo-worlds-page.blade.php`**

- Línea 301 — `24:                <code class="rounded bg-gray-100 px-1 py-0.5 text-xs dark:bg-gray-800">`
- Línea 301 — `45:                    <dl class="mb-4 grid grid-cols-3 gap-3 text-center">`
- Línea 301 — `50:                            <dd class="mt-0.5 text-2xl font-semibold tabular-nums text-gray-900 dark:text-white">`
- Línea 301 — `58:                            <dd class="mt-0.5 text-2xl font-semibold tabular-nums text-gray-900 dark:text-white">`
- Línea 301 — `66:                            <dd class="mt-0.5 text-2xl font-semibold tabular-nums text-gray-900 dark:text-white">`

**`resources/views/filament/prestaciones/snapshot-modal.blade.php`**

- Línea 301 — `7:                        <th class="py-2 pr-4 font-semibold text-gray-600 dark:text-gray-400">Campo</th>`
- Línea 301 — `8:                        <th class="py-2 font-semibold text-gray-600 dark:text-gray-400">Valor</th>`
- Línea 301 — `14:                            <td class="py-1 pr-4 font-mono text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap`
- Línea 301 — `17:                            <td class="py-1 text-gray-800 dark:text-gray-200">`

**`resources/views/inicio.blade.php`**

- Línea 301 — `20:    <span class="fw-semibold">{{ config('app.name') }}</span>`
- Línea 301 — `21:    <div class="d-flex align-items-center gap-2">`
- Línea 301 — `27:<div class="container py-5 text-center">`

**`resources/views/layouts/operativo.blade.php`**

- Línea 301 — `80:                    class="topbar__user-btn"`

**`resources/views/livewire/admin/gestor-unidades-organizativas.blade.php`**

- Línea 301 — `10:<div class="p-6">`
- Línea 301 — `13:    <div class="flex items-center justify-between mb-6">`
- Línea 301 — `16:            <p class="mt-1 text-sm text-gray-500">`
- Línea 301 — `22:            class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700"`
- Línea 301 — `30:        <div class="p-4 mb-4 text-green-800 bg-green-100 rounded-md">`
- Línea 301 — `36:    <div class="mb-4">`
- Línea 301 — `41:            class="w-full px-4 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:r`
- Línea 301 — `50:            <div class="p-8 text-center text-gray-500">`
- Línea 301 — `59:            <div class="w-full max-w-lg p-6 bg-white rounded-lg shadow-xl">`
- Línea 301 — `61:                <h2 class="mb-4 text-lg font-semibold text-gray-900">`
- Línea 301 — `69:                        <label class="block mb-1 text-sm font-medium text-gray-700">`
- Línea 301 — `75:                            class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focu`
- Línea 301 — `79:                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>`
- Línea 301 — `85:                        <label class="block mb-1 text-sm font-medium text-gray-700">`
- Línea 301 — `90:                            class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focu`
- Línea 301 — `98:                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>`
- Línea 301 — `104:                        <label class="block mb-1 text-sm font-medium text-gray-700">`
- Línea 301 — `109:                            class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none foc`
- Línea 301 — `117:                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>`
- Línea 301 — `122:                    <div class="flex justify-end gap-3 pt-2">`
- Línea 301 — `126:                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-`
- Línea 301 — `132:                            class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700`

**`resources/views/livewire/admin/partials/uo-nodo.blade.php`**

- Línea 301 — `10:    <div class="flex items-center justify-between px-4 py-3 hover:bg-gray-50"`
- Línea 301 — `23:                <span class="ml-2 px-1.5 py-0.5 text-xs rounded bg-gray-100 text-gray-500">`
- Línea 301 — `27:                    <span class="ml-1 px-1.5 py-0.5 text-xs rounded bg-yellow-100 text-yellow-700">`
- Línea 301 — `38:                class="px-3 py-1 text-xs text-blue-700 bg-blue-50 rounded hover:bg-blue-100"`
- Línea 301 — `48:                    class="px-3 py-1 text-xs text-red-700 bg-red-50 rounded hover:bg-red-100"`
- Línea 301 — `56:                    class="px-3 py-1 text-xs text-green-700 bg-green-50 rounded hover:bg-green-100"`

**`resources/views/livewire/centros/selector-prestaciones-centro-modal.blade.php`**

- Línea 301 — `1:<div class="p-1">`

**`resources/views/livewire/centros/selector-prestaciones-centro.blade.php`**

- Línea 301 — `15:                class="block w-full rounded-lg border border-gray-300 bg-white py-2 pl-9 pr-3 text-sm`
- Línea 301 — `29:                        class="rounded-full border px-3 py-1 text-xs font-medium transition-colors`
- Línea 301 — `46:        <div class="col-span-2 overflow-y-auto rounded-lg border border-gray-200 dark:border-gray-700"`
- Línea 301 — `52:                    <div class="sticky top-0 bg-gray-50 px-4 py-2 dark:bg-gray-900">`
- Línea 301 — `60:                        <div class="flex items-start gap-3 border-b border-gray-50 px-4 py-3`
- Línea 301 — `64:                            <div class="pt-0.5">`
- Línea 301 — `96:                                <p class="mt-0.5 font-mono text-xs text-gray-400">`
- Línea 301 — `106:                <div class="px-4 py-8 text-center text-sm text-gray-400 dark:text-gray-500">`
- Línea 301 — `118:                <span class="rounded-full bg-primary-50 px-2 py-0.5 text-xs font-medium`
- Línea 301 — `127:                    <p class="px-3 py-6 text-center text-xs text-gray-400 dark:text-gray-500">`
- Línea 301 — `137:                        <div class="flex items-start gap-2 border-b border-gray-50 px-3 py-2.5`
- Línea 301 — `165:    <div class="flex items-center justify-between border-t border-gray-200 pt-3 dark:border-gray-700">`
- Línea 301 — `174:            class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white`
- Línea 301 — `187:            <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">`
- Línea 301 — `188:                <div class="w-full max-w-md rounded-xl border border-gray-200 bg-white p-6`
- Línea 301 — `191:                    <div class="mb-4 flex items-start justify-between gap-3">`
- Línea 301 — `194:                            <h3 class="mt-1 text-base font-medium text-gray-900 dark:text-white">`
- Línea 301 — `228:                    <div class="mt-5 text-right">`
- Línea 301 — `232:                            class="rounded-lg border border-gray-200 px-4 py-2 text-sm text-gray-600`

**`resources/views/welcome.blade.php`**

- Línea 301 — `22:    <body class="bg-[#FDFDFC] dark:bg-[#0a0a0a] text-[#1b1b18] flex p-6 lg:p-8 items-center lg:justify-center min-h-s`
- Línea 301 — `23:        <header class="w-full lg:max-w-4xl max-w-[335px] text-sm mb-6 not-has-[nav]:hidden">`
- Línea 301 — `29:                            class="inline-block px-5 py-1.5 dark:text-[#EDEDEC] border-[#19140035] hover:border-[#191`
- Línea 301 — `36:                            class="inline-block px-5 py-1.5 dark:text-[#EDEDEC] text-[#1b1b18] border border-transpar`
- Línea 301 — `44:                                class="inline-block px-5 py-1.5 dark:text-[#EDEDEC] border-[#19140035] hover:border-[`
- Línea 301 — `53:            <main class="flex max-w-[335px] w-full flex-col-reverse lg:max-w-4xl lg:flex-row">`
- Línea 301 — `54:                <div class="text-[13px] leading-[20px] flex-1 p-6 pb-12 lg:p-20 bg-white dark:bg-[#161615] dark:text-`
- Línea 301 — `55:                    <h1 class="mb-1 font-medium">Let's get started</h1>`
- Línea 301 — `56:                    <p class="mb-2 text-[#706f6c] dark:text-[#A1A09A]">Laravel has an incredibly rich ecosystem. <br>`
- Línea 301 — `57:                    <ul class="flex flex-col mb-4 lg:mb-6">`
- Línea 301 — `58:                        <li class="flex items-center gap-4 py-2 relative before:border-l before:border-[#e3e3e0] dark`
- Línea 301 — `59:                            <span class="relative py-1 bg-white dark:bg-[#161615]">`
- Línea 301 — `85:                        <li class="flex items-center gap-4 py-2 relative before:border-l before:border-[#e3e3e0] dark`
- Línea 301 — `86:                            <span class="relative py-1 bg-white dark:bg-[#161615]">`
- Línea 301 — `115:                            <a href="https://cloud.laravel.com" target="_blank" class="inline-block dark:bg-[#eeeeec`
- Línea 301 — `121:                <div class="bg-[#fff2f2] dark:bg-[#1D0002] relative lg:-ml-px -mb-px lg:mb-0 rounded-t-lg lg:rounded`

## 6. Botones sin color de texto explícito

Detecta elementos `<button` o `role="button"` con clases `bg-*` pero sin `text-*`.

✅ Sin botones detectados con `bg-` sin `text-`.

---

## Resumen ejecutivo

| Categoría | Ocurrencias |
|-----------|-------------|
| 1. Bloques `<style>` en Blade | 5 |
| 2. Atributos `style=""` estructurales | 14 |
| 3a. Colores hex/rgb en Blade | 26 |
| 3b. Colores hex en CSS ad-hoc | 58 |
| 4. JS innecesario para navegación | 0 |
| 5. Clases Bootstrap residuales | 110 |
| 6. Botones sin color de texto | 0 |
| **TOTAL** | **213** |

_Informe generado por `analizar-frontend.sh`. Revisa manualmente las ocurrencias antes de corregirlas._

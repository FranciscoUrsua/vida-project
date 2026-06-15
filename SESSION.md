# SESSION — VIDA 360

_Actualizado: 2026-06-15_

## Tarea completada

Corrección de tres bugs en la pantalla de intervención (`CiudadanoPage` y páginas de escala/valoración):

1. **Iconos Lucide desaparecen al navegar** — `livewire:updated` no existe en Livewire 4.2.1.
   Reemplazado por `Livewire.hook('morphed', () => queueMicrotask(() => lucide.createIcons(...)))`.
   Registrado dentro de `livewire:initialized`. (`layouts/operativo.blade.php`)

2. **Botones de la toolbox pierden contenido** — El modo compacto del Cambio 6 (7 columnas,
   labels ocultos con `display:none`) se confundía con pérdida de contenido. Eliminado: la
   toolbox siempre muestra 4 columnas y labels visibles; el estado activo se indica solo con color.

3. **Herramienta Escala — dos bugs:**
   - *«Abrir en pantalla completa» no aparece siempre*: `wire:model` en Livewire 4 es diferido;
     el botón `@if($formEscala['tipo_escala_id'])` no aparece hasta el siguiente re-render.
     Fix: `wire:model.live` en los selects de escala y valoración.
   - *«No se encontró el instrumento seleccionado»*: Livewire 4 full-page components no pasan
     query string params a `mount()` (solo parámetros de ruta). Fix: leer con `request()->query()`.
   - *TypeError al guardar*: `guardar()` declaraba `RedirectResponse` como retorno pero Livewire 4
     devuelve su propio `Redirector`. Fix: retorno `void` + `$this->redirect()`. Aplicado en
     `RegistrarEscalaPage` y `RegistrarValoracionPage`.
   - *Columna izquierda volvió a 300px fijos*: restaurado `1fr 2fr` en `.ciudadano-layout`.

## Estado actual

### Archivos modificados en esta sesión

| Archivo | Cambio |
|---|---|
| `resources/views/layouts/operativo.blade.php` | Hook `morphed` para Lucide icons |
| `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php` | `wire:key`, sin modo compacto, `wire:model.live` en selects |
| `Modules/Intervencion/app/Http/Livewire/RegistrarEscalaPage.php` | `mount()` lee query string; `guardar()` → void + `$this->redirect()` |
| `Modules/Intervencion/app/Http/Livewire/RegistrarValoracionPage.php` | Ídem para valoración |
| `resources/css/app-operativo.css` | `.ciudadano-layout` → `1fr 2fr` |

### Comportamiento actual de la herramienta Escala
- El selector de instrumento re-renderiza el componente inmediatamente al cambiar (`.live`).
- El botón «Abrir en pantalla completa» aparece en cuanto se elige un instrumento.
- La pantalla completa carga correctamente el TipoEscala.
- Al guardar redirige de vuelta a la Historia Social.

### TODOs documentados en código (sin cambios)
- `CiudadanoPage::statPrestaciones()`: integrar con módulo Prestaciones cuando esté disponible.
- `ciudadano-page.blade.php`: route PISO show (Entrega 4).
- `ciudadano-page.blade.php`: menú ⋯ con acciones adicionales del expediente.
- `ciudadano-page.blade.php`: modal "Ver historial completo" de accesos.
- `ciudadano-page.blade.php`: unidades_convivencia (tabla pendiente).

### Estado de los tests TF-LW-CIU-*
Siguen en rojo por `ciudadano_id = 9001` hardcodeado (viola FK). No era objetivo de esta sesión.

## Siguiente paso recomendado

1. **Corregir CiudadanoPageTest** — Prioridad alta: los tests fallan por FK antes de llegar al código.
2. **Integrar `statPrestaciones`** con el módulo Prestaciones una vez disponible.
3. **Modal "Ver historial completo"** de accesos (TODO en ambas vistas).
4. **Flujo de autorización de colectivos protegidos** — AccesoProtegido (Módulo Ciudadanía) pendiente.

## Contexto técnico para retomar

### Livewire 4 — restricciones descubiertas esta sesión
- `livewire:updated` no existe. Usar `Livewire.hook('morphed', cb)` tras `livewire:initialized`.
- Full-page components (route handlers): `mount()` solo recibe parámetros de ruta, no query string.
  Leer query string con `request()->query('param')` directamente en `mount()`.
- `redirect()` en un componente devuelve `Livewire\Features\SupportRedirects\Redirector`.
  Usar `$this->redirect(route(...))` con retorno `void`, no `RedirectResponse`.
- `wire:model` es diferido. Usar `wire:model.live` cuando el re-render inmediato es necesario
  (p.ej. botones condicionales que dependen del valor seleccionado).

### Layout CiudadanoPage
- `.ciudadano-layout`: `grid-template-columns: 1fr 2fr` (columna ciudadano 1/3, herramientas 2/3).
- Toolbox: 4 columnas fijas, labels siempre visibles, estado activo solo por color.
- El branding (logo/nombre) se almacena en `organizacion_configuracion` via `ConfiguracionService`.
- El plan de intervención lee de `unidades_organizativas.plan_nombre_corto / plan_nombre_completo`.

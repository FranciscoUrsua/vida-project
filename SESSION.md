# SESSION — VIDA 360

_Actualizado: 2026-06-15_

## Tarea completada

Filtro contextual del timeline + modal de detalle de apunte en `CiudadanoPage`.

## Estado actual

### Cambios aplicados en esta sesión

**Filtro sugerido (Cambio 1)**
- `$filtroSugerido: ?string` en `CiudadanoPage` — al activar una herramienta, el filtro
  del timeline correspondiente se resalta (borde primario, fondo transparente, hint ↑)
  sin aplicarse: solo es visual.
- `seleccionarHerramienta()` actualiza `$filtroSugerido` con el mapa herramienta→filtro.
- Timeline expandido a 8 filtros: Todos, Plan, Entrevista, Anotación, Derivación,
  Gestión, Valoración, Escala — todos con estado activo y sugerido diferenciados.
- `apuntesHS()` ampliado para filtrar por los 7 tipos de `TipoApunte`.
- CSS: `.hs-timeline-filter`, `.hs-timeline-filter--activo`, `.hs-timeline-filter--sugerido`,
  `.hs-timeline-filter__hint`.

**Modal de detalle de apunte (Cambio 2)**
- 4 propiedades en `CiudadanoPage`: `$modalApunteId`, `$modalApunteTipo`,
  `$modalApunteDatos`, `$modalApunteAbierto`.
- `verApunte(int $apunteId)`: carga datos del apunte desde la colección en memoria,
  lazy-carga `PaseEscala→tipoEscala` para tipo escala.
- `cerrarModalApunte()`: limpia todo el estado modal.
- Timeline: `wire:click` cambiado de `toggleApunte` a `verApunte`; previsualización
  del contenido truncada en una línea.
- Modal genérico para entrevista, anotación, derivación, gestión.
- SlideOver (panel lateral) para escala (score, interpretación, secciones) y valoración.
- Cierre con botón ×, click fuera del panel y tecla Escape (`x-on:keydown.escape.window`).
- CSS: `.hs-modal-*`, `.hs-slideover-*`, `.hs-escala-*`.

**Fix de tests**
- `ciudadano_id = 9001` (hardcodeado, violaba FK) reemplazado por `Ciudadano::factory()->create()->id`.
- Tests TF-LW-CIU-28, 29, 30 añadidos.
- Suite completa del filtro: 52/52 en verde.

### TODOs documentados en código (sin cambios)
- `CiudadanoPage::statPrestaciones()`: integrar con módulo Prestaciones.
- Modal "Ver historial completo" de accesos (enlace "Ver todo").
- Route PISO show (Entrega 4).
- Menú ⋯ con acciones del expediente.
- `unidades_convivencia` (tabla pendiente).

## Siguiente paso recomendado

1. **Modal "Ver historial completo" de accesos** — el enlace "Ver todo" existe pero apunta a `#`.
2. **Integrar `statPrestaciones`** con el módulo Prestaciones cuando esté disponible.
3. **Flujo de autorización de colectivos protegidos** — AccesoProtegido (Módulo Ciudadanía) pendiente.
4. **PISO/plan detail page** (Entrega 4).

## Contexto técnico para retomar

### Livewire 4 — restricciones consolidadas esta sesión
- `livewire:updated` no existe. Usar `Livewire.hook('morphed', cb)` tras `livewire:initialized`.
- Full-page components: `mount()` solo recibe parámetros de ruta, no query string.
  Leer con `request()->query('param')` directamente.
- `redirect()` en un componente devuelve `Livewire\Features\SupportRedirects\Redirector`.
  Usar `$this->redirect(route(...))` con retorno `void`.
- `wire:model` es diferido. Usar `wire:model.live` cuando el re-render inmediato es necesario.
- `get*Property()` (convención Livewire 3) está soportado por compatibilidad en Livewire 4.

### Layout CiudadanoPage
- `.ciudadano-layout`: `1fr 2fr` (columna ciudadano 1/3, herramientas 2/3).
- Toolbox: 4 columnas fijas, labels siempre visibles, estado activo solo por color.
- Filtros: 8 pills con estado activo (fondo sólido) y sugerido (solo borde).
- Modal: z-index 500 (sobre todo el layout). Alpine para cierre con Escape.

# Plan de corrección UI

Complementa `revision-frontend-ui.md`. Sigue sin tocarse el código. Orden: primero la red de CI (clases PHP + tests visuales), después catálogo y Sass, después partir pantallas y recortar repintados.

Criterio de hecho:

1. `ui:auditar` falla si se escribe `bg-protectd` en un `match`.
2. Quitar `bg-protected` del resultado nivel 3 rompe un test.
3. Teclear en el buscador de UC no vuelve a pintar el timeline.

---

## Fase 0 — Contratos de test (1–2 días)

Antes de mover clases, fijar lo que no puede romperse.

- En `AccesosExpedienteTest`, además de `data-acceso`, afirmar el HTML visual: `bg-warning-subtle` en sospechoso, `bg-danger-subtle` + `border-danger` + el texto de revisión en anómalo, `opacity-75` en propio. Renombrar los métodos o recuperar la aserción de clase.
- En `BuscarCiudadanoPageTest` TF-LW-BUS-04, hacer `->html()` y afirmar `bg-protected`, `text-protected-emphasis`, el copy de colectivo protegido y el botón de solicitar acceso.
- Test de snapshot (o assert sobre el CSS compilado) de que existen `.bg-protected`, `.text-protected`, `.text-protected-emphasis`, `.btn-outline-protected`, `.alert-protected`. Clava el orden de `_bootstrap-vida.scss` sin parsear Sass.

## Fase 1 — El auditor ve `match` / `@php` (2–3 días)

Objetivo: una errata en un mapa PHP falle R1 en CI.

- En `AuditorUi::clasesUsadas()`, extraer literales de cadenas que parezcan clases Bootstrap/catálogo dentro de `@php … @endphp` y de `match() { … }`. Heurística segura: tokens `/^(btn|bg|text|border|badge|alert|rounded|fw|fs|opacity|d|p|m|gap|col|row|list-group|form)-[\w-]+$/` o que estén en el catálogo. No tratar como clase cualquier string (`'Cerrada'`, `'vacaciones'`).
- Extraer literales a la derecha de `=>` en `match` y en arrays tipo `$coloresTipo` / `$estados`. Comillas simples y dobles.
- Cuando `class="… {{ $var }}"`, si `$var` se asignó en el mismo fichero a un literal o a un `match`, resolver estáticamente esas ramas. Si no, aviso (como las clases dinámicas), no silencio.
- Test nuevo en `UiAuditarTest`: vista con `@php $x = 'btn-fantasma'; @endphp` y `class="{{ $x }}"` → R1. Otro con `match(...) => 'bg-protectd'` → R1.
- No hace falta ejecutar PHP: basta regex/AST de literales. Documentar el límite (concatenación sigue siendo aviso).

## Fase 2 — Una sola fuente para badges / estados (1–2 días)

Hoy el mismo mapa está copiado en `ciudadano-page`, `ficha-ciudadano-page`, `buscar-ciudadano-page`, tres vistas de Agenda y `actividades-page`.

- Helper o enum con `clasesCss(): string` (p. ej. `EstadoHistoria::Abierto->badgeClasses()`). Blade: `class="badge {{ $historia->estado->badgeClasses() }}"`.
- El auditor, con la fase 1, verá los literales en el enum (R5 ya cuenta uso desde PHP; R1 pasará a validarlos).
- Mapa de nivel de búsqueda (1/2/3 → `bg-success` / `bg-warning` / `bg-protected`) en un solo sitio, con test que pinte el HTML.
- Mover ahí también las etiquetas de sexo / nivel de identificación (hoy `ficha-ciudadano-page` usa `H`/`M`/`NB`; `Requisitos.md` pide `M`/`F`/`D`).

## Fase 3 — Sass protegido contra un reorder (medio día)

- Comentario de guarda al inicio de `_bootstrap-vida.scss`: bloque A (functions + overrides + tokens), B (variables + variables-dark), C (merge `$theme-colors`), D (`maps`), E (merges subtle/emphasis), F (mixins + utilities + piezas + api). Prohibido mover C después de D.
- El test de fase 0 sobre el CSS compilado es la red de verdad. Opcional: script en CI que falle si `protected` no está en `$theme-colors` antes de `@import maps`.
- No importar tokens que usen `$primary` / mixins hasta después de `variables`.
- Pasar `html { font-size: 18px }` a `$font-size-base` en `_bootstrap-overrides`, para que las utilidades `rem` de Bootstrap y el cuerpo compartan la misma escala.

## Fase 4 — Catálogo: borrar o renombrar (1–2 días)

Después del auditor ampliado, para que R5 no dé huérfanos a medias.

- Renombrar `activo` → `op-nav-item--activo` y `alerta` → `op-nav-badge--alerta`. Actualizar SCSS, Blade y tests de navegación.
- Sustituir `op-collapse-label-*` por dos spans con `d-none` y `.collapsed`, o un solo texto + `aria-expanded`.
- Valorar tirar `op-empty` / `__icon` / `__text` y usar `text-center text-body-secondary py-5` + `icon-20`.
- Colapsar `icon-12..40` a `icon-sm` (14), `icon` (16), `icon-lg` (20), `icon-empty` (40). Fuera 13 y 15.
- `mensajes-toasts`: dejar solo el offset bajo el topbar (utilidad con `$vida-topbar-height`) y usar `.toast-container.position-fixed.start-50.translate-middle-x`, que ya está en la vista.
- `topbar__title`, `topbar__title-sep`, `topbar__user-nombre` → `h5 mb-0 text-truncate` / `text-body-secondary`. Dejar `topbar__logo*` y el ancho 196 px.
- Extraer `<x-op.badge-estado>`, `<x-op.empty>`, `<x-op.lista-scroll>` para que Claude no improvise la receta en cada módulo.

## Fase 5 — Tests y CI (medio día)

- `ui:auditar` sin `--informe` en el workflow cuando la fase 1 esté verde.
- No añadir reglas nuevas (R7) hasta que R1 cubra PHP.

## Fase 6 — Dejar de repintar pantallas enteras (3–5 días)

Problema: `CiudadanoPage` (1.131 líneas PHP, ~20 `#[Computed]`, Blade ~63 KB) y `plan-page` (~66 KB) remontan todo el expediente por un buscador `live`.

- Partir en hijos Livewire: timeline, widget de accesos, UC, toolbox, ficha de relaciones. `lazy`/`defer` y `wire:key` estable. El padre solo orquesta ciudadano/historia.
- Objetivo: teclear en el buscador de UC no regenera el timeline.
- Mismo corte en `ficha-ciudadano-page` (`relacionBusqueda` con `wire:model.live`).
- Sacar `$herramientas` a constante de clase o config. Quitar `wire:key` de bloques estáticos (`toolbox-grid`).
- No unificar el shell a `navbar` Bootstrap en el mismo PR que el auditor.

## Fase 7 — Menos trabajo por request (1–2 días)

- Sustituir `\App\Models\Ciudadano::find($ucCiudadanoSeleccionado)` en `ciudadano-page.blade.php` por un `#[Computed]` o propiedad ya cargada.
- Cachear `Configuracion::logoUrl()` / `nombreAplicacion()` (o pasarlas al layout una vez).
- En `plan-page`, sacar `$datos`, `$doc` y `$yaEnPlan` de los bucles a Computed.
- Toasts: `wire:poll.60s` solo si hay alertas pendientes, o endpoint ligero / Echo. `wire:ignore` + Alpine para minimizar/expandir, de modo que Livewire no morphee el toast abierto (era el motivo de pintar todo desde sesión).
- Admin `uo-nodo`: si algún día entra en el ámbito del auditor, pasar el `padding-left` a custom property `--uo-nivel` o dejar solo el valor dinámico en `style` (ya permitido).

---

## Orden

| Orden | Qué | Para qué |
|---|---|---|
| 0 | Tests visuales + snapshot `protected` | Red de seguridad |
| 1 | Auditor lee `match`/`@php` | CI pilla erratas de Claude |
| 2 | Enums/helper de badges + sexo | Una fuente, menos Blade PHP |
| 3 | Guarda Sass + `$font-size-base` | `protected` no se pierde; `rem` coherentes |
| 4 | Podar catálogo + componentes `x-op.*` | Menos clases inventadas |
| 5 | CI bloqueante | No volver al modo informe |
| 6 | Partir `CiudadanoPage` / `PlanPage` | Deja de repintarse el mundo |
| 7 | Poll de toasts + queries en vista | Menos trabajo por request |

No meter Tailwind. No unificar Filament con este catálogo. No reescribir el shell a `navbar` en el mismo PR que el auditor.

---

## Cobertura respecto al diagnóstico

| Hallazgo en `revision-frontend-ui.md` | Fase |
|---|---|
| R1 no ve `match`/`@php` | 1 |
| Badges duplicados en 7 vistas | 2 |
| Orden Sass / utilidades `protected` | 0 + 3 |
| Tests solo `data-acceso` / solo `nivel` | 0 |
| Catálogo sobrante (`activo`, `alerta`, icons, toasts, topbar, empty, collapse) | 4 |
| `font-size: 18px` ad-hoc | 3 |
| Nav/topbar que reinventan Bootstrap | 4 |
| Pocas piezas `x-op.*` | 4 |
| Pantallas-dios + `wire:model.live` | 6 |
| `Ciudadano::find` y logo en cada render | 7 |
| `wire:poll.60s` global | 7 |
| Sexo `H/M/NB` vs `M/F/D` | 2 |
| Markup duplicado (nombre en dropdown) | 6 (al tocar el shell, menor) |

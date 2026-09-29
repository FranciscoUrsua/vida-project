# Revisión del frontend reformado (Claude CLI)

Informe de problemas. No se ha modificado código.

Ámbito: superficie operativa/pública (Blade + Livewire + Bootstrap 5.3), `php artisan ui:auditar`, `_bootstrap-vida.scss`, tests de restricción visual y `config/ui-catalogo.php` (47 clases). Código: rama `main` de [FranciscoUrsua/vida-project](https://github.com/FranciscoUrsua/vida-project), directorio `vida/`. Fecha: 29 de septiembre de 2026.

Criterio original: clases asignadas desde PHP; orden de imports Sass; tests que ya no miran clases CSS; clases del catálogo que Bootstrap ya cubre. Añadido después: estilos ad-hoc, clases inventadas evitables, pantallas que se repintan enteras, queries en la vista y toasts globales.

---

## 1. `ui:auditar` no ve las clases asignadas en `match` / `@php`

`AuditorUi::clasesUsadas()` solo extrae literales de:

- atributo `class="…"`
- `:class` / `x-bind:class`
- `@class([...])`
- el patrón `'class' => '…'`

No recorre bloques `@php`, `match()`, arrays asociativos ni variables interpoladas (`class="… {{ $estadoClase }}"`). El test `r1_verifica_literales_dentro_de_expresiones_blade_y_de_class` cubre el caso con literales *dentro* del atributo (`{{ $activo ? 'btn-primary' : 'btn-fantasma' }}`), no el caso en que el literal vive en PHP y el atributo solo interpola la variable.

Consecuencia: una errata (`bg-protectd`, `text-protegido-emphasis`, `btn-fantasma`) en un `match` o en un array de `@php` no genera R1 y el CI la deja pasar. R5 sí puede contar esas cadenas como «uso» de una clase del catálogo si aparecen en PHP, pero no valida que existan en el CSS compilado.

Puntos ciegos concretos:

| Vista | Qué asigna | Cómo se pinta |
|---|---|---|
| `ciudadano-page.blade.php` | `match($historia->estado)` → `$estadoClase`; array `$coloresTipo` | `class="badge … {{ $estadoClase }}"` y `{{ $coloresTipo[$apunte->tipo->value] ?? 'bg-secondary' }}` |
| `buscar-ciudadano-page.blade.php` | `match($resultado['nivel'])` → `$puntoClase` = `bg-protected` / `bg-warning` / `bg-success` | El color de colectivos protegidos (el tema custom) no lo audita R1 |
| `ficha-ciudadano-page.blade.php` | Tres `match`: `$nivelClase`, `$estadoClase` de prestación, `$tipoClase` de registro | `class="badge {{ $var }}"` |
| Agenda supervisor | `$chipClass`, `$estadoClass`, `$tipoChip` | `class="badge {{ $var }}"` |
| `actividades-page.blade.php` | `$estados[$sesion->estado] ?? ['bg-secondary-subtle …']` | `class="badge {{ $badgeClass }}"` |

Tampoco se parsean `"class" => "…"` con comillas dobles ni concatenaciones. Las clases dinámicas tipo `badge-{{ $tipo }}` solo producen aviso, no fallo.

El mismo mapa `bg-*-subtle text-*-emphasis` está copiado en siete vistas. Cada copia es un sitio donde una errata se escapa.

---

## 2. `_bootstrap-vida.scss`: orden de imports y fallos silenciosos

El orden actual coincide, en lo sustancial, con la guía de Bootstrap 5.3:

`functions` → overrides/tokens → `variables` → `variables-dark` → merge de `$theme-colors` → `maps` → merges subtle/emphasis → `mixins` → `utilities` → componentes → `helpers` → `utilities/api`.

Hoy no hay un error de orden que impida compilar. El riesgo es silencioso: Sass no avisa y el CSS sale incompleto.

- Mover el `map-merge` de `$theme-colors` **después** de `maps`: `maps.scss` congela `$theme-colors-rgb`. Desaparecen `btn-protected`, `text-protected`, `alert-protected`, etc. El build sigue verde.
- Mover el merge de `$theme-colors-text` / `$utilities-*-subtle` **antes** de `maps`: esos mapas se crean en `maps.scss`. `map-merge` sobre un mapa no definido aborta o pisa el de Bootstrap. Hoy están después de `maps` (correcto); no hay test que lo fije.
- `_vida-sass-tokens.scss` se importa **antes** de `variables`. Hoy no usa `$primary` ni mixins. Si alguien añade `tint-color($primary)` ahí, Sass falla o usa `null` sin un test.
- No hay comprobación de que las utilidades `protected` existan en el CSS compilado. Como `bg-protected` y `text-protected-emphasis` solo salen de un `match`/`@php`, una rotura del mapa no la ve ni el auditor ni el CI.

Detalle: `_bootstrap-components.scss` reestila `.btn`, `.btn-outline-secondary`, `.form-label`, `thead th` (uppercase + tracking) con tokens `$vida-*`. R4 no exige catálogo al reestilar Bootstrap, pero es CSS acoplado a elementos concretos.

---

## 3. Tests que ya no miran la clase CSS de la restricción

Varios tests se reescribieron para no acoplarse a Bootstrap. El contrato *visual* puede quitarse y el test sigue verde.

- **`AccesosExpedienteTest` TF-AUD-INT-05/06/07.** Los métodos se llaman `…_tiene_clase_sospechoso`, `…_tiene_clase_anomalo`, `accesos_propios_tienen_clase_propio`. Afirman `data-acceso="sospechoso|anomalo|propio"` y, en anomalía, el texto «Modificación desde otra UO — revisar». No comprueban `bg-warning-subtle`, `bg-danger-subtle`, `border-danger` ni `opacity-75`. Si se borran las ramas `@class` de `ciudadano-page.blade.php` (~550–555), los tests no fallan. `data-acceso` no pinta nada; es un gancho.
- **`BuscarCiudadanoPageTest` TF-LW-BUS-04.** `resultado_colectivo_protegido_muestra_nivel_3` solo lee `$resultados[0]['nivel'] === 3`. No renderiza HTML. Se puede quitar `bg-protected`, `text-protected-emphasis` y el copy de colectivo protegido sin que el test se entere. El botón «Solicitar acceso» del comentario del test tampoco se afirma en el HTML.
- Los nombres (`tiene_clase_*`) ya no asertan clase.

Lo que sí seguiría fallando si se quita la restricción de *datos* (no la visual): nivel, visibilidad de domicilio, conteo de accesos, no exposición de IP.

---

## 4. Catálogo (47 clases): qué sobra o choca con Bootstrap

Ninguna está `pendiente`. Varias son justificables (shell, iconos SVG, alto de ventana). Candidatas a sobrar o mal modeladas:

| Clase | Problema |
|---|---|
| `activo` | Estado de `.op-nav-item`. Bootstrap usa `.active`. Nombre genérico: cualquier `class="activo"` cuenta como uso del catálogo. Colisión y falso negativo R5. |
| `alerta` | Sufijo suelto de `.op-nav-badge`. Bootstrap: `.bg-danger`, `.text-bg-danger`, `.badge`. |
| `op-collapse-label-collapsed` / `expanded` | Bootstrap ya pone `.collapsed` en el disparador. Dos textos se intercambian con eso o con `.d-none`. |
| `op-empty` + `__icon` + `__text` | Cubrible con `text-center text-body-secondary py-5` + SVG. |
| `icon-12` … `icon-40` (8) | Bootstrap no dimensiona SVG, cierto. Ocho tamaños a 1 px; `icon-13` e `icon-15` no se justifican. |
| `mensajes-toasts` | Bootstrap ya tiene `.toast-container.position-fixed`. La vista ya usa esas clases; la propia solo aporta el offset bajo el topbar. |
| `mensajes-hilo__bubble` | El catálogo admite que existe `.mw-100`. Candidata a `w-75` / `w-50`. |
| `topbar__*` (8, sin prefijo `op-`) | Inconsistentes con `op-topbar`. Varias son `d-flex`, `align-items-center`, `text-truncate`, `fw-semibold`. |

Sí merecen quedarse: `op-layout` / `op-sidebar` / `op-main` / `op-topbar`, `op-page--fill`, `op-avatar`, `op-lista-scroll`, `plan-index`, `plan-editor-area`, `mensajes-bandeja*`, `mensajes-toasts-minimizadas*`.

---

## 5. Estilos ad-hoc y clases inventadas (hallazgo extra)

- `html { font-size: 18px }` en `_op-layout.scss` cambia **todas** las utilidades `rem` de Bootstrap (`p-3`, `fs-6`, `btn-sm`). Es un tema global fuera del catálogo. Debería ser `$font-size-base` en `_bootstrap-overrides`.
- `.op-nav-item` es flex + hover + estado activo a mano. Equivale a `.nav-link` / `.list-group-item-action`. El badge equivale a `.badge.text-bg-info` / `.text-bg-danger`. Se puede dejar `op-sidebar` y usar stock dentro.
- `op-topbar` es un navbar fijo; parte del BEM `topbar__*` duplica utilidades Bootstrap.
- `alerta-toast.blade.php` usa ya `toast-container position-fixed start-50 translate-middle-x` **y** `mensajes-toasts` para el offset. Doble sistema.
- `[x-cloak]` y el `font-size` del `html` viven como selectores de elemento, fuera de R4.
- Fuera de ámbito operativo: `resources/views/livewire/admin/partials/uo-nodo.blade.php` tiene `style="padding-left: {{ 16 + $nivel * 24 }}px"`. El auditor excluye admin. Si entra en el ámbito, es el patrón que se quería evitar.

En `resources/views/components` solo hay `avatar` y el shell. Cada módulo reescribe card + header + list-group + badge + empty. Por eso es fácil inventar clases: no hay `<x-op.empty>` ni `<x-op.badge-estado>`.

---

## 6. Código que pinta y repinta de más (hallazgo extra)

### Pantallas-dios

`CiudadanoPage.php`: 1.131 líneas, ~20 `#[Computed]`. Su Blade ~63 KB. `plan-page.blade.php` ~66 KB. `ficha-ciudadano-page` ~40 KB.

Un `wire:model.live` o un click en un rincón remonta el padre y vuelve a pintar timeline, herramientas, accesos, UC, relaciones y el modal de prescribir.

- `ciudadano-page`: `wire:model.live.debounce.300ms="ucBusqueda"` en la misma página que la línea de tiempo. Cada tecla (con debounce) recalcula `apuntesHS`, `accesosRecientes`, `relacionesAgrupadas`, etc.
- `ficha-ciudadano-page`: `wire:model.live="relacionBusqueda"`, mismo patrón.
- Toolbox: array `$herramientas` reconstruido en `@php` en cada render (dato estático).
- `wire:key="toolbox-grid"` sobre un bloque estático no aporta y sugiere remounts de más.

### Consultas en la vista

- `ciudadano-page.blade.php` ~línea 840: `\App\Models\Ciudadano::find($ucCiudadanoSeleccionado)` dentro de `@php`. Dominio en la plantilla y un query extra por render.
- `operativo-shell`: `Configuracion::logoUrl()` y `nombreAplicacion()` en cada request del layout.
- `plan-page`: `@php $datos = $pfd->ficha?->datos`, `$doc = $this->ciudadano?->documentoVigente`, `$yaEnPlan = $this->catalogoIdsEnPlan` dentro de bucles.

### Poll global de toasts

El shell monta `<livewire:mensajes-alerta-toast />` en **todas** las pantallas. La vista tiene `wire:poll.60s` sobre el árbol entero. El comentario del Blade dice que quitaron estado Alpine porque «el morph de Livewire no lo respetaba» y lo resolvieron pintando todo desde la sesión Laravel.

Efecto: cada minuto, en cualquier pantalla, Livewire re-renderiza cabeceras, iconos y botones de reconocer.

### Markup duplicado

Nombre del usuario en el botón del dropdown y otra vez dentro del menú; el avatar también.

---

## 7. Inconsistencia de dominio que acaba en la UI

`Requisitos.md` pide sexo `M` / `F` / `D`. `ficha-ciudadano-page` hace `match` `H` / `M` / `NB`. El `default` pinta el crudo. Etiquetas vacías o códigos sueltos en pantalla. Nivel de identificación y estado de historia tienen etiquetas en la vista, no en el enum (misma causa que los badges duplicados).

---

## 8. Huecos del auditor relacionados

- R5 cuenta literales en PHP como uso. Un `match` que ya no se pinta puede mantener viva una clase huérfana.
- No hay regla que exija `data-acceso`. Si se elimina el atributo, `ui:auditar` no protesta; solo los tests de `data-acceso`.
- No hay snapshot de que `.bg-protected` exista en el CSS compilado.

---

## Resumen

El agujero principal para el CI es el primero: las clases metidas en `match`/`@php` no pasan por R1. El color `protected`, único motivo de partir Bootstrap en imports, está en ese agujero.

Los tests de accesos y de colectivo protegido ya no anclan el marcado visual.

El Sass, hoy, está en el orden correcto; falta un test de las utilidades `protected`.

Del catálogo sobran o están mal nombradas `activo`, `alerta`, las collapse-label, el trío `op-empty`, varios `icon-*` y parte de `topbar__*` / `mensajes-toasts`.

Además: pantallas que se remontan enteras por un buscador `live`, un `find()` en Blade, poll de toasts cada 60 s en todo el shell, `font-size` global a 18 px, y casi ningún componente Blade compartido.

No se ha aplicado ningún cambio en el repositorio.

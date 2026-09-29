# Bootstrap como único sistema de estilos (operativo y público)

**Fecha:** 2026-09-28
**Estado:** completado el 2026-09-28 (fases 1 a 5). Pendiente de la revisión de Grok del conjunto.
**Sustituye a:** `bootstrap-migration-plan.md`, `app-operativo-remediation-plan.md`
y `frontend-bootstrap-guardrails.md` de `docs/design-system/` (borrados en la fase 5).

---

## 1. Por qué

El 2026-09-28 el botón «Dar de alta nueva persona» de `alta-ciudadano` era
invisible: texto blanco sobre `var(--color-primary)`, una variable que no existe
en el bundle operativo. No es un fallo aislado. La auditoría de ese día encontró:

- **Tres formas de pintar lo mismo** en las vistas operativas: clases
  Bootstrap, unas 560 clases propias en `resources/scss/_*.scss` y estilos
  inline (248 `style=`, 94 colores hexadecimales). La primera auditoría también
  señaló clases Tailwind sin efecto (`items-center` 83 veces); era un falso
  positivo del script provisional, que confundía `align-items-center` con
  `items-center`. El informe de `ui:auditar` (anexo B) no encuentra Tailwind en
  las vistas del ámbito.
- **Dos sistemas de tokens que no coinciden:** las variables Sass
  (`_bootstrap-overrides.scss`, `_vida-sass-tokens.scss`), que son las únicas
  compiladas en el operativo, y las variables CSS `--color-*` de
  `resources/css/vida/colors_and_type.css`, que no se cargan en el operativo
  pero se usan 164 veces en 7 vistas. Además los valores difieren
  (`$danger` #9A3A2F frente a `--color-danger` #B0432E; lo mismo con
  `success` y `warning`).
- **Tailwind y Bootstrap juntos** en `app-public.scss`.
- **Ficheros huérfanos:** `resources/css/app.css` y `resources/css/app-operativo.css`
  no están en Vite; `_vida-tokens.scss` no lo importa nadie.
- **Al menos 49 clases definidas y sin uso** (cifra mínima: el recuento no
  detecta selectores anidados).
- **Documentación contradictoria:** `SKILL.md` manda usar `colors_and_type.css`
  como referencia de tokens, lo que lleva a escribir `var(--color-*)` y romper
  el operativo.

Las normas ya existían (principio 4.18, guardrails). Se han incumplido porque
nada comprobaba que se cumplieran. Este plan corrige el código y añade esa
comprobación como definición de «terminado».

---

## 2. Decisiones

1. **Bootstrap 5.3 es el único sistema de estilos** de las superficies operativa
   (Livewire), pública (login, onboarding, errores) y de sus layouts.
2. **Quedan fuera:**
   - **Filament**, con su tema Tailwind (`resources/css/filament/admin/theme.css`),
     incluidas las vistas Livewire que se pintan dentro de Filament
     (`resources/views/livewire/admin/*`, `resources/views/livewire/centros/*`,
     `resources/views/filament/*`, `Modules/*/resources/views/filament/*`).
   - **Las plantillas PDF** (`Modules/Intervencion/resources/views/pdf/*`,
     `Modules/Documentos/resources/views/informe.blade.php`): el motor de PDF no
     carga Bootstrap y el estilo inline es inevitable.
3. **Una sola fuente de tokens** fuera de Filament: `_bootstrap-overrides.scss`
   (todo lo que Bootstrap ya modela: `$primary`, `$danger`, `$body-bg`…) y
   `_vida-sass-tokens.scss` (solo lo que Bootstrap no tiene: `ink-*`, `*-soft`,
   `*-ink`, `protected`…). En Blade y SCSS solo se usan clases Bootstrap,
   variables Sass o las variables `--bs-*` que Bootstrap genera.
   `var(--color-*)` queda prohibido fuera de Filament.
4. **Catálogo cerrado de clases propias.** Toda clase que no sea de Bootstrap
   debe figurar en `vida/config/ui-catalogo.php` con su tipo (`componente`,
   `pantalla` o `gancho`), su estado y el motivo por el que Bootstrap no basta.
   Lo que no está en el catálogo no existe.
5. **Revisión:** cada módulo migrado lo revisa Grok sobre el código antes de
   darlo por cerrado.

---

## 3. La comprobación: `php artisan ui:auditar`

Comando Artisan sin acceso a BD (`app/Console/Commands/UiAuditarCommand.php`,
lógica en `app/Support/Ui/AuditorUi.php`). Lee las vistas, el SCSS, el CSS de
Bootstrap sin modificar y el CSS compilado de `public/build` (requiere
`npm run build` previo). Sale con código distinto de 0 si encuentra infracciones
o si falta el CSS compilado.

Configuración:
- `config/ui-auditoria.php`: ámbito. Qué vistas van con cada bundle, qué queda
  fuera (Filament, PDF), ficheros de tokens y dónde se busca el uso de las
  clases.
- `config/ui-catalogo.php`: el catálogo cerrado de clases propias.

| Regla | Comprueba | Cubre |
|---|---|---|
| **R1. Clase inexistente** | Toda clase usada en una vista del ámbito existe en el CSS compilado de su bundle (`app-operativo` o `app-public`), o está en el catálogo como gancho JS/Livewire. Detecta Tailwind muerto, erratas y clases de otro sistema. | «todo usa Bootstrap» |
| **R2. Estilo inline** | Ningún `style=` en Blade salvo los que solo asignan valores dinámicos (`{{ }}` en cada declaración). | «sin estilos inventados» |
| **R3. Color literal o variable inexistente** | Ningún color hexadecimal ni `rgb()`/`rgba()` en Blade, ni en SCSS fuera de `_bootstrap-overrides.scss` y `_vida-sass-tokens.scss`. Toda `var(--…)` usada en Blade o SCSS está definida en el CSS compilado (en la práctica, `--bs-*` o una variable local declarada en el mismo componente). El 2026-09-28 había unas 60 variables distintas sin definir (`--color-*`, `--space-*`, `--radius-*`, `--shadow-*`…), en vistas y en SCSS. | «sin estilos inventados» |
| **R4. Clase fuera de catálogo** | Toda clase definida en `resources/scss/_*.scss` (excepto overrides de clases Bootstrap) figura en `config/ui-catalogo.php`, y toda entrada del catálogo está definida en el SCSS. | «sin estilos inventados» |
| **R5. Clase huérfana** | Toda clase del catálogo se usa en al menos una vista, clase PHP o fichero JS. | «sin estilos huérfanos» |
| **R6. Tailwind en el ámbito** | Ninguna entrada de Vite del ámbito importa Tailwind. | «todo usa Bootstrap» |

Opciones:

- `--modulo=Ciudadania` limita la salida a las vistas de un módulo (para cerrar
  módulos uno a uno en la fase 3).
- `--informe` lista las infracciones sin fallar (solo durante la migración).
- `--generar-catalogo` imprime, en formato PHP y agrupadas por fichero, las
  clases propias del SCSS que faltan en el catálogo, como `pendiente`.

Detalles de las reglas:
- R1 lee `class="…"`, `wire:*.class`, `:class` (claves literales),
  `@class([...])` y `'class' => '…'`. Dentro de `{{ }}` toma los literales que
  actúan como resultado (tras `?`, `:`, `=>`), no los que se comparan.
- R2 cuenta declaraciones, no atributos: `style="a: 1; b: 2"` son dos.
- R4 no exige catálogo para reestilar una clase de Bootstrap (`.op-x .btn`).
  Las clases `pendiente` fallan en modo bloqueante y se resumen por fichero.
- R5 busca el nombre completo de la clase como palabra en vistas, PHP y JS.
  Las clases que solo se construyen por concatenación salen como huérfanas.
  Al revés, una clase que se llama igual que una vista o ruta (p. ej.
  `alta-ciudadano`, por `livewire.alta-ciudadano`) cuenta como usada aunque no
  lo esté: al migrar una pantalla, revisar a mano la clase raíz.
- Con `--modulo` solo se aplican R1 a R3 (las vistas); R4 a R6 son globales.

Límite conocido: las clases construidas dinámicamente (`"badge-{{ $tipo }}"`) no
se pueden verificar. R1 las marca como aviso y deben sustituirse por un `match`
en PHP que devuelva clases completas o por `@class([...])`.

Tests: `tests/Feature/Ui/UiAuditarTest.php` (TF-UI-01 a TF-UI-21), con vistas y
SCSS de ejemplo en un directorio temporal. Cada regla tiene al menos un caso que
debe pasar y uno que debe fallar. No usan la BD.

CI: el job `test` de `.github/workflows/ci.yml` tiene el paso «Auditoría de
estilos»: `npm ci`, `npm run build` y `php artisan ui:auditar --informe`. En la
fase 5 se quita `--informe` y pasa a bloquear el despliegue. El job `test` no
ejecuta los tests PHP, que requieren PostgreSQL; eso no cambia aquí.

---

## 4. Fases

### Fase 1. Una sola fuente de estilos

1. **Tokens.**
   - Decidir los valores canónicos donde las dos paletas difieren (`danger`,
     `success`, `warning` y cualquier otra que salga al compararlas) y dejarlos
     en `_bootstrap-overrides.scss`. Criterio: contraste AA sobre blanco y sobre
     `$body-bg` para texto; si ambos cumplen, manda el valor de Bootstrap, que es
     el que se ve hoy en el operativo.
   - Completar `_vida-sass-tokens.scss` con lo que falte (`protected`,
     `accent`…).
   - Tabla de equivalencias `--color-*` → Bootstrap/Sass en el anexo A, para la
     fase 3.
2. **Build.**
   - Quitar Tailwind de `app-public.scss` (`@import 'tailwindcss'`, `@source`,
     `@theme`) y migrar las clases Tailwind de las vistas públicas
     (`auth/login`, `auth/onboarding`, `errors/sin-rol`, `welcome`).
   - Borrar `resources/css/app.css`, `resources/css/app-operativo.css` y
     `resources/scss/_vida-tokens.scss`.
   - `resources/css/vida/colors_and_type.css` pasa a
     `docs/design-system/stylesheets/` como referencia de diseño; deja de estar
     en `resources/`. El tema de Filament tiene su propia copia de tokens.
   - El plugin `@tailwindcss/vite` se queda solo para el tema de Filament.
3. **Documentación.**
   - `SKILL.md`, `README.md` del design system: tokens = Sass/Bootstrap; nada de
     `var(--color-*)` fuera de Filament; enlace a este documento.
   - Principio 4.18: el punto 1 dice «variables CSS»; pasa a «variables Sass
     compiladas en Bootstrap».
   - `CLAUDE.md`: añadir este fichero a la tabla del §6 y la regla del §2
     «Frontend y UI».

**Terminada cuando:** `npm run build` genera `app-operativo` y `app-public` sin
Tailwind, no queda ninguna referencia a los ficheros borrados y la
documentación no se contradice.

### Fase 2. La comprobación

1. Tests de `ui:auditar` (TF-UI-*), escritos antes que el comando.
2. Comando `ui:auditar` con las reglas R1 a R6.
3. `config/ui-catalogo.php` inicial: todas las clases propias que hay hoy,
   marcadas con `estado => 'pendiente'`. El comando las cuenta aparte; una clase
   solo sale de `pendiente` en la fase 4.
4. Paso en el CI con `--informe`.
5. Primer informe completo guardado en el anexo B: es la lista exacta del
   trabajo de las fases 3 y 4.

**Terminada cuando:** los tests pasan y el informe cubre todas las vistas del
ámbito. Hecho el 2026-09-28: 21 tests, catálogo con 559 clases `pendiente`,
informe en el anexo B.

### Fase 3. Migrar las vistas, por módulos

Orden (de más a menos infracciones según la auditoría del 2026-09-28):

1. **Ciudadanía:** `alta-ciudadano`, `ficha-ciudadano-page`.
2. **Intervención:** `buscar-ciudadano-page`, `registrar-valoracion-page`,
   `ver-ficha-page`, `registrar-escala-page`, `ciudadano-page`, `plan-page`,
   resto.
3. **Agenda y Supervisión** (Mensajes y Documentos no tienen infracciones en
   sus vistas; solo quedan las clases de su SCSS, en la fase 4).
5. **Público y layouts** (`operativo-shell`, `operativo`, `supervision`,
   `public`).

En cada vista, cada elemento acaba en:
- una clase Bootstrap estándar, o
- un componente del catálogo `op-*`, o
- una clase de pantalla documentada en el catálogo (solo estructura real).

Por módulo:
1. Migrar las vistas. Las clases propias que la migración deja sin uso (R5) se
   borran en el mismo commit, del SCSS y del catálogo; no se dejan para la fase 4.
2. `php artisan ui:auditar --modulo=X` sin infracciones.
3. Tests del módulo en verde.
4. Commit y push (staging se despliega solo).
5. Revisión de Grok sobre el código del commit. Lo que señale se corrige antes
   de pasar al siguiente módulo.

### Fase 4. Reducir el SCSS

Cada clase `pendiente` del catálogo acaba en uno de tres destinos:
- **se borra**: Bootstrap ya lo hace (p. ej. `plan-badge`, `uc-badge` →
  `badge`; `plan-table` → `table`; `ficha-input` → `form-control`);
- **componente `op-*`**: reutilizable, con propósito documentado;
- **clase de pantalla**: estructura propia de una pantalla, documentada.

Las huérfanas (R5) se borran sin más. Los overrides de clases Bootstrap dentro
de `_op-*.scss` se revisan: o se convierten en variables de
`_bootstrap-overrides.scss` o se eliminan.

**Terminada cuando:** no queda ninguna clase `pendiente`.

### Fase 5. Cierre

1. `ui:auditar` pasa a bloquear en el CI (sin `--informe`).
2. Se borran los tres documentos sustituidos.
3. `CLAUDE.md` §2: «Toda tarea que toque Blade o SCSS termina con
   `php artisan ui:auditar` en verde».
4. Entrada en `docs/decisiones-tecnicas.md` que sustituya a la 3.8 (BEM): BEM
   sigue siendo la nomenclatura del catálogo, pero la fuente de verdad es el
   catálogo.

---

## 5. Resultado (2026-09-28)

- `php artisan ui:auditar` sin infracciones y bloqueante en el CI.
- Todas las vistas del ámbito usan solo Bootstrap. Sin estilos inline salvo
  valores dinámicos, sin colores literales ni variables CSS inexistentes, sin
  Tailwind.
- Catálogo: **47 clases propias**, todas aprobadas con su motivo (antes, unas
  560). SCSS propio: de unas 4.450 líneas a unas 600. Borrados
  `_public-pages.scss`, `_op-ciudadano.scss` y `_op-support-pages.scss`.
- Tokens: una sola fuente. `$vida-topbar-height` sustituye a los `56px`
  repetidos. Colores de alertas, tablas, campos y foco pasan a variables de
  Bootstrap en `_bootstrap-overrides.scss`.

### Decisiones tomadas durante la migración

- **Color de tema `protected`** (colectivos especialmente protegidos) añadido a
  los mapas de Bootstrap. Para ello `_bootstrap-vida.scss` importa Bootstrap por
  partes, en el orden que recomienda su documentación. Existen `text-protected`,
  `bg-protected-subtle`, `text-protected-emphasis`, `btn-outline-protected`…
- **Componentes nuevos del catálogo:** `op-page--fill` (pantallas de alto fijo
  con scroll por zonas: expediente, mis casos), `op-avatar` (con el componente
  `<x-avatar>`, que ahora acepta `nombre`) y `op-lista-scroll`.
- **Clases de pantalla** que se quedan: `plan-index` (índice fijo bajo la barra
  del plan) y `plan-editor-area` (alto mínimo del editor contenteditable).
- **Ancho único de offcanvas** (`$offcanvas-horizontal-width: 480px`) para los
  tres paneles laterales.
- **`table-sm` recupera su densidad**: antes una regla global le forzaba el
  mismo relleno vertical que a `table` (`$table-cell-padding-y: .7rem`).
- **Calendario mensual** de la agenda como tabla (`row-cols-*` llega a 6).
- **Menú de usuario del topbar** con el `dropdown` de Bootstrap.
- **Nada de nombres de clase concatenados:** `match` o arrays con clases
  completas.
- Accesos al expediente: el acceso de lectura desde otra UO se marca como
  «sospechoso» (lo pedía TF-AUD-INT-05, que ya fallaba). Los tests que miraban
  clases CSS miran ahora un marcador semántico (`data-acceso`) o el texto.

### Límites conocidos del auditor

- ~~Las clases que se asignan desde PHP no se comprueban contra el CSS.~~
  Resuelto el 2026-09-29 (§6).
- R5 da por usada una clase que se llama igual que una vista o ruta
  (`alta-ciudadano`): al retirar una pantalla, revisar su clase raíz a mano.

## Anexo A. Equivalencias de tokens

Valores canónicos (fase 1, 2026-09-28): donde las dos paletas diferían manda
Bootstrap. `--color-success` (#4B8A5B, contraste 4,13 sobre blanco) y
`--color-warning` (#B8852F, 3,26) no llegaban a AA; en `danger` e `info`
cumplían las dos y se conserva el valor que ya se veía en el operativo. La copia
de referencia `docs/design-system/stylesheets/colors_and_type.css` se ha alineado.

| Variable antigua | En Blade | En SCSS |
|---|---|---|
| `--color-primary` | `text-primary`, `bg-primary`, `btn-primary`, `border-primary`, `link-primary` | `$primary` / `var(--bs-primary)` |
| `--color-primary-soft` / `-ink` | `bg-primary-subtle` / `text-primary-emphasis` | `$primary-bg-subtle` / `$primary-text-emphasis` |
| `--color-danger`, `-success`, `-warning`, `-info` | `text-*`, `bg-*`, `btn-*`, `alert-*`, `badge text-bg-*` | `$danger`… / `var(--bs-danger)`… |
| `--color-*-soft` / `--color-*-ink` (danger, success, warning, info) | `bg-*-subtle` / `text-*-emphasis`; borde `border-*-subtle` | `$*-bg-subtle` / `$*-text-emphasis` |
| `--color-*-50`, `--color-*-bg`, `--color-*-border` (no existían ni en la paleta) | `bg-*-subtle`, `border-*-subtle` | ídem |
| `--color-ink-900`, `--color-text-primary` | `text-body` (por defecto) | `$body-color` |
| `--color-ink-800`, `-700` | `text-body-emphasis` / `text-body` | `$vida-ink-800`, `$vida-ink-700` |
| `--color-ink-600`, `--color-text-secondary` | `text-body-secondary` | `$body-secondary-color` |
| `--color-ink-500`, `-400` | `text-body-tertiary` | `$vida-ink-500`, `$vida-ink-400` |
| `--color-ink-300`, `--color-border` | `border` | `$border-color` |
| `--color-ink-200`, `-100`, `--color-neutral-*` | `border-light-subtle`, `bg-body-tertiary` | `$vida-ink-200`, `$vida-ink-100` |
| `--color-paper`, `--color-bg-50` | `bg-body` | `$body-bg` |
| `--color-sand`, `--color-surface-alt` | `bg-body-tertiary` | `$body-tertiary-bg` |
| `--color-surface` | `bg-white` o, mejor, `card` | `$vida-surface` |
| `--color-accent` | clase del catálogo | `$vida-accent`, `$vida-accent-soft`, `$vida-accent-ink` |
| `--color-protected` | clase del catálogo (`op-chip--protegido` o equivalente) | `$vida-protected`, `-soft`, `-ink` |
| `--space-N` | utilidades `p-*`, `m-*`, `gap-*` | `$spacer * n` / `map-get($spacers, n)` |
| `--radius-sm`, `-md`, `-lg`, `-pill` | `rounded-1`, `rounded`, `rounded-3`, `rounded-pill` | `$border-radius-sm`, `$border-radius`, `$border-radius-lg`, `$vida-radius-pill` |
| `--shadow-sm`, `--shadow-1`, `--shadow-2` | `shadow-sm`, `shadow` | `$vida-shadow-sm`, `$vida-shadow-2` |
| `--font-mono` | `font-monospace` | `$font-family-monospace` |
| `--motion-fast` | — | `$vida-motion-fast` |

Cambio visible de la fase 1: `alert-*`, `badge text-bg-*-subtle`, `list-group-item-*`
y las utilidades `*-subtle` / `*-emphasis` pasan a los tonos suaves VIDA en lugar
de los que Bootstrap calculaba a partir del color base.

## Anexo B. Informe inicial de `ui:auditar`

Ejecución del 2026-09-28, tras la fase 2 (`php artisan ui:auditar --informe`):
**1.724 infracciones y 8 avisos.** Para el detalle actual, ejecutar el comando.

| Ámbito | R1 | R2 | R3 | R4 | R5 | R6 | Avisos |
|---|---|---|---|---|---|---|---|
| Agenda | 3 | 14 | | | | | |
| Ciudadanía | 7 | 374 | 99 | | | | 1 |
| Intervención | 11 | 309 | 104 | | | | 7 |
| Supervisión | 1 | 2 | | | | | |
| `resources/views` (app) | 6 | | | | | | |
| SCSS | | | 736 | | | | |
| Catálogo | | | | 8 | 50 | | |

Lectura:
- **R2 y R3 en vistas se concentran en cinco pantallas**: `alta-ciudadano`
  (374 declaraciones inline, 96 colores o variables),
  `registrar-valoracion-page` (112 / 33), `buscar-ciudadano-page` (100 / 28),
  `ver-ficha-page` (52 / 15) y `registrar-escala-page` (41 / 12). El resto de
  vistas tiene como mucho unas pocas declaraciones.
- **R1 (28 clases inexistentes)**: casi todas son clases propias que se usan
  pero nunca se definieron (`avatar`, `op-toolbar`, `op-empty--compact`,
  `op-nav-footer`, `citizen-file__*`, `cases-screen__*`, `form-label-sm`…). Solo
  `col-span-2` es de Tailwind.
- **R3 en SCSS (736)**: 570 son `var(--…)` que no existen (sobre todo `--color-*`)
  y el resto, colores literales repetidos de la paleta. Por fichero:
  `_op-components.scss` 396, `_op-ciudadano.scss` 134, `_public-pages.scss` 122,
  `_bootstrap-components.scss` 33, `_op-support-pages.scss` 32, `_op-plan.scss` 18,
  `_op-mensajes.scss` 1.
- **R4**: las 559 clases del catálogo están `pendiente` (una línea por fichero).
- **R5 (50 huérfanas)**: parte son clases que solo se construyen por
  concatenación (`plan-badge--{estado}`, `plan-estado-{estado}`,
  `ficha-atencion-tipo--{tipo}`, `acceso-fila__accion--{accion}`…): son los 8
  avisos de R1. Al migrar, se escriben con el nombre completo (un `match` que
  devuelva la clase entera) o se sustituyen por clases Bootstrap.
- **Sin infracciones en vistas**: Mensajes, Documentos y la mayor parte de
  Supervisión y Agenda. No hay Tailwind en ninguna entrada del ámbito (R6).

## 6. Corrección tras la revisión de Grok (2026-09-29)

Revisión en `docs/front/revision-frontend-ui.md` y plan en
`docs/front/plan-correccion-frontend-ui.md`, con los cambios acordados.

- **Clases que se deciden en PHP.** Dentro de un atributo de clase, cada `{{ }}`
  tiene que resolverse en literales (también en ternarios y `??`) o en una
  llamada a un método `clases…()`. Si no, R1 falla («no se puede comprobar»).
  Las clases de esos métodos salen de una `App\Support\Ui\FuenteClasesCss`,
  que declara en `clasesCss()` todas las que puede devolver. El auditor localiza
  las fuentes solo (busca `implements FuenteClasesCss`) y comprueba cada clase
  contra el CSS del bundle operativo. Concatenar (`badge-{{ $x }}`) también
  falla ahora.
- **Paleta única de estados:** `App\Support\Ui\Tono` (primario, éxito, aviso,
  peligro, info, neutro, protegido) con `clasesSuave()`, `clasesFuerte()`,
  `clasesPunto()` y `clasesBloque()`. Los enums con estado o tipo visible tienen
  `tono()`; los valores sin enum, una clase `Tonos` por módulo
  (`Modules\X\Support\Ui\Tonos`). No se vuelven a escribir tablas de clases en
  las vistas.
- **R7:** clases exigidas en el CSS compilado (`clases_exigidas` en
  `config/ui-auditoria.php`), para la familia `protected`.
- Salieron a la luz clases inexistentes que el auditor no veía:
  `bg-purple-subtle`, `text-purple-emphasis` y `border-dashed` en el cuadrante
  del supervisor (las sesiones pasan a tono info; las reservas, a neutro).


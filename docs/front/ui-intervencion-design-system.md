# Instrucciones para Claude CLI — Corrección visual UI operativa
## `docs/instrucciones-cli/ui-intervencion-design-system.md`

> El interfaz operativo (Livewire) fue implementado con colores, tipografía e iconos
> que no respetan el design system del proyecto. Esta tarea corrige todas las
> desviaciones para que el entorno operativo sea visualmente coherente con el
> backoffice Filament.
>
> **Fuente de verdad absoluta:** `docs/design-system/colors_and_type.css`
> **Patrones de componentes:** `docs/design-system/ui_kits/vida_app/kit.css`
> **Resumen del sistema:** `docs/design-system/SKILL.md` y `docs/design-system/README.md`
> **Módulos afectados:** `resources/css/`, `resources/views/layouts/`,
> `Modules/Intervencion/resources/views/`

---

## Paso 1 — Leer antes de tocar nada

```bash
cat docs/design-system/SKILL.md
cat docs/design-system/README.md
cat docs/design-system/colors_and_type.css
cat docs/design-system/ui_kits/vida_app/kit.css
```

Estos cuatro documentos son la referencia normativa. Todo lo que se implementa
en esta tarea debe derivarse de ellos, no de los mockups PDF ni de ningún otro
documento visual. Si hay contradicción entre el kit y los mockups, el kit gana.

---

## Paso 2 — Inventario de desviaciones actuales

Antes de hacer cambios, documentar exactamente qué hay mal:

```bash
# Buscar referencias a colores hardcodeados incorrectos (morado #534AB7, etc.)
grep -rn "#534AB7\|#EEEDFE\|#AFA9EC\|#3C3489\|#26215C\|#CECBF6" \
  resources/views/ Modules/Intervencion/resources/views/ \
  resources/css/ --include="*.blade.php" --include="*.css" -l

# Buscar uso de iconos legacy que deben reemplazarse por Heroicons
grep -rn "heroicon-|blade-heroicons|icon-12|icon-13|icon-14|icon-16|icon-20" \
  resources/views/ Modules/Intervencion/resources/views/ \
  --include="*.blade.php"

# Buscar fuentes incorrectas o ausentes
grep -rn "font-family\|Inter\|Roboto\|sans-serif" \
  resources/css/ resources/views/layouts/ --include="*.css" --include="*.blade.php"

# Buscar el layout operativo actual
cat resources/views/layouts/operativo.blade.php
```

Anotar los ficheros afectados antes de proceder.

---

## Paso 3 — CSS operativo: crear o corregir `app-operativo.css`

El interfaz operativo necesita su propio fichero CSS que importe los tokens
del design system y defina los patrones de componentes operativos.

### 3.1 Crear `resources/css/app-operativo.css`

Si ya existe, vaciarlo y reescribirlo. Si no existe, crearlo.

```css
/* ============================================================
   VIDA 360 — CSS superficie operativa (Livewire)
   Importa los tokens del design system y aplica patrones
   específicos del interfaz del TSR.
   ============================================================ */

/* 1. Tokens del design system — fuente de verdad absoluta */
@import url('./../../docs/design-system/colors_and_type.css');

/* 2. Tailwind (utilities) */
@import "tailwindcss/utilities";

/* 3. Kit de componentes operativos */
@import url('./../../docs/design-system/ui_kits/vida_app/kit.css');
```

Si la ruta relativa a `docs/` no funciona desde `resources/css/`, copiar
`colors_and_type.css` y `ui_kits/vida_app/kit.css` a `resources/css/vida/`
y ajustar los imports. **No modificar los ficheros originales en `docs/`.**

### 3.2 Registrar en `vite.config.js`

Añadir `resources/css/app-operativo.css` al array `input` de Vite, junto
a los otros entry points existentes. Verificar que no duplica entradas.

---

## Paso 4 — Layout operativo: corregir `resources/views/layouts/operativo.blade.php`

### 4.1 Carga de assets

El layout debe cargar:

```blade
{{-- Fuentes (ya incluidas en colors_and_type.css vía @import, pero por si acaso) --}}
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

{{-- CSS operativo --}}
@vite('resources/css/app-operativo.css')

{{-- Iconos --}}
{{-- Heroicons se renderiza desde Blade mediante blade-ui-kit/blade-heroicons; no requiere JS ni CDN. --}}
```

Livewire no necesita reinicializar iconos cuando se usan Heroicons renderizados desde Blade.

### 4.2 Tipografía base

En el `<body>` o en el CSS global del layout, asegurarse de que la fuente
base es Source Sans 3:

```css
body {
    font-family: var(--font-sans); /* definido en colors_and_type.css */
    font-size: 16px;
    line-height: 1.5;
    color: var(--color-ink-900);
    background-color: var(--color-paper);
}
```

### 4.3 Fondo

El fondo de la aplicación es `var(--color-paper)` (`#FAF7F1`), no blanco puro.
Verificar que el `<body>` y el contenedor principal usan este valor.

---

## Paso 5 — Migración de iconos: sistema único Heroicons

Todos los iconos de la superficie operativa deben expresarse con `blade-ui-kit/blade-heroicons`.
No usar atributos o clases legacy de iconos, CDNs de iconos ni inicialización JS para iconografía.

### 5.1 Sintaxis de uso

```blade
{{-- Antes --}}
{{-- Icono legacy embebido directamente en el marcado --}}

{{-- Después --}}
<x-heroicon-o-calendar class="icon-16" aria-hidden="true"/>
```

### 5.2 Reglas

- usar `<x-heroicon-o-nombre />` para outline;
- usar `<x-heroicon-s-nombre />` solo cuando el estado requiera versión solid;
- tamaños permitidos en operativo: `icon-12`, `icon-13`, `icon-14`, `icon-16`, `icon-20`;
- no usar estilos inline para tamaño de icono salvo que el ejemplo documente un caso excepcional y justificado.

### 5.3 Equivalencias frecuentes

| Necesidad | Heroicon recomendado |
|---|---|
| calendario | `heroicon-o-calendar` |
| búsqueda | `heroicon-o-magnifying-glass` |
| usuarios | `heroicon-o-user-group` |
| alerta | `heroicon-o-exclamation-triangle` |
| editar | `heroicon-o-pencil-square` |
| abrir enlace | `heroicon-o-arrow-top-right-on-square` |
| cerrar | `heroicon-o-x-mark` |
| desplegar | `heroicon-o-chevron-down` |
| volver | `heroicon-o-arrow-left` |

### 5.4 Revisión

- no dejar clases o atributos legacy de iconos;
- no dejar atributos legacy de iconos en snippets o ejemplos;
- no cargar CDNs de iconos en layouts;
- no reinicializar iconos tras navegación Livewire: Heroicons se renderiza en Blade.

## Paso 6 — Corrección de colores

### 6.1 Paleta incorrecta vs paleta correcta

| Uso | Color incorrecto (mockup) | Token correcto | Valor |
|---|---|---|---|
| Primario / acciones | `#534AB7` (morado) | `--color-primary` | `#2A5B8A` |
| Fondo primario claro | `#EEEDFE` | `--color-primary-50` | ver `colors_and_type.css` |
| Borde primario | `#AFA9EC` | `--color-primary-200` | ver `colors_and_type.css` |
| Texto primario oscuro | `#3C3489` | `--color-primary-800` | ver `colors_and_type.css` |
| Fondo general | blanco puro `#FFFFFF` | `--color-paper` | `#FAF7F1` |
| Fondo secundario | `#F1EFE8` | `--color-sand` | ver `colors_and_type.css` |
| Texto principal | no definido | `--color-ink-900` | `#1D160E` |
| Texto secundario | `#888780` | `--color-ink-500` | ver `colors_and_type.css` |
| Borde por defecto | `#D3D1C7` | `--color-ink-200` | `#E6E1D8` |
| Peligro / alerta | `#993C1D` | `--color-danger` | brick-red (ver CSS) |
| Fondo peligro | `#FAECE7` | `--color-danger-50` | ver `colors_and_type.css` |
| Éxito / seguimiento | `#0F6E56` | `--color-success` | sage-green (ver CSS) |
| Fondo éxito | `#E1F5EE` | `--color-success-50` | ver `colors_and_type.css` |
| Advertencia | `#854F0B` | `--color-warning` | amber ochre (ver CSS) |
| Fondo advertencia | `#FAEEDA` | `--color-warning-50` | ver `colors_and_type.css` |
| Protegido | — | `--color-protected` | deep plum `#6B3D6B` |

**Instrucción crítica:** no usar valores hex hardcodeados en las vistas Blade.
Usar siempre las variables CSS (`var(--color-primary)`, etc.). Si un valor
concreto no tiene variable definida en `colors_and_type.css`, consultar el
fichero y usar la variable más próxima — nunca inventar un hex nuevo.

### 6.2 Chips de estado

Los chips de estado siguen el patrón del design system:
- Fondo: color semántico al 12% de opacidad (`--color-success-50`, etc.)
- Texto: color semántico al 100% (`--color-success`, etc.)
- Border-radius: `--radius-pill` (999px)
- Font-size: 12px, font-weight: 600
- Sin mayúsculas forzadas

```blade
{{-- Correcto --}}
<span style="
    background: var(--color-success-50);
    color: var(--color-success);
    border-radius: var(--radius-pill);
    font-size: 12px;
    font-weight: 600;
    padding: 2px 10px;
">En seguimiento</span>

{{-- Incorrecto --}}
<span style="background:#E1F5EE;color:#085041;...">En seguimiento</span>
```

### 6.3 Semáforo de seguimiento en la tabla de casos

| Estado | Fondo | Texto |
|---|---|---|
| Vencido | `--color-danger-50` | `--color-danger` |
| Próximo (7 días) | `--color-warning-50` | `--color-warning` |
| Programado | `--color-success-50` | `--color-success` |
| Sin programar | transparent | `--color-ink-400` |

### 6.4 Citas en la agenda por tipo

| Tipo | Fondo | Borde izquierdo |
|---|---|---|
| `entrevista` | `--color-primary-50` | `--color-primary` |
| `seguimiento` | `--color-success-50` | `--color-success` |
| `urgencia` | `--color-danger-50` | `--color-danger` |
| `evento` | `--color-sand` | `--color-ink-300` |

---

## Paso 7 — Sidebar

El sidebar debe seguir los patrones de `ui_kits/vida_app/kit.css`.
Verificar específicamente:

- Fondo del sidebar: blanco (`#FFFFFF`), no paper.
- Borde derecho: `1px solid var(--color-ink-200)`.
- Ítem activo: fondo `--color-primary-50`, texto `--color-primary`, sin borde izquierdo adicional (el fondo es suficiente).
- Ítem hover: fondo `--color-sand`.
- Tipografía de ítems: 14px, Source Sans 3, weight 400 (500 en activo).
- Badges: fondo semántico, texto semántico, `--radius-pill`.
  - Badge de alertas sin reconocer: `--color-danger-50` / `--color-danger`.
  - Badge de casos: `--color-info-50` / `--color-info`.
- Logo/wordmark: usar `vida360-wordmark.svg` de `docs/design-system/assets/logos/`.
  No usar texto plano "VIDA 360" si el SVG está disponible.
- Avatar de usuario: iniciales en `--color-primary-50` con texto `--color-primary`.

---

## Paso 8 — Tarjetas y bordes

Todas las tarjetas (resultados de búsqueda, citas, entradas de la historia, etc.):

```css
background: #FFFFFF;
border: 1px solid var(--color-ink-200);
border-radius: var(--radius-md);  /* 8px */
box-shadow: var(--shadow-1);
padding: 20px;
```

Los paneles elevados (modales, detalles expandidos):
```css
border-radius: var(--radius-lg);  /* 14px */
box-shadow: var(--shadow-2);
```

No usar `border-radius` hardcodeado. Siempre `var(--radius-*)`.

---

## Paso 9 — Tabla de casos (`mis-casos-page.blade.php`)

- Cabecera: fondo `--color-sand`, no gris genérico.
- Filas: fondo blanco, hover `--color-paper`.
- Alto de fila: 52px (cómodo) o 40px (compacto si hay toggle).
- Borde: `1px solid var(--color-ink-200)` en separadores horizontales.
- Primera columna con enlace: color `--color-primary`, sin subrayado por defecto, subrayado en hover.
- Truncación con ellipsis, nunca wrap en celdas de tabla.

---

## Paso 10 — Formularios de herramientas (pantalla del ciudadano)

Inputs y selects:
```css
border: 1px solid var(--color-ink-300);
border-radius: var(--radius-sm);  /* 4px */
font-family: var(--font-sans);
font-size: 14px;
padding: 8px 12px;
```

Focus ring obligatorio:
```css
outline: 2px solid var(--color-primary);
outline-offset: 2px;
```

Labels:
```css
font-size: 12px;
color: var(--color-ink-600);
font-weight: 500;
margin-bottom: 4px;
```

Botón primario:
```css
background: var(--color-primary);
color: #FFFFFF;
border-radius: var(--radius-md);
font-weight: 600;
padding: 8px 16px;
font-size: 14px;
```

Botón secundario:
```css
background: transparent;
border: 1px solid var(--color-ink-300);
color: var(--color-ink-700);
border-radius: var(--radius-md);
```

---

## Paso 11 — Compilar y verificar

```bash
npm run build
php artisan view:clear
php artisan config:clear
```

Verificación visual en el navegador — revisar en este orden:
1. Login → ¿fondo paper? ¿Source Sans 3? ¿botón azul Retiro?
2. Sidebar → ¿iconos Heroicons correctamente renderizados? ¿azul Retiro en ítem activo?
3. Agenda → ¿colores de citas correctos? ¿fondo paper?
4. Tabla de casos → ¿cabecera sand? ¿semáforo con tokens correctos?
5. Búsqueda → ¿chips de estado con tokens? ¿borde protegido con --color-protected?
6. Pantalla ciudadano → ¿focus rings? ¿botones con verb correcto y color correcto?

---

## Tests

No hay tests de snapshot visual automatizados en este proyecto. La verificación
es manual. Anotar en `CHANGELOG.md` los ficheros modificados.

Lo que SÍ hay que verificar con tests existentes tras los cambios CSS:

```bash
# Asegurarse de que no se ha roto nada en el backend
php artisan test --filter=Intervencion
php artisan test --filter=Auth
php artisan test 2>&1 | tail -5
```

Los cambios de esta tarea son exclusivamente de presentación (CSS, Blade).
No deben afectar a ningún test de backend. Si algún test falla, investigar
antes de continuar.

---

## Lo que NO hay que hacer

- No inventar colores nuevos. Todo valor de color debe venir de `colors_and_type.css`.
- No usar sistemas de iconos legacy en ningún fichero nuevo ni modificado.
- No hardcodear valores hex en atributos `style=""` de las vistas Blade.
  Usar siempre `var(--nombre-del-token)`.
- No modificar `docs/design-system/colors_and_type.css` ni ningún fichero
  de `docs/design-system/`. Son de solo lectura para esta tarea.
- No tocar el CSS de Filament (`resources/css/filament/`). El backoffice
  ya está correcto y es independiente.
- No reescribir la lógica PHP de los componentes Livewire. Esta tarea es
  exclusivamente de presentación.
- No usar gradientes, glassmorphism, sombras de color, ni animaciones de rebote.
- No usar emojis en el chrome del producto.

---

## Checklist de finalización

- [ ] `npm run build` completa sin errores
- [ ] `php artisan test` no introduce fallos nuevos
- [ ] No hay referencias a iconos legacy en ningún fichero Blade del módulo Intervención
- [ ] No hay colores hex hardcodeados en los atributos `style` de las vistas
- [ ] El sidebar usa `--color-primary` para el ítem activo (azul Retiro, no morado)
- [ ] Los botones primarios son azul Retiro (`--color-primary`)
- [ ] El fondo general es `--color-paper` (`#FAF7F1`), no blanco puro
- [ ] La tipografía es Source Sans 3 (verificar en DevTools → Computed → font-family)
- [ ] Los iconos son Heroicons y respetan los tamaños documentados (`icon-12`, `icon-13`, `icon-14`, `icon-16`, `icon-20`)
- [ ] Los chips de estado usan `--radius-pill` y el patrón semántico del design system
- [ ] El focus ring es visible en todos los inputs y botones interactivos
- [ ] Los campos de código (DNIs, NI-HSU-CM) usan JetBrains Mono
- [ ] Entrada añadida en `CHANGELOG.md`
